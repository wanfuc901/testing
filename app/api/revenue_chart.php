<?php
require_once __DIR__ . "/../../app/config/config.php";

header("Content-Type: application/json; charset=utf-8");

$sql = "
    SELECT 
        DATE(booked_at) AS date,
        SUM(price) AS total
    FROM tickets
    WHERE (status='confirmed' OR paid = 1)
    GROUP BY DATE(booked_at)
    ORDER BY date ASC
";

$rs = $conn->query($sql);

$data = [];
while ($row = $rs->fetch_assoc()) {
    $data[] = [
        "date" => $row["date"],       // yyyy-mm-dd
        "total" => (float)$row["total"]
    ];
}

echo json_encode($data);
