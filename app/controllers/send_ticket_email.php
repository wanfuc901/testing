<?php
/**
 * Gửi mail vé cho khách. Được require từ luồng đặt vé, nhận $payment_id
 * từ scope gọi.
 */

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/mailer.php';

/* ==========================================================
   NHẬN PAYMENT ID
========================================================== */
$pid = intval($payment_id ?? 0);
if ($pid <= 0) return;

/* ==========================================================
   LẤY PAYMENT + KHÁCH HÀNG
========================================================== */
$sql = "
    SELECT p.*, c.email, c.fullname
    FROM payments p
    LEFT JOIN customers c ON c.customer_id = p.customer_id
    WHERE p.payment_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pid);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay || empty($pay['email'])) return;

/* ==========================================================
   GHẾ TỪ ORDER_DATA
========================================================== */
$data = json_decode($pay['order_data'], true);
$seatArr = $data['seats'] ?? [];

$labels = [];
$q = $conn->prepare("SELECT row_number, col_number FROM seats WHERE seat_id=?");

foreach ($seatArr as $sid) {
    $q->bind_param("i", $sid);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    if ($r) {
        $labels[] = chr(64 + $r['row_number']) . $r['col_number'];
    }
}
$q->close();

/* ==========================================================
   PHÂN BIỆT HÌNH THỨC THANH TOÁN
========================================================== */
$isOnline = ($pay['method'] === 'online');

$paymentText = $isOnline
    ? "ĐÃ THANH TOÁN ONLINE"
    : "THANH TOÁN TẠI QUẦY";

$noteText = $isOnline
    ? "Vé đã được thanh toán. Vui lòng xuất trình mã QR khi vào rạp."
    : "Vui lòng thanh toán tại quầy trước giờ chiếu để nhận vé.";

/* ==========================================================
   SEND MAIL
========================================================== */
try {
    $mail = vincine_mailer();
} catch (RuntimeException $e) {
    error_log('[vincine] ' . $e->getMessage());
    return;
}

$mail->addAddress($pay['email'], $pay['fullname']);

$mail->Subject = "Vé xem phim #{$pid} – Vincent Cinemas";
$mail->isHTML(true);

/* ==========================================================
   EMAIL BODY (GIỮ STYLE – CHỈ SỬA NỘI DUNG)
========================================================== */
$mail->Body = '

<div style="
    font-family: Arial, sans-serif;
    background:#111111;
    padding:24px;
    color:#eeeeee;
    max-width:520px;
    margin:0 auto;
    border-radius:14px;
    border:1px solid #222222;
">

    <div style="text-align:center; margin-bottom:16px;">
        <div style="
            display:inline-block;
            padding:6px 14px;
            border-radius:999px;
            border:1px solid #e50914;
            font-size:11px;
            letter-spacing:2px;
            text-transform:uppercase;
            color:#f5c518;
        ">
            Vincent Cinemas
        </div>
    </div>

    <h2 style="
        margin:10px 0 6px;
        color:#f5c518;
        font-weight:700;
        text-align:center;
        letter-spacing:1px;
    ">
        THÔNG TIN VÉ XEM PHIM
    </h2>

    <p style="
        text-align:center;
        font-size:13px;
        margin:0 0 14px;
        color:' . ($isOnline ? '#5cff87' : '#ffb347') . ';
        font-weight:700;
    ">
        '.$paymentText.'
    </p>

    <div style="height:1px;background:rgba(255,255,255,.08);margin:18px 0 16px;"></div>

    <div style="font-size:14px; line-height:1.7; padding:0 4px;">

        <p><span style="color:#999;">Mã đơn:</span><br>
        <strong>'.$pay['provider_txn_id'].'</strong></p>

        <p><span style="color:#999;">Khách hàng:</span><br>
        <strong>'.htmlspecialchars($pay['fullname']).'</strong></p>

        <p><span style="color:#999;">Ghế:</span><br>
        <strong>'.implode(", ", $labels).'</strong></p>

        <p><span style="color:#999;">Tổng tiền:</span><br>
        <strong style="color:#e50914;font-size:17px;">
            '.number_format($pay['amount'],0,",",".").' đ
        </strong></p>

    </div>

    <div style="
        margin:22px auto 10px;
        padding:14px;
        background:#1c1c1c;
        border-radius:10px;
        text-align:center;
        width:fit-content;
        border:1px solid #2a2a2a;
    ">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($pay['provider_txn_id']).'"
             style="border-radius:6px;">
        <p style="font-size:12px;color:#999;margin:8px 0 0;">
            '.$noteText.'
        </p>
    </div>

    <div style="height:1px;background:rgba(255,255,255,.08);margin:18px 0;"></div>

    <p style="font-size:12px;color:#777;text-align:center;margin:0;">
        Email này được gửi tự động – vui lòng không trả lời.<br>
        <strong style="color:#f5c518;">Vincent Cinemas</strong>
    </p>

</div>
';

try {
    $mail->send();
} catch (Throwable $e) {
    error_log('[vincine] send_ticket_email failed for payment ' . $pid . ': ' . $e->getMessage());
}
