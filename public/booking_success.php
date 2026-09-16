<?php
/**
 * Màn hình xác nhận đặt vé thành công.
 *
 * Hai nguồn dữ liệu:
 *   - ?pid=  : đơn đã thanh toán qua PayOS, đọc lại từ DB (kiểm tra chủ sở hữu)
 *   - session: luồng thanh toán tại quầy, dữ liệu còn trong $_SESSION
 */

require_once __DIR__ . '/../app/include/auth.php';

$payment_id = (int)($_GET['pid'] ?? 0);

if ($payment_id > 0) {

    vincine_require_customer();
    $customer_id = vincine_customer_id();

    $stmt = $conn->prepare("
        SELECT amount, order_data, method, status
        FROM payments
        WHERE payment_id = ? AND customer_id = ?
    ");
    if (!$stmt) {
        error_log('[vincine] booking_success prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Hệ thống đang bận. Vui lòng thử lại sau.');
    }

    $stmt->bind_param('ii', $payment_id, $customer_id);
    $stmt->execute();
    $paid = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$paid) {
        http_response_code(404);
        exit('Không tìm thấy hóa đơn.');
    }

    $order = json_decode((string)$paid['order_data'], true) ?: [];
    $showtime_id = (int)($order['showtime_id'] ?? 0);
    $total       = (float)$paid['amount'];
    $method      = (string)$paid['method'];

    /* Dựng lại nhãn ghế từ DB thay vì tin session. */
    $seatLabels = [];
    $stmtSeat = $conn->prepare("SELECT `row_number`, `col_number` FROM seats WHERE seat_id = ?");
    foreach (($order['seats'] ?? []) as $sid) {
        $sid = (int)$sid;
        $stmtSeat->bind_param('i', $sid);
        $stmtSeat->execute();
        $row = $stmtSeat->get_result()->fetch_assoc();
        if ($row) {
            $seatLabels[] = chr(64 + (int)$row['row_number']) . (int)$row['col_number'];
        }
    }
    $stmtSeat->close();

} else {

    if (empty($_SESSION['last_booking'])) {
        http_response_code(400);
        exit('Không có thông tin đặt vé.');
    }

    $booking     = $_SESSION['last_booking'];
    $showtime_id = (int)$booking['showtime_id'];
    $seatLabels  = $booking['seat_labels'] ?? [];
    $total       = $booking['total'];
    $method      = $booking['method'];
}

$stmt = $conn->prepare("
    SELECT m.title, r.name AS room_name, s.start_time, s.end_time
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.showtime_id=?");
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$show = $stmt->get_result()->fetch_assoc();

// ==== Tạo QR nội dung vé ====
$qrContent =
"🎬 Phim: {$show['title']}
🏠 Phòng: {$show['room_name']}
🕒 Thời gian: " . date('H:i', strtotime($show['start_time'])) . " - " . date('H:i', strtotime($show['end_time'])) . "
💺 Ghế: " . implode(', ', $seatLabels) . "
💵 Tổng: " . number_format($total, 0, ',', '.') . " ₫
💳 Thanh toán: " . ($method === 'cash' ? 'Tại quầy' : 'Online') . "
🎟️ Cảm ơn bạn đã đặt vé tại VinCine";

/* Mã QR vẽ tại trình duyệt bằng thư viện cục bộ: nội dung vé không rời khỏi hệ thống. */

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>VinCine · Đặt vé thành công</title>
<link rel="stylesheet" href="../public/assets/css/style.css">
<link rel="stylesheet" href="../public/assets/bootstrap-icons/bootstrap-icons.css">
</head>
<body class="success-body">

<div class="success-container">
  <div class="success-card">
    <div class="success-header">
      <i class="bi bi-check-circle-fill"></i>
      <h2>Đặt vé thành công!</h2>
      <p>Cảm ơn bạn đã đặt vé tại <strong>VinCine</strong>.</p>
    </div>

    <div class="success-content">
      <div class="success-info">
        <p><i class="bi bi-film"></i> <strong>Phim:</strong> <?= htmlspecialchars($show['title']) ?></p>
        <p><i class="bi bi-easel2"></i> <strong>Phòng:</strong> <?= htmlspecialchars($show['room_name']) ?></p>
        <p><i class="bi bi-clock-history"></i> <strong>Thời gian:</strong> <?= date('H:i', strtotime($show['start_time'])) ?> - <?= date('H:i', strtotime($show['end_time'])) ?></p>
        <p><i class="bi bi-grid-3x3-gap"></i> <strong>Ghế:</strong> <?= implode(', ', $seatLabels) ?></p>
        <p class="price-line"><i class="bi bi-cash-stack"></i> <strong>Tổng tiền:</strong> <span><?= number_format($total, 0, ',', '.') ?> ₫</span></p>
        <p><i class="bi bi-credit-card-2-front"></i> <strong>Phương thức:</strong> <?= ($method === 'cash') ? 'Thanh toán tại quầy' : 'Online' ?></p>
        <a href="../index.php" class="btn-home">
          <i class="bi bi-house-door-fill"></i> Về trang chủ
        </a>
      </div>

      <div class="success-qr">
        <div id="ticketQr"></div>
        <script src="assets/vendor/qrcodejs/qrcode.min.js"></script>
        <script>
          new QRCode(document.getElementById('ticketQr'), {
            text: <?= json_encode($qrContent, JSON_UNESCAPED_UNICODE) ?>,
            width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M
          });
        </script>
        <p>Quét mã bằng zalo để xem thông tin vé</p>
      </div>
    </div>
  </div>
</div>

</body>
</html>
