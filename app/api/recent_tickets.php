<?php
// app/api/recent_tickets.php — 5 vé mới nhất cho dashboard admin.
declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

header('Content-Type: application/json; charset=utf-8');
vincine_require_admin(true);

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
  LEFT JOIN movies m    ON s.movie_id = m.movie_id
  ORDER BY t.ticket_id DESC
  LIMIT 5
";

$rs = $conn->query($sql);

if ($rs === false) {
    // Không trả câu SQL/thông báo lỗi MySQL ra client.
    error_log('[vincine] recent_tickets query failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Không tải được danh sách vé.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$out = [];
while ($row = $rs->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
