<?php
include __DIR__ . "/../config/config.php";
include __DIR__ . "/../models/Movie.php";

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    header("Location: index.php?p=home");
    exit;
}

$movieModel = new Movie($conn);
$results = $movieModel->search($q);

include __DIR__ . "/../views/layouts/search_result.php";

?>
