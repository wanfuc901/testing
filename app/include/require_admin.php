<?php
/**
 * Guard drop-in cho mọi endpoint quản trị.
 *
 * Các file trong app/controllers/admin, app/api và admin/ nằm dưới webroot nên
 * gọi thẳng bằng URL được — checkAdmin() trong main.php không bảo vệ chúng.
 * require_once file này ở dòng đầu để chặn từ tầng endpoint.
 *
 * Kiểm tra CSRF nằm luôn ở đây để không endpoint POST nào bị bỏ sót;
 * với request GET thì vincine_verify_csrf() trả về ngay.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

vincine_require_admin();
vincine_verify_csrf();
