<?php
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
    $types  = str_repeat('i', count($seat_ids) + 1);
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
   FINALIZE PAYMENT
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

    /* LẤY ORDER_DATA */
    $data = json_decode($payment['order_data'] ?? '', true);
    if (!$data) throw new Exception("order_data lỗi");

    $customer_id = (int)$payment['customer_id'];
    $showtime_id = (int)$data['showtime_id'];
    $seat_ids    = $data['seats'];
    $ticket_price = (float)$data['ticket_price'];
    $combos       = $data['combos'] ?? [];

    if (empty($seat_ids)) throw new Exception("Không có ghế trong order_data");

    /* LẤY VÉ HOLD */
    $hold = [];
    $stmtH = $conn->prepare("SELECT ticket_id, seat_id FROM tickets WHERE payment_id=?");
    $stmtH->bind_param("i", $payment_id);
    $stmtH->execute();
    $r = $stmtH->get_result();
    while ($row = $r->fetch_assoc()) $hold[] = $row;
    $stmtH->close();

    $now = date("Y-m-d H:i:s");
    $ticket_ids = [];
    $emitSeats = [];

    $conn->begin_transaction();

    try {

        /* ===============================
           CASE A — UPDATE VÉ HOLD
        =============================== */
        if (!empty($hold)) {

            $sqlU = "
                UPDATE tickets
                SET paid=1,
                    status='pending',
                    price=?,
                    customer_id=?
                WHERE ticket_id=?
            ";

            $stmtU = $conn->prepare($sqlU);
            if (!$stmtU) throw new Exception($conn->error);

            foreach ($hold as $t) {
                $tid = (int)$t['ticket_id'];

                $stmtU->bind_param(
                    "dii",
                    $ticket_price,
                    $customer_id,
                    $tid
                );
                $stmtU->execute();

                $ticket_ids[] = $tid;
            }
            $stmtU->close();

            $emitSeats = array_column($hold, 'seat_id');
        }

        /* ===============================
           CASE B — INSERT VÉ MỚI
        =============================== */
        else {

            if (!is_seat_available($showtime_id, $seat_ids))
                throw new Exception("Ghế đã có người đặt");

            $sqlT = "
                INSERT INTO tickets
                (showtime_id, seat_id, customer_id, payment_id, channel,
                 paid, status, price)
                VALUES (?, ?, ?, ?, 'online', 1, 'pending', ?)
            ";

            $stmtT = $conn->prepare($sqlT);
            if (!$stmtT) throw new Exception($conn->error);

            foreach ($seat_ids as $sid) {

                $stmtT->bind_param(
                    "iiiid",
                    $showtime_id,
                    $sid,
                    $customer_id,
                    $payment_id,
                    $ticket_price
                );
                $stmtT->execute();

                $ticket_ids[] = $stmtT->insert_id;
            }

            $stmtT->close();

            $emitSeats = $seat_ids;
        }

        /* ===============================
           INSERT COMBO
        =============================== */
        if (!empty($combos)) {

            $sqlC = "
                INSERT INTO payment_combos(payment_id, combo_id, qty, price, total)
                VALUES (?, ?, ?, ?, ?)
            ";

            $stmtC = $conn->prepare($sqlC);

            foreach ($combos as $c) {

                $cid   = $c['id'] ?? $c['combo_id'];
                $qty   = $c['qty'];
                $price = $c['price'];
                $total = $qty * $price;

                $stmtC->bind_param("iiidd", $payment_id, $cid, $qty, $price, $total);
                $stmtC->execute();
            }

            $stmtC->close();
        }

        /* UPDATE PAYMENT */
        $stmtU = $conn->prepare("UPDATE payments SET status='pending', paid_at=? WHERE payment_id=?");
        $stmtU->bind_param("si", $now, $payment_id);
        $stmtU->execute();
        $stmtU->close();

        $conn->commit();

        /* REALTIME */
        emit_seat_booked_done($showtime_id, $emitSeats);
        emit_payment_update($payment_id, 'pending');

        return ["status"=>"ok","ticket_ids"=>$ticket_ids];

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/* ============================
   GHI NHẬN ĐÃ THANH TOÁN
============================ */

/**
 * Đánh dấu đơn hàng đã nhận được tiền và xuất vé.
 *
 * Chỉ được gọi sau khi đã xác thực chữ ký webhook PayOS hoặc sau khi tra cứu
 * trạng thái trực tiếp từ API PayOS. Hàm này idempotent: gọi lại nhiều lần
 * cho cùng một đơn không tạo thêm vé và không đổi gì thêm.
 *
 * @return array{status: string, ticket_ids: int[]} status: paid | already
 * @throws RuntimeException
 */
function vincine_mark_payment_paid(int $payment_id, ?string $reference = null): array
{
    global $conn;

    if ($payment_id <= 0) {
        throw new RuntimeException('payment_id không hợp lệ');
    }

    $conn->begin_transaction();

    try {
        /*
         * Khóa dòng đơn hàng trong suốt giao dịch. Hai webhook đến cùng lúc
         * thì cái thứ hai phải chờ và sẽ thấy trạng thái đã là 'paid'.
         */
        $stmt = $conn->prepare("SELECT payment_id, status FROM payments WHERE payment_id = ? FOR UPDATE");
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            throw new RuntimeException('Không tìm thấy đơn hàng ' . $payment_id);
        }

        if (in_array($payment['status'], ['paid', 'success'], true)) {
            $conn->commit();
            return ['status' => 'already', 'ticket_ids' => []];
        }

        $stmt = $conn->prepare("
            UPDATE payments
            SET status = 'paid', paid_at = NOW(), payos_reference = ?
            WHERE payment_id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('si', $reference, $payment_id);
        $stmt->execute();
        $stmt->close();

        /*
         * Tiền đã vào tài khoản và được PayOS xác nhận nên vé chốt luôn,
         * không cần nhân viên đối soát thủ công như luồng chuyển khoản cũ.
         */
        $stmt = $conn->prepare("
            UPDATE tickets
            SET paid = 1, status = 'confirmed', confirmed_by = 'payos', confirmed_at = NOW()
            WHERE payment_id = ? AND status <> 'cancelled'
        ");
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $stmt->close();

        $ticket_ids = [];
        $showtime_id = 0;
        $seat_ids = [];

        $stmt = $conn->prepare("SELECT ticket_id, showtime_id, seat_id FROM tickets WHERE payment_id = ? AND status = 'confirmed'");
        if ($stmt) {
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($row = $rs->fetch_assoc()) {
                $ticket_ids[]  = (int)$row['ticket_id'];
                $seat_ids[]    = (int)$row['seat_id'];
                $showtime_id   = (int)$row['showtime_id'];
            }
            $stmt->close();
        }

        $conn->commit();

        if ($showtime_id > 0 && $seat_ids) {
            emit_seat_booked_done($showtime_id, $seat_ids);
        }
        emit_payment_update($payment_id, 'paid');

        return ['status' => 'paid', 'ticket_ids' => $ticket_ids];

    } catch (Throwable $e) {
        $conn->rollback();
        throw new RuntimeException($e->getMessage(), 0, $e);
    }
}

/**
 * Sinh orderCode cho PayOS: số nguyên dương, tăng dần, duy nhất vĩnh viễn.
 *
 * Ghép mốc thời gian với payment_id nên không đụng nhau kể cả khi database
 * được tạo lại từ đầu (payment_id quay về 1 nhưng thời gian thì không).
 */
function vincine_payos_build_order_code(int $payment_id): int
{
    return (int)(time() . str_pad((string)($payment_id % 1000), 3, '0', STR_PAD_LEFT));
}
