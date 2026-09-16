<?php
/**
 * Router chính.
 *
 * Bảng tuyến đường là nguồn dữ liệu duy nhất cho cả việc nạp file lẫn việc
 * xác định trang nào thuộc khu vực quản trị — thay cho kiểm tra tiền tố
 * chuỗi "admin" vốn dễ bỏ sót khi thêm tuyến mới.
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

if (!defined('VINCINE_ROOT')) {
    define('VINCINE_ROOT', dirname(__DIR__, 2));
}

const VINCINE_DEFAULT_PAGE = 'home';

/**
 * @return array<string, array{file: string, admin?: bool}>
 */
function vincine_routes(): array
{
    static $routes = null;

    if ($routes !== null) {
        return $routes;
    }

    $routes = [
        /* ===== Trang người dùng ===== */
        'home'       => ['file' => 'app/views/layouts/home.php'],
        'abt'        => ['file' => 'public/about.php'],
        'mv'         => ['file' => 'app/views/movies/movie.php'],
        'bk'         => ['file' => 'app/views/tickets/booking.php'],
        'ck'         => ['file' => 'public/checkout.php'],
        'acc'        => ['file' => 'public/account.php'],
        'am'         => ['file' => 'app/views/layouts/all_movie.php'],
        'ao'         => ['file' => 'app/views/layouts/all_offers.php'],
        'od'         => ['file' => 'app/views/offers/offers.php'],
        'nowshowing' => ['file' => 'app/views/layouts/all_movie.php'],
        'upcoming'   => ['file' => 'app/views/layouts/all_movie.php'],

        /* ===== Trang không có menu/footer ===== */
        'login'      => ['file' => 'public/login.php'],
        'rg'         => ['file' => 'public/register.php'],
        'fp'         => ['file' => 'public/forgot_password.php'],

        /* ===== Xử lý biểu mẫu ===== */
        'pcl'        => ['file' => 'app/controllers/process_login.php'],
        'pcr'        => ['file' => 'app/controllers/process_register.php'],
        'cbp'        => ['file' => 'app/controllers/process_combo.php'],
        'cbs'        => ['file' => 'public/combo_select.php'],
        'srch'       => ['file' => 'app/controllers/process_search.php'],

        /* ===== PayOS đưa trình duyệt quay về ===== */
        'payos_return' => ['file' => 'app/controllers/payos_redirect.php'],
        'payos_cancel' => ['file' => 'app/controllers/payos_redirect.php'],

        /* ===== Khu vực quản trị ===== */
        'admin'           => ['file' => 'admin/dashboard.php',            'admin' => true],
        'admin_dashboard' => ['file' => 'admin/dashboard.php',            'admin' => true],
        'admin_payments'  => ['file' => 'admin/payments.php',             'admin' => true],
        'admin_movies'    => ['file' => 'admin/movies.php',               'admin' => true],
        'admin_showtimes' => ['file' => 'admin/showtimes.php',            'admin' => true],
        'admin_users'     => ['file' => 'admin/users.php',                'admin' => true],
        'admin_tickets'   => ['file' => 'admin/tickets.php',              'admin' => true],
        'admin_revenue'   => ['file' => 'admin/revenue.php',              'admin' => true],
        'admin_combos'    => ['file' => 'admin/combos.php',               'admin' => true],
        'admin_ranking'   => ['file' => 'admin/ranking.php',              'admin' => true],
        'admin_genres'    => ['file' => 'admin/genres.php',               'admin' => true],
        'admin_sched'     => ['file' => 'app/admin/showtimes/index.php',  'admin' => true],
    ];

    return $routes;
}

/** Trang đang được yêu cầu, đã chuẩn hóa về một tuyến hợp lệ. */
function vincine_current_page(): string
{
    $page = $_GET['p'] ?? VINCINE_DEFAULT_PAGE;

    if (!is_string($page)) {
        return VINCINE_DEFAULT_PAGE;
    }

    if ($page === 'logout') {
        return 'logout';
    }

    return isset(vincine_routes()[$page]) ? $page : VINCINE_DEFAULT_PAGE;
}

function vincine_is_admin_page(string $page): bool
{
    return (bool)(vincine_routes()[$page]['admin'] ?? false);
}

/**
 * Nạp view/controller tương ứng với tuyến.
 */
function main(string $page = VINCINE_DEFAULT_PAGE): void
{
    /* View và controller được include bên dưới chạy trong scope của hàm này. */
    global $conn;

    if ($page === 'logout') {
        /* Đăng xuất là thao tác đổi trạng thái: bắt buộc POST kèm token. */
        vincine_verify_csrf(false);

        session_unset();
        session_destroy();
        header('Location: index.php?p=home');
        exit;
    }

    $route = vincine_routes()[$page] ?? vincine_routes()[VINCINE_DEFAULT_PAGE];

    if (!empty($route['admin'])) {
        vincine_require_admin(false);
    }

    $target = VINCINE_ROOT . '/' . $route['file'];

    if (!is_file($target)) {
        error_log('[vincine] route target missing: ' . $route['file']);
        http_response_code(500);
        echo '<p style="color:#e50914;text-align:center">Trang này hiện không khả dụng.</p>';
        return;
    }

    include $target;
}
