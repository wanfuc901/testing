<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$sql = "
  SELECT 
    t.ticket_id,
    c.fullname AS user_name,
    m.title,
    DATE_FORMAT(s.start_time, '%d/%m %H:%i') AS show_time,
    t.status,
    t.price AS amount
  FROM tickets t
  LEFT JOIN customers c ON t.customer_id = c.customer_id
  LEFT JOIN showtimes s ON t.showtime_id = s.showtime_id
  LEFT JOIN movies m ON s.movie_id = m.movie_id
  ORDER BY t.ticket_id DESC
  LIMIT 5
";

$rs = $conn->query($sql);

if (!$rs) {
    echo json_encode([
        "error" => true,
        "sql_error" => $conn->error,
        "sql" => $sql
    ]);
    exit;
}

$out = [];
while ($r = $rs->fetch_assoc()) {
    $out[] = $r;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
