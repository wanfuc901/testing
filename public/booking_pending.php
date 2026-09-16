<?php
/**
 * Màn hình chờ admin xác nhận chuyển khoản. Chỉ chủ đơn mới xem được.
 */

require_once __DIR__ . '/../app/include/auth.php';

$payment_id = (int)($_GET['pid'] ?? 0);
if ($payment_id <= 0) {
    http_response_code(400);
    exit('Thiếu payment_id.');
}

vincine_require_customer();
$customer_id = vincine_customer_id();

/* Lấy bill của chính khách hàng đang đăng nhập */
$sql = "
    SELECT p.*, c.fullname
    FROM payments p
    LEFT JOIN customers c ON c.customer_id = p.customer_id
    WHERE p.payment_id = ? AND p.customer_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('[vincine] booking_pending prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Hệ thống đang bận. Vui lòng thử lại sau.');
}

$stmt->bind_param('ii', $payment_id, $customer_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pay) {
    http_response_code(404);
    exit('Không tìm thấy hóa đơn.');
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đơn hàng đang chờ xác nhận</title>
<link rel="stylesheet" href="../public/assets/css/style.css">
</head>

<body class="pending-body">
    
<div class="pending-container">
  <div class="pending-card">

    <h2>Đơn hàng đang chờ xác nhận</h2>

    <p><strong>Mã đơn:</strong> <?= htmlspecialchars($pay['provider_txn_id']) ?></p>
    <p><strong>Số tiền:</strong> <?= number_format($pay['amount'],0,',','.') ?>₫</p>
    <p><strong>Khách hàng:</strong> <?= htmlspecialchars($pay['fullname'] ?? "Khách") ?></p>
    <p><strong>Trạng thái:</strong> 
        <span style="color:#f5b400;font-weight:bold;">
            <?= $pay['status'] ?>
        </span>
    </p>

    <hr>

    <h3>Vui lòng chuyển khoản theo hướng dẫn trong email hoặc trang thanh toán.</h3>
    <p>Sau khi admin xác nhận, bạn sẽ nhận được vé tại trang <strong>Hóa đơn của tôi</strong>.</p>

    <a href="../index.php" class="btn-home">Về trang chủ</a>
  </div>
</div>

</body>
</html>
