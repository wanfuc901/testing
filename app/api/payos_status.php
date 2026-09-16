<?php
/**
 * Trang thanh toán hỏi trạng thái đơn hàng mỗi vài giây.
 *
 * Nguồn sự thật là webhook PayOS. Endpoint này chủ yếu đọc trạng thái trong
 * DB; chỉ khi đơn vẫn đang pending mới hỏi thẳng PayOS — để phòng trường hợp
 * webhook bị chậm hoặc chưa cấu hình (chạy local chẳng hạn).
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/payos.php';
require_once __DIR__ . '/../../helpers/order_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

vincine_require_customer(true);

$customerId = vincine_customer_id();
$paymentId  = (int)($_GET['payment_id'] ?? 0);

if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_id không hợp lệ']);
    exit;
}

$stmt = $conn->prepare("
    SELECT payment_id, status, amount, payos_order_code
    FROM payments
    WHERE payment_id = ? AND customer_id = ?
");
if (!$stmt) {
    error_log('[vincine] payos_status prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi hệ thống']);
    exit;
}

$stmt->bind_param('ii', $paymentId, $customerId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy đơn hàng']);
    exit;
}

$status = (string)$payment['status'];

/* Đã xong hoặc đã hủy thì không cần hỏi PayOS nữa. */
if ($status !== 'pending') {
    echo json_encode(['status' => $status, 'source' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderCode = (int)($payment['payos_order_code'] ?? 0);
if ($orderCode <= 0) {
    echo json_encode(['status' => $status, 'source' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ====================================================
   HỎI THẲNG PAYOS
==================================================== */
try {
    $link = vincine_payos_get_link($orderCode);
} catch (Throwable $e) {
    error_log('[vincine] payos_status: không tra cứu được đơn ' . $paymentId . ': ' . $e->getMessage());
    echo json_encode(['status' => $status, 'source' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payosStatus = strtoupper((string)($link['status'] ?? ''));

if ($payosStatus === 'PAID') {
    /*
     * Chỉ tin khi số tiền đã trả khớp số tiền đơn hàng. PayOS cho phép trả
     * một phần, số tiền thiếu thì chưa coi là thanh toán xong.
     */
    $amountPaid = (int)($link['amountPaid'] ?? 0);
    $expected   = (int)round((float)$payment['amount']);

    if ($amountPaid >= $expected) {
        $reference = null;
        foreach (($link['transactions'] ?? []) as $transaction) {
            if (!empty($transaction['reference'])) {
                $reference = (string)$transaction['reference'];
                break;
            }
        }

        try {
            $result = vincine_mark_payment_paid($paymentId, $reference);
            echo json_encode(['status' => 'paid', 'source' => 'payos', 'result' => $result['status']], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            error_log('[vincine] payos_status: ghi nhận thất bại đơn ' . $paymentId . ': ' . $e->getMessage());
        }
    }
}

if (in_array($payosStatus, ['CANCELLED', 'EXPIRED'], true)) {
    echo json_encode(['status' => 'canceled', 'source' => 'payos'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'pending', 'source' => 'payos'], JSON_UNESCAPED_UNICODE);
