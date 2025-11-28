<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . "/../app/config/config.php";

if (!isset($_SESSION['pending_order'])) {
    die("Không có đơn hàng pending.");
}

$order = $_SESSION['pending_order'];

/* ============================
   GHẾ
============================ */
$seatLabels = $order['seat_labels'] ?? [];

if (empty($seatLabels)) {
    $seatLabels = [];
    if (!empty($order['seat_ids'])) {
        $ids = implode(',', array_map('intval', $order['seat_ids']));
        $q = $conn->query("SELECT row_number, col_number FROM seats WHERE seat_id IN ($ids)");
        while ($s = $q->fetch_assoc()) {
            $seatLabels[] = chr(64 + $s['row_number']) . $s['col_number'];
        }
    }
}

$seatText = implode(', ', $seatLabels);

/* ============================
   COMBO
============================ */
$combos = $order['combos'] ?? [];
$totalCombo = 0;

foreach ($combos as $c) {
    $totalCombo += floatval($c['total']);
}

$ticketAmount = floatval($order['ticket_total'] ?? 0);
$amount       = $ticketAmount + $totalCombo;

/* ============================
   BANK INFO
============================ */
$orderCode       = $order['order_code'];
$description     = "Thanh toan hoa don {$orderCode}";
$account_name    = "PHAM HOANG PHUC";
$account_number  = "0944649923";
$bank_code       = "970422"; // MBBank
$transaction_id  = "VC" . time();

/* ============================
   TẠO MÃ QR
============================ */

$qrDataUrl = null;

// InfinityFree BLOCK outbound CURL → tạo fallback QR
$qrDataUrl = "https://img.vietqr.io/image/{$bank_code}-{$account_number}-compact.png?amount={$amount}&addInfo={$description}";

/* ============================
   LƯU SESSION
============================ */
$_SESSION['bank_txn'] = [
    'transaction_id' => $transaction_id,
    'order_code'     => $orderCode,
    'amount'         => $amount,
    'seat_labels'    => $seatLabels,
    'order'          => $order
];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Thanh toán vé phim</title>
<style>
body{background:#0d0d0d;color:#f1f1f1;font-family:'Poppins',sans-serif;margin:0;}
.payment-page{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.payment-card{max-width:520px;width:100%;background:#1b1b1b;border-radius:18px;padding:28px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.6);}
h2{color:#f5c518;font-size:24px;margin-bottom:20px;}
.info-row{display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.1);padding:10px 0;font-size:15px;}
.label{color:#aaa;} 
.amount{color:#f33;font-weight:700;}
.note{color:#ccc;font-style:italic;word-break:break-word;max-width:280px;text-align:right;}
.qr-box{margin:24px 0;}
.qr-frame{display:inline-block;padding:16px;background:#fff;border-radius:12px;}
.qr-frame img{max-width:220px;display:block;}
.btn-confirm{margin-top:20px;width:100%;padding:12px;border:none;border-radius:999px;font-weight:700;background:#f33;color:#fff;font-size:16px;cursor:pointer;transition:.2s;}
.btn-confirm:hover{background:#c11;transform:translateY(-2px);}
</style>
</head>
<body>
<div class="payment-page">
  <div class="payment-card">
    <h2>Thanh toán chuyển khoản</h2>

    <div class="bank-info">
      <div class="info-row"><span class="label">Mã hóa đơn</span><span><?=htmlspecialchars($orderCode)?></span></div>
      <div class="info-row"><span class="label">Người nhận</span><span><?=$account_name?></span></div>
      <div class="info-row"><span class="label">Số tài khoản</span><span><?=$account_number?></span></div>
      <div class="info-row"><span class="label">Ngân hàng</span><span><?=$bank_code?></span></div>
      <div class="info-row"><span class="label">Ghế</span><span><?=$seatText?></span></div>

      <?php foreach($combos as $c): ?>
        <div class="info-row">
          <span class="label"><?= htmlspecialchars($c['name']) ?></span>
          <span><?= $c['qty'] ?> × <?= number_format($c['price']) ?>đ</span>
        </div>
      <?php endforeach; ?>

      <div class="info-row"><span class="label">Tổng tiền</span><span class="amount"><?=number_format($amount)?> VND</span></div>
      <div class="info-row"><span class="label">Nội dung CK</span><span class="note"><?=$description?></span></div>
    </div>

    <div class="qr-box">
      <p>Quét mã QR để thanh toán</p>
      <div class="qr-frame">
          <img src="<?=$qrDataUrl?>" alt="VietQR">
      </div>
    </div>

    <form method="POST" action="../app/controllers/payment_callback.php">
      <input type="hidden" name="transaction_id" value="<?=htmlspecialchars($transaction_id)?>">
      <button type="submit" name="action" value="simulate_success" class="btn-confirm">
        Xác nhận đã chuyển khoản
      </button>
    </form>

  </div>
</div>

<script>
let timeLeft = 300;
const box = document.createElement("div");
box.style.cssText="margin-top:18px;font-size:17px;font-weight:600;color:#f5c518;letter-spacing:1px;";
document.querySelector(".payment-card").appendChild(box);

function tick(){
    const m = String(Math.floor(timeLeft/60)).padStart(2,"0");
    const s = String(timeLeft%60).padStart(2,"0");
    box.innerHTML = "Thời gian thanh toán: "+m+":"+s;
    if(timeLeft <= 0){ cancelOrder(); return; }
    timeLeft--;
}
function cancelOrder(){
    fetch("../app/controllers/payment_cancel.php",{method:"POST"})
    .then(()=>window.location.href="../../index.php?timeout=1");
}
setInterval(tick,1000);
tick();
</script>

</body>
</html>
