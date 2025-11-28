<?php
require_once __DIR__ . '/../config/config.php';
$role = $_GET['role'] ?? 'all';
$where = ($role==='all') ? '' : "WHERE a.role='$role'";

$sql = "
SELECT a.*, u.name 
FROM activity_logs a
LEFT JOIN users u ON u.user_id=a.user_id
$where AND (a.last_seen IS NOT NULL AND a.last_seen > NOW() - INTERVAL 30 SECOND)
ORDER BY a.id DESC
LIMIT 10
";

$rs = $conn->query($sql);
?>
<div class="admin-activity-widget">
  <div class="tabs">
    <a href="?p=admin_dashboard&role=all"   class="<?= $role=='all'?'active':'' ?>">Tất cả</a>
    <a href="?p=admin_dashboard&role=admin" class="<?= $role=='admin'?'active':'' ?>">Admin</a>
    <a href="?p=admin_dashboard&role=user"  class="<?= $role=='user'?'active':'' ?>">User</a>
  </div>

  <div id="activityList">
    <?php while($r=$rs->fetch_assoc()): ?>
      <div class="activity-item">
        <div class="info">
          <b><?= htmlspecialchars($r['name'] ?: 'Ẩn danh') ?></b> 
          <span class="role">(<?= $r['role'] ?>)</span>
          <span class="action">· <?= htmlspecialchars($r['action']) ?></span>
        </div>
        <div class="meta">
          <small><?= date('H:i:s d/m',strtotime($r['created_at'])) ?></small>
          <span class="dots"><span></span><span></span><span></span></span>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<style>
.admin-activity-widget{margin-top:10px}
.admin-activity-widget .tabs a{margin-right:6px;padding:4px 8px;border-radius:6px;color:var(--text);}
.admin-activity-widget .tabs a.active{background:var(--red);color:#fff;}
.activity-item{display:flex;justify-content:space-between;align-items:center;
  padding:6px 0;border-bottom:1px solid var(--border);}
.dots{display:inline-flex;gap:3px;}
.dots span{width:6px;height:6px;background:var(--gold);border-radius:50%;animation:bounce 1s infinite;}
.dots span:nth-child(2){animation-delay:0.2s}
.dots span:nth-child(3){animation-delay:0.4s}
@keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-5px)}}
</style>

<script>
function reloadActivity(){
  fetch('app/api/get_activity.php?role=<?= $role ?>&page=1')
    .then(r=>r.text())
    .then(html=>{
      document.getElementById('activityList').innerHTML=html;
    });
}
setInterval(reloadActivity,5000);
</script>
