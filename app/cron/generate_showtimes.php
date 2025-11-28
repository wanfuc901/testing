<?php
require __DIR__ . "/../config/config.php";
require __DIR__ . "/../models/ShowtimeModel.php";

$days = (int)($_GET['days'] ?? 7); // mặc định 7 ngày tới
$days = max(1, min(60, $days));

$created = 0;
for ($i=0; $i<$days; $i++){
  $date = date('Y-m-d', strtotime("+$i day"));
  $r = ShowtimeModel::generateForDate($conn, $date);
  $created += $r['created'];
}
echo "generated=$created";
