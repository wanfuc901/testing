<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../../vendor/phpmailer/PHPMailer.php';
require __DIR__ . '/../../../vendor/phpmailer/SMTP.php';
require __DIR__ . '/../../../vendor/phpmailer/Exception.php';
require __DIR__ . '/../../../helpers/realtime.php';   // ← CHỈ CẦN THÊM NÀY

$conn->set_charset("utf8mb4");

// === LẤY DANH SÁCH ID CẦN XỬ LÝ ===
$ids = [];
if (!empty($_POST['tickets'])) {
    foreach ($_POST['tickets'] as $tid) {
        if (is_numeric($tid)) $ids[] = (int)$tid;
    }
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ids[] = (int)$_GET['id'];
}

if (!$ids) {
    header("Location: ../../../index.php?p=admin_tickets&msg=invalid");
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$valid = ['mark_paid','confirm','cancel'];
if (!in_array($action, $valid)) {
    header("Location: ../../../index.php?p=admin_tickets&msg=invalid_action");
    exit;
}

$in = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));


// ===============================
// 1) XỬ LÝ TRONG DATABASE
// ===============================
switch ($action) {
    case 'mark_paid':
        $stmt = $conn->prepare("UPDATE tickets SET paid=1, status='paid' WHERE ticket_id IN ($in)");
        $newStatus = "paid";
        break;

    case 'confirm':
        $stmt = $conn->prepare("UPDATE tickets SET status='confirmed' WHERE ticket_id IN ($in)");
        $newStatus = "confirmed";
        break;

    case 'cancel':
        $stmt = $conn->prepare("UPDATE tickets SET status='cancelled' WHERE ticket_id IN ($in)");
        $newStatus = "cancelled";
        break;
}

$stmt->bind_param($types, ...$ids);
$stmt->execute();
$stmt->close();


// ===============================
// 2) BẮN REALTIME CHO TỪNG TICKET
// ===============================
foreach ($ids as $ticketId) {
    emit_ticket_update($ticketId, $newStatus);
}


// ===============================
// 3) GỬI MAIL (nếu cần) – giữ nguyên code của bạn
// ===============================
if ($action === 'mark_paid' || $action === 'confirm') {

    $sql = "
        SELECT 
            u.user_id, u.email, u.name,
            t.ticket_id, t.price, t.status,
            s.start_time, s.end_time,
            r.name AS room_name,
            m.title AS movie_title,
            st.row_number, st.col_number
        FROM tickets t
        JOIN users u     ON t.user_id=u.user_id
        JOIN showtimes s ON t.showtime_id=s.showtime_id
        JOIN rooms r     ON s.room_id=r.room_id
        JOIN movies m    ON s.movie_id=m.movie_id
        JOIN seats st    ON t.seat_id=st.seat_id
        WHERE t.ticket_id IN ($in)
          AND t.status IN ('paid','confirmed')
        ORDER BY u.user_id, s.start_time, t.ticket_id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $byUser = [];
    while ($r = $res->fetch_assoc()) {
        $uid = (int)$r['user_id'];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'email' => $r['email'],
                'name'  => $r['name'],
                'tickets' => []
            ];
        }
        $byUser[$uid]['tickets'][] = $r;
    }
    $stmt->close();


    // === GỬI MAIL GỘP ===
    $baseURL = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/VincentCinemas";
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'phuc.pham.vst@gmail.com';
        $mail->Password = 'fvde ashj zbgq ohtr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->SMTPKeepAlive = true;
        $mail->setFrom('phuc.pham.vst@gmail.com', 'VinCine Support');

        foreach ($byUser as $uid => $info) {
                        $mail->clearAllRecipients();
            $mail->addAddress($info['email'], $info['name']);
            $mail->isHTML(true);
            $mail->Subject = "🎬 Xác nhận vé VinCine (" . count($info['tickets']) . " vé)";

            $rowsHtml = "";
            $total = 0;
            foreach ($info['tickets'] as $t) {
                $seat = "H{$t['row_number']}C{$t['col_number']}";
                $ticketLink = "{$baseURL}/public/ticket_detail.php?ticket_id={$t['ticket_id']}";
                $total += (float)$t['price'];
                $rowsHtml .= "
                <tr>
                  <td style='padding:10px;border-bottom:1px solid #eee;'>#{$t['ticket_id']}</td>
                  <td style='padding:10px;border-bottom:1px solid #eee;'>{$seat}</td>
                  <td style='padding:10px;text-align:right;border-bottom:1px solid #eee;color:#2ecc71;font-weight:bold;'>".number_format($t['price'],0,',','.')." ₫</td>
                  <td style='padding:10px;text-align:center;border-bottom:1px solid #eee;'>
                    <a href='{$ticketLink}' style='color:#e50914;font-weight:600;text-decoration:none;'>Xem vé</a>
                  </td>
                </tr>";
            }

            $mail->Body = "
<!DOCTYPE html>
<html lang='vi'>
<head><meta charset='UTF-8'><title>Xác nhận vé VinCine</title></head>
<body style='margin:0;padding:0;background:#f7f7f7;font-family:Segoe UI,Arial,sans-serif;color:#333;'>
  <div style='max-width:680px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.08);'>
    <div style='background:#d90429;padding:20px 30px;text-align:center;color:#fff;'>
      <h1 style='margin:0;font-size:22px;'>🎬 XÁC NHẬN VÉ ĐIỆN ẢNH - <span style='background:#fff;color:#111;padding:3px 8px;border-radius:4px;'>VINCINE</span></h1>
    </div>
    <div style='padding:30px;'>
      <p style='font-size:16px;margin:0 0 10px;'>Xin chào <b>{$info['name']}</b>,</p>
      <p>Cảm ơn bạn đã <b style='color:#2ecc71;'>thanh toán thành công</b> cho <b>".count($info['tickets'])." vé phim</b>.</p>
      <table style='width:100%;border-collapse:collapse;margin-top:15px;border:1px solid #eee;'>
        <thead>
          <tr style='background:#f9f9f9;'>
            <th style='padding:10px;text-align:left;'>Mã vé</th>
            <th style='padding:10px;text-align:left;'>Ghế</th>
            <th style='padding:10px;text-align:right;'>Giá</th>
            <th style='padding:10px;text-align:center;'>Chi tiết</th>
          </tr>
        </thead>
        <tbody>{$rowsHtml}</tbody>
      </table>
      <div style='text-align:right;margin-top:10px;font-size:15px;'>
        <b>Tổng cộng:</b> <span style='color:#2ecc71;font-weight:bold;'>".number_format($total,0,',','.')." ₫</span>
      </div>
      <div style='text-align:center;margin-top:30px;'>
        <a href='{$baseURL}/index.php?p=acc' style='background:#e50914;color:#fff;padding:12px 26px;border-radius:999px;text-decoration:none;font-weight:700;'>Quản lý vé</a>
      </div>
    </div>
    <div style='background:#111;color:#ccc;font-size:12px;text-align:center;padding:12px;'>© 2025 VinCine. Email tự động, vui lòng không phản hồi.</div>
  </div>
</body>
</html>";
            $mail->send();
        }

        $mail->smtpClose();
    } catch (Exception $e) {
        error_log("Mail batch fail: ".$mail->ErrorInfo);
    }
}


// ===============================
// 4) CHUYỂN VỀ ADMIN TICKETS
// ===============================
header("Location: ../../../index.php?p=admin_tickets&msg=done");
exit;

?>
