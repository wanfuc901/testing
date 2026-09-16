<?php
/**
 * ĐÃ GỠ BỎ — trang thanh toán MoMo cũ.
 *
 * Không tuyến nào dẫn tới trang này. Biểu mẫu của nó gửi tới
 * payment_callback.php với tham số không còn khớp nên không hoạt động.
 *
 * Có thể xóa hẳn: git rm public/payment_online_momo.php
 */

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Trang này đã ngừng hoạt động. Vui lòng đặt vé lại từ trang chọn ghế.';
