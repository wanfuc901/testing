<?php
include "app/config/config.php";

$page = $_GET['p'] ?? 'am';
$title = 'Tất cả phim';
$filter = '';

/* ====== PHÂN LOẠI TRANG ====== */
if ($page === 'nowshowing') {
  // Có ít nhất 1 suất chiếu
  $filter = "
    WHERE EXISTS (
      SELECT 1 FROM showtimes s 
      WHERE s.movie_id = m.movie_id
    )
  ";
  $title = 'Phim đang chiếu';
} elseif ($page === 'upcoming') {
  // Chưa có suất chiếu nào diễn ra (chỉ có trong tương lai)
  $filter = "
    WHERE NOT EXISTS (
      SELECT 1 FROM showtimes s 
      WHERE s.movie_id = m.movie_id 
        AND s.start_time <= NOW()
    )
  ";
  $title = 'Phim sắp chiếu';
}

/* ====== TOP 3 DOANH THU ====== */
$topSql = "
  SELECT m.movie_id
  FROM movies m
  $filter
  ORDER BY 
    m.revenue DESC,
    m.avg_rating DESC,
    (
      SELECT COUNT(*) 
      FROM tickets t
      JOIN showtimes s ON t.showtime_id = s.showtime_id
      WHERE s.movie_id = m.movie_id
    ) DESC
  LIMIT 3
";
$topQuery = $conn->query($topSql);
if (!$topQuery) die('Lỗi SQL Top: '.$conn->error);

$topMovies = [];
$rank = 1;
while ($r = $topQuery->fetch_assoc()) {
  $topMovies[$r['movie_id']] = $rank++;
}

/* ====== TRUY VẤN CHÍNH ====== */
if ($page === 'am') {
  // ----- TẤT CẢ PHIM -----
  $sql = "
    SELECT m.*, g.name AS genre_name,
           ROUND(m.avg_rating,1) AS avg_rating,
           (SELECT COUNT(*) FROM ratings r WHERE r.movie_id=m.movie_id) AS rating_count,
           (SELECT COUNT(*) 
   FROM tickets t 
   JOIN showtimes s2 ON t.showtime_id = s2.showtime_id 
  WHERE s2.movie_id = m.movie_id) AS total_tickets,
           (
             SELECT MIN(ABS(TIMESTAMPDIFF(SECOND, NOW(), s.start_time)))
             FROM showtimes s WHERE s.movie_id = m.movie_id
           ) AS nearest_diff
    FROM movies m
    LEFT JOIN genres g ON m.genre_id=g.genre_id
    ORDER BY 
      m.revenue DESC,
      m.avg_rating DESC,
      nearest_diff ASC
  ";
} elseif ($page === 'nowshowing') {
  // ----- PHIM ĐANG CHIẾU -----
  $sql = "
    SELECT m.*, g.name AS genre_name,
           ROUND(m.avg_rating,1) AS avg_rating,
           (SELECT COUNT(*) FROM ratings r WHERE r.movie_id=m.movie_id) AS rating_count,
           (
             SELECT MIN(ABS(TIMESTAMPDIFF(SECOND, NOW(), s.start_time)))
             FROM showtimes s WHERE s.movie_id = m.movie_id
           ) AS nearest_diff
    FROM movies m
    LEFT JOIN genres g ON m.genre_id = g.genre_id
    $filter
    ORDER BY 
      m.revenue DESC,
      m.avg_rating DESC,
      nearest_diff ASC
  ";
} else {
  // ----- PHIM SẮP CHIẾU -----
  $sql = "
    SELECT m.*, g.name AS genre_name,
           ROUND(m.avg_rating,1) AS avg_rating,
           (SELECT COUNT(*) FROM ratings r WHERE r.movie_id=m.movie_id) AS rating_count,
           (
             SELECT s.start_time 
             FROM showtimes s 
             WHERE s.movie_id = m.movie_id AND s.start_time >= NOW()
             ORDER BY s.start_time ASC LIMIT 1
           ) AS upcoming_show
    FROM movies m
    LEFT JOIN genres g ON m.genre_id = g.genre_id
    $filter
    ORDER BY 
      m.revenue DESC,
      m.avg_rating DESC,
      upcoming_show ASC
  ";
}

/* ====== CHẠY TRUY VẤN VÀ KIỂM TRA ====== */
$result = $conn->query($sql);
if (!$result) die('Lỗi SQL Chính: '.$conn->error);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> – VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>

<section class="all-movies">
  <div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>

    <?php if ($page === 'am'): ?>
    <div class="filter-bar">
      <button class="filter-btn active" data-genre="all">Tất cả</button>
      <?php
        $genres = $conn->query("SELECT * FROM genres ORDER BY name");
        while($g = $genres->fetch_assoc()){
          echo '<button class="filter-btn" data-genre="'.$g['genre_id'].'">'.htmlspecialchars($g['name']).'</button>';
        }
      ?>
    </div>
    <?php endif; ?>

    <div class="grid" id="allMovieGrid">
      <?php
        if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            echo '<article class="card" data-genre="'.$row['genre_id'].'">';

            // Huy hiệu Top 1–3 doanh thu
            if (isset($topMovies[$row['movie_id']])) {
              $r = $topMovies[$row['movie_id']];
              echo '<div class="top-badge top-'.$r.'">'.$r.'</div>';
            }

            echo '<a href="index.php?p=mv&id=' . $row['movie_id'] . '" style="text-decoration:none;color:inherit">';
            echo '<div class="poster"><img src="app/views/movies/' . htmlspecialchars($row['poster_url']) . '" alt="' . htmlspecialchars($row['title']) . '"></div>';
            echo '<div class="card-body">';
            echo '<div class="title">' . htmlspecialchars($row['title']) . '</div>';
            echo '<div class="meta">';
            echo '<span class="tag">'.($row['genre_name'] ?? "Khác").'</span> ';
            echo '<span>⭐ '.($row['avg_rating'] ?? 0.0).' ('.(int)$row['rating_count'].')</span> ';
            echo '<span>'.$row['duration'].' phút</span>';
            echo '</div></div></a></article>';
          }
        } else {
          echo "<p>Không có phim nào thuộc mục này.</p>";
        }
      ?>
    </div>
  </div>
</section>

<script>
// Lọc phim client-side
const buttons = document.querySelectorAll('.filter-btn');
const cards = document.querySelectorAll('.card[data-genre]');
buttons.forEach(btn=>{
  btn.addEventListener('click',()=>{
    buttons.forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const gid = btn.dataset.genre;
    cards.forEach(c=>{
      c.style.display = (gid==='all'||c.dataset.genre===gid)?'':'none';
    });
  });
});
</script>

</body>
</html>
