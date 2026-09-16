<?php
/**
 * Bootstrap cấu hình Vincent Cinemas.
 *
 * Nạp biến môi trường từ app/config/env.php (không commit) hoặc getenv(),
 * mở kết nối MySQL dùng chung qua biến $conn và khai báo hằng số toàn cục.
 */

declare(strict_types=1);

if (defined('VINCINE_CONFIG_LOADED')) {
    /*
     * File đã chạy trong request này. Một số view include lại config.php từ
     * bên trong thân hàm main(), nên phải tái xuất $conn vào scope hiện tại —
     * nếu không biến sẽ không tồn tại ở đó.
     */
    $conn = $GLOBALS['vincine_conn'] ?? null;
    return;
}
define('VINCINE_CONFIG_LOADED', true);

/* ====================================================
   1) NẠP BIẾN MÔI TRƯỜNG
==================================================== */

/** @var array<string,mixed> $VINCINE_ENV */
$VINCINE_ENV = [];

$envFile = __DIR__ . '/env.php';
if (is_file($envFile)) {
    $loaded = require $envFile;
    if (is_array($loaded)) {
        $VINCINE_ENV = $loaded;
    }
}

/*
 * Khai báo có điều kiện: PHP bind hàm khai báo ở top-level ngay lúc biên dịch
 * file, tức là TRƯỚC khi lệnh return ở guard phía trên chạy. Một số view
 * include (không phải include_once) config.php nên nếu không bọc function_exists
 * sẽ lỗi "Cannot redeclare".
 */
if (!function_exists('vincine_env')) {
    /**
     * Đọc một khóa cấu hình: env.php > biến môi trường hệ thống > giá trị mặc định.
     */
    function vincine_env(string $key, $default = null)
    {
        global $VINCINE_ENV;

        if (array_key_exists($key, $VINCINE_ENV)) {
            return $VINCINE_ENV[$key];
        }

        $fromSystem = getenv($key);
        if ($fromSystem !== false && $fromSystem !== '') {
            return $fromSystem;
        }

        return $default;
    }
}

/* ====================================================
   2) HẰNG SỐ ỨNG DỤNG
==================================================== */

/** Giá vé fallback khi phim chưa khai báo ticket_price. */
define('VINCINE_DEFAULT_TICKET_PRICE', 80000.00);

/** Thời gian session không hoạt động trước khi bị hủy (giây). */
define('VINCINE_SESSION_LIFETIME', 3600);

/** Thời hạn hiệu lực của mã OTP đặt lại mật khẩu (giây). */
define('VINCINE_OTP_LIFETIME', 300);

define('VINCINE_DEBUG', filter_var(vincine_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

if (!defined('QR_SECRET')) {
    define('QR_SECRET', (string)vincine_env('QR_SECRET', ''));
}

define('GOOGLE_CLIENT_ID', (string)vincine_env('GOOGLE_CLIENT_ID', ''));

/* ====================================================
   3) CHẾ ĐỘ BÁO LỖI
   Production: ghi log, không in ra trình duyệt (tránh lộ
   đường dẫn server, câu SQL và thông tin kết nối).
==================================================== */

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');
ini_set('display_errors', VINCINE_DEBUG ? '1' : '0');
ini_set('display_startup_errors', VINCINE_DEBUG ? '1' : '0');
error_reporting(VINCINE_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/* ====================================================
   4) COOKIE PHIÊN AN TOÀN
   Phải đặt TRƯỚC session_start() đầu tiên.
==================================================== */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ====================================================
   5) KẾT NỐI MYSQL
==================================================== */

/*
 * Codebase kiểm tra lỗi truy vấn bằng giá trị trả về (if (!$rs) ...),
 * nên tắt chế độ ném exception của mysqli để hành vi đồng nhất trên
 * mọi phiên bản PHP (PHP >= 8.1 mặc định bật exception).
 */
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli(
    (string)vincine_env('DB_HOST', '127.0.0.1'),
    (string)vincine_env('DB_USER', 'root'),
    (string)vincine_env('DB_PASS', ''),
    (string)vincine_env('DB_NAME', 'vincine'),
    (int)vincine_env('DB_PORT', 3306)
);

if ($conn->connect_errno) {
    error_log('[vincine] MySQL connect failed: ' . $conn->connect_error);
    http_response_code(503);

    if (VINCINE_DEBUG) {
        exit('<b>Lỗi kết nối MySQL:</b> ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
    }
    exit('Hệ thống đang bảo trì. Vui lòng thử lại sau.');
}

$conn->set_charset('utf8mb4');

/* Tham chiếu toàn cục để mọi scope lấy lại được cùng một kết nối. */
$GLOBALS['vincine_conn'] = $conn;

date_default_timezone_set('Asia/Ho_Chi_Minh');
