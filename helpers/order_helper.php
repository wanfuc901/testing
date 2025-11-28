<?php
// helpers/order_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/realtime.php'; // đã có sẵn bên bạn

/**
 * Tạo mã hóa đơn dạng #HDHHMMSSDDMMYYYYXXX
 */
function generate_order_code() {
    $rand = mt_rand(100, 999);
    return '#HD' . date('HisdmY') . $rand;
}

/**
 * Kiểm tra ghế còn trống cho suất chiếu
 */
function is_seat_available($showtime_id, array $seat_ids) {
    global $conn;

    if (empty($seat_ids)) return false;

    $placeholders = implode(',', array_fill(0, count($seat_ids), '?'));
    $types = str_repeat('i', count($seat_ids) + 1);
    $params = array_merge([$showtime_id], $seat_ids);

    $sql = "
        SELECT COUNT(*) AS c
        FROM tickets
        WHERE showtime_id = ?
          AND seat_id IN ($placeholders)
          AND status IN ('pending','paid','confirmed')
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL ERROR is_seat_available: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($res['c'] == 0);
}

/**
 * Hàm finalize_payment dùng chung cho admin/callback
 * - Tạo vé
 * - Gán payment_id cho vé
 * - Tạo payment_combos
 * - Update payment.status = 'success'
 * - Emit realtime
 */
function finalize_payment($payment_id, $confirmed_by = null) {
    global $conn;

    $payment_id = (int)$payment_id;
    if ($payment_id <= 0) {
        throw new Exception("payment_id không hợp lệ");
    }

    // 1. Lấy thông tin payment
    $sqlPay = "SELECT * FROM payments WHERE payment_id = ?";
    $stmt = $conn->prepare($sqlPay);
    if (!$stmt) throw new Exception("SQL ERROR SELECT payment: " . $conn->error);

    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception("Không tìm thấy payment");
    }

    // 1.1. Nếu đã thành công rồi thì bỏ qua (idempotent)
    if ($payment['status'] === 'success') {
        return [
            'status' => 'ok',
            'message' => 'Payment đã success trước đó, không tạo vé nữa (idempotent).'
        ];
    }

    // 2. Đọc JSON order_data
    if (empty($payment['order_data'])) {
        throw new Exception("order_data trống, không thể tạo vé");
    }

    $orderData = json_decode($payment['order_data'], true);
    if (!is_array($orderData)) {
        throw new Exception("order_data không phải JSON hợp lệ");
    }

    $user_id      = (int)$payment['user_id'];
    $showtime_id  = isset($orderData['showtime_id']) ? (int)$orderData['showtime_id'] : 0;
    $seat_ids     = isset($orderData['seats']) ? $orderData['seats'] : [];
    $ticket_price = isset($orderData['ticket_price']) ? (float)$orderData['ticket_price'] : 0;
    $ticket_total = isset($orderData['ticket_total']) ? (float)$orderData['ticket_total'] : 0;
    $combo_items  = isset($orderData['combos']) ? $orderData['combos'] : [];
    $combo_total  = isset($orderData['combo_total']) ? (float)$orderData['combo_total'] : 0;
    $total_amount = isset($orderData['total_amount']) ? (float)$orderData['total_amount'] : 0;

    if ($showtime_id <= 0 || $user_id <= 0 || empty($seat_ids)) {
        throw new Exception("Dữ liệu order_data thiếu showtime_id / user_id / seats");
    }

    // 3. Kiểm tra khớp số tiền (option, có thể relax nếu cần)
    if ((float)$payment['amount'] != (float)$total_amount) {
        // Tùy bạn: có thể throw hoặc chỉ cảnh báo log
        // throw new Exception("Số tiền payment.amount không khớp total_amount trong order_data");
    }

    // 4. Kiểm tra ghế còn trống
    if (!is_seat_available($showtime_id, $seat_ids)) {
        throw new Exception("Một hoặc nhiều ghế đã được đặt trước đó.");
    }

    // 5. Bắt đầu transaction
    $conn->begin_transaction();

    try {
        $now = date('Y-m-d H:i:s');

        // 5.1. Insert tickets cho từng ghế
        $ticketIds = [];

        $sqlTicket = "
            INSERT INTO tickets
                (showtime_id, seat_id, user_id, channel, paid, status, confirmed_by, confirmed_at, price, payment_id)
            VALUES
                (?, ?, ?, 'online', 1, 'confirmed', ?, ?, ?, ?)
        ";
        $stmtT = $conn->prepare($sqlTicket);
        if (!$stmtT) throw new Exception("SQL ERROR INSERT ticket: " . $conn->error);

        foreach ($seat_ids as $sid) {
            $sid = (int)$sid;

            $stmtT->bind_param(
                "iiissdi",
                $showtime_id,
                $sid,
                $user_id,
                $confirmed_by,
                $now,
                $ticket_price,
                $payment_id
            );
            $stmtT->execute();

            $newId = $stmtT->insert_id;
            if ($newId > 0) {
                $ticketIds[] = $newId;
            }
        }
        $stmtT->close();

        // 5.2. Insert combos vào payment_combos nếu có
        if (!empty($combo_items)) {
            $sqlCombo = "
                INSERT INTO payment_combos (payment_id, combo_id, qty, price, total)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmtC = $conn->prepare($sqlCombo);
            if (!$stmtC) throw new Exception("SQL ERROR INSERT payment_combos: " . $conn->error);

            foreach ($combo_items as $ci) {
                $cid   = (int)($ci['combo_id'] ?? 0);
                $qty   = (int)($ci['qty'] ?? 1);
                $price = (float)($ci['price'] ?? 0);
                $total = $price * $qty;

                $stmtC->bind_param("iiidd", $payment_id, $cid, $qty, $price, $total);
                $stmtC->execute();
            }
            $stmtC->close();
        }

        // 5.3. Cập nhật payment → success
        $sqlUpdPay = "
            UPDATE payments
            SET status = 'success', paid_at = ?
            WHERE payment_id = ?
        ";
        $stmtU = $conn->prepare($sqlUpdPay);
        if (!$stmtU) throw new Exception("SQL ERROR UPDATE payment: " . $conn->error);

        $stmtU->bind_param("si", $now, $payment_id);
        $stmtU->execute();
        $stmtU->close();

        // 5.4. Commit
        $conn->commit();

        // 6. Emit realtime để frontend + admin update ghế
        realtime_push('ticket_confirmed', [
            'showtime_id' => $showtime_id,
            'seat_ids'    => $seat_ids,
            'user_id'     => $user_id,
            'payment_id'  => $payment_id,
            'ticket_ids'  => $ticketIds
        ]);

        return [
            'status'      => 'ok',
            'message'     => 'Tạo vé + cập nhật payment thành công.',
            'ticket_ids'  => $ticketIds,
            'showtime_id' => $showtime_id,
        ];

    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }
}
