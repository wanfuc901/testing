<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../app/config/config.php';

// Nếu chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location:public/login.php");
    exit;
}

// Kiểm tra ticket_id
if (!isset($_GET['ticket_id'])) {
    die("Thiếu mã vé.");
}

$ticket_id = (int)$_GET['ticket_id'];
$user_id   = $_SESSION['user_id'];

// ✅ Lấy thông tin vé chi tiết
$sql = "
    SELECT 
        t.ticket_id,
        t.price,
        t.status,
        t.booked_at,
        s.row_number,
        s.col_number,
        m.title,
        m.genre,
        m.duration,
        m.poster_url,
        sh.start_time,
        sh.end_time,
        r.name AS room_name
    FROM tickets t
    JOIN showtimes sh ON t.showtime_id = sh.showtime_id
    JOIN movies m ON sh.movie_id = m.movie_id
    JOIN seats s ON t.seat_id = s.seat_id
    JOIN rooms r ON sh.room_id = r.room_id
    WHERE t.ticket_id = ? AND t.user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ticket_id, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("<p style='color:red;text-align:center;margin-top:40px;'>❌ Vé không tồn tại hoặc bạn không có quyền xem!</p>");
}

// ✅ Đường dẫn ảnh poster
$poster_path = !empty(trim($ticket['poster_url']))
    ? "/VincentCinemas/app/views/banners/" . htmlspecialchars(trim($ticket['poster_url']))
    : "/VincentCinemas/public/assets/img/no-poster.png";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chi tiết vé - VinCine</title>
  <link rel="stylesheet" href="/VincentCinemas/public/assets/css/style.css">
</head>
<body>

<div class="checkout-wrapper">
  <h2>🎫 Chi tiết vé đã đặt</h2>

  <div class="movie-detail" style="align-items:flex-start;">
    <div class="poster">
      <img src="<?= $poster_path ?>" alt="<?= htmlspecialchars($ticket['title']) ?>">
    </div>
    <div class="info">
      <h1><?= htmlspecialchars($ticket['title']) ?></h1>
      <p><strong>🎬 Thể loại:</strong> <?= htmlspecialchars($ticket['genre'] ?: 'Đang cập nhật') ?></p>
      <p><strong>⏱️ Thời lượng:</strong> <?= htmlspecialchars($ticket['duration']) ?> phút</p>
      <p><strong>🏢 Phòng chiếu:</strong> <?= htmlspecialchars($ticket['room_name']) ?></p>
      <p><strong>🕓 Thời gian:</strong> 
        <?= date("d/m/Y H:i", strtotime($ticket['start_time'])) ?> - 
        <?= date("H:i", strtotime($ticket['end_time'])) ?>
      </p>
      <p><strong>💺 Ghế:</strong> H<?= $ticket['row_number'] ?>C<?= $ticket['col_number'] ?></p>
      <p><strong>💰 Giá vé:</strong> <?= number_format($ticket['price'], 0, ',', '.') ?> ₫</p>
      <p><strong>📅 Đặt lúc:</strong> <?= date("d/m/Y H:i", strtotime($ticket['booked_at'])) ?></p>
      <p><strong>📄 Trạng thái:</strong>
        <?php if ($ticket['status'] == 'confirmed'): ?>
          <span style="color:#2ecc71;font-weight:600;">Đã xác nhận</span>
        <?php elseif ($ticket['status'] == 'paid'): ?>
          <span style="color:#3498db;font-weight:600;">Đã thanh toán</span>
        <?php elseif ($ticket['status'] == 'pending'): ?>
          <span style="color:#f39c12;font-weight:600;">Chờ xử lý</span>
        <?php else: ?>
          <span style="color:#e74c3c;font-weight:600;">Đã hủy</span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div style="text-align:center;margin-top:30px;">
   <button onclick="history.back()" class="btn-confirm" style="width:auto;padding:12px 32px;">⬅ Quay lại</button>
  </div>
</div>

</body>
</html>
