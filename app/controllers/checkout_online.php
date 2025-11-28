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
    SELECT m.ticket_price
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.movie_id
    WHERE s.showtime_id = ?
");
$stmtPrice->bind_param("i", $showtime_id);
$stmtPrice->execute();
$stmtPrice->bind_result($ticketPrice);
$stmtPrice->fetch();
$stmtPrice->close();

if (!$ticketPrice) $ticketPrice = 80000;

$totalTicket = count($seatArr) * $ticketPrice;
$totalPrice  = $totalTicket + $combo_total;

/* ===================================================================================
   OFFLINE PAYMENT — TẠO VÉ NGAY
=================================================================================== */
if ($method === 'cash') {

    $txn = generate_order_code();

    $insertPay = $conn->prepare("
        INSERT INTO payments
        (user_id, method, amount, status, provider_txn_id, paid_at)
        VALUES (?, 'offline', ?, 'success', ?, NOW())
    ");

    $insertPay->bind_param("ids", $user_id, $totalPrice, $txn);
    $insertPay->execute();
    $payment_id = $insertPay->insert_id;
    $insertPay->close();

    /* INSERT TICKET */
    $sqlT = "
        INSERT INTO tickets
        (showtime_id, seat_id, user_id, payment_id, channel,
         paid, status, price, booked_at)
        VALUES (?, ?, ?, ?, 'offline', 1, 'confirmed', ?, NOW())
    ";

    $stmtT = $conn->prepare($sqlT);

    foreach ($seatArr as $sid) {
        $stmtT->bind_param(
            "iiiid",
            $showtime_id,
            $sid,
            $user_id,
            $payment_id,
            $ticketPrice
        );
        $stmtT->execute();
        $booked[] = $sid;
    }
    $stmtT->close();

    /* REALTIME */
    emit_seat_booked_done($showtime_id, $booked);

    unset($_SESSION['temp_booking']);
    header("Location: ../../public/booking_success.php");
    exit;
}

/* ===================================================================================
   ONLINE PAYMENT — TẠO PAYMENT PENDING + VÉ HOLD
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

/* INSERT PAYMENT */
$stmtPay = $conn->prepare("
    INSERT INTO payments
    (user_id, method, amount, order_data, status, provider_txn_id, created_at)
    VALUES (?, 'online', ?, ?, 'pending', ?, NOW())
");
$stmtPay->bind_param("idss", $user_id, $totalPrice, $orderData, $orderCode);
$stmtPay->execute();
$payment_id = $stmtPay->insert_id;
$stmtPay->close();

/* INSERT VÉ PENDING (HOLD) */
$sqlHold = "
    INSERT INTO tickets
    (showtime_id, seat_id, user_id, payment_id, channel,
     paid, status, price, booked_at)
    VALUES (?, ?, ?, ?, 'online', 0, 'pending', ?, NOW())
";

$stmtHold = $conn->prepare($sqlHold);

foreach ($seatArr as $sid) {
    $stmtHold->bind_param(
        "iiiid",
        $showtime_id,
        $sid,
        $user_id,
        $payment_id,
        $ticketPrice
    );
    $stmtHold->execute();
}
$stmtHold->close();

/* REALTIME: GHẾ ĐANG GIỮ */
emit_seat_locked($showtime_id, $seatArr);

unset($_SESSION['temp_booking']);

header("Location: ../../app/views/payment/payment_qr.php?payment_id=" . $payment_id);
exit;
