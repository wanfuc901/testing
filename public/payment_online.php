<?php
/**
 * ĐÃ GỠ BỎ — trang thanh toán chuyển khoản cũ.
 *
 * Không tuyến nào dẫn tới trang này nữa: luồng đang chạy là
 * checkout_online.php -> app/views/payment/payment_qr.php.
 *
 * Nội dung cũ còn ba lỗi chưa từng được sửa:
 *   - Biểu mẫu gửi transaction_id + action=simulate_success, trong khi
 *     payment_callback.php chỉ nhận payment_id -> không bao giờ chạy đúng.
 *   - Hết giờ thì gọi payment_cancel.php vốn chỉ là một file JavaScript,
 *     nên đơn hàng không bao giờ được hủy.
 *   - Tên chủ tài khoản, số tài khoản, ghế và nội dung chuyển khoản in
 *     thẳng ra HTML không escape.
 *
 * Có thể xóa hẳn: git rm public/payment_online.php
 */

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Trang này đã ngừng hoạt động. Vui lòng đặt vé lại từ trang chọn ghế.';
