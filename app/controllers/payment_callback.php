    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    require __DIR__ . "/../config/config.php";
    require_once __DIR__ . "/../../helpers/realtime.php";
    require_once __DIR__ . "/../../helpers/order_helper.php";

    $conn->set_charset("utf8mb4");
    date_default_timezone_set("Asia/Ho_Chi_Minh");

    ini_set('display_errors',1);
    error_reporting(E_ALL);

    /* ============================
    LẤY PAYMENT ID
    ============================ */
    $payment_id = intval($_POST['payment_id'] ?? 0);

    if ($payment_id <= 0) {
        die("Thiếu hoặc sai payment_id.");
    }

    /* ============================
    LẤY BILL
    ============================ */
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) die("Không tìm thấy hóa đơn.");
    if ($pay['status'] !== 'pending') die("Hóa đơn không còn ở trạng thái pending.");

    /* ============================
    TẠO VÉ BẰNG finalize_payment()
    ============================ */
    try {
        finalize_payment($payment_id, "user_callback");

        /* DEBUG — kiểm tra file email có được include đúng không */
    $debug_log = __DIR__ . "/../../logs/email_debug.log";

    file_put_contents($debug_log, 
        "[" . date("Y-m-d H:i:s") . "] START email for payment_id=$payment_id\n",
        FILE_APPEND
    );

    include __DIR__ . "/send_ticket_email.php";

    file_put_contents($debug_log, 
        "[" . date("Y-m-d H:i:s") . "] END email for payment_id=$payment_id\n\n",
        FILE_APPEND
    );


        include __DIR__ . "/send_ticket_email.php";

    } catch (Exception $e) {
        die("Finalize payment failed: " . $e->getMessage());
    }

    header("Location: ../../public/booking_pending.php?pid=".$payment_id);
    exit;


    ?>
