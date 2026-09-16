<?php
// app/api/recent_activity.php — 6 vé đặt gần nhất cho dashboard admin.
declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

// Ép header JSON sớm để lỗi quyền cũng trả đúng định dạng
header('Content-Type: application/json; charset=utf-8');
vincine_require_admin(true);

$sql = "
  SELECT c.fullname AS user_name,
         m.title,
         DATE_FORMAT(t.booked_at, '%d/%m/%Y %H:%i') AS booked_at,
         t.status
  FROM tickets t
  JOIN customers c ON c.customer_id = t.customer_id
  JOIN showtimes s ON s.showtime_id = t.showtime_id
  JOIN movies m    ON m.movie_id = s.movie_id
  ORDER BY t.booked_at DESC
  LIMIT 6
";

$rs = $conn->query($sql);

if ($rs === false) {
    error_log('[vincine] recent_activity query failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Không tải được hoạt động gần đây.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$out = [];
while ($row = $rs->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
