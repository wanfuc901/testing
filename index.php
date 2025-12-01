<?php
session_start();
ob_start();

/* ===== Kiểm tra session hết hạn ===== */
if (isset($_SESSION['last_active'])) {
    if (time() - $_SESSION['last_active'] > 3600) {
        session_unset();
        session_destroy();
        header("Location: index.php?p=login&msg=session_expired");
        exit;
    }
}
$_SESSION['last_active'] = time();

/* ===== Lấy trang hiện tại ===== */
$page = $_GET['p'] ?? 'home';
$role = $_SESSION['role'] ?? 'guest';

/* ===== Trang không có menu/footer ===== */
$noLayout = ["login", "rg", "fp"];

/* ====================================================
   PHÂN QUYỀN CHUẨN
==================================================== */

/* ADMIN → chỉ được vào admin/... */
if ($role === "admin") {
    if (substr($page, 0, 5) !== "admin") {
        header("Location: index.php?p=admin_dashboard");
        exit;
    }
}

/* CUSTOMER → KHÔNG được vào admin */
if ($role === "customer") {
    if (substr($page, 0, 5) === "admin") {
        header("Location: index.php?p=home");
        exit;
    }
}

/* USER → KHÔNG được vào admin */
if ($role === "user") {
    if (substr($page, 0, 5) === "admin") {
        header("Location: index.php?p=home");
        exit;
    }
}


/* ===== Nạp router chính ===== */
require_once "app/controllers/main.php";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>VinCine</title>
<link rel="icon" type="image/png" href="public/assets/icons/favicon.png">
<link rel="stylesheet" href="public/assets/css/style.css">
</head>

<body class="<?php echo in_array($page, $noLayout) ? 'vc-auth-body' : ''; ?>">

<?php
/* Loader */
if (!in_array($page, $noLayout)) {
    include "public/loading/loader.php";
}

/* ===== Gọi main router ===== */
if (function_exists('main')) {

    /* ADMIN */
    if ($role === "admin") {
        main();
    }

    /* USER + CUSTOMER + GUEST */
    else {
        if (!in_array($page, $noLayout)) {
            include "app/views/layouts/menu.php";
        }

        main();

        if (!in_array($page, $noLayout)) {
            include "app/views/layouts/footer.php";
        }
    }

} else {
    echo "<p style='color:red;text-align:center'>⚠ Lỗi: main() chưa tồn tại!</p>";
}
?>
</body>
</html>
