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
