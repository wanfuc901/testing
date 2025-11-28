<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Lấy doanh thu từng ngày trong tháng hiện tại
$sql = "
    SELECT 
        DATE(paid_at) as date,
        SUM(amount) as total
    FROM payments
    WHERE status = 'success'
      AND amount > 0
      AND DATE_FORMAT(paid_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
    GROUP BY DATE(paid_at)
    ORDER BY DATE(paid_at)
";

$rs = $conn->query($sql);
$data = [];

while($row = $rs->fetch_assoc()){
    $data[] = [
        'date'  => $row['date'],
        'total' => (float)$row['total']
    ];
}

echo json_encode($data);
