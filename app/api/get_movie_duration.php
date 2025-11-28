<?php
require_once __DIR__ . '/../config/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$id = intval($_GET['id'] ?? 0);
$q = $conn->prepare("SELECT duration FROM movies WHERE movie_id=?");
$q->bind_param("i", $id);
$q->execute();
$res = $q->get_result()->fetch_assoc();
echo json_encode($res ?: ['duration'=>0], JSON_UNESCAPED_UNICODE);
