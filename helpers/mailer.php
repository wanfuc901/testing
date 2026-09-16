<?php
/**
 * Khởi tạo PHPMailer với cấu hình SMTP lấy từ biến môi trường.
 *
 * Thay cho việc lặp lại host/username/password ở từng controller.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('vincine_mailer')) {
    /**
     * @throws RuntimeException khi SMTP chưa được cấu hình.
     */
    function vincine_mailer(): PHPMailer
    {
        $username = (string)vincine_env('MAIL_USERNAME', '');
        $password = (string)vincine_env('MAIL_PASSWORD', '');

        if ($username === '' || $password === '') {
            throw new RuntimeException(
                'SMTP chưa được cấu hình. Điền MAIL_USERNAME và MAIL_PASSWORD trong app/config/env.php.'
            );
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string)vincine_env('MAIL_HOST', 'smtp.gmail.com');
        $mail->Port       = (int)vincine_env('MAIL_PORT', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $from     = (string)vincine_env('MAIL_FROM', $username);
        $fromName = (string)vincine_env('MAIL_FROM_NAME', 'VinCine Support');
        $mail->setFrom($from, $fromName);

        return $mail;
    }
}
