<?php
/**
 * Trang QR thanh toán PayOS của một đơn hàng.
 *
 * Chỉ chủ đơn mới xem được — trang hiển thị số ghế, số tiền và mã QR.
 * Trạng thái thanh toán do webhook PayOS quyết định; trang này chỉ hỏi lại
 * mỗi vài giây để chuyển màn hình khi tiền đã vào.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../../helpers/payos.php';

/* ============================
   LẤY payment_id
============================ */
$payment_id = (int)($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    http_response_code(400);
    exit('payment_id không hợp lệ');
}

vincine_require_customer();
$customer_id = vincine_customer_id();

/* ============================
   LẤY PAYMENT CỦA CHÍNH MÌNH
============================ */
$stmt = $conn->prepare("
    SELECT payment_id, amount, status, order_data, provider_txn_id,
           payos_order_code, payos_payment_link_id, payos_qr_code,
           UNIX_TIMESTAMP(created_at) AS created_ts
    FROM payments
    WHERE payment_id = ? AND customer_id = ?
");
if (!$stmt) {
    error_log('[vincine] payment_qr prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Hệ thống đang bận. Vui lòng thử lại sau.');
}

$stmt->bind_param('ii', $payment_id, $customer_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) {
    http_response_code(404);
    exit('Không tìm thấy hóa đơn');
}

/* Đơn đã thanh toán xong thì không cần đứng ở trang QR nữa. */
if (in_array($pay['status'], ['paid', 'success'], true)) {
    header('Location: ../../../public/booking_success.php?pid=' . $payment_id);
    exit;
}

if ($pay['status'] === 'canceled') {
    http_response_code(410);
    exit('Hóa đơn này đã bị hủy. Vui lòng đặt lại vé.');
}

$data = json_decode((string)$pay['order_data'], true);
if (!is_array($data)) {
    error_log('[vincine] payment_qr: order_data hỏng ở đơn ' . $payment_id);
    http_response_code(500);
    exit('Dữ liệu đơn hàng không hợp lệ.');
}

/* ============================
   GHẾ
============================ */
$seatLabels = [];
$stmtSeat = $conn->prepare("SELECT `row_number`, `col_number` FROM seats WHERE seat_id = ?");
foreach (($data['seats'] ?? []) as $sid) {
    $sid = (int)$sid;
    $stmtSeat->bind_param('i', $sid);
    $stmtSeat->execute();
    $row = $stmtSeat->get_result()->fetch_assoc();

    if ($row) {
        $seatLabels[] = chr(64 + (int)$row['row_number']) . (int)$row['col_number'];
    }
}
$stmtSeat->close();

$seatText     = implode(', ', $seatLabels);
$combos       = $data['combos'] ?? [];
$ticket_total = (float)($data['ticket_total'] ?? 0);
$combo_total  = (float)($data['combo_total'] ?? 0);
$total_amount = (float)($data['total_amount'] ?? $pay['amount']);

$qrPayload  = (string)($pay['payos_qr_code'] ?? '');
$linkId     = (string)($pay['payos_payment_link_id'] ?? '');
$checkout   = $linkId !== '' ? 'https://pay.payos.vn/web/' . $linkId : '';

/* Thời gian còn lại của link thanh toán. */
$secondsLeft = max(0, ((int)$pay['created_ts'] + PAYOS_LINK_TTL) - time());

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán đơn <?= $e($pay['provider_txn_id']) ?></title>

<style>
html,body{height:100%;background:#0d0d0d;margin:0;font-family:'Poppins',system-ui,sans-serif;color:#eee}
.payment-page{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#1b1b1b;border-radius:16px;padding:28px;max-width:520px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.6)}
h2{color:#f5c518;font-size:24px;margin:0 0 4px}
.sub{color:#888;font-size:13px;margin:0 0 20px}
.info-row{display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12)}
.label{color:#aaa}
.amount{color:#f33;font-weight:700}
.qr-frame{background:#fff;padding:16px;border-radius:12px;margin:20px 0;text-align:center;min-height:252px;display:flex;align-items:center;justify-content:center}
.qr-frame canvas,.qr-frame img{display:block}
.qr-hint{text-align:center;color:#aaa;font-size:13px;margin:-10px 0 16px}
.countdown{text-align:center;font-size:17px;font-weight:600;color:#f5c518;letter-spacing:1px;margin:8px 0 4px}
.countdown.urgent{color:#f33}
.state{text-align:center;font-size:14px;margin:12px 0 0;min-height:20px}
.state.waiting{color:#888}
.state.done{color:#2ecc71;font-weight:600}
.state.failed{color:#f33;font-weight:600}
.btn{display:block;width:100%;box-sizing:border-box;padding:12px;border:none;border-radius:999px;background:#e50914;color:#fff;font-weight:700;cursor:pointer;font-size:16px;margin-top:14px;text-align:center;text-decoration:none}
.btn:hover{background:#b20710}
.btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.25);color:#ddd}
.btn.ghost:hover{background:rgba(255,255,255,.08)}
</style>
</head>
<body>

<div class="payment-page">
  <div class="card">

    <h2>Quét mã để thanh toán</h2>
    <p class="sub">Đơn <?= $e($pay['provider_txn_id']) ?> · tiền vào là vé chốt tự động</p>

    <div class="info-row"><span class="label">Ghế</span><span><?= $e($seatText) ?></span></div>

    <div class="info-row">
      <span class="label">Tiền vé</span>
      <span><?= number_format($ticket_total, 0, ',', '.') ?> ₫</span>
    </div>

    <?php foreach ($combos as $combo): ?>
      <div class="info-row">
        <span class="label"><?= $e($combo['name'] ?? 'Combo') ?></span>
        <span><?= (int)($combo['qty'] ?? 0) ?> × <?= number_format((float)($combo['price'] ?? 0), 0, ',', '.') ?> ₫</span>
      </div>
    <?php endforeach; ?>

    <?php if ($combo_total > 0): ?>
      <div class="info-row">
        <span class="label">Tiền combo</span>
        <span><?= number_format($combo_total, 0, ',', '.') ?> ₫</span>
      </div>
    <?php endif; ?>

    <div class="info-row">
      <span class="label">Tổng cộng</span>
      <span class="amount"><?= number_format($total_amount, 0, ',', '.') ?> ₫</span>
    </div>

    <div class="qr-frame" id="qrFrame">
      <span style="color:#666;font-size:13px">Đang tạo mã QR…</span>
    </div>
    <p class="qr-hint">Mở app ngân hàng bất kỳ, quét mã. Số tiền và nội dung đã điền sẵn.</p>

    <div class="countdown" id="countdown"></div>
    <p class="state waiting" id="state">Đang chờ thanh toán…</p>

    <?php if ($checkout !== ''): ?>
      <a class="btn ghost" href="<?= $e($checkout) ?>" target="_blank" rel="noopener">
        Không quét được? Mở trang thanh toán PayOS
      </a>
    <?php endif; ?>

    <a class="btn ghost" href="../../../index.php?p=home">Hủy và về trang chủ</a>
  </div>
</div>

<script src="../../../public/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script>
const PAYMENT_ID  = <?= (int)$payment_id ?>;
const QR_PAYLOAD  = <?= json_encode($qrPayload) ?>;
const CSRF_TOKEN  = <?= json_encode(vincine_csrf_token()) ?>;
const POLL_MS     = 3000;

let secondsLeft = <?= (int)$secondsLeft ?>;
let finished    = false;

const frameEl     = document.getElementById('qrFrame');
const countdownEl = document.getElementById('countdown');
const stateEl     = document.getElementById('state');

/* ============================
   VẼ MÃ QR
============================ */
if (QR_PAYLOAD) {
  frameEl.innerHTML = '';
  new QRCode(frameEl, {
    text: QR_PAYLOAD,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.M
  });
} else {
  frameEl.innerHTML = '<span style="color:#c0392b;font-size:13px">Không dựng được mã QR. Dùng nút bên dưới để thanh toán.</span>';
}

/* ============================
   ĐỒNG HỒ ĐẾM NGƯỢC
============================ */
function renderCountdown() {
  if (finished) return;

  if (secondsLeft <= 0) {
    countdownEl.textContent = 'Đã hết thời gian giữ ghế';
    expire();
    return;
  }

  const m = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
  const s = String(secondsLeft % 60).padStart(2, '0');
  countdownEl.textContent = 'Còn lại ' + m + ':' + s;
  countdownEl.classList.toggle('urgent', secondsLeft <= 60);
  secondsLeft--;
}

/* ============================
   HỎI TRẠNG THÁI
============================ */
async function poll() {
  if (finished) return;

  try {
    const res = await fetch('../../api/payos_status.php?payment_id=' + PAYMENT_ID, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });

    if (!res.ok) return;

    const body = await res.json();

    if (body.status === 'paid' || body.status === 'success') {
      succeed();
    } else if (body.status === 'canceled') {
      fail('Đơn hàng đã bị hủy.');
    }
  } catch (err) {
    /* Mất mạng tạm thời: im lặng, lần hỏi sau sẽ biết. */
  }
}

function succeed() {
  finished = true;
  stateEl.className = 'state done';
  stateEl.textContent = 'Đã nhận được thanh toán. Đang chuyển tới vé của bạn…';
  countdownEl.textContent = '';
  setTimeout(() => {
    window.location.href = '../../../public/booking_success.php?pid=' + PAYMENT_ID;
  }, 1500);
}

function fail(message) {
  finished = true;
  stateEl.className = 'state failed';
  stateEl.textContent = message;
}

async function expire() {
  if (finished) return;
  finished = true;

  stateEl.className = 'state failed';
  stateEl.textContent = 'Hết thời gian giữ ghế. Đang hủy đơn…';

  try {
    await fetch('../../../app/controllers/payment_callback.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': CSRF_TOKEN
      },
      body: 'payment_id=' + PAYMENT_ID + '&action=auto_cancel'
    });
  } catch (err) {
    /* Hủy phía server thất bại thì cron/PayOS vẫn cho link hết hạn. */
  }

  setTimeout(() => {
    window.location.href = '../../../index.php?p=home&timeout=1';
  }, 2000);
}

renderCountdown();
setInterval(renderCountdown, 1000);
poll();
setInterval(poll, POLL_MS);
</script>

</body>
</html>
