<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../config/config.php';

// ===== LẤY SESSION TẠM ĐẶT VÉ =====
$temp = $_SESSION['temp_booking'] ?? null;
if (!$temp) die("Phiên đặt vé không hợp lệ.");

// ===== DỮ LIỆU COMBO NGƯỜI DÙNG CHỌN =====
$combo_ids  = $_POST['combo_id'] ?? [];
$combo_qtys = $_POST['combo_qty'] ?? [];

$comboList  = [];
$totalCombo = 0;

// ===== NẾU CÓ CHỌN COMBO =====
if (!empty($combo_ids)) {
    foreach ($combo_ids as $id) {
        $id = intval($id);
        $qty = intval($combo_qtys[$id] ?? 0);
        if ($qty <= 0) continue;

        $stmt = $conn->prepare("SELECT name, price FROM combos WHERE combo_id=? AND active=1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$c) continue;

        $comboList[] = [
            'id'    => $id,
            'name'  => $c['name'],
            'price' => floatval($c['price']),
            'qty'   => $qty,
            'total' => $c['price'] * $qty
        ];
        $totalCombo += $c['price'] * $qty;
    }
}

// ===== CẬP NHẬT SESSION TẠM =====
$_SESSION['temp_booking']['combos'] = $comboList;
$_SESSION['temp_booking']['combo_total'] = $totalCombo;

// ===== CHUYỂN SANG TRANG THANH TOÁN =====
$showtime_id = intval($temp['showtime_id'] ?? 0);
$seats       = trim($temp['seats'] ?? '');

if (!$showtime_id || !$seats) die("Thiếu dữ liệu đặt vé.");

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đang chuyển đến thanh toán...</title>
<style>
body{font-family:sans-serif;background:#0d0d0d;color:#f1f1f1;display:flex;align-items:center;justify-content:center;height:100vh;}
.box{padding:20px 40px;border-radius:12px;background:#1b1b1b;box-shadow:0 6px 20px rgba(0,0,0,.4);text-align:center;}
</style>
</head>
<body>
<div class="box">
  <p>Đang chuyển đến trang thanh toán...</p>
  <form id="redirectForm" method="post" action="index.php?p=ck">
    <input type="hidden" name="showtime_id" value="<?= htmlspecialchars($showtime_id) ?>">
    <input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
  </form>
</div>

<script>
setTimeout(()=>{document.getElementById('redirectForm').submit();},500);
</script>
</body>
</html>
