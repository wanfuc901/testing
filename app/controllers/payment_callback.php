<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../helpers/realtime.php";
require_once __DIR__ . "/../../helpers/order_helper.php";

$conn->set_charset("utf8mb4");
date_default_timezone_set("Asia/Ho_Chi_Minh");

ini_set('display_errors',1);
error_reporting(E_ALL);


/* ============================
   NHẬN ACTION
============================ */
$action = $_POST['action'] ?? '';
$payment_id = intval($_POST['payment_id'] ?? 0);


/* ============================
   1) XỬ LÝ HỦY TỰ ĐỘNG
============================ */
if ($action === 'auto_cancel') {

    if ($payment_id <= 0) {
        die("payment_id không hợp lệ");
    }

    // HỦY PAYMENT
    $conn->query("
        UPDATE payments 
        SET status = 'canceled', canceled_at = NOW()
        WHERE payment_id = $payment_id
    ");

    // MỞ GHẾ
    $conn->query("
        UPDATE seats 
        SET temp_locked = 0, temp_locked_until = NULL
        WHERE seat_id IN (SELECT seat_id FROM tickets WHERE payment_id = $payment_id)
    ");

    echo "OK";
    exit;
}


/* ============================
   2) XỬ LÝ XÁC NHẬN ĐÃ CHUYỂN KHOẢN
============================ */
if ($payment_id <= 0) {
    die("Thiếu hoặc sai payment_id.");
}

/* Lấy PAYMENT */
$stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) die("Không tìm thấy hóa đơn.");
if ($pay['status'] !== 'pending') die("Hóa đơn không còn pending.");


/* ============================
   FINALIZE PAYMENT (người dùng bấm)
============================ */
try {
    finalize_payment($payment_id, "user_callback");
} catch (Exception $e) {
    die("Finalize payment failed: " . $e->getMessage());
}

/* Gửi mail */
include __DIR__ . "/send_ticket_email.php";

/* Điều hướng */
header("Location: ../../public/booking_pending.php?pid=" . $payment_id);
exit;
?>
