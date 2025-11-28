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

/* ===== Trang KHÔNG HIỂN THỊ menu + footer + loader ===== */
$noLayout = ["login", "rg", "fp"];

/* ===== Điều hướng quyền ===== */
if ($page !== 'logout') {

    // Admin mà vào trang không phải admin → đẩy về admin_dashboard
    if ($role === 'admin' && substr($page, 0, 5) !== 'admin') {
        header("Location: index.php?p=admin_dashboard");
        exit;
    }

    // User mà vào admin → đẩy về home
    if ($role === 'user' && substr($page, 0, 5) === 'admin') {
        header("Location: index.php?p=home");
        exit;
    }
}


/* ===== Nạp router chính ===== */
require_once "app/controllers/main.php"; // nhớ kiểm tra đường dẫn này
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
if (!in_array($page, $noLayout)) {
    include "public/loading/loader.php";
}

if (function_exists('main')) {

    if ($role === 'admin') {
        main();
    } else {

        if (!in_array($page, $noLayout)) {
            include "app/views/layouts/menu.php";
        }

        main();

        if (!in_array($page, $noLayout)) {
            include "app/views/layouts/footer.php";
        }
    }

} else {
    echo "<p style='color:red;text-align:center'>⚠️ Lỗi: chưa định nghĩa hàm main() trong app/controllers/main.php</p>";
}
?>
</body>
</html>
