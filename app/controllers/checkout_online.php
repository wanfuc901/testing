<?php
/**
 * Tạo đơn đặt vé cho khách hàng (thanh toán tại quầy hoặc chuyển khoản).
 */

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/realtime.php';
require_once __DIR__ . '/../../helpers/order_helper.php';
require_once __DIR__ . '/../../helpers/payos.php';

/* ========= VALIDATE ========= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

vincine_verify_csrf(false);
vincine_require_customer();
$customer_id = vincine_customer_id();

$showtime_id = (int)($_POST['showtime_id'] ?? 0);
$seats       = trim((string)($_POST['seats'] ?? ''));
$method      = (string)($_POST['payment_method'] ?? 'cash');

if ($showtime_id <= 0 || $seats === '') {
    http_response_code(400);
    exit('Dữ liệu không hợp lệ.');
}

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
if (!$stmtPrice) {
    error_log('[vincine] checkout_online price prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Hệ thống đang bận. Vui lòng thử lại sau.');
}

$stmtPrice->bind_param("i", $showtime_id);
$stmtPrice->execute();
$stmtPrice->bind_result($ticketPrice);
$stmtPrice->fetch();
$stmtPrice->close();

$ticketPrice = (float)($ticketPrice ?: VINCINE_DEFAULT_TICKET_PRICE);

if (empty($seatArr)) {
    http_response_code(400);
    exit('Bạn chưa chọn ghế nào.');
}

/* Chặn đặt trùng trước khi ghi: ghế có thể vừa bị người khác giữ. */
if (!is_seat_available($showtime_id, $seatArr)) {
    http_response_code(409);
    exit('Một số ghế bạn chọn vừa có người đặt. Vui lòng chọn lại.');
}

$totalTicket = count($seatArr) * $ticketPrice;
$totalPrice  = $totalTicket + $combo_total;

/* ===================================================================================
   OFFLINE PAYMENT — TẠO VÉ NGAY
=================================================================================== */
if ($method === 'cash') {

    $txn = generate_order_code();

    $insertPay = $conn->prepare("
        INSERT INTO payments
        (customer_id, method, amount, status, provider_txn_id, paid_at)
        VALUES (?, 'offline', ?, 'pending', ?, NOW())
    ");
    $insertPay->bind_param("ids", $customer_id, $totalPrice, $txn);
    $insertPay->execute();
    $payment_id = $insertPay->insert_id;
    $insertPay->close();

    /* INSERT TICKET */
    $sqlT = "
        INSERT INTO tickets
        (showtime_id, seat_id, customer_id, payment_id, channel,
         paid, status, price, booked_at)
        VALUES (?, ?, ?, ?, 'offline', 1, 'confirmed', ?, NOW())
    ";

    $stmtT = $conn->prepare($sqlT);

    $booked = [];
    foreach ($seatArr as $sid) {
        $stmtT->bind_param(
            "iiiid",
            $showtime_id,
            $sid,
            $customer_id,
            $payment_id,
            $ticketPrice
        );
        $stmtT->execute();
        $booked[] = $sid;
    }
    $stmtT->close();

    /* ===== Tạo seat labels để hiện lên trang success ===== */
    $seatLabels = [];
    $q = $conn->prepare("SELECT row_number, col_number FROM seats WHERE seat_id=?");
    foreach ($seatArr as $sid) {
        $q->bind_param("i", $sid);
        $q->execute();
        $rs = $q->get_result()->fetch_assoc();
        if ($rs) {
            $rowChar = chr(64 + intval($rs['row_number']));
            $seatLabels[] = $rowChar . $rs['col_number'];
        }
    }
    $q->close();

    /* ===== LƯU SESSION CHO booking_success ===== */
    $_SESSION['last_booking'] = [
        'showtime_id' => $showtime_id,
        'seat_labels' => $seatLabels,
        'total'       => $totalPrice,
        'method'      => 'cash'
    ];

    /* REALTIME */
    emit_seat_booked_done($showtime_id, $booked);
    /* send_ticket_email.php đọc $payment_id từ scope này. */
    require __DIR__ . '/send_ticket_email.php';
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
    (customer_id, method, amount, order_data, status, provider_txn_id, created_at)
    VALUES (?, 'online', ?, ?, 'pending', ?, NOW())
");
$stmtPay->bind_param("idss", $customer_id, $totalPrice, $orderData, $orderCode);
$stmtPay->execute();
$payment_id = $stmtPay->insert_id;
$stmtPay->close();

/* INSERT VÉ PENDING (HOLD) */
$sqlHold = "
    INSERT INTO tickets
    (showtime_id, seat_id, customer_id, payment_id, channel,
     paid, status, price, booked_at)
    VALUES (?, ?, ?, ?, 'online', 0, 'pending', ?, NOW())
";

$stmtHold = $conn->prepare($sqlHold);

foreach ($seatArr as $sid) {
    $stmtHold->bind_param(
        "iiiid",
        $showtime_id,
        $sid,
        $customer_id,
        $payment_id,
        $ticketPrice
    );
    $stmtHold->execute();
}
$stmtHold->close();

/* REALTIME: GHẾ ĐANG GIỮ */
emit_seat_locked($showtime_id, $seatArr);

unset($_SESSION['temp_booking']);

/* ===================================================================================
   TẠO LINK THANH TOÁN PAYOS
   Thất bại ở bước này thì phải nhả ghế ngay, không để đơn treo giữ chỗ.
=================================================================================== */
$payosOrderCode = vincine_payos_build_order_code($payment_id);

try {
    $baseUrl = rtrim((string)vincine_env('APP_BASE_URL', ''), '/');

    $link = vincine_payos_create_link(
        $payosOrderCode,
        (int)round($totalPrice),
        'VC' . substr((string)$payosOrderCode, -7),
        $baseUrl . '/index.php?p=payos_return&pid=' . $payment_id,
        $baseUrl . '/index.php?p=payos_cancel&pid=' . $payment_id,
        time() + PAYOS_LINK_TTL,
        [
            'buyerName'  => (string)($_SESSION['fullname'] ?? ''),
            'buyerEmail' => (string)($_SESSION['email'] ?? ''),
        ]
    );

    $stmtLink = $conn->prepare(
        "UPDATE payments
         SET payos_order_code = ?, payos_payment_link_id = ?, payos_qr_code = ?
         WHERE payment_id = ?"
    );
    $linkId = (string)($link['paymentLinkId'] ?? '');
    $qrCode = (string)($link['qrCode'] ?? '');
    $stmtLink->bind_param('issi', $payosOrderCode, $linkId, $qrCode, $payment_id);
    $stmtLink->execute();
    $stmtLink->close();

} catch (Throwable $e) {
    error_log('[vincine] Không tạo được link PayOS cho đơn ' . $payment_id . ': ' . $e->getMessage());

    /* Nhả ghế đang giữ để người khác đặt được. */
    $rollback = $conn->prepare(
        "UPDATE tickets SET status = 'cancelled' WHERE payment_id = ? AND status = 'pending'"
    );
    if ($rollback) {
        $rollback->bind_param('i', $payment_id);
        $rollback->execute();
        $rollback->close();
    }

    $conn->query("UPDATE payments SET status = 'canceled', canceled_at = NOW() WHERE payment_id = " . (int)$payment_id);

    http_response_code(502);
    exit('Cổng thanh toán đang bận, chưa tạo được mã QR. Vui lòng thử lại sau ít phút.');
}

header("Location: ../../app/views/payment/payment_qr.php?payment_id=" . $payment_id);
exit;
?>
