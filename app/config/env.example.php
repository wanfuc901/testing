<?php
/**
 * Mẫu cấu hình môi trường cho Vincent Cinemas.
 *
 * Copy file này thành `env.php` (cùng thư mục) rồi điền giá trị thật.
 * `env.php` nằm trong .gitignore nên KHÔNG bao giờ được commit.
 *
 *   cp app/config/env.example.php app/config/env.php
 *
 * Mọi khóa ở đây cũng có thể đặt bằng biến môi trường cùng tên
 * (ví dụ trên hosting hoặc trong Docker), env.php có độ ưu tiên cao hơn.
 */

declare(strict_types=1);

return [
    /* === Cơ sở dữ liệu === */
    'DB_HOST' => '127.0.0.1',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'vincine',
    'DB_PORT' => 3306,

    /* === Ứng dụng === */
    // true: hiện lỗi PHP ra trình duyệt (chỉ dùng khi dev trên máy cá nhân)
    'APP_DEBUG' => false,
    // Khóa ký chữ ký QR vé. Sinh bằng: php -r "echo bin2hex(random_bytes(32));"
    'QR_SECRET' => 'thay-bang-chuoi-ngau-nhien-64-ky-tu',

    /* === SMTP gửi mail (Gmail App Password) === */
    'MAIL_HOST'      => 'smtp.gmail.com',
    'MAIL_PORT'      => 587,
    'MAIL_USERNAME'  => '',
    'MAIL_PASSWORD'  => '',
    'MAIL_FROM'      => '',
    'MAIL_FROM_NAME' => 'VinCine Support',

    /* === Đăng nhập Google (OAuth 2.0 Client ID) === */
    // Lấy tại https://console.cloud.google.com/apis/credentials
    'GOOGLE_CLIENT_ID' => '',
];
