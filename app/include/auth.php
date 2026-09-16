<?php
/**
 * Helper xác thực & phân quyền dùng chung.
 *
 * Vai trò hợp lệ khớp với schema:
 *   - users.role    : 'admin' | 'staff'
 *   - customers     : luôn map thành 'customer'
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('VINCINE_ROLE_ADMIN')) {
    define('VINCINE_ROLE_ADMIN', 'admin');
    define('VINCINE_ROLE_STAFF', 'staff');
    define('VINCINE_ROLE_CUSTOMER', 'customer');
}

/** Danh sách vai trò được phép tồn tại trong session. */
function vincine_valid_roles(): array
{
    return [VINCINE_ROLE_ADMIN, VINCINE_ROLE_STAFF, VINCINE_ROLE_CUSTOMER];
}

function vincine_current_role(): string
{
    $role = $_SESSION['role'] ?? '';

    return in_array($role, vincine_valid_roles(), true) ? (string)$role : '';
}

function vincine_is_logged_in(): bool
{
    return vincine_current_role() !== '';
}

function vincine_is_admin(): bool
{
    return vincine_current_role() === VINCINE_ROLE_ADMIN;
}

/** Nhân sự nội bộ: admin hoặc staff (phân biệt với khách hàng). */
function vincine_is_staff(): bool
{
    return in_array(vincine_current_role(), [VINCINE_ROLE_ADMIN, VINCINE_ROLE_STAFF], true);
}

function vincine_is_customer(): bool
{
    return vincine_current_role() === VINCINE_ROLE_CUSTOMER;
}

/** ID khách hàng đang đăng nhập, 0 nếu không phải khách hàng. */
function vincine_customer_id(): int
{
    return vincine_is_customer() ? (int)($_SESSION['customer_id'] ?? 0) : 0;
}

/** ID nhân sự đang đăng nhập, 0 nếu không phải admin/staff. */
function vincine_user_id(): int
{
    return vincine_is_staff() ? (int)($_SESSION['user_id'] ?? 0) : 0;
}

/**
 * Request này mong đợi JSON hay HTML?
 * Dùng để trả lỗi đúng định dạng thay vì chèn HTML vào response JSON.
 */
function vincine_wants_json(): bool
{
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        return true;
    }

    return stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

/**
 * Chặn request nếu không phải admin. Gọi ở đầu mọi endpoint quản trị.
 *
 * @param bool|null $asJson null = tự suy luận từ header request.
 */
function vincine_require_admin(?bool $asJson = null): void
{
    if (vincine_is_admin()) {
        return;
    }

    $asJson = $asJson ?? vincine_wants_json();

    http_response_code(403);

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
       . '<title>Không có quyền truy cập</title></head><body>'
       . '<h1>403 &mdash; Bạn không có quyền truy cập khu vực này.</h1>'
       . '<p><a href="/index.php?p=login">Đăng nhập bằng tài khoản quản trị</a></p>'
       . '</body></html>';
    exit;
}

/** Chặn request nếu chưa đăng nhập bằng tài khoản khách hàng. */
function vincine_require_customer(?bool $asJson = null): void
{
    if (vincine_customer_id() > 0) {
        return;
    }

    $asJson = $asJson ?? vincine_wants_json();

    http_response_code(401);

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: /index.php?p=login');
    exit;
}

/**
 * Làm mới session ID sau khi đăng nhập thành công (chống session fixation).
 * Gọi ngay trước khi ghi user_id/customer_id vào session.
 */
function vincine_start_authenticated_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_regenerate_id(true);
}

/* ====================================================
   CSRF
   Token gắn với phiên, dùng chung cho mọi biểu mẫu.
   session_regenerate_id() giữ nguyên dữ liệu phiên nên
   token vẫn hợp lệ sau khi đăng nhập.
==================================================== */

if (!defined('VINCINE_CSRF_FIELD')) {
    define('VINCINE_CSRF_FIELD', '_csrf');
    define('VINCINE_CSRF_HEADER', 'HTTP_X_CSRF_TOKEN');
}

function vincine_csrf_token(): string
{
    if (empty($_SESSION[VINCINE_CSRF_FIELD])) {
        $_SESSION[VINCINE_CSRF_FIELD] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION[VINCINE_CSRF_FIELD];
}

/** Ô input ẩn để nhúng vào biểu mẫu POST. */
function vincine_csrf_input(): string
{
    return '<input type="hidden" name="' . VINCINE_CSRF_FIELD . '" value="'
         . htmlspecialchars(vincine_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Thẻ meta để JavaScript đọc token khi gọi fetch. */
function vincine_csrf_meta(): string
{
    return '<meta name="csrf-token" content="'
         . htmlspecialchars(vincine_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Chặn request POST không kèm token hợp lệ.
 * Chấp nhận token trong body (biểu mẫu) hoặc header X-CSRF-Token (fetch).
 */
function vincine_verify_csrf(?bool $asJson = null): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $sent = (string)($_POST[VINCINE_CSRF_FIELD] ?? $_SERVER[VINCINE_CSRF_HEADER] ?? '');
    $expected = (string)($_SESSION[VINCINE_CSRF_FIELD] ?? '');

    if ($expected !== '' && $sent !== '' && hash_equals($expected, $sent)) {
        return;
    }

    error_log('[vincine] CSRF token không hợp lệ: ' . ($_SERVER['REQUEST_URI'] ?? '?'));

    $asJson = $asJson ?? vincine_wants_json();

    /*
     * Dùng 403 chứ không dùng 419: 419 là quy ước riêng của Laravel, không có
     * trong chuẩn HTTP, và Apache biến nó thành 500 Internal Server Error.
     */
    http_response_code(403);

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'csrf_token_invalid'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
       . '<title>Phiên làm việc hết hạn</title></head><body>'
       . '<h1>Phiên làm việc đã hết hạn</h1>'
       . '<p>Vui lòng tải lại trang và thao tác lại.</p>'
       . '<p><a href="/index.php">Về trang chủ</a></p>'
       . '</body></html>';
    exit;
}

/* ====================================================
   GIỚI HẠN SỐ LẦN ĐĂNG NHẬP SAI
   Dựa trên bảng login_attempts (xem DTB/migrations).
==================================================== */

if (!defined('VINCINE_LOGIN_MAX_PER_EMAIL')) {
    /** Số lần sai tối đa cho một email trong cửa sổ thời gian. */
    define('VINCINE_LOGIN_MAX_PER_EMAIL', 5);
    /** Số lần sai tối đa từ một địa chỉ IP trong cửa sổ thời gian. */
    define('VINCINE_LOGIN_MAX_PER_IP', 20);
    /** Độ dài cửa sổ thời gian tính bằng phút. */
    define('VINCINE_LOGIN_WINDOW_MINUTES', 15);
}

/** IP của client, cắt cho vừa cột VARCHAR(45). */
function vincine_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Bảng login_attempts có tồn tại không.
 *
 * Nếu chưa chạy migration thì bỏ qua throttle thay vì chặn đăng nhập —
 * mất một lớp phòng thủ phụ vẫn tốt hơn là khoá toàn bộ người dùng.
 */
function vincine_login_attempts_available(mysqli $conn): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $rs = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    $available = ($rs !== false && $rs->num_rows > 0);

    if (!$available) {
        error_log('[vincine] Thiếu bảng login_attempts — chưa chạy migration, throttle đăng nhập bị tắt.');
    }

    return $available;
}

/**
 * Số lần đăng nhập sai còn được phép. Trả về 0 nghĩa là đang bị khoá.
 */
function vincine_login_attempts_left(mysqli $conn, string $email): int
{
    if (!vincine_login_attempts_available($conn)) {
        return VINCINE_LOGIN_MAX_PER_EMAIL;
    }

    $stmt = $conn->prepare("
        SELECT
            SUM(identifier = ?) AS by_email,
            SUM(ip = ?)         AS by_ip
        FROM login_attempts
        WHERE attempted_at > (NOW() - INTERVAL ? MINUTE)
    ");
    if (!$stmt) {
        error_log('[vincine] login_attempts_left prepare failed: ' . $conn->error);
        return VINCINE_LOGIN_MAX_PER_EMAIL;
    }

    $ip     = vincine_client_ip();
    $window = VINCINE_LOGIN_WINDOW_MINUTES;
    $stmt->bind_param('ssi', $email, $ip, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $byEmail = (int)($row['by_email'] ?? 0);
    $byIp    = (int)($row['by_ip'] ?? 0);

    return max(0, min(
        VINCINE_LOGIN_MAX_PER_EMAIL - $byEmail,
        VINCINE_LOGIN_MAX_PER_IP - $byIp
    ));
}

function vincine_login_record_failure(mysqli $conn, string $email): void
{
    if (!vincine_login_attempts_available($conn)) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (?, ?, NOW())"
    );
    if (!$stmt) {
        error_log('[vincine] login_record_failure prepare failed: ' . $conn->error);
        return;
    }

    $ip = vincine_client_ip();
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->close();

    /* Dọn bản ghi cũ để bảng không phình vô hạn. */
    $conn->query(
        'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'
    );
}

/** Xoá lịch sử sai sau khi đăng nhập thành công. */
function vincine_login_clear_failures(mysqli $conn, string $email): void
{
    if (!vincine_login_attempts_available($conn)) {
        return;
    }

    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}
