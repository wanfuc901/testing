<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../helpers/realtime.php";

$conn->set_charset("utf8mb4");
date_default_timezone_set("Asia/Ho_Chi_Minh");

/* ========= COMMON ERROR CHECK ========= */
function chk($stmt, $name) {
    global $conn;
    if (!$stmt) die("SQL ERROR at [$name]: " . $conn->error);
}

/* ========= FORMAT ORDER CODE ========= */
function generateOrderCode() {
    return "#HD" . date("HisdmY") . sprintf("%03d", random_int(100,999));
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
   🔥🔥🔥 OFFLINE PAYMENT — SUCCESS IMMEDIATELY
=================================================================================== */
if ($method === 'cash') {

    /* ===== Create provider_txn_id unified format ===== */
    $txn = generateOrderCode();

    $insertPay = $conn->prepare("
        INSERT INTO `payments`
        (`user_id`, `method`, `amount`, `status`, `provider_txn_id`, `paid_at`)
        VALUES (?, 'offline', ?, 'success', ?, NOW())
    ");
    chk($insertPay, "insertPayment");

    $u = (int)$user_id;
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

    $seatLabels = [];
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

        /* FIX: intermediate vars for PHP 7.2 */
        $st_id = (int)$showtime_id;
        $sid   = (int)$seatId;
        $uid   = (int)$user_id;
        $price = (float)$ticketPrice;
        $pid   = (int)$payment_id;

        $insertTicket->bind_param("iiidi", $st_id, $sid, $uid, $price, $pid);
        $insertTicket->execute();

        /* Lấy label ghế */
        $stmtSeat = $conn->prepare("
            SELECT `row_number`, `col_number`
            FROM `seats`
            WHERE `seat_id`=?
        ");
        chk($stmtSeat, "seatInfo");
        $stmtSeat->bind_param("i", $sid);
        $stmtSeat->execute();
        $s = $stmtSeat->get_result()->fetch_assoc();
        $stmtSeat->close();

        if ($s) {
            $seatLabels[] = chr(64 + intval($s['row_number'])) . intval($s['col_number']);
        }

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
   🔥🔥🔥 ONLINE PAYMENT — CREATE PENDING PAYMENT
=================================================================================== */

$orderCode = generateOrderCode();

$stmtPay = $conn->prepare("
    INSERT INTO `payments`
    (`user_id`, `method`, `amount`, `status`, `provider_txn_id`, `created_at`)
    VALUES (?, 'online', ?, 'pending', ?, NOW())
");
chk($stmtPay, "insertPayPending");

$u = (int)$user_id;
$tot = (float)$totalPrice;

$stmtPay->bind_param("ids", $u, $tot, $orderCode);
$stmtPay->execute();
$stmtPay->close();

/* ===== SEAT LABELS ===== */
$seatLabels = [];
foreach ($seatArr as $seatId) {
    $sid = (int)$seatId;
    $stmtSeat = $conn->prepare("
        SELECT `row_number`, `col_number`
        FROM `seats`
        WHERE `seat_id`=?
    ");
    chk($stmtSeat, "seatInfo");

    $stmtSeat->bind_param("i", $sid);
    $stmtSeat->execute();
    $row = $stmtSeat->get_result()->fetch_assoc();
    $stmtSeat->close();

    if ($row) {
        $seatLabels[] = chr(64 + intval($row['row_number'])) . intval($row['col_number']);
    }
}

/* ===== SAVE SESSION ===== */
$_SESSION['pending_order'] = [
    'showtime_id'  => $showtime_id,
    'seat_ids'     => $seatArr,
    'seat_labels'  => $seatLabels,
    'ticket_total' => $totalTicket,
    'combos'       => $combos,
    'combo_total'  => $combo_total,
    'total'        => $totalPrice,
    'method'       => $method,
    'order_code'   => $orderCode
];

header("Location: ../../public/payment_online.php");
exit;
?>
