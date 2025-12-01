<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../vendor/phpmailer/PHPMailer.php";
require_once __DIR__ . "/../../vendor/phpmailer/SMTP.php";
require_once __DIR__ . "/../../vendor/phpmailer/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;

$pid = intval($payment_id);

/* ============================
   LẤY PAYMENT + THÔNG TIN KHÁCH
============================ */
$sql = "
    SELECT p.*, c.email, c.fullname
    FROM payments p
    LEFT JOIN customers c ON c.customer_id = p.customer_id
    WHERE p.payment_id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL ERROR: " . $conn->error . "<br>SQL:<br>" . $sql);
}

$stmt->bind_param("i", $pid);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) return;

/* ============================
   ORDER DATA
============================ */
$data = json_decode($pay['order_data'], true);
$seatArr = $data['seats'];

/* ============================
   GHÉP GHẾ
============================ */
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
/* ============================
   SEND MAIL
============================ */
$mail = new PHPMailer(true);

$mail->CharSet  = 'UTF-8';
$mail->Encoding = 'base64';

$mail->isSMTP();
$mail->Host       = "smtp.gmail.com";
$mail->SMTPAuth   = true;
$mail->SMTPSecure = "tls";
$mail->Port       = 587;

$mail->Username   = "phuc.pham.vst@gmail.com";
$mail->Password   = "fvde ashj zbgq ohtr"; // nhớ sau này dùng biến môi trường

// Nên để From trùng tài khoản Gmail để tránh bị đánh spam
$mail->setFrom("phuc.pham.vst@gmail.com", "Vincent Cinemas");
$mail->addAddress($pay['email'], $pay['fullname']);

$mail->Subject = "Vé xem phim #{$pid}";
$mail->isHTML(true);


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

    <!-- BRAND TEXT HEADER -->
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

    <!-- TITLE -->
    <h2 style="
        margin:10px 0 4px;
        color:#f5c518;
        font-weight:700;
        text-align:center;
        letter-spacing:1px;
    ">
        THÔNG TIN VÉ XEM PHIM
    </h2>

    <p style="text-align:center; font-size:13px; color:#aaaaaa; margin:0 0 14px;">
        Cảm ơn bạn đã đặt vé tại <strong style="color:#f5c518;">Vincent Cinemas</strong>.
    </p>

    <div style="height:1px;background:rgba(255,255,255,.08);margin:18px 0 16px;"></div>

    <!-- ORDER INFO -->
    <div style="font-size:14px; line-height:1.7; padding:0 4px;">

        <p style="margin:0 0 10px;">
            <span style="color:#999999;">Mã đơn:</span><br>
            <strong style="font-size:16px;">'.$pay['provider_txn_id'].'</strong>
        </p>

        <p style="margin:0 0 10px;">
            <span style="color:#999999;">Khách hàng:</span><br>
            <strong>'.htmlspecialchars($pay['fullname']).'</strong>
        </p>

        <p style="margin:0 0 10px;">
            <span style="color:#999999;">Ghế đã đặt:</span><br>
            <strong>'.implode(", ", $labels).'</strong>
        </p>

        <p style="margin:0 0 10px;">
            <span style="color:#999999;">Tổng tiền:</span><br>
            <strong style="color:#e50914; font-size:17px;">
                '.number_format($pay['amount'], 0, ",", ".").' đ
            </strong>
        </p>

    </div>

    <!-- QR CODE -->
    <div style="
        margin:24px auto 8px;
        padding:14px;
        background:#1c1c1c;
        border-radius:10px;
        text-align:center;
        width:fit-content;
        border:1px solid #2a2a2a;
    ">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($pay['provider_txn_id']).'" 
             alt="QR vé xem phim"
             style="display:block;border-radius:6px;">
        <p style="font-size:12px; color:#999999; margin:8px 0 0;">
            Đưa mã này cho nhân viên để lấy vé vật lý
        </p>
    </div>

    <div style="height:1px;background:rgba(255,255,255,.08);margin:18px 0;"></div>

    <!-- FOOTER -->
    <p style="font-size:12px; color:#777777; text-align:center; line-height:1.5; margin:0;">
        Email này được gửi tự động, vui lòng không trả lời.<br>
        Nếu cần hỗ trợ, hãy liên hệ quầy dịch vụ của 
        <strong style="color:#f5c518;">Vincent Cinemas</strong>.
    </p>

</div>

';




$mail->send();
?>
