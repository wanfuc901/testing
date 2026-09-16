<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require __DIR__ . "/../../config/config.php";
require __DIR__ . "/../../include/check_log.php";

/* ===============================
   LẤY ID PHIM & KIỂM TRA
================================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID phim không hợp lệ.");
}
$movie_id = intval($_GET['id']);

/* ===============================
   LẤY THÔNG TIN PHIM
================================= */
$stmt = $conn->prepare("SELECT * FROM movies WHERE movie_id = ?");
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$movie) die("Không tìm thấy phim.");

/* ===============================
   LẤY SUẤT CHIẾU TRONG NGÀY (GIỜ VN)
================================= */

// Xác định khoảng ngày theo timezone Việt Nam
$today      = date("Y-m-d");               // ví dụ: 2025-11-26
$startDayVN = $today . " 00:00:00";        // 2025-11-26 00:00:00
$endDayVN   = $today . " 23:59:59";        // 2025-11-26 23:59:59

$sqlShow = "
    SELECT s.showtime_id, s.start_time, s.end_time, r.name AS room_name
    FROM showtimes s
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.movie_id = ?
      AND s.status = 'active'
      AND s.start_time BETWEEN ? AND ?
    ORDER BY s.start_time ASC
";

$stmt2 = $conn->prepare($sqlShow);
$stmt2->bind_param("iss", $movie_id, $startDayVN, $endDayVN);
$stmt2->execute();
$showtimes = $stmt2->get_result();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($movie['title']) ?> - VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
</head>

<body>

<!-- ============================
     THÔNG TIN PHIM
============================= -->
<div class="movie-detail container">
  <div class="poster">
    <img src="app/views/movies/<?= htmlspecialchars($movie['poster_url'] ?: 'default.png') ?>"
         alt="<?= htmlspecialchars($movie['title']) ?>">
  </div>

  <div class="info">
    <h1><?= htmlspecialchars($movie['title']) ?></h1>
    <p><strong>Thể loại:</strong> <?= htmlspecialchars($movie['genre']) ?></p>
    <p><strong>Thời lượng:</strong> <?= intval($movie['duration']) ?> phút</p>
    <p><strong>Khởi chiếu:</strong> <?= htmlspecialchars($movie['release_date']) ?></p>
    <div class="desc"><?= nl2br(htmlspecialchars($movie['description'])) ?></div>
  </div>
</div>


<!-- ============================
     LỊCH CHIẾU TRONG NGÀY
============================= -->
<div class="showtimes">
  <h2>🎬 Lịch chiếu hôm nay</h2>
  <div class="cinema-location">Vincent Cinemas Cần Thơ</div>

  <?php if ($showtimes->num_rows > 0): ?>
    <div class="schedule-grid">

      <?php while ($st = $showtimes->fetch_assoc()): ?>
        <?php
            $startTime = strtotime($st['start_time']);
            $endTime   = strtotime($st['end_time']);
        ?>

        <div class="schedule-card">
          <p><strong><?= htmlspecialchars($st['room_name']) ?></strong></p>

          <a href="index.php?p=bk&movie_id=<?= $movie_id ?>&showtime_id=<?= $st['showtime_id'] ?>"
             class="time-btn">
            <?= date("H:i", $startTime) ?> - <?= date("H:i", $endTime) ?>
          </a>
        </div>

      <?php endwhile; ?>

    </div>

  <?php else: ?>
    <p class="no-showtime">Hôm nay chưa có suất chiếu.</p>
  <?php endif; ?>
</div>


<!-- ============================
     KHỐI ĐÁNH GIÁ
============================= -->
<div class="showtimes" style="margin-top:40px;">
  <h2>⭐ Đánh giá phim</h2>
  <div class="cinema-location">Hãy chia sẻ cảm nhận của bạn</div>

  <div class="schedule-grid">
    <div class="schedule-card" style="flex:0 0 100%; text-align:center;">

      <?php $avg = number_format(round($movie['avg_rating'] ?? 0, 1), 1); ?>

      <div class="rating-display" style="margin-bottom:16px;">
        <div style="font-size:24px; color:var(--gold);"><?= $avg ?>/5</div>
        <div style="font-size:26px;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <?= ($avg >= $i ? '★' : '☆') ?>
          <?php endfor; ?>
        </div>
      </div>

      <form method="POST" action="app/controllers/ajax_rate.php" class="rating-form">
<?= vincine_csrf_input() ?>
        <input type="hidden" name="movie_id" value="<?= $movie_id ?>">

        <div class="stars-input">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" id="star<?= $i ?>" name="stars" value="<?= $i ?>">
            <label for="star<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>

        <button type="submit" class="btn primary" style="margin-top:10px;">Gửi đánh giá</button>
      </form>

    </div>
  </div>
</div>

</body>
</html>

<script>
/*
 * Đã gỡ vòng lặp ping tới app/api/ping_activity.php và
 * app/api/leave_activity.php: hai file này không tồn tại nên mỗi 15 giây
 * trình duyệt lại nhận một lỗi 404.
 */
</script>
