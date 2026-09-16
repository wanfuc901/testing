<?php
require_once __DIR__ . '/../app/include/require_admin.php';
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/include/check_log.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';


function queryValue(mysqli $conn, string $sql, string $field, $default = 0) {
    $res = $conn->query($sql);
    if (!$res) return $default;
    $row = $res->fetch_assoc();
    return $row[$field] ?? $default;
}



$kpis = [
  'movies'    => queryValue($conn, "SELECT COUNT(*) c FROM movies", 'c'),
  'showtimes' => queryValue($conn, "SELECT COUNT(*) c FROM showtimes", 'c'),
  'tickets'   => queryValue($conn, "SELECT COUNT(*) c FROM tickets", 'c'),
  'users'     => queryValue($conn, "SELECT COUNT(*) c FROM users", 'c'),
];


/* Doanh thu hôm nay */
$today = queryValue($conn, "
  SELECT SUM(price) s
  FROM tickets
  WHERE (status='confirmed' OR paid=1)
    AND DATE(booked_at)=CURDATE()
", 's', 0);

/* Doanh thu tháng */
$month = queryValue($conn, "
  SELECT SUM(price) s
  FROM tickets
  WHERE (status='confirmed' OR paid=1)
    AND DATE_FORMAT(booked_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
", 's', 0);
?>



<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">

<div class="admin-wrap">
  <div class="admin-container">

    <div class="admin-title">
      <h1><i class="bi bi-speedometer2"></i> Bảng điều khiển</h1>
      <div class="admin-actions">
        <a class="btn ghost" href="index.php?p=admin_revenue">
          <i class="bi bi-graph-up"></i> Xem doanh thu
        </a>
      </div>
    </div>

    <!-- KPI GRID -->
    <div class="card-grid">
      <div class="card" onclick="go('admin_movies')"><i class="bi bi-film"></i><div class="k">Tổng phim</div><div class="v"><?= $kpis['movies'] ?></div></div>
      <div class="card" onclick="go('admin_showtimes')"><i class="bi bi-calendar-event"></i><div class="k">Suất chiếu</div><div class="v"><?= $kpis['showtimes'] ?></div></div>
      <div class="card" onclick="go('admin_tickets')"><i class="bi bi-ticket-perforated"></i><div class="k">Tổng vé</div><div class="v"><?= $kpis['tickets'] ?></div></div>
      <div class="card" onclick="go('admin_users')"><i class="bi bi-people"></i><div class="k">Người dùng</div><div class="v"><?= $kpis['users'] ?></div></div>
    </div>

    <div class="card-grid" style="margin-top:14px">
      <div class="card" onclick="go('admin_revenue')"><i class="bi bi-cash-stack"></i><div class="k">Doanh thu hôm nay</div><div class="v"><?= number_format($today) ?> đ</div></div>
      <div class="card" onclick="go('admin_revenue')"><i class="bi bi-piggy-bank"></i><div class="k">Doanh thu tháng</div><div class="v"><?= number_format($month) ?> đ</div></div>
      <div class="card" onclick="go('admin_tickets')"><i class="bi bi-hourglass-split"></i><div class="k">Vé chờ</div><div class="v"><?= queryValue($conn, "SELECT COUNT(*) c FROM tickets WHERE status='pending'", 'c') ?></div></div>
      <div class="card" onclick="go('admin_tickets')"><i class="bi bi-check-circle"></i><div class="k">Vé đã xác nhận</div><div class="v"><?= queryValue($conn, "SELECT COUNT(*) c FROM tickets WHERE status='confirmed'", 'c') ?></div></div>
    </div>
    <?php
$bankRes = $conn->query("
  SELECT * FROM payment_accounts
  WHERE is_active = 1
  LIMIT 1
");
$bank = $bankRes ? $bankRes->fetch_assoc() : [];

?>

<div class="admin-title" style="margin-top:28px">
  <h1><i class="bi bi-bank"></i> Tài khoản nhận tiền</h1>

  <div class="admin-actions">
    <button class="btn ghost" onclick="toggleBankForm()">
      <i class="bi bi-pencil-square"></i> Chỉnh sửa
    </button>
  </div>
</div>
<div id="bankFormWrap" class="admin-form"
     style="max-width:520px; display:none;margin: 0 auto;">
<form method="post" action="app/controllers/admin/update_payment_account.php">
<?= vincine_csrf_input() ?>


  <div class="form-grid">
    <div class="full">
      <label><i class="bi bi-bank2"></i> Ngân hàng</label>
      <select name="bank_code" required>
        <option value="970422" <?=($bank['bank_code']??'')=='970422'?'selected':''?>>MB Bank</option>
        <option value="970436" <?=($bank['bank_code']??'')=='970436'?'selected':''?>>Vietcombank</option>
        <option value="970415" <?=($bank['bank_code']??'')=='970415'?'selected':''?>>VietinBank</option>
        <option value="970407" <?=($bank['bank_code']??'')=='970407'?'selected':''?>>Techcombank</option>
        <option value="970418" <?=($bank['bank_code']??'')=='970418'?'selected':''?>>BIDV</option>
      </select>
    </div>
    <div class="full">
      <label><i class="bi bi-person-badge"></i> Tên người nhận</label>
      <input class="input" name="account_name"
             value="<?=htmlspecialchars($bank['account_name'] ?? '')?>" required>
    </div>
    <div class="full">
      <label><i class="bi bi-credit-card"></i> Số tài khoản</label>
      <input class="input" name="account_number"
             value="<?=htmlspecialchars($bank['account_number'] ?? '')?>" required>
    </div>
  </div>
  <div class="form-btns">
    <button class="btn primary">
      <i class="bi bi-save"></i> Lưu & áp dụng
    </button>
  </div>
</form>
</div>
    <!-- Chart -->
    <div class="admin-title" style="margin-top:28px">
      <h1><i class="bx bx-line-chart"></i> Doanh thu tháng này</h1>
    </div>
    <div class="chart-box"><canvas id="chart7" height="120"></canvas></div>

    <!-- Recent Tickets -->
    <div class="admin-title" style="margin-top:30px">
      <h1><i class='bx bx-history'></i> Vé gần đây</h1>
    </div>

    <table class="admin-table">
      <thead>
        <tr><th>Mã</th><th>Khách</th><th>Phim</th><th>Suất</th><th>Trạng thái</th><th>Tổng</th></tr>
      </thead>
      <tbody id="recentBody">
        <tr><td colspan="6" style="text-align:center;color:var(--muted)">Đang tải...</td></tr>
      </tbody>
    </table>

  </div>
</div>


<script src="public/assets/js/admin/chart.js@4.js"></script>
<script>
let chart, abortRevenue, abortRecent;

/* === Load doanh thu tháng hiện tại === */
async function loadRevenue() {
  try {
    if (abortRevenue) abortRevenue.abort();
    abortRevenue = new AbortController();

    const res = await fetch("app/api/revenue_chart.php", { signal: abortRevenue.signal });
    const data = await res.json();

    const ym = new Date().toISOString().slice(0,7);
    const filtered = data.filter(r => r.date.startsWith(ym));

    const labels = filtered.map(r => r.date.substr(5));
    const values = filtered.map(r => (r.total / 1_000_000).toFixed(2));

    if (!chart) {
      chart = new Chart(document.getElementById("chart7"), {
        type: "line",
        data: {
          labels,
          datasets: [{
            label: "Doanh thu (triệu ₫)",
            data: values,
            borderColor: "#e50914",
            backgroundColor: "rgba(229,9,20,.15)",
            fill: true,
            tension: .3
          }]
        },
        options: { plugins: { legend: { display:false } } }
      });
    } else {
      chart.data.labels = labels;
      chart.data.datasets[0].data = values;
      chart.update();
    }

  } catch(err) { /* im lặng */ }
}

/* === Load vé gần đây === */
async function loadRecent(){
  const body = document.getElementById("recentBody");

  try {
    if (abortRecent) abortRecent.abort();
    abortRecent = new AbortController();

    const res = await fetch("app/api/recent_tickets.php", { signal: abortRecent.signal });
    const data = await res.json();

    if (!Array.isArray(data) || data.length === 0) {
      body.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted)">Không có dữ liệu</td></tr>`;
      return;
    }

    body.innerHTML = data.map(r => `
      <tr class="ticket-row" data-id="${r.ticket_id}">
        <td>#${r.ticket_id}</td>
        <td>${r.user_name}</td>
        <td>${r.title}</td>
        <td>${r.show_time}</td>
        <td>
          <span class="badge ${
            (r.status=='paid'||r.status=='confirmed') ? 'ok'
            : (r.status=='pending'?'warn':'err')
          }">${r.status}</span>
        </td>
        <td>₫${Number(r.amount).toLocaleString('vi-VN')}</td>
      </tr>
    `).join("");

    /* Click highlight ticket */
    document.querySelectorAll(".ticket-row").forEach(tr => {
      tr.addEventListener("click", () => {
        window.location.href = "index.php?p=admin_tickets&highlight=" + tr.dataset.id;
      });
    });

  } catch(err) {
    body.innerHTML = `<tr><td colspan="6" style="text-align:center;color:red">Không tải được dữ liệu</td></tr>`;
  }
}

/* Lần đầu mở dashboard */
loadRevenue();
loadRecent();

/* Đi tới trang */
function go(p){ window.location.href = "index.php?p="+p; }

/* Animation card */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".card-grid .card").forEach((c,i)=>{
    c.style.animation = "fadeUpCard 0.6s ease forwards";
    c.style.animationDelay = `${i*0.15}s`;
  });
});

document.querySelector(".btn-book").addEventListener("click", function(e){
    if (selectedSeatIds.length === 0) {
        e.preventDefault();
        alert("Vui lòng chọn ít nhất 1 ghế trước khi thanh toán.");
        return false;
    }
});
</script>


<!-- Real-time -->
<script src="https://vincent-realtime-node.onrender.com/socket.io/socket.io.js"></script>
<script>
const socket = io("https://vincent-realtime-node.onrender.com", { transports:["websocket"] });

socket.on("dashboard_update", () => {
  loadRevenue();
  loadRecent();

  document.querySelectorAll(".card-grid .card").forEach(c => {
    c.style.animation = "flashCard 0.6s ease";
    setTimeout(() => c.style.animation = "", 600);
  });
});

function toggleBankForm(){
  const box = document.getElementById("bankFormWrap");
  if (!box) return;

  if (box.style.display === "none" || box.style.display === "") {
    box.style.display = "block";
    box.scrollIntoView({ behavior: "smooth", block: "start" });
  } else {
    box.style.display = "none";
  }
}
</script>

<style>
@keyframes flashCard {
  0%   { transform: scale(1); background: rgba(255,255,255,0); }
  50%  { transform: scale(1.05); background: rgba(229,9,20,0.15); }
  100% { transform: scale(1); background: rgba(255,255,255,0); }
}
</style>
