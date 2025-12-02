<link rel="stylesheet" href="public/assets/boxicon/css/boxicons.min.css">
<link rel="stylesheet" href="public/assets/css/admin.css">

<div class="admin-sidebar">
  <div class="sidebar-logo">
    <i class='bx bxs-movie-play'></i>
    <span class="logo-text">VinCine Admin</span>
  </div>

  <ul class="sidebar-menu">
    <li><a href="index.php?p=admin_dashboard" class="nav-link"><i class='bx bxs-dashboard'></i><span>Tổng quan</span></a></li>
    <li><a href="index.php?p=admin_movies" class="nav-link"><i class='bx bxs-film'></i><span>Phim</span></a></li>
    <li><a href="index.php?p=admin_genres" class="nav-link"> <i class='bx bxs-category'></i><span>Loại phim</span> </a></li>
    <li>
  <a href="index.php?p=admin_ranking" class="nav-link">
    <i class='bx bx-bar-chart-alt-2'></i>
    <span>Bảng xếp hạng</span>
  </a>
</li>
    <li><a href="index.php?p=admin_showtimes" class="nav-link"><i class='bx bxs-movie'></i><span>Suất chiếu</span></a></li>
    <li><a href="index.php?p=admin_sched" class="nav-link"><i class='bx bx-time-five'></i><span>Lịch chiếu</span></a></li>
    <li><a href="index.php?p=admin_tickets" class="nav-link"><i class='bx bx-receipt'></i><span>Vé</span></a></li>
    <li>
      <a href="index.php?p=admin_payments" class="nav-link">
        <i class='bx bx-credit-card'></i>
        <span>Hóa đơn</span>
      </a>
    </li>
    <li><a href="index.php?p=admin_combos" class="nav-link">
      <i class='bx bxs-drink'></i><span>Combo</span>
    </a></li>

    <li><a href="index.php?p=admin_users" class="nav-link"><i class='bx bxs-group'></i><span>Người dùng</span></a></li>
    <li><a href="index.php?p=admin_revenue" class="nav-link"><i class='bx bxs-bank'></i><span>Doanh thu</span></a></li>
  </ul>

  <div class="sidebar-footer">
    <?php
if (isset($_POST['logout_now'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<form method="post">
  <button type="submit" name="logout_now" class="logout-btn">
    <i class='bx bx-log-out'></i><span>Đăng xuất</span>
  </button>
</form>
  </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.card');
  cards.forEach((card, i) => {
    card.style.animationDelay = `${i * 0.1}s`; // 100ms mỗi thẻ
  });
}); </script>