<?php
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';

/* ===== Hiển thị lỗi khi dev ===== */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ===== Thiết lập khoảng ngày mặc định ===== */
$today = date('Y-m-d');
$firstLastMonth = date('Y-m-01', strtotime('first day of last month'));
$from = $_GET['from'] ?? $firstLastMonth;
$to   = $_GET['to'] ?? $today;

/* ===== Kiểm tra hợp lệ định dạng ngày ===== */
$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reDate, $from)) $from = $firstLastMonth;
if (!preg_match($reDate, $to))   $to = $today;

/* ===== Hàm truy vấn an toàn + báo lỗi ===== */
function q($conn, $sql) {
  $rs = $conn->query($sql);
  if ($rs === false) {
    die("SQL ERROR: " . $conn->error . "<br><pre>$sql</pre>");
  }
  return $rs;
}

/* === 1. Doanh thu theo tháng === */
$revenueData = q($conn, "
  SELECT DATE_FORMAT(t.booked_at, '%Y-%m') AS thang,
         SUM(COALESCE(t.price,0)) AS doanh_thu
  FROM tickets t
  WHERE (t.status IN ('paid','confirmed') OR t.paid=1)
    AND DATE(t.booked_at) BETWEEN '{$from}' AND '{$to}'
  GROUP BY DATE_FORMAT(t.booked_at, '%Y-%m')
  ORDER BY thang ASC
");
$labels_month = [];
$values_month = [];
while($r = $revenueData->fetch_assoc()){
  $labels_month[] = $r['thang'];
  $values_month[] = (float)$r['doanh_thu'];
}

/* === 2. Tổng hợp nhanh === */
$stats = q($conn, "
  SELECT
    (SELECT IFNULL(SUM(price),0)
     FROM tickets
     WHERE (status IN ('paid','confirmed') OR paid=1)) AS total_revenue,

    (SELECT COUNT(*)
     FROM tickets
     WHERE (status IN ('paid','confirmed') OR paid=1)) AS total_tickets,

    (SELECT COUNT(*) FROM showtimes WHERE status='active') AS total_showtimes,

    (SELECT COUNT(DISTINCT user_id) FROM tickets) AS total_users
")->fetch_assoc();

/* === 3. Top 5 phim doanh thu cao === */
$topMovies = q($conn, "
  SELECT m.title, IFNULL(SUM(t.price),0) AS revenue
  FROM movies m
  LEFT JOIN showtimes s ON s.movie_id=m.movie_id
  LEFT JOIN tickets t ON t.showtime_id=s.showtime_id
  WHERE (t.status IN ('paid','confirmed') OR t.paid=1)
  GROUP BY m.movie_id
  ORDER BY revenue DESC
  LIMIT 5
");
$movieLabels = [];
$movieValues = [];
while($row = $topMovies->fetch_assoc()){
  $movieLabels[] = $row['title'];
  $movieValues[] = (float)$row['revenue'];
}

/* === 4. Doanh thu theo phòng chiếu === */
$byRoom = q($conn, "
  SELECT 
    r.name AS room_name,
    COUNT(DISTINCT s.showtime_id) AS total_showtimes,
    COUNT(t.ticket_id) AS total_tickets,
    IFNULL(SUM(t.price),0) AS revenue
  FROM rooms r
  LEFT JOIN showtimes s ON s.room_id = r.room_id
  LEFT JOIN tickets t ON t.showtime_id = s.showtime_id
  WHERE (t.status IN ('paid','confirmed') OR t.paid=1)
  GROUP BY r.room_id
  ORDER BY revenue DESC
");
$roomLabels = [];
$roomValues = [];
$roomDetails = [];
while($r = $byRoom->fetch_assoc()){
  $roomLabels[] = $r['room_name'];
  $roomValues[] = (float)$r['revenue'];
  $roomDetails[] = $r;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>VinCine · Báo cáo doanh thu</title>
<link rel="stylesheet" href="public/assets/css/admin.css">
<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="public/assets/boxicons/css/boxicons.min.css">
<script src="public/assets/js/admin/chart.js@4.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body class="stats-page">
<div class="admin-wrap">
  <div class="admin-container">
    <div class="admin-title">
      <h1><i class="bi bi-bar-chart-steps"></i> Báo cáo & Thống kê doanh thu</h1>
      <div class="admin-actions">
        <a href="index.php?p=dashboard" class="btn ghost"><i class="bi bi-speedometer2"></i> Trang quản trị</a>
      </div>
    </div>

    <p class="subtitle">Xem thống kê theo tháng, theo phim hoặc theo phòng chiếu.</p>

    <!-- Bộ lọc thời gian -->
    <div class="card" style="margin-bottom:20px;">
      <form class="filter-form" method="get" action="index.php">
        <input type="hidden" name="p" value="admin_revenue">
        <div class="filter-group">
          <label><i class="bi bi-bar-chart"></i> Loại thống kê:</label>
          <select id="chartType" name="type">
            <option value="month" selected>Theo tháng</option>
            <option value="movie">Theo phim</option>
            <option value="room">Theo phòng chiếu</option>
          </select>
          <label><i class="bi bi-calendar-week"></i> Từ:</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
          <label>Đến:</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="filter-buttons">
          <button type="submit"><i class="bi bi-funnel"></i> Lọc</button>
          <a href="app/reports/export_revenue_excel.php?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>">Xuất Excel</a>
          <button type="button" class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> In</button>
        </div>
      </form>
    </div>

    <!-- Tổng quan -->
    <div class="card-grid">
      <div class="card"><i class="bi bi-cash-stack"></i><div class="k">Tổng doanh thu</div><div class="v"><?= number_format($stats['total_revenue'],0,',','.') ?> đ</div></div>
      <div class="card"><i class="bi bi-ticket-perforated"></i><div class="k">Vé đã thanh toán</div><div class="v"><?= number_format($stats['total_tickets']) ?></div></div>
      <div class="card"><i class="bx bx-movie-play"></i><div class="k">Suất chiếu</div><div class="v"><?= number_format($stats['total_showtimes']) ?></div></div>
      <div class="card"><i class="bi bi-person-check"></i><div class="k">Khách hàng</div><div class="v"><?= number_format($stats['total_users']) ?></div></div>
    </div>

    <!-- Biểu đồ -->
    <div class="chart-box">
      <h2><i class="bi bi-graph-up-arrow"></i> Biểu đồ thống kê</h2>
      <div class="chart-container"><canvas id="chartDynamic"></canvas></div>
    </div>
    <!-- Bảng chi tiết doanh thu theo phòng -->
<div class="card" id="roomDetailBox" style="margin-top:25px; display:none;">
  <h3><i class="bi bi-building"></i> Chi tiết doanh thu theo phòng</h3>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Phòng</th>
        <th>Suất chiếu</th>
        <th>Số vé</th>
        <th>Doanh thu</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($roomDetails)): ?>
        <?php foreach ($roomDetails as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['room_name']) ?></td>
          <td><?= number_format($r['total_showtimes']) ?></td>
          <td><?= number_format($r['total_tickets']) ?></td>
          <td><?= number_format($r['revenue'],0,',','.') ?> đ</td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted)">Không có dữ liệu</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

  </div>
</div>

<script>
const ctx = document.getElementById('chartDynamic');
let chart;

const dataSets = {
  month: {
    labels: <?= json_encode($labels_month) ?>,
    data: <?= json_encode($values_month) ?>,
    label: 'Doanh thu theo tháng (VNĐ)',
    type: 'line',
    color: '#00e676'
  },
  movie: {
    labels: <?= json_encode($movieLabels) ?>,
    data: <?= json_encode($movieValues) ?>,
    label: 'Top 5 phim doanh thu cao nhất',
    type: 'bar',
    color: '#f5c518'
  },
  room: {
    labels: <?= json_encode($roomLabels) ?>,
    data: <?= json_encode($roomValues) ?>,
    label: 'Doanh thu theo phòng chiếu',
    type: 'doughnut',
    color: '#2196f3'
  }
};

function renderChart(type) {
  const d = dataSets[type];
  if (chart) chart.destroy();
  chart = new Chart(ctx, {
    type: d.type,
    data: {
      labels: d.labels,
      datasets: [{
        label: d.label,
        data: d.data,
        borderColor: d.color,
        backgroundColor: d.type === 'doughnut'
          ? ['#f5c518','#03a9f4','#e91e63','#8bc34a','#9e9e9e','#ff9800']
          : d.type === 'bar'
          ? ['#f5c518','#00e676','#2196f3','#e91e63','#9c27b0']
          : 'rgba(0,230,118,0.15)',
        fill: true,
        tension: 0.35
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { 
          position: d.type === 'doughnut' ? 'bottom' : 'top', 
          labels: { color: '#fff' } 
        },
        datalabels: d.type === 'doughnut' ? {
          color: '#fff',
          font: { size: 13, weight: 'bold' },
          formatter: (value, ctx) => {
            const dataset = ctx.chart.data.datasets[0].data;
            const total = dataset.reduce((a,b)=>a+Number(b),0);
            const pct = total ? (value/total*100).toFixed(1)+'%' : '';
            return pct;
          }
        } : false
      },
      scales: d.type === 'doughnut' ? {} : {
        x: { ticks: { color: '#ccc' } },
        y: { ticks: { color: '#ccc' } }
      }
    },
    plugins: d.type === 'doughnut' ? [ChartDataLabels] : []
  });

  // Hiện bảng chi tiết khi chọn "room"
  const box = document.getElementById('roomDetailBox');
  if (type === 'room') box.style.display = 'block';
  else box.style.display = 'none';
}

// Khởi tạo mặc định
renderChart('month');

// Đổi biểu đồ khi người dùng chọn loại
document.getElementById('chartType').addEventListener('change', e => renderChart(e.target.value));
</script>

</body>
</html>
