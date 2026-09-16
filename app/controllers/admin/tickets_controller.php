<?php
require_once __DIR__ . '/../../include/require_admin.php';
require_once __DIR__ . '/../../../helpers/mailer.php';
require_once __DIR__ . '/../../../helpers/realtime.php';

/* ============================================
   LẤY DANH SÁCH ID TICKET
============================================ */
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

$in    = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

/* ============================================
   1) CẬP NHẬT DATABASE
============================================ */
/* ============================================
   1) CẬP NHẬT DATABASE
============================================ */

/* Tạo placeholder an toàn */
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

/* Tạo câu SQL theo action */
switch ($action) {

    case 'mark_paid':
        $sql = "UPDATE tickets 
                SET paid = 1, status = 'paid' 
                WHERE ticket_id IN ($placeholders)";
        $newStatus = "paid";
        break;

    case 'confirm':
        $sql = "UPDATE tickets 
                SET status = 'confirmed' 
                WHERE ticket_id IN ($placeholders)";
        $newStatus = "confirmed";
        break;

    case 'cancel':
        $sql = "UPDATE tickets 
                SET status = 'cancelled' 
                WHERE ticket_id IN ($placeholders)";
        $newStatus = "cancelled";
        break;
}

/* Prepare statement */
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL PREPARE ERROR: " . $conn->error . "<br>Query: $sql");
}

/* Bind dữ liệu */
$stmt->bind_param($types, ...$ids);

/* Thực thi */
$stmt->execute();
$stmt->close();

/* ============================================
   2) BẮN REALTIME CHO TỪNG TICKET
============================================ */
foreach ($ids as $ticketId) {
    emit_ticket_update($ticketId, $newStatus);
}


/* ============================================
   3) GỬI MAIL XÁC NHẬN (nếu mark_paid / confirm)
============================================ */
if ($action === 'mark_paid' || $action === 'confirm') {

    $sql = "
    SELECT 
        u.customer_id,
        u.email,
        u.fullname AS name,
        t.ticket_id,
        t.price,
        t.status,
        s.start_time,
        s.end_time,
        r.name AS room_name,
        m.title AS movie_title,
        st.row_number,
        st.col_number
    FROM tickets t
    JOIN customers u ON t.customer_id = u.customer_id
    JOIN showtimes s ON t.showtime_id = s.showtime_id
    JOIN rooms r     ON s.room_id = r.room_id
    JOIN movies m    ON s.movie_id = m.movie_id
    JOIN seats st    ON t.seat_id = st.seat_id
    WHERE t.ticket_id IN ($placeholders)
      AND t.status IN ('paid', 'confirmed')
    ORDER BY u.customer_id, s.start_time, t.ticket_id
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('[vincine] tickets_controller email prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Không gửi được email xác nhận vé.');
}

$stmt->bind_param($types, ...$ids);   // Không còn lỗi
$stmt->execute();
$res = $stmt->get_result();


    $byUser = [];
    while ($row = $res->fetch_assoc()) {
        $uid = (int)$row['customer_id'];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'email' => $row['email'],
                'name'  => $row['name'],
                'tickets' => []
            ];
        }
        $byUser[$uid]['tickets'][] = $row;
    }
    $stmt->close();

    /* === GỬI MAIL GỘP === */
    $baseURL = (isset($_SERVER['HTTPS']) ? "https://" : "http://")
              . $_SERVER['HTTP_HOST'] . "/VincentCinemas";

    try {
        $mail = vincine_mailer();
        $mail->SMTPKeepAlive = true;

        foreach ($byUser as $uid => $info) {

            $mail->clearAllRecipients();
            $mail->addAddress($info['email'], $info['name']);
            $mail->isHTML(true);
            $mail->Subject = "🎬 Xác nhận vé VinCine (" . count($info['tickets']) . " vé)";

            $rowsHtml = "";
            $total    = 0;

            foreach ($info['tickets'] as $t) {
                $seat = "H{$t['row_number']}C{$t['col_number']}";
                $ticketURL = "{$baseURL}/public/ticket_detail.php?ticket_id={$t['ticket_id']}";

                $total += (float)$t['price'];
                $rowsHtml .= "
                    <tr>
                        <td style='padding:10px;'>#{$t['ticket_id']}</td>
                        <td style='padding:10px;'>{$seat}</td>
                        <td style='padding:10px;text-align:right;color:#2ecc71;font-weight:bold;'>"
                        . number_format($t['price'],0,',','.') . " ₫</td>
                        <td style='padding:10px;text-align:center;'>
                            <a href='{$ticketURL}' style='color:#e50914;font-weight:600;'>Xem vé</a>
                        </td>
                    </tr>
                ";
            }

            $mail->Body = "
<!DOCTYPE html>
<html lang='vi'>
<body style='margin:0;padding:0;background:#f7f7f7;font-family:Segoe UI,Arial;'>
  <div style='max-width:680px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;'>
    <div style='background:#d90429;padding:20px;text-align:center;color:#fff;'>
      <h1 style='margin:0;font-size:22px;'>🎬 XÁC NHẬN VÉ - VINCINE</h1>
    </div>

    <div style='padding:30px;'>
      <p>Xin chào <b>{$info['name']}</b>, cảm ơn bạn đã thanh toán thành công.</p>

      <table style='width:100%;border-collapse:collapse;border:1px solid #eee;'>
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

      <p style='text-align:right;margin-top:10px;'>
        <b>Tổng cộng:</b> 
        <span style='color:#2ecc71;font-weight:bold;'>" . number_format($total,0,',','.') . " ₫</span>
      </p>

      <div style='text-align:center;margin-top:25px;'>
        <a href='{$baseURL}/index.php?p=acc' 
           style='background:#e50914;color:#fff;padding:12px 25px;border-radius:50px;text-decoration:none;font-weight:700;'>
          Quản lý vé
        </a>
      </div>
    </div>

    <div style='background:#111;color:#ccc;font-size:12px;text-align:center;padding:12px;'>
      © 2025 VinCine. Email tự động.
    </div>
  </div>
</body>
</html>";

            $mail->send();
        }

        $mail->smtpClose();

    } catch (Exception $e) {
        $detail = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
        error_log('[vincine] mail send fail: ' . $detail);
    }
}

/* ============================================
   4) CHUYỂN TRANG
============================================ */
header("Location: ../../../index.php?p=admin_tickets&msg=done");
exit;

?>

