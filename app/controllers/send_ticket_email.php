<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../../vendor/phpmailer/PHPMailer.php";
require_once __DIR__ . "/../../vendor/phpmailer/SMTP.php";
require_once __DIR__ . "/../../vendor/phpmailer/Exception.php";

$debug_log = __DIR__ . "/../../logs/email_debug.log";
file_put_contents($debug_log, "[".date("Y-m-d H:i:s")."] send_ticket_email.php loaded\n", FILE_APPEND);


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ============================
   LẤY PAYMENT
============================ */
$pid = intval($payment_id);

$stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
$stmt->bind_param("i", $pid);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) return;

/* ============================
   LẤY ORDER DATA
============================ */
$data = json_decode($pay['order_data'], true);

$showtime_id = intval($data['showtime_id']);
$seats       = $data['seats'] ?? [];
$combos      = $data['combos'] ?? [];

/* ============================
   LẤY SUẤT CHIẾU (DÙNG ĐÚNG CỘT)
============================ */
$stmt = $conn->prepare("
    SELECT start_time, movie_id, room_id
    FROM showtimes 
    WHERE showtime_id=?
");
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$show = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$show) return;

$start_time = $show['start_time'];
$show_date  = date("d/m/Y", strtotime($start_time));
$show_hour  = date("H:i", strtotime($start_time));

/* ============================
   LẤY PHIM
============================ */
$stmt = $conn->prepare("SELECT title FROM movies WHERE movie_id=?");
$stmt->bind_param("i", $show['movie_id']);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();
$stmt->close();

$movie_title = $movie['title'] ?? "";

/* ============================
   LẤY PHÒNG
============================ */
$stmt = $conn->prepare("SELECT name FROM rooms WHERE room_id=?");
$stmt->bind_param("i", $show['room_id']);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

$room_name = $room['name'] ?? "";

/* ============================
   GHẾ
============================ */
$seatLabels = [];
foreach ($seats as $sid) {
    $st2 = $conn->prepare("SELECT row_number, col_number FROM seats WHERE seat_id=?");
    $st2->bind_param("i", $sid);
    $st2->execute();
    $r = $st2->get_result()->fetch_assoc();
    $st2->close();

    if ($r) {
        $seatLabels[] = chr(64 + $r['row_number']) . $r['col_number'];
    }
}
$seatText = implode(", ", $seatLabels);

/* ============================
   QR BANK
============================ */
$qrUrl = "https://img.vietqr.io/image/970422-0944649923-compact.png?amount={$pay['amount']}"
       . "&addInfo=" . urlencode("Thanh toan ma don {$pay['provider_txn_id']}");

/* ============================
   SEND MAIL
============================ */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "phuc.pham.vst@gmail.com";
    $mail->Password   = "fvde ashj zbgq ohtr"; 
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;

    $mail->CharSet = "UTF-8";

    $mail->setFrom("phuc.pham.vst@gmail.com", "VinCine Ticket");
    $mail->addAddress($pay['email'], $pay['name'] ?? "Khách hàng");
    $mail->isHTML(true);

    $mail->Subject = "🎫 Vé xem phim - $movie_title | VinCine";

$mail->Body = "
<!DOCTYPE html>
<html>
<body style='background:#f7f7f7;font-family:Poppins,Arial,sans-serif;'>

<div style='max-width:620px;margin:25px auto;background:#fff;border-radius:14px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);overflow:hidden;'>


  <div style='background:#e50914;padding:18px;text-align:center;'>
    <h1 style='color:#fff;margin:0;font-size:22px;'>Vé Xem Phim - VinCine</h1>
  </div>

  <div style='padding:24px;'>

    <h2 style='margin:0;color:#e50914;'>Xin chào {$pay['name']}</h2>
    <p>Đây là thông tin vé của bạn:</p>

    <div style='background:#fafafa;padding:15px;border-radius:10px;border:1px solid #ddd;'>
      <p><b>Phim:</b> $movie_title</p>
      <p><b>Phòng:</b> $room_name</p>
      <p><b>Ngày chiếu:</b> $show_date</p>
      <p><b>Giờ chiếu:</b> $show_hour</p>
      <p><b>Ghế:</b> $seatText</p>
    </div>

    <h3 style='margin-top:20px;color:#d4af37;'>Combo đã mua:</h3>
";

foreach ($combos as $c) {
$mail->Body .= "<p>- {$c['name']} ({$c['qty']} × " . number_format($c['price']) . "đ)</p>";
}

$mail->Body .= "
    <h3 style='margin-top:20px;color:#d4af37;'>QR thanh toán</h3>
    <div style='text-align:center;margin:15px 0;'>
      <img src='$qrUrl' style='width:200px;border-radius:10px;'/>
    </div>

    <p><b>Tổng tiền:</b> " . number_format($pay['amount']) . "đ</p>

  </div>

  <div style='background:#111;color:#ccc;text-align:center;padding:12px;'>
    © 2025 VinCine — Email tự động, vui lòng không trả lời.
  </div>

</div>

</body>
</html>
";

    $mail->send();

} catch (Exception $e) {
    file_put_contents(
        $debug_log, 
        "[".date("Y-m-d H:i:s")."] Mail error: ".$mail->ErrorInfo."\n", 
        FILE_APPEND
    );
}

?>
