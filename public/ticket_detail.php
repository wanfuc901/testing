<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . '/../app/config/config.php';

/* ============================================================
   1. KIỂM TRA ĐĂNG NHẬP (USER HOẶC CUSTOMER)
============================================================ */
if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'user', 'customer'])
) {
    header("Location: index.php?p=login");
    exit;
}

/* Lấy ID người dùng đúng bảng */
$viewer_id = ($_SESSION['role'] === 'customer')
    ? ($_SESSION['customer_id'] ?? 0)
    : ($_SESSION['user_id'] ?? 0);


/* ============================================================
   2. LẤY ticket_id
============================================================ */
if (!isset($_GET['ticket_id'])) {
    die("Thiếu mã vé.");
}

$ticket_id = (int)$_GET['ticket_id'];


/* ============================================================
   3. LẤY THÔNG TIN VÉ — KIỂM TRA THEO customer_id
============================================================ */
$sql = "
    SELECT 
        t.ticket_id,
        t.price,
        t.status,
        t.booked_at,
        t.payment_id,
        s.row_number,
        s.col_number,
        m.title,
        m.genre,
        m.duration,
        m.poster_url,
        sh.start_time,
        sh.end_time,
        r.name AS room_name
    FROM tickets t
    JOIN showtimes sh ON t.showtime_id = sh.showtime_id
    JOIN movies m ON sh.movie_id = m.movie_id
    JOIN seats s ON t.seat_id = s.seat_id
    JOIN rooms r ON sh.room_id = r.room_id
    WHERE t.ticket_id = ? 
      AND t.customer_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ticket_id, $viewer_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die("<p style='color:red;text-align:center;margin-top:40px;'>❌ Vé không tồn tại hoặc bạn không có quyền xem!</p>");
}

/* ============================================================
   4. POSTER LOCAL
============================================================ */
$poster_path = !empty(trim($ticket['poster_url']))
    ? "../app/views/banners/" . htmlspecialchars(trim($ticket['poster_url']))
    : "assets/img/no-poster.png";


/* ============================================================
   5. QR PAYMENT NẾU VÉ ĐANG PENDING
============================================================ */
$qrBlock = "";

if ($ticket['status'] === 'pending' && !empty($ticket['payment_id'])) {

    $pid = (int)$ticket['payment_id'];

    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($pay) {

        $data = json_decode($pay['order_data'], true);

        // danh sách ghế
        $seatLabels = [];
        foreach ($data['seats'] as $sid) {
            $q = $conn->prepare("SELECT row_number, col_number FROM seats WHERE seat_id=?");
            $q->bind_param("i", $sid);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();

            if ($r) {
                $seatLabels[] = chr(64 + intval($r['row_number'])) . intval($r['col_number']);
            }
        }
        $seatText = implode(", ", $seatLabels);

        // Bank info
        $orderCode      = $pay['provider_txn_id'];
        $description    = "Thanh toan ma don {$orderCode}";
        $account_name   = "PHAM HOANG PHUC";
        $account_number = "0944649923";
        $bank_code      = "970422"; 
        $total_amount   = $data['total_amount'];

        // QR
        $qrUrl = "https://img.vietqr.io/image/{$bank_code}-{$account_number}-compact.png?"
               . "amount={$total_amount}&addInfo=" . urlencode($description);

        $qrBlock = "
        <div class='qr-box'>
            <h3>Quét QR để thanh toán</h3>
            <p style='color:#bbb;margin-top:-6px;'>Hiện hóa đơn chưa được xác nhận.</p>

            <p><strong>Mã đơn:</strong> {$orderCode}</p>
            <p><strong>Ghế:</strong> {$seatText}</p>
            <p><strong>Nội dung CK:</strong> {$description}</p>

            <div class='qr-frame'>
              <img src='{$qrUrl}' alt='QR CODE'>
            </div>
        </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chi tiết vé - VinCine</title>

  <!-- CSS LOCAL -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/bootstrap-icons/bootstrap-icons.css">

  <style>
    .qr-box{
        background:#111;
        padding:20px;
        border-radius:14px;
        color:#eee;
        margin-top:25px;
        text-align:center;
    }
    .qr-frame{
        background:#fff;
        padding:15px;
        border-radius:10px;
        margin:15px auto;
        width: fit-content;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .qr-frame img{
        width:220px;
        display:block;
        margin:auto;
    }
  </style>
</head>
<body>

<div class="checkout-wrapper">
  <h2><i class="bi bi-ticket-perforated"></i> Chi tiết vé đã đặt</h2>

  <div class="movie-detail" style="align-items:flex-start;">
    <div class="poster">
      <img src="<?= $poster_path ?>" alt="<?= htmlspecialchars($ticket['title']) ?>">
    </div>

    <div class="info">
      <h1><?= htmlspecialchars($ticket['title']) ?></h1>

      <p><strong>Thể loại:</strong> <?= htmlspecialchars($ticket['genre']) ?></p>

      <p><strong>Thời lượng:</strong> <?= htmlspecialchars($ticket['duration']) ?> phút</p>

      <p><strong>Phòng chiếu:</strong> <?= htmlspecialchars($ticket['room_name']) ?></p>

      <p><strong>Thời gian:</strong>
        <?= date("d/m/Y H:i", strtotime($ticket['start_time'])) ?> -
        <?= date("H:i", strtotime($ticket['end_time'])) ?>
      </p>

      <p><strong>Ghế:</strong>
        H<?= $ticket['row_number'] ?>C<?= $ticket['col_number'] ?>
      </p>

      <p><strong>Giá vé:</strong>
        <?= number_format($ticket['price'], 0, ',', '.') ?> ₫
      </p>

      <p><strong>Đặt lúc:</strong>
        <?= date("d/m/Y H:i", strtotime($ticket['booked_at'])) ?>
      </p>

      <p><strong>Trạng thái:</strong>
        <?php
        if ($ticket['status'] === 'confirmed') echo "<span style='color:#2ecc71;'>Đã xác nhận</span>";
        elseif ($ticket['status'] === 'paid') echo "<span style='color:#3498db;'>Đã thanh toán</span>";
        elseif ($ticket['status'] === 'pending') echo "<span style='color:#f39c12;'>Chờ thanh toán</span>";
        else echo "<span style='color:#e74c3c;'>Đã hủy</span>";
        ?>
      </p>
    </div>
  </div>

  <?= $qrBlock ?>

  <div style="text-align:center;margin-top:30px;">
    <button onclick="history.back()" class="btn-confirm" style="width:auto;padding:12px 32px;">
        <i class="bi bi-arrow-left"></i> Quay lại
    </button>
  </div>
</div>

</body>
</html>
