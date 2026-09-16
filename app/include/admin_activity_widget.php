<?php
/**
 * ĐÃ GỠ BỎ — widget nhật ký hoạt động trên dashboard.
 *
 * Không file nào include widget này. Nội dung cũ ghép $_GET['role'] vào SQL,
 * sinh câu lệnh hỏng khi role='all' ("... AND ..." không có WHERE) và đọc bảng
 * `activity_logs` không tồn tại.
 *
 * Có thể xóa hẳn: git rm app/include/admin_activity_widget.php
 */

declare(strict_types=1);
