<?php
require_once __DIR__ . '/../config/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$date = $_GET['date'] ?? date('Y-m-d');

$q = $conn->prepare("
  SELECT s.showtime_id, s.start_time, s.end_time,
         m.title, m.movie_id, m.poster_url, 
         r.room_id
  FROM showtimes s
  JOIN movies m ON m.movie_id=s.movie_id
  JOIN rooms r  ON r.room_id=s.room_id
  WHERE DATE(s.start_time)=?
  ORDER BY s.start_time
");
$q->bind_param("s", $date);
$q->execute();
$res = $q->get_result();
echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
