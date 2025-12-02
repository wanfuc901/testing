<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . "/../../config/config.php";

$conn->set_charset("utf8mb4");
date_default_timezone_set("Asia/Ho_Chi_Minh");

/* ============================
   LẤY payment_id
============================ */
$payment_id = intval($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) die("payment_id không hợp lệ");

/* ============================
   LẤY PAYMENT
============================ */
$stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) die("Không tìm thấy hóa đơn");

$data = json_decode($pay['order_data'], true);
if (!$data) die("order_data lỗi hoặc trống");

/* ============================
   GHẾ
============================ */
$seatLabels = [];
foreach ($data['seats'] as $sid) {
    $stmtS = $conn->prepare("SELECT row_number, col_number FROM seats WHERE seat_id=?");
    $stmtS->bind_param("i", $sid);
    $stmtS->execute();
    $row = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();

    if ($row) {
        $seatLabels[] = chr(64 + intval($row['row_number'])) . intval($row['col_number']);
    }
}
$seatText = implode(", ", $seatLabels);

/* ============================
   COMBO
============================ */
$combos = $data['combos'] ?? [];
$combo_total = (float)($data['combo_total'] ?? 0);

$ticket_total = (float)$data['ticket_total'];
$total_amount = (float)$data['total_amount'];

/* ============================
   BANK INFO
============================ */
$orderCode       = $pay['provider_txn_id'];
$description     = "Thanh toan ma don {$orderCode}";
$account_name    = "PHAM HOANG PHUC";
$account_number  = "0944649923";
$bank_code       = "970422"; // MBBank

/* ============================
   QR
============================ */
$qrUrl = "https://img.vietqr.io/image/{$bank_code}-{$account_number}-compact.png?"
       . "amount={$total_amount}&addInfo=" . urlencode($description);

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Thanh toán hóa đơn <?=htmlspecialchars($orderCode)?></title>

<style>
/* --- DÙNG CSS ĐẸP BẠN GỬI --- */
html,body{height:100%;background:#0d0d0d;margin:0;font-family:'Poppins',sans-serif;color:#eee}
.payment-page{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#1b1b1b;border-radius:16px;padding:28px;max-width:520px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.6)}
h2{color:#f5c518;font-size:24px;margin:0 0 20px}
.info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12)}
.label{color:#aaa}
.amount{color:#f33;font-weight:700}
.qr-frame{background:#fff;padding:16px;border-radius:12px;margin:20px 0;text-align:center}
.qr-frame img{width:220px}
.btn{width:100%;padding:12px;border:none;border-radius:999px;background:#e50914;color:#fff;font-weight:700;cursor:pointer;font-size:16px;margin-top:12px}
.btn:hover{background:#b20710}
</style>

</head>
<body>

<div class="payment-page">
  <div class="card">

    <h2>Thanh toán chuyển khoản</h2>

    <div class="info-row"><span class="label">Mã hóa đơn</span><span><?=htmlspecialchars($orderCode)?></span></div>
    <div class="info-row"><span class="label">Người nhận</span><span><?=$account_name?></span></div>
    <div class="info-row"><span class="label">Số tài khoản</span><span><?=$account_number?></span></div>
    <div class="info-row"><span class="label">Ngân hàng</span><span><?=$bank_code?></span></div>
    <div class="info-row"><span class="label">Ghế</span><span><?=$seatText?></span></div>

    <?php foreach($combos as $c): ?>
        <div class="info-row">
            <span class="label"><?=htmlspecialchars($c['name'])?></span>
            <span><?=$c['qty']?> × <?=number_format($c['price'])?>đ</span>
        </div>
    <?php endforeach; ?>

    <div class="info-row">
        <span class="label">Tổng cộng</span>
        <span class="amount"><?=number_format($total_amount)?>đ</span>
    </div>

    <div class="info-row">
        <span class="label">Nội dung CK</span>
        <span><?=htmlspecialchars($description)?></span>
    </div>

    <div class="info-row" style="justify-content:center; border-bottom:none; margin-top:10px;">
    <span class="label">Thời gian thanh toán còn:</span>
    <span id="countdown" style="color:#f33; font-weight:700; margin-left:6px;">05:00</span>
    </div>


    <div class="qr-frame">
      <img src="<?=$qrUrl?>" alt="QR Code">
    </div>

    <form method="post" action="../../../app/controllers/payment_callback.php">
      <input type="hidden" name="payment_id" value="<?=$payment_id?>">
      <button class="btn" name="action" value="simulate_success">Xác nhận đã chuyển khoản</button>
    </form>

  </div>
</div>


<script>
let remaining = 300; // 5 phút

const countdownEl = document.getElementById("countdown");

function updateCountdown() {
    let m = Math.floor(remaining / 60);
    let s = remaining % 60;

    countdownEl.textContent =
        (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);

    if (remaining <= 0) {
        autoCancelPayment();
    } else {
        remaining--;
        setTimeout(updateCountdown, 1000);
    }
}

updateCountdown();

/* ============================
   GỬI AJAX HỦY ĐƠN
============================ */
function autoCancelPayment() {

    fetch("../../../app/controllers/payment_callback.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "payment_id=<?= $payment_id ?>&action=auto_cancel"
    })
    .then(res => res.text())
    .then(() => {
        alert("Hóa đơn đã hết hạn và bị hủy tự động.");
        window.location.href = "../../../index.php?p=home";
    })
    .catch(() => {
        alert("Không thể hủy hóa đơn. Vui lòng thử lại.");
    });
}
</script>


</body>
</html>
