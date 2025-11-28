<?php
require __DIR__ . "/../config/config.php";
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$stmt = $conn->prepare(
  "SELECT movie_id, title, poster_url 
   FROM movies 
   WHERE title LIKE CONCAT('%', ?, '%') 
   ORDER BY release_date DESC 
   LIMIT 6"
);
$stmt->bind_param("s", $q);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $out[] = [
    'id'     => (int)$row['movie_id'],
    'title'  => $row['title'],
    'poster' => "/VincentCinemas/app/views/banners/" . $row['poster_url']
  ];
}
echo json_encode($out);
