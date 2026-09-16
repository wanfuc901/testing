<?php
/**
 * Nhận webhook PayOS — đây là nơi DUY NHẤT hệ thống công nhận "đã nhận tiền".
 *
 * Nguyên tắc:
 *   1. Chưa verify chữ ký HMAC thì chưa đụng vào dữ liệu. Nếu bỏ bước này,
 *      bất kỳ ai cũng POST giả được để tự duyệt vé của mình.
 *   2. Số tiền PayOS báo phải khớp số tiền đơn hàng trong DB.
 *   3. Idempotent: PayOS gửi lại cùng một giao dịch nhiều lần. Khóa UNIQUE
 *      trên payos_webhook_log.reference đảm bảo chỉ xử lý đúng một lần.
 *
 * Endpoint này KHÔNG kiểm tra session và KHÔNG kiểm tra CSRF: người gọi là
 * máy chủ PayOS, không phải trình duyệt. Chữ ký HMAC chính là cơ chế xác thực.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../helpers/payos.php';
require_once __DIR__ . '/../../helpers/order_helper.php';

header('Content-Type: application/json; charset=utf-8');

/** Trả lời PayOS rồi kết thúc. */
function payos_reply(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Ghi nhật ký webhook. Trả về false nếu reference đã được ghi trước đó. */
function payos_log_webhook(
    mysqli $conn,
    ?int $orderCode,
    ?string $reference,
    ?int $paymentId,
    bool $verified,
    bool $processed,
    string $note,
    string $rawBody
): bool {
    $stmt = $conn->prepare("
        INSERT INTO payos_webhook_log
            (order_code, reference, payment_id, verified, processed, note, raw_body, received_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        error_log('[vincine] payos_webhook log prepare failed: ' . $conn->error);
        return true;
    }

    $verifiedInt  = $verified ? 1 : 0;
    $processedInt = $processed ? 1 : 0;
    $body         = mb_substr($rawBody, 0, 60000);

    $stmt->bind_param('isiiiss', $orderCode, $reference, $paymentId, $verifiedInt, $processedInt, $note, $body);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    // 1062 = trùng khóa UNIQUE trên reference -> webhook này đã xử lý rồi.
    if (!$ok && $errno === 1062) {
        return false;
    }

    if (!$ok) {
        error_log('[vincine] payos_webhook log insert failed: ' . $conn->error);
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payos_reply(false, 'Chỉ chấp nhận POST', 405);
}

$rawBody = (string)file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    error_log('[vincine] payos_webhook: body không phải JSON hợp lệ');
    payos_reply(false, 'Body không hợp lệ', 400);
}

/* ====================================================
   1) XÁC THỰC CHỮ KÝ — trước mọi thứ khác
==================================================== */
$data = vincine_payos_verify_webhook($payload);

if ($data === null) {
    error_log('[vincine] payos_webhook: chữ ký không hợp lệ, từ chối');
    payos_log_webhook($conn, null, null, null, false, false, 'Chữ ký không hợp lệ', $rawBody);
    payos_reply(false, 'Chữ ký không hợp lệ', 401);
}

$orderCode = isset($data['orderCode']) ? (int)$data['orderCode'] : 0;
$amount    = isset($data['amount']) ? (int)$data['amount'] : 0;
$reference = isset($data['reference']) ? (string)$data['reference'] : '';
$code      = (string)($data['code'] ?? $payload['code'] ?? '');

/*
 * PayOS gọi thử endpoint này khi đăng ký webhook, với orderCode giả (123).
 * Chữ ký của gói tin đó vẫn hợp lệ, nên chỉ cần trả 200 để xác nhận sống.
 */
if ($orderCode <= 0 || $reference === '') {
    payos_log_webhook($conn, $orderCode ?: null, null, null, true, false, 'Gói tin kiểm tra', $rawBody);
    payos_reply(true, 'Webhook hoạt động');
}

/* ====================================================
   2) CHỐNG XỬ LÝ TRÙNG
==================================================== */
if (!payos_log_webhook($conn, $orderCode, $reference, null, true, false, 'Đang xử lý', $rawBody)) {
    payos_reply(true, 'Giao dịch đã được xử lý trước đó');
}

/* ====================================================
   3) TÌM ĐƠN HÀNG
==================================================== */
$stmt = $conn->prepare("SELECT payment_id, amount, status FROM payments WHERE payos_order_code = ?");
if (!$stmt) {
    error_log('[vincine] payos_webhook prepare failed: ' . $conn->error);
    payos_reply(false, 'Lỗi hệ thống', 500);
}

$stmt->bind_param('i', $orderCode);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    error_log('[vincine] payos_webhook: không tìm thấy đơn với orderCode ' . $orderCode);
    // Vẫn trả 200: đơn không tồn tại thì PayOS gửi lại cũng vô ích.
    payos_reply(true, 'Không tìm thấy đơn hàng');
}

$paymentId = (int)$payment['payment_id'];

/* ====================================================
   4) ĐỐI CHIẾU SỐ TIỀN
   Không tin số tiền trong webhook: phải khớp đơn trong DB.
==================================================== */
if ($amount !== (int)round((float)$payment['amount'])) {
    error_log(sprintf(
        '[vincine] payos_webhook: lệch số tiền đơn %d — PayOS báo %d, DB ghi %s',
        $paymentId,
        $amount,
        $payment['amount']
    ));
    payos_reply(true, 'Số tiền không khớp, đã ghi nhận để đối soát thủ công');
}

/* Chỉ giao dịch thành công mới ghi nhận. */
if ($code !== '' && $code !== '00') {
    payos_reply(true, 'Giao dịch không thành công, bỏ qua');
}

/* ====================================================
   5) GHI NHẬN ĐÃ THANH TOÁN
==================================================== */
try {
    $result = vincine_mark_payment_paid($paymentId, $reference);
} catch (Throwable $e) {
    error_log('[vincine] payos_webhook: không ghi nhận được đơn ' . $paymentId . ': ' . $e->getMessage());
    payos_reply(false, 'Lỗi xử lý đơn hàng', 500);
}

$conn->query(sprintf(
    "UPDATE payos_webhook_log SET processed = 1, payment_id = %d, note = '%s' WHERE reference = '%s'",
    $paymentId,
    $conn->real_escape_string($result['status']),
    $conn->real_escape_string($reference)
));

/*
 * Gửi mail vé, chỉ ở lần xử lý đầu tiên. Mail hỏng không được làm webhook
 * trả lỗi — PayOS sẽ gửi lại và ta xử lý trùng vô ích.
 */
if ($result['status'] === 'paid') {
    $payment_id = $paymentId;
    try {
        require __DIR__ . '/../controllers/send_ticket_email.php';
    } catch (Throwable $e) {
        error_log('[vincine] payos_webhook: gửi mail vé thất bại đơn ' . $paymentId . ': ' . $e->getMessage());
    }
}

payos_reply(true, 'Đã ghi nhận thanh toán cho đơn ' . $paymentId);
