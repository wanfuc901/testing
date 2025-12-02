<?php
function main() {

    $p = $_REQUEST["p"] ?? "home";

    switch ($p) {

        /* ====== TRANG NGƯỜI DÙNG ====== */
        case "home":
            include("app/views/layouts/home.php");
            break;

        case "abt":
            include("public/about.php");
            break;

        /* ====== TRANG KHÔNG LOAD HEADER/FOOTER ====== */
        case "login":
            include("public/login.php");
            break;

        case "rg":
            include("public/register.php");
            break;

        case "fp":
            include("public/forgot_password.php");
            break;

        /* ====== TRANG KHÁC ====== */
        case "mv":
            include("app/views/movies/movie.php");
            break;

        case "bk":
            include("app/views/tickets/booking.php");
            break;

        case "ck":
            include("public/checkout.php");
            break;

        case "acc":
            include("public/account.php");
            break;

        case "am":
            include("app/views/layouts/all_movie.php");
            break;

        case "ao":
            include("app/views/layouts/all_offers.php");
            break;

        case "od":
            include("app/views/offers/offers.php");
            break;

        case "nowshowing":
        case "upcoming":
            include("app/views/layouts/all_movie.php");
            break;

        /* ====== XỬ LÝ FORM ====== */
        case "pcl":
            include("app/controllers/process_login.php");
            break;

        case "pcr":
            include("app/controllers/process_register.php");
            break;

        case "pcb":
            include("app/controllers/process_booking.php");
            break;

        case "cbp":
            include("app/controllers/process_combo.php");
            break;

        case "cbs":
            include("public/combo_select.php");
            break;

        case "srch":
            include("app/controllers/process_search.php");
            break;

        /* ===== ADMIN – bắt buộc login admin ===== */
        case "admin":
        case "admin_dashboard":
            checkAdmin();
            include("admin/dashboard.php");
            break;
        case "admin_payments":
            checkAdmin();
            include("admin/payments.php");
            break;
        case "admin_movies":
            checkAdmin();
            include("admin/movies.php");
            break;

        case "admin_showtimes":
            checkAdmin();
            include("admin/showtimes.php");
            break;

        case "admin_users":
            checkAdmin();
            include("admin/users.php");
            break;

        case "admin_tickets":
            checkAdmin();
            include("admin/tickets.php");
            break;

        case "admin_revenue":
            checkAdmin();
            include("admin/revenue.php");
            break;

        case "admin_sched":
            checkAdmin();
            include("app/admin/showtimes/index.php");
            break;

        case "admin_combos":
            checkAdmin();
            include("admin/combos.php");
            break;

        case "admin_test":
            checkAdmin();
            include("admin/test.php");
            break;

        case "admin_ranking":
            checkAdmin();
            include("admin/ranking.php");
            break;
        case "admin_genres":
            include "admin/genres.php";
            break;


        /* ====== ĐĂNG XUẤT ====== */
        case "logout":
            session_unset();
            session_destroy();
            header("Location: index.php?p=home");
            exit;
            break;

        /* ====== MẶC ĐỊNH ====== */
        default:
            include("app/views/layouts/home.php");
            break;
    }
}

/* ===== Kiểm tra quyền admin ===== */
function checkAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: index.php?p=login");
        exit;
    }
}
