<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../app/config/config.php";

/* ============================================================
   1. KIỂM TRA ĐĂNG NHẬP (CHO PHÉP CUSTOMER + USER + ADMIN)
============================================================ */
if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['customer', 'user', 'admin'])
) {
    header("Location: index.php?p=login");
    exit;
}

/* 
   LẤY ID NGƯỜI DÙNG THEO ROLE
   - admin/user dùng user_id
   - customer dùng customer_id
*/
$isCustomer = ($_SESSION['role'] === 'customer');
$customer_id = $isCustomer ? ($_SESSION['customer_id'] ?? 0) : 0;

/* Nếu là admin/user → không có customer_id → không hiển thị lịch sử vé */
if ($isCustomer && $customer_id <= 0) {
    die("<p style='color:red;text-align:center;margin-top:20px;'>Lỗi: Session không hợp lệ!</p>");
}

/* ============================================================
   2. LẤY THÔNG TIN NGƯỜI DÙNG
============================================================ */
if ($isCustomer) {
    // Customer
    $stmt = $conn->prepare("SELECT fullname AS name, email, created_at FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
} else {
    // User/Admin
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT name, email, created_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* ============================================================
   3. PHÂN TRANG CHO CUSTOMER
============================================================ */
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$total = 0;
$tickets = null;

if ($isCustomer) {

    // Đếm vé
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM tickets WHERE customer_id = ?");
    $countStmt->bind_param("i", $customer_id);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    $total_pages = max(1, ceil($total / $limit));

    // Lấy danh sách vé
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
        WHERE t.customer_id = ?
        ORDER BY t.booked_at DESC
        LIMIT ?, ?
    ";

    $stmt2 = $conn->prepare($sqlTickets);
    $stmt2->bind_param("iii", $customer_id, $offset, $limit);
    $stmt2->execute();
    $tickets = $stmt2->get_result();
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tài khoản - VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
  <link rel="stylesheet" href="public/assets/boxicons-free/free/fonts/basic/boxicons.min.css">
</head>

<body>

<div class="account-wrapper container">

  <div class="profile-header">
    <div class="profile-info">
      <h1><i class='bx bxs-user-circle'></i> <?= htmlspecialchars($user['name']) ?></h1>
      <p>📧 <?= htmlspecialchars($user['email']) ?></p>

      <?php if ($isCustomer): ?>
        <p>🕓 Thành viên từ: <?= date("d/m/Y", strtotime($user['created_at'])) ?></p>
      <?php endif; ?>

      <form method="post" action="index.php?p=logout">
        <button class="logout-btn"><i class='bx bx-log-out'></i> Đăng xuất</button>
      </form>
    </div>
  </div>

  <?php if ($isCustomer): ?>
  <h2 style="color:var(--gold);margin:20px 0;">
    <i class='bx bxs-ticket'></i> Lịch sử đặt vé
  </h2>

  <?php if ($tickets && $tickets->num_rows > 0): ?>
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
          <?= $t['channel'] === 'online'
              ? "<span class='channel-online'><i class='bx bx-globe'></i> Online</span>"
              : "<span class='channel-offline'><i class='bx bxs-store'></i> Tại quầy</span>" ?>
        </td>

        <td>
          <?php
            if ($t['status'] == 'confirmed') echo "<span class='status-confirmed'><i class='bx bx-check-circle'></i> Đã xác nhận</span>";
            elseif ($t['status'] == 'pending') echo "<span class='status-pending'><i class='bx bx-time-five'></i> Chờ xử lý</span>";
            elseif ($t['status'] == 'paid') echo "<span class='status-confirmed'><i class='bx bx-credit-card'></i> Đã thanh toán</span>";
            else echo "<span class='status-cancelled'><i class='bx bx-x-circle'></i> Đã hủy</span>";
          ?>
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
  <?php endif; ?>

</div>

</body>
</html>
