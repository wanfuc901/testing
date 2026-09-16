<?php
/**
 * Người dùng bấm "Tôi đã chuyển khoản" hoặc hết giờ giữ ghế.
 *
 * Đơn hàng chỉ chuyển sang trạng thái chờ admin xác nhận — không tự đánh dấu
 * đã thu tiền. Mọi thao tác đều phải là chủ đơn hàng.
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/realtime.php';
require_once __DIR__ . '/../../helpers/order_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

vincine_require_customer();

$customerId = vincine_customer_id();
$action     = (string)($_POST['action'] ?? '');
$paymentId  = (int)($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    http_response_code(400);
    exit('Thiếu hoặc sai payment_id.');
}

/* ============================
   LẤY ĐƠN & KIỂM TRA SỞ HỮU
============================ */
$stmt = $conn->prepare("SELECT payment_id, customer_id, status FROM payments WHERE payment_id = ?");
if (!$stmt) {
    error_log('[vincine] payment_callback prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Hệ thống đang bận. Vui lòng thử lại sau.');
}

$stmt->bind_param('i', $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || (int)$payment['customer_id'] !== $customerId) {
    // Không phân biệt "không tồn tại" và "không phải của bạn".
    http_response_code(404);
    exit('Không tìm thấy hóa đơn.');
}

if ($payment['status'] !== 'pending') {
    http_response_code(409);
    exit('Hóa đơn không còn ở trạng thái chờ thanh toán.');
}

/* ============================
   1) HỦY ĐƠN (hết giờ giữ ghế)
============================ */
if ($action === 'auto_cancel') {

    $conn->begin_transaction();

    try {
        $cancelPayment = $conn->prepare(
            "UPDATE payments SET status = 'canceled', canceled_at = NOW() WHERE payment_id = ? AND status = 'pending'"
        );
        if (!$cancelPayment) {
            throw new RuntimeException($conn->error);
        }
        $cancelPayment->bind_param('i', $paymentId);
        $cancelPayment->execute();
        $cancelPayment->close();

        /*
         * Trả ghế về trạng thái trống bằng cách hủy chính các vé đang giữ.
         * is_seat_available() chỉ tính vé có status pending/paid/confirmed.
         */
        $releaseSeats = $conn->prepare(
            "UPDATE tickets SET status = 'cancelled' WHERE payment_id = ? AND status = 'pending'"
        );
        if (!$releaseSeats) {
            throw new RuntimeException($conn->error);
        }
        $releaseSeats->bind_param('i', $paymentId);
        $releaseSeats->execute();
        $releaseSeats->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[vincine] auto_cancel failed for payment ' . $paymentId . ': ' . $e->getMessage());
        http_response_code(500);
        exit('Không hủy được đơn hàng.');
    }

    emit_payment_update($paymentId, 'canceled');

    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
    exit;
}

/* ============================
   2) KHÁCH BÁO ĐÃ CHUYỂN KHOẢN
============================ */
try {
    finalize_payment($paymentId, 'user_callback');
} catch (Exception $e) {
    error_log('[vincine] finalize_payment failed for ' . $paymentId . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Không ghi nhận được thanh toán. Vui lòng liên hệ quầy vé.');
}

/* Gửi mail xác nhận (send_ticket_email.php đọc biến $payment_id từ scope này) */
$payment_id = $paymentId;
try {
    require __DIR__ . '/send_ticket_email.php';
} catch (Throwable $e) {
    error_log('[vincine] send_ticket_email failed for ' . $paymentId . ': ' . $e->getMessage());
}

header('Location: ../../public/booking_pending.php?pid=' . $paymentId);
exit;
