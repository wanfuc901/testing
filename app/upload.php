<?php
/**
 * Upload ảnh poster phim / ảnh combo từ khu vực quản trị.
 *
 * Trả về tên file đã lưu để form ghi vào cột image/poster_url.
 */

declare(strict_types=1);

require_once __DIR__ . '/include/require_admin.php';

/** Dung lượng tối đa mỗi ảnh (byte). */
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

/** Loại upload hợp lệ => thư mục đích tương ứng. */
const UPLOAD_TYPES = [
    'movie' => 'movies',
    'combo' => 'combos',
];

/** Đuôi file hợp lệ => MIME thật mà getimagesize phải trả về. */
const UPLOAD_ALLOWED_MIME = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

header('Content-Type: text/html; charset=utf-8');

/**
 * Kết thúc request với một thông báo đã escape.
 */
function upload_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo '<p style="font-family:Poppins,sans-serif;color:#c0392b">❌ '
       . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_fail('Phương thức không hợp lệ.', 405);
}

$file = $_FILES['file'] ?? null;

if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    upload_fail('Chưa chọn file để upload.');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $reason = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'File vượt quá dung lượng cho phép.'
        : 'Upload thất bại (mã lỗi ' . (int)$file['error'] . ').';
    upload_fail($reason);
}

if ((int)$file['size'] > UPLOAD_MAX_BYTES) {
    upload_fail('Ảnh tối đa ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . 'MB.');
}

/* === Loại upload === */
$type = (string)($_POST['type'] ?? 'movie');
if (!isset(UPLOAD_TYPES[$type])) {
    upload_fail('Loại upload không hợp lệ.');
}

/* === Đuôi file === */
$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if (!isset(UPLOAD_ALLOWED_MIME[$extension])) {
    upload_fail('Định dạng không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP.');
}

/*
 * Kiểm tra nội dung thật của file, không tin phần mở rộng:
 * một file PHP đổi tên thành .png sẽ bị chặn ở đây.
 */
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== UPLOAD_ALLOWED_MIME[$extension]) {
    upload_fail('File không phải ảnh hợp lệ.');
}

/* === Thư mục đích === */
$targetDir = __DIR__ . '/views/' . UPLOAD_TYPES[$type] . '/';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    error_log('[vincine] upload: không tạo được thư mục ' . $targetDir);
    upload_fail('Không tạo được thư mục lưu ảnh.', 500);
}

/* === Lưu file với tên do server sinh === */
$newName    = uniqid('img_', true) . '.' . $extension;
$targetFile = $targetDir . $newName;

if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
    error_log('[vincine] upload: move_uploaded_file thất bại -> ' . $targetFile);
    upload_fail('Upload thất bại. Kiểm tra quyền ghi thư mục.', 500);
}

$publicPath = 'app/views/' . UPLOAD_TYPES[$type] . '/' . $newName;

echo '<p style="font-family:Poppins,sans-serif;color:#111">'
   . '✅ Upload thành công: <b>' . htmlspecialchars($newName, ENT_QUOTES, 'UTF-8') . '</b><br>'
   . '📁 Lưu tại: <code>' . htmlspecialchars($publicPath, ENT_QUOTES, 'UTF-8') . '</code>'
   . '</p>';
