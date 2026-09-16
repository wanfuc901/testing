<?php
/**
 * Nơi PayOS đưa trình duyệt khách quay về sau khi thanh toán (returnUrl)
 * hoặc sau khi khách bấm hủy trên trang PayOS (cancelUrl).
 *
 * KHÔNG dùng trang này để công nhận đã thanh toán: đây chỉ là một redirect
 * trên trình duyệt, khách hoàn toàn tự gõ được URL. Việc ghi nhận tiền chỉ
 * xảy ra ở webhook đã verify chữ ký, hoặc khi tra cứu thẳng API PayOS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/payos.php';
require_once __DIR__ . '/../../helpers/order_helper.php';

$payment_id = (int)($_GET['pid'] ?? 0);
$isCancel   = (($_GET['p'] ?? '') === 'payos_cancel');

if ($payment_id <= 0) {
    header('Location: index.php?p=home');
    exit;
}

vincine_require_customer();
$customer_id = vincine_customer_id();

$stmt = $conn->prepare("
    SELECT payment_id, status, amount, payos_order_code
    FROM payments
    WHERE payment_id = ? AND customer_id = ?
");
if (!$stmt) {
    error_log('[vincine] payos_redirect prepare failed: ' . $conn->error);
    header('Location: index.php?p=home');
    exit;
}

$stmt->bind_param('ii', $payment_id, $customer_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    header('Location: index.php?p=home');
    exit;
}

/* ====================================================
   KHÁCH BẤM HỦY TRÊN TRANG PAYOS
==================================================== */
if ($isCancel) {
    if ($payment['status'] === 'pending') {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "UPDATE payments SET status = 'canceled', canceled_at = NOW() WHERE payment_id = ? AND status = 'pending'"
            );
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "UPDATE tickets SET status = 'cancelled' WHERE payment_id = ? AND status = 'pending'"
            );
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            emit_payment_update($payment_id, 'canceled');
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[vincine] payos_redirect hủy đơn ' . $payment_id . ' thất bại: ' . $e->getMessage());
        }
    }

    header('Location: index.php?p=home&payment=canceled');
    exit;
}

/* ====================================================
   QUAY VỀ SAU KHI THANH TOÁN
   Hỏi lại PayOS thay vì tin redirect.
==================================================== */
if ($payment['status'] === 'pending' && (int)$payment['payos_order_code'] > 0) {
    try {
        $link       = vincine_payos_get_link((int)$payment['payos_order_code']);
        $amountPaid = (int)($link['amountPaid'] ?? 0);
        $expected   = (int)round((float)$payment['amount']);

        if (strtoupper((string)($link['status'] ?? '')) === 'PAID' && $amountPaid >= $expected) {
            $reference = null;
            foreach (($link['transactions'] ?? []) as $transaction) {
                if (!empty($transaction['reference'])) {
                    $reference = (string)$transaction['reference'];
                    break;
                }
            }

            vincine_mark_payment_paid($payment_id, $reference);
            $payment['status'] = 'paid';
        }
    } catch (Throwable $e) {
        error_log('[vincine] payos_redirect tra cứu đơn ' . $payment_id . ' thất bại: ' . $e->getMessage());
    }
}

if (in_array($payment['status'], ['paid', 'success'], true)) {
    header('Location: public/booking_success.php?pid=' . $payment_id);
    exit;
}

/* Chưa nhận được tiền: quay lại trang QR để khách thanh toán tiếp. */
header('Location: app/views/payment/payment_qr.php?payment_id=' . $payment_id);
exit;
