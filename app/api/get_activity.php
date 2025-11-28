<?php
require_once __DIR__ . '../../config/config.php';
$role = $_GET['role'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$where = ($role==='all') ? '' : "WHERE a.role='$role'";

$sql = "
SELECT a.*, u.name
FROM activity_logs a
LEFT JOIN users u ON u.user_id=a.user_id
$where
ORDER BY a.id DESC
LIMIT $limit OFFSET $offset
";
$rs = $conn->query($sql);

while($r=$rs->fetch_assoc()){
  echo "<div class='activity-item'>";
  echo "<div class='info'><b>".htmlspecialchars($r['name'] ?: 'Ẩn danh')."</b> ";
  echo "<span class='role'>(".$r['role'].")</span> ";
  echo "<span class='action'>· ".htmlspecialchars($r['action'])."</span></div>";
  echo "<div class='meta'><small>".date('H:i:s d/m/Y',strtotime($r['created_at']))."</small>";
  echo "<span class='dots'><span></span><span></span><span></span></span></div></div>";
}
