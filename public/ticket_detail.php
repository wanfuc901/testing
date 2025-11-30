<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . '/../app/config/config.php';

// Nếu chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Lấy ticket_id
if (!isset($_GET['ticket_id'])) {
    die("Thiếu mã vé.");
}

$ticket_id = (int)$_GET['ticket_id'];
$user_id   = $_SESSION['user_id'];

/* ============================================================
   LẤY THÔNG TIN VÉ
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
    WHERE t.ticket_id = ? AND t.user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ticket_id, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die("<p style='color:red;text-align:center;margin-top:40px;'>❌ Vé không tồn tại hoặc bạn không có quyền xem!</p>");
}

/* ============================================================
   POSTER LOCAL
============================================================ */
$poster_path = !empty(trim($ticket['poster_url']))
    ? "../app/views/banners/" . htmlspecialchars(trim($ticket['poster_url']))
    : "assets/img/no-poster.png";

/* ============================================================
   QR PAYMENT NẾU VÉ ĐANG PENDING
============================================================ */
$qrBlock = "";

if ($ticket['status'] === 'pending' && !empty($ticket['payment_id'])) {

    $pid = (int)$ticket['payment_id'];

    // lấy payment
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($pay) {

        $data = json_decode($pay['order_data'], true);

        // GHẾ
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

        // Tạo QR VietQR
        $qrUrl = "https://img.vietqr.io/image/{$bank_code}-{$account_number}-compact.png?"
               . "amount={$total_amount}&addInfo=" . urlencode($description);

        // HTML QR
        $qrBlock = "
    <div class='qr-box'>
        <h3><i class='bi bi-upc-scan'></i> Quét QR để hoàn tất thanh toán</h3>
        <p style='color:#bbb;margin-top:-6px;'>Hiện hóa đơn chưa được xác nhận.</p>

        <p><strong><i class='bi bi-receipt'></i> Mã đơn:</strong> {$orderCode}</p>
        <p><strong><i class='bi bi-grid-1x2'></i> Ghế:</strong> {$seatText}</p>
        <p><strong><i class='bi bi-bank'></i> Ngân hàng:</strong> {$bank_code}</p>
        <p><strong><i class='bi bi-pen'></i> Nội dung CK:</strong> {$description}</p>

        <div class='qr-frame'>
          <img src='{$qrUrl}' alt='QR CODE'>
        </div>
    </div>
";
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

  <!-- ICON LOCAL -->
  <link rel="stylesheet" href="assets/bootstrap-icons/bootstrap-icons.css">

  <style>
    .qr-box{
        background:#111;
        padding:20px;
        border-radius:14px;
        color:#eee;
        margin-top:25px;
        text-align:center; /* Căn giữa toàn bộ nội dung */
    }
    .qr-frame{
        background:#fff;
        padding:15px;
        border-radius:10px;
        margin:15px auto;        /* AUTO để căn giữa BOX */
        width: fit-content;       /* Khung QR gọn theo kích thước ảnh */
        display: flex;
        justify-content: center;  /* Căn giữa QR */
        align-items: center;
    }

    .qr-frame img{
        width:220px;
        display:block;
        margin:auto;
    }
    `

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

      <p><strong><i class="bi bi-film"></i> Thể loại:</strong> <?= htmlspecialchars($ticket['genre']) ?></p>

      <p><strong><i class="bi bi-stopwatch"></i> Thời lượng:</strong>
        <?= htmlspecialchars($ticket['duration']) ?> phút
      </p>

      <p><strong><i class="bi bi-camera-reels"></i> Phòng chiếu:</strong>
        <?= htmlspecialchars($ticket['room_name']) ?>
      </p>

      <p><strong><i class="bi bi-clock-history"></i> Thời gian:</strong>
        <?= date("d/m/Y H:i", strtotime($ticket['start_time'])) ?> -
        <?= date("H:i", strtotime($ticket['end_time'])) ?>
      </p>

      <p><strong><i class="bi bi-grid-1x2"></i> Ghế:</strong>
        H<?= $ticket['row_number'] ?>C<?= $ticket['col_number'] ?>
      </p>

      <p><strong><i class="bi bi-cash-stack"></i> Giá vé:</strong>
        <?= number_format($ticket['price'], 0, ',', '.') ?> ₫
      </p>

      <p><strong><i class="bi bi-calendar-check"></i> Đặt lúc:</strong>
        <?= date("d/m/Y H:i", strtotime($ticket['booked_at'])) ?>
      </p>

      <p><strong><i class="bi bi-receipt-cutoff"></i> Trạng thái:</strong>
        <?php
        if ($ticket['status'] === 'confirmed') echo "<span style='color:#2ecc71;font-weight:600;'>Đã xác nhận</span>";
        elseif ($ticket['status'] === 'paid') echo "<span style='color:#3498db;font-weight:600;'>Đã thanh toán</span>";
        elseif ($ticket['status'] === 'pending') echo "<span style='color:#f39c12;font-weight:600;'>Chờ thanh toán</span>";
        else echo "<span style='color:#e74c3c;font-weight:600;'>Đã hủy</span>";
        ?>
      </p>
    </div>
  </div>

  <!-- QR PAYMENT -->
  <?= $qrBlock ?>

  <div style="text-align:center;margin-top:30px;">
    <button onclick="history.back()" class="btn-confirm" style="width:auto;padding:12px 32px;">
        <i class="bi bi-arrow-left"></i> Quay lại
    </button>
  </div>
</div>

</body>
</html>
