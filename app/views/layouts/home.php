<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VinCine – Đặt vé phim</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>
  <!-- Topbar -->
  <div class="topbar">
    <div class="container">
      <div class="row">
        <div>🎬 Cụm rạp VinCine</div>
        <div style="margin-left:auto">🎟️ Ưu đãi thành viên • 🍿 Bắp nước 59K</div>
      </div>
    </div>
  </div>

  <!-- Menu được include -->


  <!-- Hero -->
  <section class="hero" id="lich-chieu">
    <div class="hero-bg">
      <div class="hero-overlay"></div>
      <div class="hero-content container">
        <h1>Đặt vé nhanh. Trải nghiệm điện ảnh đỉnh.</h1>
        <p>Chọn rạp, chọn suất, vào xem ngay. Không dùng thương hiệu hay tài sản CGV.</p>
        <form class="search" role="search" aria-label="Tìm kiếm phim" method="get" action="index.php">
            <input type="hidden" name="p" value="srch">
            <label class="sr-only" for="q">Tìm phim</label>
            <input class="input" id="q" name="q" placeholder="Tìm tên phim, đạo diễn…" required />
            <button class="btn primary" type="submit">Tìm</button>
        </form>


      </div>
    </div>
  </section>

  <!-- Phim đang chiếu -->
<?php
include "app/config/config.php";
$sql = "
SELECT m.*, 
       ROUND(m.avg_rating,1) AS avg_rating, 
       (SELECT COUNT(*) FROM ratings r WHERE r.movie_id = m.movie_id) AS rating_count
FROM movies m
ORDER BY m.release_date DESC
LIMIT 10
";
$result = $conn->query($sql);
?>

<section id="phim">
  <div class="container">
    <div class="section-title">
      <h2 style="margin:0">Phim đang chiếu</h2>
      <a class="btn ghost" href="index.php?p=am">Xem tất cả</a>
    </div>
    <div class="grid" id="movieGrid">
      <?php while($row = $result->fetch_assoc()) { ?>
        <article class="card" data-genre="<?= htmlspecialchars($row['genre']) ?>">
          <a href="index.php?p=mv&id=<?= $row['movie_id'] ?>" style="text-decoration:none; color:inherit">
            <div class="poster">
              <img alt="<?= htmlspecialchars($row['title']) ?>" 
                   src="app/views/movies/<?= htmlspecialchars($row['poster_url']) ?>"/>
            </div>
            <div class="card-body">
              <div class="title"><?= htmlspecialchars($row['title']) ?></div>
              <div class="meta">
                <span class="tag">C16</span>
                <span class="rating">
                  ⭐ <?= isset($row['avg_rating']) && $row['avg_rating'] !== null ? number_format($row['avg_rating'], 1) : '–' ?>
                  (<?= intval($row['rating_count']) ?>)
                </span>
                <span><?= $row['duration'] ?>'</span>
              </div>
            </div>
          </a>
        </article>
      <?php } ?>
    </div>
  </div>
</section>


  <!-- Ưu đãi -->
  <section id="uu-dai">
    <div class="container">
      <div class="section-title">
        <h2 style="margin:0">Ưu đãi</h2>
        <a class="btn ghost" href="index.php?p=ao">Xem tất cả</a>
      </div>
      <div class="grid" style="grid-template-columns:repeat(3,1fr)">
        <?php
          $sql_banner = "SELECT * FROM banners WHERE is_active = 1 ORDER BY position ASC";
          $res_banner = $conn->query($sql_banner);
          while($banner = $res_banner->fetch_assoc()):
        ?>
          <article class="card">
  <div class="poster" style="aspect-ratio:16/9">
    <a href="index.php?p=od&id=<?= $banner['banner_id'] ?>">
      <img alt="<?= htmlspecialchars($banner['title']) ?>" 
           src="app/views/banners/<?= htmlspecialchars($banner['image_url']) ?>"/>
    </a>
  </div>
  <div class="card-body">
    <div class="title"><?= htmlspecialchars($banner['title']) ?></div>
  </div>
</article>

        <?php endwhile; ?>
      </div>
    </div>
  </section>

  

  <?php $conn->close(); ?>
  <script>
    const menuBtn = document.getElementById('btn-menu');
    const mobileMenu = document.getElementById('mobileMenu');
    if(menuBtn){
      menuBtn.addEventListener('click', () => {
        const state = mobileMenu.hasAttribute('hidden');
        mobileMenu.toggleAttribute('hidden');
        menuBtn.setAttribute('aria-expanded', state ? 'true':'false');
      });
    }

    const chips = document.querySelectorAll('.chip');
    const cards = document.querySelectorAll('.card[data-genre]');
    chips.forEach(ch => ch.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      ch.classList.add('active');
      const f = ch.dataset.filter;
      cards.forEach(card => {
        card.style.display = (f==='all' || card.dataset.genre===f) ? '' : 'none';
      })
    }));

    document.getElementById('btn-book').addEventListener('click', ()=>{
      alert('Demo: Tính năng đặt vé sẽ được tích hợp sau.');
    });

   
  </script>

   <script src="public/assets/js/search_suggest.js"></script>
<script>
(function () {
  // chạy 1 lần sau khi DOM sẵn sàng
  function onReady(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded',fn); }

  onReady(function(){
    // Đợi 1 giây rồi mới animate
    setTimeout(function(){

      // Helper: gắn class + delay theo thứ tự
      function animateStagger(selector, baseDelay, step){
        var els = document.querySelectorAll(selector);
        els.forEach(function(el, i){
          // không đụng class/HTML hiện hữu: chỉ thêm class phụ
          el.classList.add('will-animate');
          el.style.setProperty('--d', (baseDelay + i*step) + 's');
          // force reflow để đảm bảo animation nhận delay
          void el.offsetWidth;
          el.classList.add('show');
        });
      }

      // ===== GHIM CÁC KHỐI ĐANG CÓ SẴN =====
      // Header + hero + section-title
      animateStagger('header.topmenu, .hero, .section-title', 0.00, 0.10);

      // Card dashboard admin
      animateStagger('.card-grid .card', 0.15, 0.10);

      // Bảng "Hoạt động gần đây" (hàng đã render sẵn)
      animateStagger('#recentBody tr', 0.20, 0.08);

      // Lưới phim, banner, offer trên trang chủ
      animateStagger('.grid .card, .movie-grid .movie-card, .offer-grid .offer-card', 0.25, 0.06);

      // Các container admin chung
      animateStagger('.admin-container', 0.10, 0.08);

      // Footer
      animateStagger('footer', 0.40, 0.00);

    }, 1000);
  });
})();
</script>

</body>
</html>
