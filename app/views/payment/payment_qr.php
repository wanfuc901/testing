<?php
// payment_qr.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/app/config/config.php';

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) {
    echo "payment_id không hợp lệ";
    exit;
}

$sql = "SELECT * FROM payments WHERE payment_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL ERROR: " . $conn->error);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    echo "Không tìm thấy hóa đơn.";
    exit;
}

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thanh toán online</title>
</head>
<body>
    <h2>Thanh toán đơn: <?php echo htmlspecialchars($payment['provider_txn_id']); ?></h2>
    <p>Số tiền: <?php echo number_format($payment['amount'], 0, ',', '.'); ?> đ</p>

    <!-- Tùy bạn: hiển thị QR ngân hàng với nội dung mã đơn, v.v. -->
    <p>Vui lòng chuyển khoản với nội dung: <strong><?php echo htmlspecialchars($payment['provider_txn_id']); ?></strong></p>

    <p>Sau khi thanh toán, vui lòng chờ admin xác nhận hoặc reload để xem trạng thái.</p>
</body>
</html>
