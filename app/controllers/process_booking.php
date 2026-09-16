<?php
/**
 * ĐÃ NGỪNG SỬ DỤNG — luồng đặt vé cũ theo users.user_id.
 *
 * Schema hiện tại đặt vé theo customers.customer_id; file này chèn vào các cột
 * `payments.user_id` và `tickets.user_id` không còn tồn tại nên luôn lỗi SQL.
 * Luồng đang chạy thật là app/controllers/checkout_online.php.
 *
 * File được giữ lại làm tombstone để mọi bookmark/link cũ nhận 410 thay vì
 * lỗi 500. Có thể xóa hẳn: git rm app/controllers/process_booking.php
 */

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Luồng đặt vé này đã ngừng hoạt động. Vui lòng đặt vé lại từ trang chọn ghế.";
