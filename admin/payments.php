<?php
require_once __DIR__ . "../../app/config/config.php";
require_once __DIR__ . "../../app/include/check_log.php";
include __DIR__ . "../../app/views/layouts/admin_menu.php";
require_once __DIR__ . "../../helpers/realtime.php";

$conn->set_charset("utf8mb4");

/* ============================
   Danh sách trạng thái hợp lệ
============================ */
$validStatus = ['pending','success','fail'];

/* ============================
   Nhận filter
============================ */
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

/* ============================
   Xây WHERE chuẩn
============================ */
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
   Phân trang
============================ */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

/* ============================
   Đếm tổng số dòng
============================ */
$qTotal = $conn->query("
    SELECT COUNT(*) AS c
    FROM payments p
    LEFT JOIN customers c ON c.customer_id = p.customer_id
    $whereSQL
");

if (!$qTotal) {
    die("SQL ERROR COUNT: " . $conn->error);
}

$total = $qTotal->fetch_assoc()['c'];
$totalPages = ceil($total / $perPage);

/* ============================
   Lấy danh sách payments
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

if (!$result) {
    die("SQL ERROR LIST: " . $conn->error);
}
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
               href="index.php?p=admin_payments&status=<?=$st?>"><?=$st?></a>
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
<?php while($p = $result->fetch_assoc()): ?>
<tr>
   <td>
        <?php if ($p['status'] === 'success'): ?>
            <input type="checkbox" disabled title="Đơn đã thanh toán, không thể cập nhật.">
        <?php else: ?>
            <input type="checkbox" name="payments[]" value="<?=$p['payment_id']?>">
        <?php endif; ?>
    </td>   

    <td><?=$p['payment_id']?></td>

    <td>
        <?= htmlspecialchars($p['fullname'] ?: 'Khách vô danh') ?><br>
        <span class="help"><?= htmlspecialchars($p['email']) ?></span>
    </td>

    <td><?=htmlspecialchars($p['provider_txn_id'])?></td>

    <td><?=number_format($p['amount'])?>đ</td>

    <td><?=$p['ticket_count']?></td>

    <td><?=$p['combo_count']?></td>

    <td>
        <span class="badge 
            <?= $p['status']=='success'?'ok':(
            $p['status']=='pending'?'warn':'err') ?>">
            <?=$p['status']?>
        </span>
    </td>

    <td><?=$p['created_at']?></td>

    <td>
        <button type="button" onclick="showDetail(<?=$p['payment_id']?>)" class="btn primary">
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
    <button class="btn" name="action" value="lock">Khoá đơn</button>
</div>

</form>

<?php if($totalPages > 1): ?>
<div style="display:flex;justify-content:center;margin-top:16px;gap:6px;">
  <?php for($i=1;$i<=$totalPages;$i++): ?>
    <a class="btn <?= $i==$page?'primary':'ghost' ?>" 
       href="index.php?p=admin_payments&page=<?=$i?>&status=<?=$status?>&search=<?=urlencode($search)?>">
       <?=$i?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

</div>
</div>

<!-- POPUP DETAIL -->
<div id="popup" class="popup">
  <div class="popup-content" id="popupContent"></div>
</div>

<style>
.popup{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);align-items:center;justify-content:center;z-index:9999;}
.popup-content{background:#fff;color:#000;padding:20px;border-radius:10px;max-width:600px;width:90%;max-height:90vh;overflow:auto;}
</style>

<script>
function showDetail(id) {
    fetch("app/controllers/admin/payments_controller.php?action=detail&id=" + id)
        .then(res => res.text())
        .then(html => {

            // Gắn nội dung popup + nút IN
            document.querySelector("#popupContent").innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h2 style="margin:0;font-size:20px;color:#e50914;font-weight:700">
                        Chi tiết hóa đơn #${id}
                    </h2>

                    <button class="btn primary" onclick="printInvoice(${id})">
                        <i class='bi bi-printer'></i> In hóa đơn
                    </button>
                </div>

                ${html}
            `;

            document.querySelector("#popup").style.display = "flex";
        });
}

function printInvoice(id) {

    // Lấy dữ liệu chi tiết thuần HTML từ API
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

                        <p class="total">Tổng tiền: ${Number(data.payment.amount).toLocaleString()}đ</p>

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



document.querySelector("#popup").onclick = e=>{
    if(e.target.id==="popup") e.target.style.display="none";
};

document.getElementById("selectAll").addEventListener("change", e=>{
    document.querySelectorAll('input[name="payments[]"]').forEach(cb=>{
        if(!cb.disabled) cb.checked = e.target.checked;
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById('multiForm');

    form.addEventListener("submit", e => {
        const checked = document.querySelectorAll('input[name="payments[]"]:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert("Không thể thao tác trên đơn SUCCESS hoặc không có đơn nào được chọn.");
        }
    });
});
</script>
