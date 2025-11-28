<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/include/check_log.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';
require_once __DIR__ . '/../helpers/realtime.php';   // thêm realtime thay cho activity_log


$status = $_GET['status'] ?? '';
$where = '';
if (in_array($status, ['pending','paid','confirmed','cancelled'])) {
  $where = "WHERE t.status='" . $conn->real_escape_string($status) . "'";
}

$highlight = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

$page = max(1,(int)($_GET['page']??1));
$perPage = 10;
$offset = ($page-1)*$perPage;

$total = $conn->query("
  SELECT COUNT(*) AS c FROM tickets t
  JOIN showtimes s ON s.showtime_id=t.showtime_id
  JOIN rooms r ON r.room_id=s.room_id
  JOIN movies m ON m.movie_id=s.movie_id
  $where
")->fetch_assoc()['c'] ?? 0;
$totalPages = ceil($total/$perPage);

$sql = "
SELECT t.*, m.title, s.start_time, s.end_time, r.name room_name 
FROM tickets t
JOIN showtimes s ON s.showtime_id=t.showtime_id
JOIN rooms r ON r.room_id=s.room_id
JOIN movies m ON s.movie_id=m.movie_id
$where
ORDER BY t.booked_at DESC
LIMIT $perPage OFFSET $offset
";
$rs = $conn->query($sql);
?>
<div class="admin-wrap">
  <div class="admin-container">
    <div class="admin-title">
      <h1>Quản lý vé</h1>
      <div class="admin-actions">
        <a class="btn ghost" href="index.php?p=admin_tickets">Tất cả</a>
        <?php foreach(['pending','paid','confirmed','cancelled'] as $st): ?>
          <a class="btn <?= $status===$st?'primary':'ghost' ?>" href="index.php?p=admin_tickets&status=<?= $st ?>"><?= $st ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <form action="app/controllers/admin/tickets_controller.php" method="post" id="multiForm">
      <table class="admin-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>ID</th><th>Phim</th><th>Phòng</th><th>Ghế</th>
            <th>Kênh</th><th>Trạng thái</th><th>Giá</th><th>Đặt lúc</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row=$rs->fetch_assoc()): 
            $hl = ($highlight === (int)$row['ticket_id']) ? 'highlight-ticket' : '';
          ?>
          <tr class="<?= $hl ?>">
            <td>
              <?php if(in_array($row['status'],['paid','confirmed'])): ?>
                <input type="checkbox" disabled title="❌ Vé đã thanh toán hoặc xác nhận.">
              <?php else: ?>
                <input type="checkbox" name="tickets[]" value="<?= (int)$row['ticket_id'] ?>">
              <?php endif; ?>
            </td>
            <td><?= (int)$row['ticket_id'] ?></td>
            <td><?= htmlspecialchars($row['title']) ?><br><span class="help"><?= htmlspecialchars($row['start_time']) ?></span></td>
            <td><?= htmlspecialchars($row['room_name']) ?></td>
            <td><?= (int)$row['seat_id'] ?></td>
            <td><?= htmlspecialchars($row['channel']) ?></td>
            <td><span class="badge <?= $row['status']=='confirmed'?'ok':($row['status']=='pending'?'warn':($row['status']=='paid'?'':'err')) ?>"><?= $row['status'] ?></span></td>
            <td><?= number_format((float)$row['price'],0,'.','.') ?> đ</td>
            <td><?= htmlspecialchars($row['booked_at']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

<?php if($totalPages>1): ?>
<div style="display:flex;justify-content:center;margin-top:16px;gap:6px;flex-wrap:wrap">
  <?php for($i=1;$i<=$totalPages;$i++): 
    $url="index.php?p=admin_tickets&page=$i";
    if($status!=='') $url.="&status=".urlencode($status);
  ?>
  <a href="<?= $url ?>" class="btn <?= $i==$page?'primary':'ghost' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

      <div style="margin-top:14px;display:flex;gap:10px;">
        <button type="submit" class="btn" name="action" value="mark_paid">Mark Paid</button>
        <button type="submit" class="btn primary" name="action" value="confirm">Confirm</button>
        <button type="submit" class="btn" name="action" value="cancel" onclick="return confirm('Hủy các vé đã chọn?')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- SOCKET.IO REALTIME -->
<script src="https://vincent-realtime-node.onrender.com/socket.io/socket.io.js"></script>
<script>
const socket = io("https://vincent-realtime-node.onrender.com", {
    transports: ["websocket"]
});

// Khi có bất kỳ update nào của vé từ server
socket.on("dashboard_update", payload => {
    console.log("Realtime update:", payload);

    // Tự động refresh bảng vé
    refreshTickets();
});
</script>


<!-- Overlay loading -->
<div id="loadingOverlay" style="
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.85);
  z-index:2000; display:flex;
  flex-direction:column; justify-content:center;
  align-items:center; color:#fff;
  font-family:'Poppins',sans-serif;
">
  <div style="width:60px;height:60px;border:5px solid rgba(255,255,255,.2);
              border-top-color:var(--gold);
              border-radius:50%;animation:spin 1s linear infinite"></div>
  <h3 style="margin-top:18px;font-weight:600;color:var(--gold)">
    Đang xử lý xác nhận...
  </h3>
</div>

<style>
@keyframes spin {to{transform:rotate(360deg)}}

.highlight-ticket {
  background: rgba(245,197,24,0.25) !important;
  box-shadow: inset 0 0 0 2px var(--gold);
  animation: blinkHighlight 1.2s ease-in-out 3;
}
@keyframes blinkHighlight {
  0%,100% { background: rgba(245,197,24,0.25); }
  50% { background: rgba(245,197,24,0.5); }
}
</style>

<script>
const overlay=document.getElementById('loadingOverlay');
const form=document.getElementById('multiForm');
document.getElementById('selectAll').addEventListener('change',e=>{
  document.querySelectorAll('input[name="tickets[]"]').forEach(cb=>cb.checked=e.target.checked);
});

// === xử lý form (hiệu ứng loading + chặn vé offline)
let isManualSubmit = false; // cờ kiểm tra người dùng click submit thật

form.addEventListener('submit', e => {
  isManualSubmit = true; // đánh dấu người dùng click
  const checked = document.querySelectorAll('input[name="tickets[]"]:checked');
  if (!checked.length) {
    alert('Vui lòng chọn ít nhất 1 vé để xử lý!');
    e.preventDefault();
    isManualSubmit = false;
    return;
  }

  let hasOffline = false;
  checked.forEach(cb => {
    const row = cb.closest('tr');
    const channel = row.querySelector('td:nth-child(6)')?.innerText.trim().toLowerCase();
    if (channel.includes('tại quầy') || channel.includes('offline') || channel.includes('cash')) {
      hasOffline = true;
    }
  });

  if (hasOffline) {
    alert('Không thể xác nhận vé có kênh "Thanh toán tại quầy".');
    e.preventDefault();
    isManualSubmit = false;
    return;
  }

  overlay.style.display = 'flex';
  setTimeout(() => overlay.style.display = 'none', 15000);
});

// Ẩn overlay ngay khi load hoặc reload trang
window.addEventListener('load', () => {
  if (!isManualSubmit) {
    overlay.style.display = 'none';
  }
});


// === tự cuộn tới vé highlight
document.addEventListener('DOMContentLoaded',()=>{
  const hl=document.querySelector('.highlight-ticket');
  if(hl){hl.scrollIntoView({behavior:'smooth',block:'center'});}
});

async function refreshTickets() {
    try {
        const res = await fetch(window.location.href, { cache: "no-cache" });
        const html = await res.text();

        // Tạo DOM ảo để lấy lại phần <tbody>
        const temp = document.createElement("div");
        temp.innerHTML = html;

        const newBody = temp.querySelector("tbody");
        const oldBody = document.querySelector("tbody");

        if (newBody && oldBody) {
            oldBody.innerHTML = newBody.innerHTML;
        }
    } catch(err) {
        console.error("Refresh failed:", err);
    }
}
</script>
