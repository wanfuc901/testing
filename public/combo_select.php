<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config/config.php';

$showtime_id = intval($_POST['showtime_id'] ?? 0);
$seats = trim($_POST['seats'] ?? '');
if (!$showtime_id || !$seats) die("Thiếu dữ liệu đặt vé.");

$_SESSION['temp_booking'] = [
  'showtime_id' => $showtime_id,
  'seats' => $seats
];

$combos = $conn->query("SELECT * FROM combos WHERE active=1");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Chọn Combo Bắp Nước · VinCine</title>
<link rel="stylesheet" href="public/assets/css/style.css">
<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">
</head>
<body>

<main class="combo-page">
  <div class="combo-header">
    <h1><i class="bi bi-cup-straw"></i> Chọn Combo Bắp Nước</h1>
    <p>Bạn có thể chọn nhiều combo cùng lúc.</p>
  </div>

  <form method="post" action="index.php?p=cbp" class="combo-form">
    <div class="combo-list">
      <?php while($c = $combos->fetch_assoc()): ?>
      <div class="combo-item">
        <input type="checkbox" name="combo_id[]" value="<?= $c['combo_id'] ?>">
        <div class="combo-card">
          <div class="combo-thumb">
            <img src="app/views/combos/<?= htmlspecialchars($c['image'] ?: 'default.png') ?>" alt="">
          </div>
          <div class="combo-info">
            <h3><i class="bi bi-box2-heart"></i> <?= htmlspecialchars($c['name']) ?></h3>
            <p><?= htmlspecialchars($c['description']) ?></p>
            <div class="combo-price"><i class="bi bi-cash-coin"></i> <?= number_format($c['price']) ?>₫</div>
            <div class="qty-control" data-price="<?= $c['price'] ?>">
              <button type="button" class="qty-btn minus">−</button>
              <span class="qty-value">0</span>
              <button type="button" class="qty-btn plus">+</button>
              <input type="hidden" name="combo_qty[<?= $c['combo_id'] ?>]" value="0">
            </div>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <div class="combo-footer">
      <p class="total-display">Tổng cộng: <span id="totalCombo">0₫</span></p>
      <button type="submit" class="btn primary">
        <i class="bi bi-arrow-right-circle-fill"></i> Tiếp tục thanh toán
      </button>
    </div>
  </form>
</main>

<script>
const combos = document.querySelectorAll('.qty-control');
function format(v){return v.toLocaleString('vi-VN')+'₫';}

combos.forEach(c=>{
  const minus = c.querySelector('.minus');
  const plus  = c.querySelector('.plus');
  const valEl = c.querySelector('.qty-value');
  const hidden = c.querySelector('input[type="hidden"]');
  const checkbox = c.closest('.combo-item').querySelector('input[type="checkbox"]');

  plus.onclick = ()=>{ 
    let v = parseInt(valEl.textContent)||0;
    v++; valEl.textContent=v; hidden.value=v;
    if(v>0) checkbox.checked = true;
    calcTotal();
  };
  minus.onclick = ()=>{
    let v = parseInt(valEl.textContent)||0;
    if(v>0){v--; valEl.textContent=v; hidden.value=v;}
    if(v===0) checkbox.checked=false;
    calcTotal();
  };
});

function calcTotal(){
  let total=0;
  document.querySelectorAll('.combo-item').forEach(item=>{
    const c = item.querySelector('.qty-control');
    const qty=parseInt(c.querySelector('.qty-value').textContent)||0;
    const price=parseFloat(c.dataset.price);
    total+=qty*price;
  });
  document.getElementById('totalCombo').textContent=format(total);
}
</script>

</body>
</html>
