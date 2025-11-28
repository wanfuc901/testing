<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../helpers/realtime.php";
require_once __DIR__ . "/../../helpers/order_helper.php";

$conn->set_charset("utf8mb4");
date_default_timezone_set("Asia/Ho_Chi_Minh");

/* ========= COMMON ERROR CHECK ========= */
function chk($stmt, $name) {
    global $conn;
    if (!$stmt) die("SQL ERROR at [$name]: " . $conn->error);
}

/* ========= VALIDATE ========= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Phương thức không hợp lệ.");
}

$showtime_id = intval($_POST['showtime_id'] ?? 0);
$seats       = trim($_POST['seats'] ?? '');
$method      = $_POST['payment_method'] ?? 'cash';
$user_id     = intval($_SESSION['user_id'] ?? 0);

if (!$showtime_id || !$seats || !$user_id) die("Dữ liệu không hợp lệ.");

/* ========= COMBO ========= */
$temp         = $_SESSION['temp_booking'] ?? [];
$combos       = $temp['combos'] ?? [];
$combo_total  = floatval($temp['combo_total'] ?? 0);

/* ========= SEAT LIST ========= */
$seatArr = array_unique(array_filter(array_map('intval', explode(",", $seats))));

/* ========= GET TICKET PRICE ========= */
$stmtPrice = $conn->prepare("
    SELECT m.`ticket_price`
    FROM `movies` m
    JOIN `showtimes` s ON s.`movie_id` = m.`movie_id`
    WHERE s.`showtime_id` = ?
");
chk($stmtPrice, "stmtPrice");
$stmtPrice->bind_param("i", $showtime_id);
$stmtPrice->execute();
$stmtPrice->bind_result($ticketPrice);
$stmtPrice->fetch();
$stmtPrice->close();

if (!$ticketPrice) $ticketPrice = 80000;

$totalTicket = count($seatArr) * $ticketPrice;
$totalPrice  = $totalTicket + $combo_total;

/* ===================================================================================
   🔥 OFFLINE PAYMENT — TẠO VÉ NGAY
=================================================================================== */
if ($method === 'cash') {

    $txn = generate_order_code();

    $insertPay = $conn->prepare("
        INSERT INTO `payments`
        (`user_id`, `method`, `amount`, `status`, `provider_txn_id`, `paid_at`)
        VALUES (?, 'offline', ?, 'success', ?, NOW())
    ");
    chk($insertPay, "insertPayment");

    $u      = (int)$user_id;
    $totalP = (float)$totalPrice;

    $insertPay->bind_param("ids", $u, $totalP, $txn);
    $insertPay->execute();
    $payment_id = $insertPay->insert_id;
    $insertPay->close();

    /* ===== INSERT TICKET ===== */
    $insertTicket = $conn->prepare("
        INSERT INTO `tickets`
        (`showtime_id`, `seat_id`, `user_id`, `price`, `booked_at`,
         `channel`, `paid`, `status`, `payment_id`)
        VALUES (?, ?, ?, ?, NOW(), 'offline', 1, 'confirmed', ?)
    ");
    chk($insertTicket, "insertTicket");

    $booked = [];

    foreach ($seatArr as $seatId) {
        /* Check trùng ghế */
        $stmtCheck = $conn->prepare("
            SELECT 1 FROM `tickets`
            WHERE `showtime_id`=? AND `seat_id`=?
        ");
        chk($stmtCheck, "checkSeat");
        $stmtCheck->bind_param("ii", $showtime_id, $seatId);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            continue;
        }
        $stmtCheck->close();

        $st_id = (int)$showtime_id;
        $sid   = (int)$seatId;
        $uid   = (int)$user_id;
        $price = (float)$ticketPrice;
        $pid   = (int)$payment_id;

        $insertTicket->bind_param("iiidi", $st_id, $sid, $uid, $price, $pid);
        $insertTicket->execute();

        $booked[] = $sid;
    }

    $insertTicket->close();

    /* ===== COMBO ===== */
    if (!empty($combos)) {
        $stmtCombo = $conn->prepare("
            INSERT INTO `payment_combos`
            (`payment_id`, `combo_id`, `qty`, `price`, `total`)
            VALUES (?, ?, ?, ?, ?)
        ");
        chk($stmtCombo, "insertCombo");

        foreach ($combos as $c) {
            $p_id = (int)$payment_id;
            $c_id = (int)$c['id'];
            $qty  = (int)$c['qty'];
            $pri  = (float)$c['price'];
            $tot  = (float)$c['total'];

            $stmtCombo->bind_param("iiidd", $p_id, $c_id, $qty, $pri, $tot);
            $stmtCombo->execute();
        }
        $stmtCombo->close();
    }

    unset($_SESSION['temp_booking']);

    /* REALTIME PUSH */
    if (!empty($booked)) {
        emit_seat_booked_done($showtime_id, $booked);
    }

    header("Location: ../../public/booking_success.php");
    exit;
}

/* ===================================================================================
   🔥 ONLINE PAYMENT — CHỈ TẠO HÓA ĐƠN TẠM (PENDING), CHƯA TẠO VÉ
=================================================================================== */

$orderCode = generate_order_code();

$orderData = json_encode([
    "showtime_id"  => $showtime_id,
    "seats"        => $seatArr,
    "ticket_price" => $ticketPrice,
    "ticket_total" => $totalTicket,
    "combos"       => $combos,
    "combo_total"  => $combo_total,
    "total_amount" => $totalPrice
], JSON_UNESCAPED_UNICODE);

$stmtPay = $conn->prepare("
    INSERT INTO `payments`
    (`user_id`, `method`, `amount`, `order_data`,
     `status`, `provider_txn_id`, `created_at`)
    VALUES (?, 'online', ?, ?, 'pending', ?, NOW())
");
chk($stmtPay, "insertPayPending");

$u   = (int)$user_id;
$tot = (float)$totalPrice;


$stmtPay->bind_param("idss", $u, $tot, $orderData, $orderCode);
$stmtPay->execute();
$payment_id = $stmtPay->insert_id;
$stmtPay->close();

/* Không tạo vé, không lưu pending_order session nữa */
unset($_SESSION['temp_booking']);

/* Chuyển sang trang QR */
header("Location: ../../app/views/payment/payment_qr.php?payment_id=" . $payment_id);
exit;
