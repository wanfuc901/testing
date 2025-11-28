<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/../app/config/config.php";

if (empty($_SESSION['last_booking'])) die("Không có thông tin đặt vé.");

$booking = $_SESSION['last_booking'];
$showtime_id = intval($booking['showtime_id']);
$seatLabels  = $booking['seat_labels'] ?? [];
$total       = $booking['total'];
$method      = $booking['method'];

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

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . urlencode($qrContent);

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
        <img src="<?= $qrUrl ?>" alt="QR Code vé">
        <p>Quét mã bằng zalo để xem thông tin vé</p>
      </div>
    </div>
  </div>
</div>

</body>
</html>
