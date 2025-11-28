<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Kết quả tìm kiếm - VinCine</title>
  <link rel="stylesheet" href="/VincentCinemas/public/assets/css/style.css">
</head>
<body>

<div class="container" style="padding:40px 0;">
  <h1 style="color:var(--gold);margin-bottom:20px">
    Kết quả tìm kiếm cho “<?= htmlspecialchars($_GET['q']) ?>”
  </h1>
  <?php if ($results->num_rows > 0): ?>
    <div class="grid" id="movieGrid">
      <?php while ($row = $results->fetch_assoc()): ?>
        <article class="card">
          <a href="index.php?p=mv&id=<?= $row['movie_id'] ?>" style="text-decoration:none;color:inherit">
            <div class="poster">
              <img src="app/views/banners/<?= htmlspecialchars($row['poster_url']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
            </div>
            <div class="card-body">
              <div class="title"><?= htmlspecialchars($row['title']) ?></div>
              <div class="meta">
                <span class="tag"><?= htmlspecialchars($row['genre']) ?></span>
                <span><?= (int)$row['duration'] ?>'</span>
              </div>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p style="color:#aaa;">Không tìm thấy phim nào phù hợp.</p>
  <?php endif; ?>
</div>


</body>
</html>
