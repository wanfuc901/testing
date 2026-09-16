<?php
/**
 * Điểm vào của ứng dụng: khởi tạo phiên, phân quyền theo vai trò và gọi router.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/include/auth.php';

ob_start();

/* ===== Hết hạn phiên do không hoạt động ===== */
if (isset($_SESSION['last_active']) && (time() - (int)$_SESSION['last_active']) > VINCINE_SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: index.php?p=login&msg=session_expired');
    exit;
}
$_SESSION['last_active'] = time();

require_once __DIR__ . '/app/controllers/main.php';

/* ===== Trang hiện tại (chỉ đọc từ query string) ===== */
$page = vincine_current_page();

/* ===== Trang không dùng menu/footer ===== */
$noLayout = ['login', 'rg', 'fp'];
$isAdminPage = vincine_is_admin_page($page);

/* ====================================================
   PHÂN QUYỀN
==================================================== */

/* Admin chỉ làm việc trong khu vực admin */
if (vincine_is_admin() && !$isAdminPage) {
    header('Location: index.php?p=admin_dashboard');
    exit;
}

/* Mọi vai trò còn lại (staff, customer, guest) không vào được khu vực admin */
if (!vincine_is_admin() && $isAdminPage) {
    header('Location: index.php?p=' . (vincine_is_logged_in() ? 'home' : 'login'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VinCine</title>
<link rel="icon" type="image/png" href="public/assets/icons/favicon.png">
<link rel="stylesheet" href="public/assets/css/style.css">
</head>

<body class="<?= in_array($page, $noLayout, true) ? 'vc-auth-body' : '' ?>">

<?php
if (!in_array($page, $noLayout, true)) {
    include __DIR__ . '/public/loading/loader.php';
}

if (vincine_is_admin()) {
    main($page);
} else {
    if (!in_array($page, $noLayout, true)) {
        include __DIR__ . '/app/views/layouts/menu.php';
    }

    main($page);

    if (!in_array($page, $noLayout, true)) {
        include __DIR__ . '/app/views/layouts/footer.php';
    }
}
?>
</body>
</html>
