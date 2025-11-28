<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../config/config.php";

header('Content-Type: text/plain; charset=utf-8'); // đảm bảo trả về text thuần

// --- Lấy OTP người dùng nhập ---
$otp = trim($_POST['otp'] ?? '');

// --- Kiểm tra dữ liệu và session ---
if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['otp_expire'])) {
    echo 'no_session';
    exit;
}

if (time() > $_SESSION['otp_expire']) {
    echo 'expired';
    exit;
}

// --- So sánh OTP ---
if ($otp === (string)$_SESSION['reset_otp']) {
    // Thành công → cho phép đổi mật khẩu
    $_SESSION['otp_verified'] = true;

    // Không xóa reset_email (cần để cập nhật mật khẩu)
    unset($_SESSION['reset_otp'], $_SESSION['otp_expire']);

    echo 'success';
} else {
    echo 'error';
}
?>
