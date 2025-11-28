<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../helpers/realtime.php";

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Ho_Chi_Minh');

ini_set('display_errors',1);
error_reporting(E_ALL);

/* ============================
   VALIDATE SESSION
============================ */
if (empty($_SESSION['pending_order'])) {
    die("Không có đơn hàng pending.");
}

$order = $_SESSION['pending_order'];

$showtime_id  = (int)$order['showtime_id'];
$seat_ids     = $order['seat_ids'];
$ticket_total = (float)$order['ticket_total'];
$total_price  = (float)$order['total'];
$user_id      = (int)($_SESSION['user_id'] ?? 0);

/* LẤY payment_id (NẾU ĐÃ TẠO PAYMENT Ở BƯỚC TRƯỚC) */
$payment_id   = isset($order['payment_id']) ? (int)$order['payment_id'] : null;

if (!$showtime_id || empty($seat_ids) || !$user_id) {
    die("Dữ liệu đơn hàng không hợp lệ.");
}

/* ============================
   TÍNH GIÁ MỖI GHẾ
============================ */
$seat_count = count($seat_ids);
$price_per_seat = $seat_count > 0 ? round($ticket_total / $seat_count, 2) : 0;

/* ===========================================================
   1) KHÔNG TẠO PAYMENT Ở ĐÂY, CHỈ DÙNG payment_id CÓ SẴN
=========================================================== */
/*
$stmtPay = $conn->prepare("...");
$stmtPay->execute();
$payment_id = $stmtPay->insert_id;
$stmtPay->close();
*/

/* ============================
   2) TẠO TICKETS (CONFIRMED)
============================ */
$ticket_ids = [];
$seatLabels = [];

/* Check ghế */
$stmtCheck = $conn->prepare("SELECT 1 FROM tickets WHERE showtime_id=? AND seat_id=?");

/* Insert vé – CÓ payment_id */
$stmtInsert = $conn->prepare("
    INSERT INTO tickets
    (showtime_id, seat_id, user_id, price, booked_at, channel, paid, status, payment_id)
    VALUES (?, ?, ?, ?, NOW(), 'online', 1, 'confirmed', ?)
");

/* Lấy thông tin ghế – LINH HOẠT THEO CẤU TRÚC BẢNG SEATS */
$stmtSeat = $conn->prepare("
    SELECT *
    FROM seats
    WHERE seat_id=?
");

foreach ($seat_ids as $sid) {

    /* 1) Check ghế đã có người lấy chưa */
    $stmtCheck->bind_param("ii", $showtime_id, $sid);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) continue;

    /* 2) Insert ticket (kèm payment_id) */
    $stmtInsert->bind_param("iiidi", $showtime_id, $sid, $user_id, $price_per_seat, $payment_id);
    $stmtInsert->execute();
    $ticket_ids[] = $stmtInsert->insert_id;

    /* 3) Lấy nhãn ghế linh hoạt theo cột có sẵn */
    $stmtSeat->bind_param("i", $sid);
    $stmtSeat->execute();
    $resSeat = $stmtSeat->get_result();
    $seat = $resSeat ? $resSeat->fetch_assoc() : null;

    if ($seat) {
        if (!empty($seat['seat_label'])) {
            // Nếu DB có cột seat_label thì dùng luôn
            $seatLabels[] = $seat['seat_label'];
        } elseif (!empty($seat['seat_name'])) {
            // Một số DB đặt là seat_name
            $seatLabels[] = $seat['seat_name'];
        } elseif (!empty($seat['row_label']) && !empty($seat['seat_number'])) {
            // Hoặc row_label + seat_number
            $seatLabels[] = $seat['row_label'] . $seat['seat_number'];
        } else {
            // Fallback: dùng seat_id cho chắc, tránh lỗi
            $seatLabels[] = 'Ghế #' . $sid;
        }
    }
}

$stmtCheck->close();
$stmtInsert->close();
$stmtSeat->close();

/* ===========================================================
   3) BỎ LƯU COMBOS THEO PAYMENT (payment_id không còn xử lý ở đây)
=========================================================== */

/*
$combos = $order['combos'] ?? [];
...
INSERT INTO payment_combos ...
*/

/* ============================
   4) REALTIME UPDATE GHẾ
============================ */
emit_seat_booked_done($showtime_id, $seat_ids);

/* ============================
   5) LƯU LAST BOOKING
============================ */
$_SESSION['last_booking'] = [
    "showtime_id" => $showtime_id,
    "seat_labels" => $seatLabels,
    "total"       => $total_price,
    "method"      => "online"
];

/* ============================
   6) CLEAR SESSION
============================ */
unset($_SESSION['pending_order'], $_SESSION['bank_txn']);

header("Location: ../../public/booking_success.php");
exit;
