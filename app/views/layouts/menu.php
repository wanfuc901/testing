<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="public/assets/boxicon/css/boxicons.min.css">
  <link rel="stylesheet" href="public/assets/boxicons-free/free/fonts/basic/boxicons.min.css">
<link rel="stylesheet" href="public/assets/css/style.css">

<header class="topmenu">
  <div class="container nav">
    <a class="brand" href="index.php?p=home">
      <svg viewBox="0 0 24 24" aria-hidden="true" width="24" height="24" style="fill:#e50914;">
        <path d="M12 2l2.39 4.84 5.34.78-3.86 3.76.91 5.31L12 14.77 6.22 16.69l.91-5.31L3.27 7.62l5.34-.78L12 2z"/>
      </svg>
      <span class="name">VinCine</span>
    </a>

    <nav class="nav-links" aria-label="Chính">
      <a href="index.php?p=home"><i class='bx bxs-movie'></i> Lịch chiếu</a>

      <!-- === Dropdown "Phim" === -->
      <div class="dropdown">
        <a href="index.php?p=am" class="drop-btn">
          <i class='bx bxs-video'></i> Phim <i class='bx bx-chevron-down'></i>
        </a>
        <div class="dropdown-content">
          <a href="index.php?p=nowshowing"><i class='bx bx-play-circle'></i> Phim đang chiếu</a>
          <a href="index.php?p=upcoming"><i class='bx bx-time-five'></i> Phim sắp chiếu</a>
        </div>
      </div>

      <a href="index.php?p=ao"><i class='bx bxs-ticket'></i> Ưu đãi</a>
      <a href="index.php?p=abt"><i class='bx bxs-info-circle'></i> Về chúng tôi</a>
    </nav>

    <div class="nav-cta" style="display:flex; gap:10px; align-items:center;">
      <button 
        class="btn ghost nav-btn"
        id="btn-location"
        aria-label="Chọn rạp"
        onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=9.597199672355272,105.97092570299519', '_blank')">
        <i class='bx bxs-map'></i> Địa Chỉ
      </button>

      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="index.php?p=acc" class="btn ghost nav-btn">
          <i class='bx bxs-user'></i> <?= htmlspecialchars($_SESSION['name'] ?? 'Tài khoản') ?>
        </a>
        <form method="post" action="index.php?p=logout" style="display:inline; margin:0; padding:0;">
          <button type="submit" class="btn ghost nav-btn">
            <i class='bx bxs-log-out-circle'></i> Đăng xuất
          </button>
        </form>
      <?php else: ?>
        <button class="btn ghost nav-btn" id="btn-login">
          <i class='bx bx-log-in'></i> Đăng nhập
        </button>
        <script>
          document.getElementById("btn-login").addEventListener("click", function() {
            window.location.href = "index.php?p=login";
          });
        </script>
      <?php endif; ?>

      <button class="btn ghost menu-toggle nav-btn" id="btn-menu" aria-expanded="false" aria-controls="mobileMenu">
        <i class='bx bx-menu'></i>
      </button>
    </div>
  </div>
</header>

<div id="mobileMenu" class="mobile-menu container" hidden>
  <nav>
    <a class="btn ghost" href="index.php?p=home"><i class='bx bxs-movie'></i> Lịch chiếu</a>

    <!-- Submenu "Phim" cho mobile -->
    <a class="btn ghost" href="index.php?p=am"><i class='bx bxs-video'></i> Phim</a>
    <a class="btn ghost" href="index.php?p=nowshowing" style="margin-left:24px;"><i class='bx bx-play-circle'></i> Phim đang chiếu</a>
    <a class="btn ghost" href="index.php?p=upcoming" style="margin-left:24px;"><i class='bx bx-time-five'></i> Phim sắp chiếu</a>

    <a class="btn ghost" href="index.php?p=ao"><i class='bx bxs-discount'></i> Ưu đãi</a>
    <a class="btn ghost" href="#ve-chung-toi"><i class='bx bxs-info-circle'></i> Về chúng tôi</a>

    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="index.php?p=acc" class="btn ghost nav-btn">
        <i class='bx bxs-user'></i> <?= htmlspecialchars($_SESSION['name'] ?? 'Tài khoản') ?>
      </a>
      <form method="post" action="index.php?p=logout">
        <button class="btn ghost" style="width:100%; text-align:left;">
          <i class='bx bx-exit'></i> Đăng xuất
        </button>
      </form>
    <?php else: ?>
      <a class="btn ghost" href="index.php?p=login"><i class='bx bxs-user'></i> Đăng nhập</a>
    <?php endif; ?>
  </nav>
</div>
<script>
(function(){
  document.body.classList.add('vc-preload');

  function onReady(fn){
    if(document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function(){
    // Sau 1 giây mới bắt đầu hiển thị và animate
    setTimeout(function(){
      document.body.classList.remove('vc-preload');

      function animateStagger(selector, baseDelay, step){
        const els = document.querySelectorAll(selector);
        els.forEach((el, i) => {
          el.classList.add('will-animate');
          el.style.setProperty('--d', (baseDelay + i * step) + 's');
          void el.offsetWidth; // reflow
          el.classList.add('show');
        });
      }

      // Các phần thường có trong VincentCinemas
      animateStagger('header.topmenu, .hero, .section-title', 0.0, 0.1);
      animateStagger('.card-grid .card', 0.15, 0.1);
      animateStagger('#recentBody tr', 0.25, 0.08);
      animateStagger('.movie-card, .offer-card, .admin-container', 0.2, 0.08);
      animateStagger('footer', 0.4, 0.0);

    }, 5000); // đợi 1 giây trước khi chạy
  });
})();
</script>

<script>
// Toggle mobile menu
const menuBtn = document.getElementById("btn-menu");
const mobileMenu = document.getElementById("mobileMenu");

menuBtn.addEventListener("click", () => {
  const isOpen = menuBtn.getAttribute("aria-expanded") === "true";
  menuBtn.setAttribute("aria-expanded", !isOpen);

  if (!isOpen) {
    mobileMenu.hidden = false;
  } else {
    mobileMenu.hidden = true;
  }
});
</script>
