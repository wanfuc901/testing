<?php
session_start();
include __DIR__ . "/../config/config.php";
include __DIR__ . "/../include/check_log.php";

$user_id = $_SESSION['user_id'] ?? 0;
$movie_id = intval($_POST['movie_id'] ?? 0);
$stars = intval($_POST['stars'] ?? 0);

$status = '';
$msgTitle = '';
$msgText = '';
$redirect = "../../index.php?p=mv&id=" . $movie_id;

if ($user_id <= 0) {
    $status = 'error';
    $msgTitle = 'Chưa đăng nhập';
    $msgText = 'Bạn cần đăng nhập để đánh giá phim.';
} elseif ($movie_id <= 0 || $stars < 1 || $stars > 5) {
    $status = 'error';
    $msgTitle = 'Dữ liệu không hợp lệ';
    $msgText = 'Số sao phải nằm trong khoảng từ 1 đến 5.';
} else {
    // Kiểm tra vé
    $sql = "
    SELECT COUNT(*) AS cnt
    FROM tickets t
    JOIN showtimes s ON t.showtime_id = s.showtime_id
    WHERE t.user_id = ? 
      AND s.movie_id = ? 
      AND t.status IN ('confirmed','paid')
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $movie_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res['cnt'] == 0) {
        $status = 'error';
        $msgTitle = 'Chưa mua vé';
        $msgText = 'Bạn chỉ có thể đánh giá khi đã mua vé xem phim này.';
    } else {
        // Kiểm tra đã có đánh giá chưa
        $stmt = $conn->prepare("SELECT rating_id FROM ratings WHERE movie_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $movie_id, $user_id);
        $stmt->execute();
        $has = $stmt->get_result()->fetch_assoc();

        if ($has) {
            $stmt = $conn->prepare("UPDATE ratings SET stars = ?, created_at = NOW() WHERE rating_id = ?");
            $stmt->bind_param("ii", $stars, $has['rating_id']);
            $stmt->execute();
            $status = 'success';
            $msgTitle = 'Đã cập nhật đánh giá';
            $msgText = 'Cảm ơn bạn đã cập nhật cảm nhận mới.';
        } else {
            $stmt = $conn->prepare("INSERT INTO ratings (movie_id, user_id, stars) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $movie_id, $user_id, $stars);
            $stmt->execute();
            $status = 'success';
            $msgTitle = 'Gửi đánh giá thành công';
            $msgText = 'Cảm ơn bạn đã đánh giá phim này!';
        }

        // Cập nhật trung bình phim
        $conn->query("
            UPDATE movies 
            SET avg_rating = (
                SELECT ROUND(AVG(stars),1) FROM ratings WHERE movie_id = $movie_id
            )
            WHERE movie_id = $movie_id
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đang xử lý đánh giá...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">
<style>
body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
.box {
  text-align: center;
  background: var(--card);
  padding: 40px 60px;
  border-radius: 18px;
  border: 1px solid rgba(255,255,255,.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.5);
}
h2 { color: var(--gold); margin: 12px 0; font-size: 22px; }
p { color: var(--muted); font-size: 15px; }
.spinner {
  width: 50px; height: 50px;
  border: 4px solid rgba(255,255,255,.15);
  border-top-color: var(--gold);
  border-radius: 50%;
  margin: 0 auto 25px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.checkmark, .errormark {
  width: 80px; height: 80px; display: none; margin: 0 auto 16px;
}
.checkmark circle, .errormark circle {
  stroke-dasharray: 166; stroke-dashoffset: 166;
  stroke-width: 3; fill: none; animation: draw 0.6s forwards;
}
.checkmark path, .errormark path {
  stroke-width: 3; fill: none; stroke-dasharray: 48; stroke-dashoffset: 48;
  animation: draw 0.3s ease-in-out 0.5s forwards;
}
@keyframes draw { 100% { stroke-dashoffset: 0; } }
</style>
</head>
<body>
  <div class="box">
    <div class="spinner" id="spinner"></div>

    <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle stroke="#4BB71B" cx="26" cy="26" r="25"/>
      <path stroke="#fff" d="M14 27l7 7 17-17"/>
    </svg>

    <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle stroke="#e74c3c" cx="26" cy="26" r="25"/>
      <path stroke="#fff" d="M16 16 36 36 M36 16 16 36"/>
    </svg>

    <h2 id="msgTitle">Đang xử lý...</h2>
    <p id="msgText">Vui lòng chờ trong giây lát</p>
  </div>

<script>
const spinner = document.getElementById('spinner');
const checkmark = document.getElementById('checkmark');
const errormark = document.getElementById('errormark');
const title = document.getElementById('msgTitle');
const text = document.getElementById('msgText');

setTimeout(() => {
  spinner.style.display = 'none';
  <?php if ($status === 'success'): ?>
    checkmark.style.display = 'block';
    title.innerText = "<?= $msgTitle ?>";
    text.innerText = "<?= $msgText ?>";
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, 3000);
  <?php else: ?>
    errormark.style.display = 'block';
    title.innerText = "<?= $msgTitle ?>";
    text.innerText = "<?= $msgText ?>";
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, 4000);
  <?php endif; ?>
}, 1600);
</script>
</body>
</html>
