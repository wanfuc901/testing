<?php
/**
 * Đăng ký URL webhook với PayOS và tự kiểm tra cấu hình.
 *
 * Chạy bằng dòng lệnh:
 *     php app/cron/payos_register_webhook.php
 *     php app/cron/payos_register_webhook.php https://ten-mien/app/api/payos_webhook.php
 *
 * Không truyền tham số thì lấy PAYOS_WEBHOOK_URL trong app/config/env.php.
 *
 * PayOS sẽ gọi thử URL này; nó phải công khai trên Internet và trả về HTTP 200.
 * localhost không dùng được — dùng ngrok hoặc domain thật.
 *
 * Qua HTTP thì chỉ admin đã đăng nhập mới chạy được.
 */

declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    require_once __DIR__ . '/../config/config.php';
} else {
    require_once __DIR__ . '/../include/require_admin.php';
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../../helpers/payos.php';

/** In một dòng kết quả. */
function line(string $label, string $value): void
{
    echo str_pad($label, 26) . ': ' . $value . PHP_EOL;
}

echo "=== Kiểm tra cấu hình PayOS ===" . PHP_EOL;

$config = vincine_payos_config();

foreach (['client_id' => 36, 'api_key' => 36, 'checksum_key' => 64] as $key => $expectedLength) {
    $actual = strlen($config[$key]);

    if ($actual === 0) {
        line($key, 'THIẾU — chưa điền trong app/config/env.php');
        continue;
    }

    /*
     * Checksum key trên dashboard PayOS hiển thị bị cắt trong ô nhập, rất dễ
     * copy thiếu. Sai độ dài thì mọi chữ ký đều hỏng nên cảnh báo ngay.
     */
    $note = ($actual === $expectedLength)
        ? 'OK'
        : 'NGHI NGỜ THIẾU KÝ TỰ (mong đợi ' . $expectedLength . ')';

    line($key, $actual . ' ký tự — ' . $note);
}

if (!vincine_payos_enabled()) {
    echo PHP_EOL . 'Chưa đủ khóa, dừng lại.' . PHP_EOL;
    exit(1);
}

$webhookUrl = $isCli
    ? trim((string)($argv[1] ?? vincine_env('PAYOS_WEBHOOK_URL', '')))
    : trim((string)($_GET['url'] ?? vincine_env('PAYOS_WEBHOOK_URL', '')));

echo PHP_EOL . '=== Đăng ký webhook ===' . PHP_EOL;
line('URL', $webhookUrl !== '' ? $webhookUrl : '(trống)');

if ($webhookUrl === '') {
    echo PHP_EOL
       . 'Chưa có URL. Điền PAYOS_WEBHOOK_URL trong app/config/env.php' . PHP_EOL
       . 'hoặc truyền vào: php app/cron/payos_register_webhook.php https://.../payos_webhook.php' . PHP_EOL;
    exit(1);
}

if (stripos($webhookUrl, 'https://') !== 0) {
    echo PHP_EOL . 'CẢNH BÁO: PayOS yêu cầu HTTPS. URL http:// nhiều khả năng bị từ chối.' . PHP_EOL;
}

try {
    $result = vincine_payos_confirm_webhook($webhookUrl);
    echo PHP_EOL . 'THÀNH CÔNG — PayOS đã nhận webhook URL.' . PHP_EOL;

    foreach ($result as $key => $value) {
        if (is_scalar($value)) {
            line((string)$key, (string)$value);
        }
    }
} catch (Throwable $e) {
    echo PHP_EOL . 'THẤT BẠI: ' . $e->getMessage() . PHP_EOL . PHP_EOL
       . 'Kiểm tra lại:' . PHP_EOL
       . '  - URL truy cập được từ Internet (không phải localhost)' . PHP_EOL
       . '  - Mở thẳng URL đó trả về HTTP 200, không phải 403/404/500' . PHP_EOL
       . '  - Checksum key đủ 64 ký tự' . PHP_EOL;
    exit(1);
}
