<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . "/../app/config/config.php";

// ✅ Nếu chưa đăng nhập → chuyển về login
if (!isset($_SESSION['user_id'])) {
    header("Location: public/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ Lấy thông tin user
$stmt = $conn->prepare("SELECT name, email, role, created_at FROM users WHERE user_id = ?");
if (!$stmt) die("SQL lỗi: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ✅ Thiết lập phân trang
$limit = 10; // mỗi trang 10 vé
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ✅ Đếm tổng số vé
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tickets WHERE user_id = ?");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// ✅ Lấy danh sách vé (có ngày đặt)
$sqlTickets = "
    SELECT 
        t.ticket_id,
        m.title,
        CONCAT('H', s.row_number, '-C', s.col_number) AS seat_name,
        sh.start_time,
        t.price,
        t.status,
        t.channel,
        t.booked_at
    FROM tickets t
    JOIN showtimes sh ON t.showtime_id = sh.showtime_id
    JOIN movies m ON sh.movie_id = m.movie_id
    JOIN seats s ON t.seat_id = s.seat_id
    WHERE t.user_id = ?
    ORDER BY t.booked_at DESC
    LIMIT ?, ?
";
$stmt2 = $conn->prepare($sqlTickets);
if (!$stmt2) die("Lỗi prepare SQL: " . $conn->error);
$stmt2->bind_param("iii", $user_id, $offset, $limit);
$stmt2->execute();
$tickets = $stmt2->get_result();
?>

<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tài khoản - VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
  <link rel="stylesheet" href="public/assets/boxicons-free/free/fonts/basic/boxicons.min.css">
  <style>
    
  </style>
</head>
<body>

<div class="account-wrapper container">
  <div class="profile-header">
    <div class="profile-info">
      <h1><i class='bx bxs-user-circle'></i> <?= htmlspecialchars($user['name']) ?></h1>
      <p>📧 <?= htmlspecialchars($user['email']) ?></p>
      <p>🔑 Vai trò: <?= ($user['role'] === 'admin') ? 'Quản trị viên' : 'Khách hàng' ?></p>
      <p>🕓 Thành viên từ: <?= date("d/m/Y", strtotime($user['created_at'])) ?></p>
      <form method="post" action="index.php?p=logout">
        <button class="logout-btn"><i class='bx bx-log-out'></i> Đăng xuất</button>
      </form>
    </div>
  </div>

  <h2 style="color:var(--gold);margin:20px 0;"><i class='bx bxs-ticket'></i> Lịch sử đặt vé</h2>

  <?php if ($tickets->num_rows > 0): ?>
  <table class="account-table">
    <thead>
      <tr>
        <th>Mã vé</th>
        <th>Phim</th>
        <th>Ghế</th>
        <th>Suất chiếu</th>
        <th>Giá vé</th>
        <th>Hình thức</th>
        <th>Trạng thái</th>
        <th>Ngày đặt</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($t = $tickets->fetch_assoc()): ?>
      <tr>
        <td>#<?= $t['ticket_id'] ?></td>
        <td>
          <a class="movie-link" href="public/ticket_detail.php?ticket_id=<?= $t['ticket_id'] ?>">
            <?= htmlspecialchars($t['title']) ?>
          </a>
        </td>
        <td><?= htmlspecialchars($t['seat_name']) ?></td>
        <td><?= date("d/m/Y H:i", strtotime($t['start_time'])) ?></td>
        <td><?= number_format($t['price'], 0, ',', '.') ?>₫</td>
        <td>
          <?php if ($t['channel'] === 'online'): ?>
            <span class="channel-online"><i class='bx bx-globe'></i> Online</span>
          <?php else: ?>
            <span class="channel-offline"><i class='bx bxs-store'></i> Tại quầy</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($t['status'] == 'confirmed'): ?>
            <span class="status-confirmed"><i class='bx bx-check-circle'></i> Đã xác nhận</span>
          <?php elseif ($t['status'] == 'pending'): ?>
            <span class="status-pending"><i class='bx bx-time-five'></i> Chờ xử lý</span>
          <?php elseif ($t['status'] == 'paid'): ?>
            <span class="status-confirmed"><i class='bx bx-credit-card'></i> Đã thanh toán</span>
          <?php else: ?>
            <span class="status-cancelled"><i class='bx bx-x-circle'></i> Đã hủy</span>
          <?php endif; ?>
        </td>
        <td><?= date("d/m/Y H:i", strtotime($t['booked_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- PHÂN TRANG -->
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
     <a href="index.php?p=acc&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>

  <?php else: ?>
    <p style="color:#999;margin-top:10px">Bạn chưa có vé nào được đặt.</p>
  <?php endif; ?>
</div>

</body>
</html>
