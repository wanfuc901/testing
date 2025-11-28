<?php
// public/payment_online_momo.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Lấy gợi ý từ query (nếu có)
$invoice = isset($_GET['invoice']) ? strtoupper(trim($_GET['invoice'])) : '';
$amount  = (int)($_GET['amount'] ?? 0);
if (!preg_match('/^HD\d{6}$/', $invoice)) $invoice = '';

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>MoMo Sandbox · Chuyển khoản mô phỏng</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0d0d0f;--text:#f0f0f0;--card:#151518;--muted:#a9a9a9;--accent:#e50914}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
display:flex;align-items:center;justify-content:center;min-height:100vh}
.wrap{width:92%;max-width:580px;background:var(--card);border:1px solid #27272a;border-radius:16px;padding:22px}
h1{margin:0 0 8px;font-size:20px} p.note{color:var(--muted);margin:0 0 16px}
.row{display:flex;gap:12px} .col{flex:1}
label{display:block;font-size:13px;color:#c9c9c9;margin-bottom:6px}
input,textarea{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #2c2c2f;background:#111115;color:#fff}
textarea{min-height:90px;resize:vertical}
button{margin-top:14px;width:100%;padding:12px 16px;border:0;border-radius:12px;background:#8a2be2;color:#fff;font-weight:700;cursor:pointer}
.small{margin-top:8px;color:#bbb;font-size:12px}
.help{margin-top:10px;font-size:12px;color:#f5c518}
</style>
</head>
<body>
  <div class="wrap">
    <h1>MoMo Sandbox — Mô phỏng chuyển khoản</h1>
    <p class="note">Nhập thông tin giống khi bạn quét QR. Bấm “Gửi thanh toán” để đẩy xác nhận về hệ thống.</p>

    <form method="post" action="../app/controllers/payment_callback.php" onsubmit="return validate()">
      <input type="hidden" name="manual_momo" value="1">

      <div class="row">
        <div class="col">
          <label>Số tiền (VND)</label>
          <input type="number" name="amount" id="amount" min="1" required value="<?= $amount>0 ? (int)$amount : '' ?>">
        </div>
        <div class="col">
          <label>Mã giao dịch ngân hàng (tuỳ chọn)</label>
          <input type="text" name="txn_id" placeholder="VD: MOMO2025xxxx">
        </div>
      </div>

      <label style="margin-top:10px">Nội dung chuyển khoản <small>(phải chứa mã hóa đơn HDxxxxxx)</small></label>
      <textarea name="content" id="content" placeholder="VD: Thanh toan don hang #HD000123" required><?= $invoice ? "Thanh toan don hang #{$invoice}" : "" ?></textarea>

      <div class="row" style="margin-top:10px">
        <div class="col">
          <label>Thời gian (tuỳ chọn)</label>
          <input type="text" name="time" placeholder="YYYY-MM-DD HH:MM:SS">
        </div>
        <div class="col">
          <label>Gợi ý mã hoá đơn</label>
          <input type="text" readonly value="<?= htmlspecialchars($invoice ?: 'Chưa có') ?>">
        </div>
      </div>

      <button type="submit">Gửi thanh toán (Sandbox)</button>
      <div class="small">Sau khi gửi, quay lại tab chính và bấm “Kiểm tra trạng thái”.</div>
      <div class="help">Mẹo: dán chính xác <strong>HDxxxxxx</strong> vào nội dung để hệ thống nhận diện.</div>
    </form>
  </div>

<script>
function validate(){
  const c = document.getElementById('content').value || '';
  if(!/(HD\d{6})/i.test(c)){
    alert("Nội dung CK phải chứa mã hoá đơn dạng HDxxxxxx.");
    return false;
  }
  const a = document.getElementById('amount').valueAsNumber || 0;
  if(a <= 0){ alert("Số tiền phải > 0"); return false; }
  return true;
}
</script>
</body>
</html>
