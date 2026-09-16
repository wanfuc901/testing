<?php
/**
 * Guard drop-in cho mọi endpoint quản trị.
 *
 * Các file trong app/controllers/admin, app/api và admin/ nằm dưới webroot nên
 * gọi thẳng bằng URL được — checkAdmin() trong main.php không bảo vệ chúng.
 * require_once file này ở dòng đầu tiên để chặn từ tầng endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

vincine_require_admin();
