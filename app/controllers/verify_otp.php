<?php
/**
 * Xác minh mã OTP đặt lại mật khẩu.
 * Trả về text thuần cho frontend: no_session | expired | locked | success | error
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

header('Content-Type: text/plain; charset=utf-8');

vincine_verify_csrf(true);

/** Số lần nhập sai tối đa trước khi phải xin mã mới. */
const OTP_MAX_ATTEMPTS = 5;

$otp = trim((string)($_POST['otp'] ?? ''));

if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['otp_expire'])) {
    echo 'no_session';
    exit;
}

if (time() > (int)$_SESSION['otp_expire']) {
    unset($_SESSION['reset_otp'], $_SESSION['otp_expire'], $_SESSION['otp_attempts']);
    echo 'expired';
    exit;
}

$attempts = (int)($_SESSION['otp_attempts'] ?? 0);
if ($attempts >= OTP_MAX_ATTEMPTS) {
    // Vô hiệu hóa mã hiện tại để không thể dò tiếp.
    unset($_SESSION['reset_otp'], $_SESSION['otp_expire'], $_SESSION['otp_attempts']);
    echo 'locked';
    exit;
}

// So sánh theo thời gian hằng định, tránh rò rỉ qua thời gian phản hồi.
if (hash_equals((string)$_SESSION['reset_otp'], $otp)) {
    $_SESSION['otp_verified'] = true;

    // Giữ reset_email và reset_account để bước đổi mật khẩu dùng tiếp.
    unset($_SESSION['reset_otp'], $_SESSION['otp_expire'], $_SESSION['otp_attempts']);

    echo 'success';
    exit;
}

$_SESSION['otp_attempts'] = $attempts + 1;
echo 'error';
