<?php
/**
 * ĐÃ GỠ BỎ — endpoint nhật ký hoạt động.
 *
 * Lý do gỡ:
 *   - Ghép thẳng $_GET['role'] vào câu SQL (lỗ hổng SQL injection).
 *   - Không kiểm tra quyền, ai cũng gọi được.
 *   - Truy vấn bảng `activity_logs` không tồn tại trong schema.
 *   - Chỉ được gọi bởi app/include/admin_activity_widget.php, cũng là code chết.
 *
 * Có thể xóa hẳn: git rm app/api/get_activity.php
 */

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Endpoint đã ngừng hoạt động.';
