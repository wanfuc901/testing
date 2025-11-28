<?php
// app/api/recent_activity.php
declare(strict_types=1);

// Bật báo lỗi cho DEV (có thể tắt trên PROD)
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/config.php';

// Kiểm tra đăng nhập admin (không redirect để tránh HTML chen vào JSON)
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'customer') !== 'admin')) {
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Unauthorized: admin only']);
  exit;
}

// Ép header JSON sớm để tránh BOM/HTML
header('Content-Type: application/json; charset=utf-8');

try {
  // Chuẩn bị & chạy query
$sql = "
  SELECT u.name AS user_name, m.title,
         DATE_FORMAT(t.booked_at, '%d/%m/%Y %H:%i') AS booked_at,
         t.status
  FROM tickets t
  JOIN users u ON u.user_id = t.user_id
  JOIN showtimes s ON s.showtime_id = t.showtime_id
  JOIN movies m ON m.movie_id = s.movie_id
  ORDER BY t.booked_at DESC
  LIMIT 6
";

  $rs = $conn->query($sql);
  if (!$rs) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: '.$conn->error]);
    exit;
  }

  $out = [];
  while ($row = $rs->fetch_assoc()) $out[] = $row;

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error: '.$e->getMessage()]);
}
