<?php
/**
 * ĐÃ GỠ BỎ.
 *
 * File này trước đây chỉ chứa một đoạn <script> JavaScript, không có mã PHP,
 * nhưng lại được gọi bằng fetch(..., {method:"POST"}) như một endpoint hủy
 * đơn. Kết quả: request trả về chính đoạn script đó và không hủy gì cả.
 *
 * Việc hủy đơn khi hết giờ giữ ghế do app/controllers/payment_callback.php
 * xử lý với action=auto_cancel.
 *
 * Có thể xóa hẳn: git rm app/controllers/payment_cancel.php
 */

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Endpoint đã ngừng hoạt động. Dùng payment_callback.php?action=auto_cancel.';
