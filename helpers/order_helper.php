<?php
// /helpers/order_helper.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/realtime.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

/* ============================
   MÃ ĐƠN
============================ */
function generate_order_code() {
    return "#HD" . date('HisdmY') . rand(100,999);
}

/* ============================
   KIỂM TRA GHẾ
============================ */
function is_seat_available($showtime_id, array $seat_ids) {
    global $conn;

    if (empty($seat_ids)) return false;

    $qMarks = implode(',', array_fill(0, count($seat_ids), '?'));
    $types  = str_repeat('i', count($seat_ids)+1);
    $params = array_merge([$showtime_id], $seat_ids);

    $sql = "
        SELECT COUNT(*) AS c
        FROM tickets
        WHERE showtime_id=?
        AND seat_id IN ($qMarks)
        AND status IN ('pending','paid','confirmed')
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $num = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    return ($num == 0);
}

/* ============================
   FINALIZE PAYMENT (ADMIN/CALLBACK)
============================ */
function finalize_payment($payment_id, $confirmed_by = "system") {
    global $conn;

    $payment_id = (int)$payment_id;
    if ($payment_id <= 0) throw new Exception("payment_id không hợp lệ");

    /* LẤY PAYMENT */
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) throw new Exception("Không tìm thấy payment");
    if ($payment['status'] === 'success') return ["status"=>"ok","message"=>"Đã xử lý trước đó"];

    /* LẤY ORDER_DATA */
    $data = json_decode($payment['order_data'], true);
    if (!$data) throw new Exception("order_data trống hoặc lỗi JSON");

    $user_id      = (int)$payment['user_id'];
    $showtime_id  = (int)$data['showtime_id'];
    $seat_ids     = $data['seats'];
    $ticket_price = (float)$data['ticket_price'];
    $combos       = $data['combos'];
    $total_amount = (float)$data['total_amount'];

    if (!is_seat_available($showtime_id, $seat_ids))
        throw new Exception("Ghế đã có người đặt");

    /* TRANSACTION */
    $conn->begin_transaction();
    try {
        $now = date("Y-m-d H:i:s");
        $ticket_ids = [];

        /* INSERT VÉ */
        $sqlT = "
            INSERT INTO tickets
            (showtime_id, seat_id, user_id, channel, paid, status,
             confirmed_by, confirmed_at, price, payment_id)
            VALUES (?, ?, ?, 'online', 1, 'confirmed', ?, ?, ?, ?)
        ";
        $stmtT = $conn->prepare($sqlT);

        foreach ($seat_ids as $sid) {
            $sid = (int)$sid;
            $stmtT->bind_param(
                "iiissdi",
                $showtime_id, $sid, $user_id,
                $confirmed_by, $now, $ticket_price, $payment_id
            );
            $stmtT->execute();
            $ticket_ids[] = $stmtT->insert_id;
        }
        $stmtT->close();

        /* INSERT COMBO */
        if (!empty($combos)) {
            $sqlC = "
                INSERT INTO payment_combos(payment_id, combo_id, qty, price, total)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmtC = $conn->prepare($sqlC);
            foreach ($combos as $c) {
                $cid   = (int)$c['combo_id'];
                $qty   = (int)$c['qty'];
                $price = (float)$c['price'];
                $total = $qty * $price;

                $stmtC->bind_param("iiidd", $payment_id, $cid, $qty, $price, $total);
                $stmtC->execute();
            }
            $stmtC->close();
        }

        /* UPDATE PAYMENT */
        $stmtU = $conn->prepare("UPDATE payments SET status='success', paid_at=? WHERE payment_id=?");
        $stmtU->bind_param("si", $now, $payment_id);
        $stmtU->execute();
        $stmtU->close();

        $conn->commit();

        /* REALTIME */
        realtime_push("ticket_confirmed", [
            "showtime_id"=>$showtime_id,
            "seats"=>$seat_ids
        ]);

        return ["status"=>"ok","ticket_ids"=>$ticket_ids];

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
