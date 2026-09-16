<?php
/**
 * Sinh suất chiếu cho N ngày tới.
 *
 * Chạy bằng CLI (cron) hoặc bởi admin đã đăng nhập. Trước đây file này
 * không kiểm tra gì cả nên bất kỳ ai cũng gọi được và ghi hàng loạt bản
 * ghi vào bảng showtimes.
 */

declare(strict_types=1);

const CRON_MIN_DAYS = 1;
const CRON_MAX_DAYS = 60;
const CRON_DEFAULT_DAYS = 7;

$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    require_once __DIR__ . '/../config/config.php';
} else {
    // Qua HTTP thì bắt buộc là admin đã đăng nhập.
    require_once __DIR__ . '/../include/require_admin.php';
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../models/ShowtimeModel.php';

$rawDays = $isCli
    ? ($argv[1] ?? CRON_DEFAULT_DAYS)
    : ($_GET['days'] ?? CRON_DEFAULT_DAYS);

$days = max(CRON_MIN_DAYS, min(CRON_MAX_DAYS, (int)$rawDays));

$created = 0;
for ($i = 0; $i < $days; $i++) {
    $date = date('Y-m-d', strtotime("+$i day"));

    try {
        $result = ShowtimeModel::generateForDate($conn, $date);
        $created += (int)($result['created'] ?? 0);
    } catch (Throwable $e) {
        error_log('[vincine] generate_showtimes lỗi ngày ' . $date . ': ' . $e->getMessage());
    }
}

echo "generated={$created}\n";
