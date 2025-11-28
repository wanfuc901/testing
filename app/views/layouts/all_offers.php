<?php
include "app/config/config.php";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tất cả ưu đãi – VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>

  <section class="all-offers">
    <div class="container">
      <h1>Tất cả ưu đãi</h1>
      <div class="grid" style="grid-template-columns:repeat(3,1fr)">
        <?php
          $sql_banner = "SELECT * FROM banners ORDER BY banner_id DESC";
          $res_banner = $conn->query($sql_banner);
          if ($res_banner->num_rows > 0) {
            while($banner = $res_banner->fetch_assoc()){
              echo '<article class="card">';
              echo '<div class="poster" style="aspect-ratio:16/9">';
              echo '<a href="index.php?p=od&id=' . $banner['banner_id'] . '">';
              echo '<img alt="' . htmlspecialchars($banner['title']) . '" src="app/views/banners/' . htmlspecialchars($banner['image_url']) . '">';
              echo '</a></div>';
              echo '<div class="card-body">';
              echo '<div class="title">' . htmlspecialchars($banner['title']) . '</div>';
              echo '</div></article>';
            }
          } else {
            echo "<p>Chưa có ưu đãi nào được đăng.</p>";
          }
          $conn->close();
        ?>
      </div>
    </div>
  </section>

</body>
</html>
