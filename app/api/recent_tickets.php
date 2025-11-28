<?php
require_once __DIR__ . '/../config/config.php';
ini_set('display_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$sql = "
  SELECT 
  t.ticket_id,
  u.name AS user_name,
  m.title,
  DATE_FORMAT(s.start_time, '%d/%m %H:%i') AS show_time,
  t.status,
  t.price AS amount
FROM tickets t
JOIN users u ON t.user_id = u.user_id
JOIN showtimes s ON t.showtime_id = s.showtime_id
JOIN movies m ON s.movie_id = m.movie_id
ORDER BY t.ticket_id DESC
LIMIT 5;
";
$rs = $conn->query($sql);
$out = [];
while ($r = $rs->fetch_assoc()) $out[] = $r;

echo json_encode($out);
