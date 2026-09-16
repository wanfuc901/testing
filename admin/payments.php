<?php
require_once __DIR__ . '/../app/include/require_admin.php';
require_once __DIR__ . "../../app/config/config.php";
require_once __DIR__ . "../../app/include/check_log.php";
include __DIR__ . "../../app/views/layouts/admin_menu.php";
require_once __DIR__ . "../../helpers/realtime.php";

$conn->set_charset("utf8mb4");

/* ============================
   TRẠNG THÁI HỢP LỆ
============================ */
$validStatus = ['pending','paid','success','fail'];

/* ============================
   FILTER
============================ */
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$whereSQL = "WHERE 1 ";

if ($status !== "" && in_array($status, $validStatus)) {
    $whereSQL .= " AND p.status='" . $conn->real_escape_string($status) . "' ";
}

if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereSQL .= "
        AND (
            p.provider_txn_id LIKE '%$esc%' 
         OR p.payment_id LIKE '%$esc%' 
         OR c.email LIKE '%$esc%'
         OR c.fullname LIKE '%$esc%'
         OR p.amount LIKE '%$esc%'
        )
    ";
}

/* ============================
   PAGINATION
============================ */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

/* ============================
   COUNT
============================ */
$qTotal = $conn->query("
    SELECT COUNT(*) AS c
    FROM payments p
    LEFT JOIN customers c ON c.customer_id = p.customer_id
    $whereSQL
");
if (!$qTotal) die("SQL ERROR COUNT: " . $conn->error);

$total = $qTotal->fetch_assoc()['c'];
$totalPages = ceil($total / $perPage);

/* ============================
   DATA
============================ */
$sql = "
SELECT 
    p.*,
    c.fullname,
    c.email,
    (SELECT COUNT(*) FROM tickets t WHERE t.payment_id = p.payment_id) AS ticket_count,
    (SELECT COUNT(*) FROM payment_combos pc WHERE pc.payment_id = p.payment_id) AS combo_count
FROM payments p
LEFT JOIN customers c ON c.customer_id = p.customer_id
$whereSQL
ORDER BY p.payment_id DESC
LIMIT $perPage OFFSET $offset
";

$result = $conn->query($sql);
if (!$result) die("SQL ERROR LIST: " . $conn->error);
?>

<link rel="stylesheet" href="public/assets/css/admin.css">
<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">

<div class="admin-wrap">
<div class="admin-container">

<div class="admin-title">
    <h1><i class="bi bi-receipt"></i> Quản lý hóa đơn</h1>

    <div class="admin-actions">
        <form style="display:flex;gap:8px">
            <input type="text" name="search" placeholder="Tìm mã đơn / email…"
                   value="<?=htmlspecialchars($search)?>" class="input">
            <button class="btn primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
</div>

<div class="admin-actions" style="margin:10px 0">
    <a class="btn <?= $status==''?'primary':'ghost' ?>"
       href="index.php?p=admin_payments">Tất cả</a>

    <?php foreach ($validStatus as $st): ?>
        <a class="btn <?= $status===$st?'primary':'ghost' ?>"
           href="index.php?p=admin_payments&status=<?=$st?>">
           <?=$st?>
        </a>
    <?php endforeach; ?>
</div>

<form action="app/controllers/admin/payments_controller.php" method="post" id="multiForm">
<table class="admin-table">
<thead>
<tr>
    <th><input type="checkbox" id="selectAll"></th>
    <th>ID</th>
    <th>Khách hàng</th>
    <th>Mã đơn</th>
    <th>Tiền</th>
    <th>Vé</th>
    <th>Combo</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
    <th>Chi tiết</th>
</tr>
</thead>

<tbody>
<?php while ($p = $result->fetch_assoc()): ?>
<tr>
<td>
<?php
// Chỉ KHÓA checkbox khi đã xong
$disableCheckbox = (
    in_array($p['status'], ['success','fail'])
    || ($p['method'] === 'offline' && $p['status'] === 'paid')
);

// Điều kiện KHÔNG được confirm
$disableConfirm = (
    $p['method'] === 'offline'
    || $p['status'] !== 'paid'
);

$disableReason = '';

if ($disableConfirm) {
    if ($p['method'] === 'offline') {
        $disableReason = 'Hóa đơn OFFLINE không thể confirm';
    } elseif ($p['status'] !== 'paid') {
        $disableReason = 'Chỉ confirm khi trạng thái là PAID';
    }
}
?>

<span class="checkbox-wrap"
      <?= $disableConfirm ? 'data-hint="'.htmlspecialchars($disableReason).'"' : '' ?>>
    <input type="checkbox"
           name="payments[]"
           value="<?=$p['payment_id']?>"
           <?= $disableCheckbox ? 'disabled' : '' ?>>
</span>

</td>

<td><?=$p['payment_id']?></td>

<td>
<?=htmlspecialchars($p['fullname'] ?: 'Khách vô danh')?><br>
<span class="help"><?=htmlspecialchars($p['email'])?></span>
</td>

<td><?=htmlspecialchars($p['provider_txn_id'])?></td>
<td><?=number_format($p['amount'])?>đ</td>
<td><?=$p['ticket_count']?></td>
<td><?=$p['combo_count']?></td>

<td>
<span class="badge 
<?= $p['status']=='success'?'ok':(
   $p['status']=='paid'?'info':(
   $p['status']=='pending'?'warn':'err')) ?>">
<?=$p['status']?>
</span>
</td>

<td><?=$p['created_at']?></td>

<td>
<button type="button" class="btn primary"
        onclick="showDetail(<?=$p['payment_id']?>)">
    <i class="bi bi-eye-fill"></i>
</button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<div style="margin-top:15px;display:flex;gap:10px;">
    <button class="btn" name="action" value="mark_paid">Mark Paid</button>
    <button class="btn primary" name="action" value="confirm">Confirm</button>
    <button class="btn danger" name="action" value="cancel">Cancel</button>
</div>
</form>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;margin-top:16px;gap:6px;">
<?php for ($i=1; $i<=$totalPages; $i++): ?>
<a class="btn <?= $i==$page?'primary':'ghost' ?>"
   href="index.php?p=admin_payments&page=<?=$i?>&status=<?=$status?>&search=<?=urlencode($search)?>">
<?=$i?>
</a>
<?php endfor; ?>
</div>
<?php endif; ?>

</div>
</div>

<!-- ================= POPUP DETAIL ================= -->
<div id="popup" class="popup">
  <div class="popup-content" id="popupContent"></div>
</div>

<style>
.popup{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.8);
    align-items:center;
    justify-content:center;
    z-index:9999;
}
.popup-content{
    background:#fff;
    color:#000;
    padding:20px;
    border-radius:10px;
    max-width:600px;
    width:90%;
    max-height:90vh;
    overflow:auto;
}

.checkbox-wrap {
    position: relative;
    display: inline-block;
}

.checkbox-wrap input:disabled {
    pointer-events: none; /* CỰC KỲ QUAN TRỌNG */
}

.checkbox-wrap[data-hint]:hover::after {
    content: attr(data-hint);
    position: fixed;
    top: var(--hint-top);
    left: var(--hint-left);
    background: #222;
    color: #fff;
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 6px;
    z-index: 9999; /* MAX */
    pointer-events: none;
}


.checkbox-wrap[data-hint]:hover::before {
    content: "";
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #222;
}

</style>

<script>
function showDetail(id) {
    fetch("app/controllers/admin/payments_controller.php?action=detail&id=" + id)
        .then(res => res.text())
        .then(html => {
            document.getElementById("popupContent").innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h2 style="margin:0;font-size:20px;color:#e50914;font-weight:700">
                        Chi tiết hóa đơn #${id}
                    </h2>
                    <button class="btn primary" onclick="printInvoice(${id})">
                        <i class="bi bi-printer"></i> In hóa đơn
                    </button>
                </div>
                ${html}
            `;
            document.getElementById("popup").style.display = "flex";
        });
}

function printInvoice(id) {
    fetch("app/controllers/admin/payments_controller.php?action=detail_raw&id=" + id)
        .then(res => res.json())
        .then(data => {

            const printWin = window.open("", "", "width=900,height=650");

            printWin.document.write(`
                <html>
                <head>
                    <title>Hóa đơn #${id}</title>
                    <meta charset="UTF-8">
                    <style>
                        body { 
                            font-family: 'Poppins', sans-serif; 
                            padding: 30px; 
                            color:#222;
                            line-height: 1.6;
                        }
                        .invoice-box {
                            max-width: 800px;
                            margin: auto;
                            padding: 20px;
                            border: 1px solid #eee;
                            box-shadow: 0 0 10px rgba(0,0,0,.15);
                            font-size: 15px;
                        }
                        h1 { color:#e50914; font-size: 26px; margin-bottom: 10px; }
                        h2 { font-size: 20px; margin-top:30px; color:#333; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                        th, td { padding: 10px; border: 1px solid #ddd; }
                        th { background: #f7f7f7; font-weight: 700; }
                        .total {
                            font-size: 20px;
                            color: #e50914;
                            font-weight: 700;
                            text-align: right;
                            padding-top: 10px;
                        }
                        .info-table td { border:none !important; padding:4px 0; }
                        .footer { margin-top: 40px; text-align: center; color:#777; font-size: 13px; }
                    </style>
                </head>

                <body>
                    <div class="invoice-box">
                        <h1>Vincent Cinemas</h1>
                        <p><strong>Hóa đơn #${data.payment.payment_id}</strong></p>

                        <table class="info-table">
                            <tr><td><strong>Khách hàng:</strong> ${data.payment.fullname}</td></tr>
                            <tr><td><strong>Email:</strong> ${data.payment.email}</td></tr>
                            <tr><td><strong>Mã giao dịch:</strong> ${data.payment.provider_txn_id}</td></tr>
                            <tr><td><strong>Ngày tạo:</strong> ${data.payment.created_at}</td></tr>
                            <tr><td><strong>Trạng thái:</strong> ${data.payment.status}</td></tr>
                        </table>

                        <h2>Danh sách vé</h2>
                        ${data.tickets_html}

                        <h2>Danh sách combo</h2>
                        ${data.combos_html}

                        <p class="total">
                            Tổng tiền: ${Number(data.payment.amount).toLocaleString()}đ
                        </p>

                        <div class="footer">
                            Cảm ơn bạn đã đặt vé tại Vincent Cinemas<br>
                            Vé hợp lệ khi có mã thanh toán
                        </div>
                    </div>

                    <script>
                        window.print();
                        window.onafterprint = function(){ window.close(); }
                    <\/script>
                </body>
                </html>
            `);

            printWin.document.close();
        });
}

document.getElementById("popup").onclick = e=>{
    if (e.target.id === "popup") e.target.style.display = "none";
};

document.getElementById("selectAll").addEventListener("change", e=>{
    document.querySelectorAll('input[name="payments[]"]').forEach(cb=>{
        if (!cb.disabled) cb.checked = e.target.checked;
    });
});

document.getElementById("multiForm").addEventListener("submit", e=>{
    if (!document.querySelector('input[name="payments[]"]:checked')) {
        e.preventDefault();
        alert("Không có hóa đơn nào được chọn.");
    }
});


document.addEventListener("change", () => {
    const canConfirm = [...document.querySelectorAll('input[name="payments[]"]:checked')]
        .some(cb => {
            const wrap = cb.closest(".checkbox-wrap");
            return !wrap || !wrap.dataset.hint;
        });

    document.querySelector('button[value="confirm"]').disabled = !canConfirm;
});

</script>
