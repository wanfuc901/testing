<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================
   XÁC ĐỊNH TÌNH TRẠNG LOGIN
========================== */
$isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] !== 'guest';

$displayName = $_SESSION['fullname']
            ?? $_SESSION['name']
            ?? "Tài khoản";
?>
<link rel="stylesheet" href="public/assets/boxicon/css/boxicons.min.css">
<link rel="stylesheet" href="public/assets/boxicons-free/free/fonts/basic/boxicons.min.css">
<link rel="stylesheet" href="public/assets/css/style.css">

<header class="topmenu">
  <div class="container nav">

    <!-- Logo -->
    <a class="brand" href="index.php?p=home">
      <svg viewBox="0 0 24 24" width="24" height="24" style="fill:#e50914;">
        <path d="M12 2l2.39 4.84 5.34.78-3.86 3.76.91 5.31L12 14.77 6.22 16.69l.91-5.31L3.27 7.62l5.34-.78L12 2z"/>
      </svg>
      <span class="name">VinCine</span>
    </a>

    <!-- NAV LINKS -->
    <nav class="nav-links">
      <a href="index.php?p=home"><i class='bx bxs-movie'></i> Lịch chiếu</a>

      <!-- Dropdown phim -->
      <div class="dropdown">
        <a href="#" class="drop-btn">
          <i class='bx bxs-video'></i> Phim <i class='bx bx-chevron-down'></i>
        </a>
        <div class="dropdown-content">
          <a href="index.php?p=nowshowing"><i class='bx bx-play-circle'></i> Đang chiếu</a>
          <a href="index.php?p=upcoming"><i class='bx bx-time-five'></i> Sắp chiếu</a>
        </div>
      </div>

      <a href="index.php?p=ao"><i class='bx bxs-ticket'></i> Ưu đãi</a>
      <a href="index.php?p=abt"><i class='bx bxs-info-circle'></i> Về chúng tôi</a>
    </nav>

    <!-- RIGHT CTA -->
    <div class="nav-cta" style="display:flex; gap:10px; align-items:center;">

      <button 
        class="btn ghost nav-btn"
        onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=9.597199672355272,105.97092570299519', '_blank')">
        <i class='bx bxs-map'></i> Địa Chỉ
      </button>

      <!-- USER LOGIN STATE -->
      <?php if ($isLoggedIn): ?>

        <a href="index.php?p=acc" class="btn ghost nav-btn">
          <i class='bx bxs-user'></i> <?= htmlspecialchars($displayName) ?>
        </a>

        <form method="post" action="index.php?p=logout" style="display:inline;">
          <button type="submit" class="btn ghost nav-btn">
            <i class='bx bxs-log-out-circle'></i> Đăng xuất
          </button>
        </form>

      <?php else: ?>

        <button class="btn ghost nav-btn" id="btn-login">
          <i class='bx bx-log-in'></i> Đăng nhập
        </button>

        <script>
          document.getElementById("btn-login").addEventListener("click", () => {
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

<!-- MOBILE MENU -->
<div id="mobileMenu" class="mobile-menu container" hidden>
  <nav>
    <a class="btn ghost" href="index.php?p=home"><i class='bx bxs-movie'></i> Lịch chiếu</a>

    <a class="btn ghost" href="index.php?p=am"><i class='bx bxs-video'></i> Phim</a>
    <a class="btn ghost" href="index.php?p=nowshowing" style="margin-left:24px;"><i class='bx bx-play-circle'></i> Đang chiếu</a>
    <a class="btn ghost" href="index.php?p=upcoming" style="margin-left:24px;"><i class='bx bx-time-five'></i> Sắp chiếu</a>

    <a class="btn ghost" href="index.php?p=ao"><i class='bx bxs-discount'></i> Ưu đãi</a>
    <a class="btn ghost" href="index.php?p=abt"><i class='bx bxs-info-circle'></i> Về chúng tôi</a>

    <?php if ($isLoggedIn): ?>

      <a class="btn ghost" href="index.php?p=acc">
        <i class='bx bxs-user'></i> <?= htmlspecialchars($displayName) ?>
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
// Toggle menu
const menuBtn = document.getElementById("btn-menu");
const mobileMenu = document.getElementById("mobileMenu");

menuBtn.addEventListener("click", () => {
  const open = menuBtn.getAttribute("aria-expanded") === "true";
  menuBtn.setAttribute("aria-expanded", !open);
  mobileMenu.hidden = open;
});
</script>
