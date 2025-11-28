<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../app/config/config.php";

/* ===== Hiển thị & ghi log lỗi khi dev ===== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logDir = __DIR__ . '/../app/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/checkout_' . date('Ymd') . '.log');

/* ===== Kiểm tra phương thức ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Truy cập không hợp lệ.");

/* ===== Dữ liệu đầu vào ===== */
$showtime_id = intval($_POST['showtime_id'] ?? 0);
$seats       = trim($_POST['seats'] ?? '');
if (!$showtime_id || $seats === '') die("Thiếu dữ liệu đặt vé.");

/* ===== Khởi tạo session tạm (temp_booking) ===== */
if (empty($_SESSION['temp_booking'])) {
    $_SESSION['temp_booking'] = [
        'showtime_id' => $showtime_id,
        'seats'       => $seats,
        'combos'      => [],
        'combo_total' => 0
    ];
}

/* ===== Xử lý dữ liệu ===== */
$seatArr     = array_values(array_filter(array_map('intval', explode(',', $seats))));
$ticketCount = count($seatArr);

/* ===== Giá vé ===== */
$stmt = $conn->prepare("
  SELECT m.ticket_price
  FROM movies m
  JOIN showtimes s ON m.movie_id = s.movie_id
  WHERE s.showtime_id = ?
");
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$stmt->bind_result($ticketPrice);
$stmt->fetch();
$stmt->close();

if (!$ticketPrice) $ticketPrice = 80000;
$totalTicket = $ticketCount * $ticketPrice;

/* ===== Combo từ session ===== */
$combos      = $_SESSION['temp_booking']['combos'] ?? [];
$totalCombo  = floatval($_SESSION['temp_booking']['combo_total'] ?? 0);
$totalAll    = $totalTicket + $totalCombo;

/* ===== Lấy thông tin suất chiếu ===== */
$sql = "SELECT s.start_time, s.end_time, m.title, m.poster_url, r.name AS room_name
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.room_id
        JOIN movies m ON s.movie_id = m.movie_id
        WHERE s.showtime_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$showtime = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$showtime) die("Không tìm thấy suất chiếu.");

/* ===== Tự động dò tên cột ghế ===== */
$colNames = [];
$resCols = $conn->query("SHOW COLUMNS FROM seats");
while ($r = $resCols->fetch_assoc()) $colNames[] = $r['Field'];

$rowCol = in_array('row_number', $colNames) ? 'row_number' :
         (in_array('row', $colNames) ? 'row' :
         (in_array('seat_row', $colNames) ? 'seat_row' : null));

$colCol = in_array('col_number', $colNames) ? 'col_number' :
         (in_array('col', $colNames) ? 'col' :
         (in_array('seat_col', $colNames) ? 'seat_col' : null));

if (!$rowCol || !$colCol) die("Không xác định được cột ghế trong bảng seats.");

/* ===== Lấy nhãn ghế ===== */
$seatLabels = [];
if (!empty($seatArr)) {
    $q = $conn->prepare("SELECT `$rowCol` AS rownum, `$colCol` AS colnum FROM seats WHERE seat_id = ?");
    foreach ($seatArr as $sid) {
        $q->bind_param("i", $sid);
        $q->execute();
        $res = $q->get_result();
        if ($row = $res->fetch_assoc()) {
            $rowChar = chr(64 + intval($row['rownum']));
            $seatLabels[] = $rowChar . intval($row['colnum']);
        }
    }
    $q->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Xác nhận & Thanh toán - <?= htmlspecialchars($showtime['title']) ?></title>

  <!-- FIX: KHÔNG được để public/... -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/bootstrap-icons/bootstrap-icons.css">

  <style>

  </style>
</head>
<body>

<div class="checkout-layout">
  <div class="checkout-wrapper">
    <h2><i class="bi bi-ticket-detailed"></i> Xác nhận đặt vé</h2>

    <p><strong>Phim:</strong> <?= htmlspecialchars($showtime['title']) ?></p>
    <p><strong>Phòng:</strong> <?= htmlspecialchars($showtime['room_name']) ?></p>
    <p><strong>Suất chiếu:</strong>
       <?= date("H:i", strtotime($showtime['start_time'])) ?> - <?= date("H:i", strtotime($showtime['end_time'])) ?></p>
    <p><strong>Ghế:</strong> <?= $seatLabels ? implode(", ", $seatLabels) : "-" ?></p>
    <p><strong>Số vé:</strong> <?= $ticketCount ?></p>
    <p><strong>Tiền vé:</strong> <?= number_format($totalTicket) ?> đ</p>

    <?php if (!empty($combos)): ?>
      <hr>
      <h3>Combo đã chọn</h3>
      <ul>
        <?php foreach($combos as $c): ?>
          <li><?= htmlspecialchars($c['name']) ?> × <?= intval($c['qty']) ?> — <?= number_format($c['total']) ?>đ</li>
        <?php endforeach; ?>
      </ul>
      <p><strong>Tổng combo:</strong> <?= number_format($totalCombo) ?>đ</p>
    <?php endif; ?>

    <hr style="margin:18px 0;border-color:rgba(255,255,255,.1)">
    <p class="total"><strong>Tổng cộng:</strong> <span><?= number_format($totalAll) ?> đ</span></p>

    <h3><i class="bi bi-credit-card-2-front"></i> Chọn phương thức thanh toán</h3>

    <form method="post" action="./app/controllers/checkout_online.php">
      <input type="hidden" name="showtime_id" value="<?= $showtime_id ?>">
      <input type="hidden" name="seats" value="<?= htmlspecialchars($seats, ENT_QUOTES) ?>">
      <input type="hidden" name="total_all" value="<?= htmlspecialchars($totalAll, ENT_QUOTES) ?>">

      <div class="payment-methods">
        <label><input type="radio" name="payment_method" value="momo" required> Ví MoMo</label>
        <label><input type="radio" name="payment_method" value="zalopay"> ZaloPay</label>
        <label><input type="radio" name="payment_method" value="vnpay"> VNPAY</label>
        <label><input type="radio" name="payment_method" value="bank"> Thẻ ngân hàng</label>
        <label><input type="radio" name="payment_method" value="cash"> Thanh toán tại quầy</label>
      </div>

      <button type="submit" class="btn-confirm">
        <i class="bi bi-check2-circle"></i> Xác nhận & Thanh toán
      </button>
    </form>
  </div>
</div>

</body>
</html>
