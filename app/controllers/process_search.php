<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/movie.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    header("Location: index.php?p=home");
    exit;
}

$movieModel = new Movie($conn);
$results = $movieModel->search($q);

include __DIR__ . "/../views/layouts/search_result.php";

?>
