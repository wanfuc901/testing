<?php
session_start();

// ==== 1. Kiểm tra quyền admin ====
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Bạn không có quyền upload ảnh.");
}

// ==== 2. Kiểm tra dữ liệu upload ====
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file']['name'])) {
    exit("Chưa chọn file để upload.");
}

// ==== 3. Xác định loại ảnh ====
$type = $_POST['type'] ?? 'movie'; 
$allowed_types = ['movie', 'combo']; 
if (!in_array($type, $allowed_types)) {
    exit("Loại upload không hợp lệ.");
}

// ==== 4. Xác định thư mục đích ====
$targetDir = __DIR__ . '/views/' . $type . 's/'; // tự động /movies hoặc /combos

// Nếu chưa có thư mục thì tạo
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

// ==== 5. Kiểm tra định dạng file ====
$fileName = basename($_FILES['file']['name']);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowedExt)) {
    exit("Định dạng không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP.");
}

// ==== 6. Đặt tên và lưu file ====
$newName = uniqid("img_") . "." . $ext;
$targetFile = $targetDir . $newName;

if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
    echo "<p style='font-family:Poppins,sans-serif;color:#111'>
        ✅ Upload thành công: <b>$newName</b><br>
        📁 Lưu tại: <code>app/views/{$type}s/$newName</code>
    </p>";
} else {
    echo "❌ Upload thất bại. Kiểm tra quyền ghi thư mục.";
}
?>
