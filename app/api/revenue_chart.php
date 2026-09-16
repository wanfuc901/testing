<?php
// app/api/revenue_chart.php — doanh thu theo ngày cho biểu đồ dashboard.
declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

header('Content-Type: application/json; charset=utf-8');
vincine_require_admin(true);

$sql = "
    SELECT DATE(booked_at) AS date,
           SUM(price) AS total
    FROM tickets
    WHERE status = 'confirmed' OR paid = 1
    GROUP BY DATE(booked_at)
    ORDER BY date ASC
";

$rs = $conn->query($sql);

if ($rs === false) {
    error_log('[vincine] revenue_chart query failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Không tải được dữ liệu doanh thu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];
while ($row = $rs->fetch_assoc()) {
    $data[] = [
        'date'  => $row['date'],
        'total' => (float)$row['total'],
    ];
}

echo json_encode($data);
