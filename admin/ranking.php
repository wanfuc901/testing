<?php
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';

$conn->set_charset('utf8mb4');
$type = $_GET['type'] ?? 'revenue';
$period = $_GET['period'] ?? 'all';

function q($conn, $sql) {
  $rs = $conn->query($sql);
  if ($rs === false) die("SQL ERROR: " . $conn->error);
  return $rs;
}

/* ==== Xây dựng điều kiện thời gian ==== */
$timeFilter = "";
switch ($period) {
  case 'day':
    $timeFilter = "AND DATE(s.start_time) = CURDATE()";
    break;
  case 'month':
    $timeFilter = "AND MONTH(s.start_time) = MONTH(CURDATE()) AND YEAR(s.start_time) = YEAR(CURDATE())";
    break;
  case 'year':
    $timeFilter = "AND YEAR(s.start_time) = YEAR(CURDATE())";
    break;
  case 'all':
  default:
    $timeFilter = "";
}
?>
<link rel="stylesheet" href="public/assets/boxicons/css/boxicons.min.css">
<link rel="stylesheet" href="public/assets/css/admin_css.css">

<style>
.filter-form {display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px}
.filter-group label{margin-right:6px;font-weight:500;color:var(--muted);}
.top-medal {font-size:22px; vertical-align:middle;}
.top-1 {color:#00b894;} .top-2 {color:#0984e3;} .top-3 {color:#fdcb6e;}
.poster {width:50px;height:70px;object-fit:cover;border-radius:6px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .25s ease, box-shadow .25s ease;}
.poster:hover {transform:scale(1.06);box-shadow:0 4px 10px rgba(0,0,0,.25);}
.rev-1 {color:#00b894;font-weight:700;} .rev-2 {color:#0984e3;font-weight:700;} .rev-3 {color:#fdcb6e;font-weight:700;} .rev-normal {color:#636e72;}
</style>

<div class="admin-wrap">
  <div class="admin-container">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="p" value="admin_ranking">

      <div class="filter-group">
        <label><i class='bx bx-filter-alt'></i>Chọn loại:</label>
        <select name="type" onchange="this.form.submit()" class="input">
          <option value="revenue" <?= $type=='revenue'?'selected':'' ?>>Phim doanh thu cao nhất</option>
          <option value="rating" <?= $type=='rating'?'selected':'' ?>>Phim rating cao nhất</option>
          <option value="spender" <?= $type=='spender'?'selected':'' ?>>Người dùng chi tiêu nhiều</option>
          <option value="showtime" <?= $type=='showtime'?'selected':'' ?>>Khung giờ bán chạy nhất</option>
        </select>
      </div>

      <div class="filter-group">
        <label><i class='bx bx-calendar'></i>Thời gian:</label>
        <select name="period" onchange="this.form.submit()" class="input">
          <option value="day" <?= $period=='day'?'selected':'' ?>>Hôm nay</option>
          <option value="month" <?= $period=='month'?'selected':'' ?>>Tháng này</option>
          <option value="year" <?= $period=='year'?'selected':'' ?>>Năm nay</option>
          <option value="all" <?= $period=='all'?'selected':'' ?>>Mọi thời đại</option>
        </select>
      </div>
    </form>

<?php
switch ($type) {

/* ==== 1️⃣ PHIM DOANH THU CAO NHẤT ==== */
case 'revenue':
  $sql = "
    SELECT 
      m.movie_id,
      m.title,
      m.poster_url,
      SUM(t.price) AS total_revenue,
      COUNT(t.ticket_id) AS total_tickets
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.movie_id
    JOIN tickets t ON t.showtime_id = s.showtime_id
    WHERE t.paid = 1 $timeFilter
    GROUP BY m.movie_id
    ORDER BY total_revenue DESC
    LIMIT 10
  ";
  $rs = q($conn, $sql);
  echo "<h3><i class='bx bx-movie-play'></i> Top 10 phim doanh thu cao nhất</h3>";
  echo "<table class='admin-table'>
        <thead><tr><th>#</th><th>Poster</th><th>Tên phim</th><th>Vé bán</th><th>Doanh thu (₫)</th></tr></thead><tbody>";
  $i=1;
  while($r=$rs->fetch_assoc()){
    $medal=($i==1?"<i class='bx bxs-crown top-medal top-1'></i>":($i==2?"<i class='bx bxs-medal top-medal top-2'></i>":($i==3?"<i class='bx bxs-medal top-medal top-3'></i>":"")));
    $poster = $r['poster_url'] ? "app/views/movies/{$r['poster_url']}" : "public/assets/img/no_poster.png";
    $class = ($i==1?'rev-1':($i==2?'rev-2':($i==3?'rev-3':'rev-normal')));
    echo "<tr>
            <td>{$medal} {$i}</td>
            <td><img src='{$poster}' class='poster'></td>
            <td>{$r['title']}</td>
            <td>{$r['total_tickets']}</td>
            <td><strong class='{$class}'>".number_format($r['total_revenue'])."₫</strong></td>
          </tr>";
    $i++;
  }
  echo "</tbody></table>";
  break;

/* ==== 2️⃣ PHIM RATING ==== */
case 'rating':
  $sql = "
    SELECT 
      m.movie_id,m.title,m.poster_url,
      IFNULL(ROUND(AVG(r.stars),1),0) AS avg_rating,
      COUNT(r.rating_id) AS review_count
    FROM movies m
    LEFT JOIN ratings r ON r.movie_id = m.movie_id
    GROUP BY m.movie_id,m.title,m.poster_url
    ORDER BY avg_rating DESC,review_count DESC
    LIMIT 10";
  $rs = q($conn, $sql);
  echo "<h3><i class='bx bx-star'></i> Top 10 phim có rating cao nhất</h3>";
  echo "<table class='admin-table'>
        <thead><tr><th>#</th><th>Poster</th><th>Tên phim</th><th>Rating</th><th>Lượt đánh giá</th></tr></thead><tbody>";
  $i=1;
  while($r=$rs->fetch_assoc()){
    $medal=($i==1?"<i class='bx bxs-crown top-medal top-1'></i>":($i==2?"<i class='bx bxs-medal top-medal top-2'></i>":($i==3?"<i class='bx bxs-medal top-medal top-3'></i>":"")));
    $poster = $r['poster_url'] ? "app/views/movies/{$r['poster_url']}" : "public/assets/img/no_poster.png";
    echo "<tr>
            <td>{$medal} {$i}</td>
            <td><img src='{$poster}' class='poster'></td>
            <td>{$r['title']}</td>
            <td><i class='bx bxs-star' style='color:var(--gold)'></i> {$r['avg_rating']}</td>
            <td>{$r['review_count']}</td>
          </tr>";
    $i++;
  }
  echo "</tbody></table>";
  break;

/* ==== 3️⃣ NGƯỜI DÙNG CHI TIÊU ==== */
case 'spender':
  $sql = "
    SELECT u.user_id,u.name,u.email,
           SUM(t.price) AS total_spent, COUNT(t.ticket_id) AS tickets
    FROM users u
    JOIN tickets t ON t.user_id = u.user_id
    JOIN showtimes s ON s.showtime_id = t.showtime_id
    WHERE t.paid = 1 $timeFilter
    GROUP BY u.user_id
    ORDER BY total_spent DESC
    LIMIT 10";
  $rs = q($conn, $sql);
  echo "<h3><i class='bx bx-user-circle'></i> Top 10 người dùng chi tiêu nhiều nhất</h3>";
  echo "<table class='admin-table'>
        <thead><tr><th>#</th><th>Tên</th><th>Email</th><th>Vé đã mua</th><th>Tổng chi (₫)</th></tr></thead><tbody>";
  $i=1;
  while($r=$rs->fetch_assoc()){
    $medal=($i==1?"<i class='bx bxs-crown top-medal top-1'></i>":($i==2?"<i class='bx bxs-medal top-medal top-2'></i>":($i==3?"<i class='bx bxs-medal top-medal top-3'></i>":"")));
    $class = ($i==1?'rev-1':($i==2?'rev-2':($i==3?'rev-3':'rev-normal')));
    echo "<tr>
            <td>{$medal} {$i}</td>
            <td>{$r['name']}</td>
            <td>{$r['email']}</td>
            <td>{$r['tickets']}</td>
            <td><strong class='{$class}'>".number_format($r['total_spent'])."₫</strong></td>
          </tr>";
    $i++;
  }
  echo "</tbody></table>";
  break;

/* ==== 4️⃣ KHUNG GIỜ BÁN CHẠY ==== */
case 'showtime':
  $sql = "
    SELECT 
      HOUR(s.start_time) AS hour_slot,
      COUNT(t.ticket_id) AS sold,
      SUM(t.price) AS total_revenue
    FROM showtimes s
    JOIN tickets t ON t.showtime_id = s.showtime_id
    WHERE t.paid = 1 $timeFilter
    GROUP BY hour_slot
    ORDER BY sold DESC";
  $rs = q($conn, $sql);
  echo "<h3><i class='bx bx-time-five'></i> Khung giờ bán chạy nhất</h3>";
  echo "<table class='admin-table'>
        <thead><tr><th>#</th><th>Khung giờ</th><th>Vé bán</th><th>Doanh thu (₫)</th></tr></thead><tbody>";
  $i=1;
  while($r=$rs->fetch_assoc()){
    $medal=($i==1?"<i class='bx bxs-crown top-medal top-1'></i>":($i==2?"<i class='bx bxs-medal top-medal top-2'></i>":($i==3?"<i class='bx bxs-medal top-medal top-3'></i>":"")));
    $class = ($i==1?'rev-1':($i==2?'rev-2':($i==3?'rev-3':'rev-normal')));
    echo "<tr>
            <td>{$medal} {$i}</td>
            <td>{$r['hour_slot']}:00</td>
            <td>{$r['sold']}</td>
            <td><strong class='{$class}'>".number_format($r['total_revenue'] ?: 0)."₫</strong></td>
          </tr>";
    $i++;
  }
  echo "</tbody></table>";
  break;

default:
  echo "<p>Loại không hợp lệ</p>";
}
?>
  </div>
</div>
