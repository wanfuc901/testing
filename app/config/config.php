<?php
// Ngăn việc include nhiều lần
if (!defined("VINCINE_CONFIG_LOADED")) {
    define("VINCINE_CONFIG_LOADED", true);

    // Phân biệt môi trường LOCAL hoặc HOSTING
    // LOCAL: đường dẫn gốc chứa "xampp" hoặc đang chạy Windows
    $isLocal = (stripos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false)
            || (PHP_OS_FAMILY === 'Windows');

    // === LOCAL (XAMPP) ===
    if ($isLocal) {
        $dbHost = "127.0.0.1";   // luôn dùng 127.0.0.1 để chạy ngrok không lỗi
        $dbUser = "root";
        $dbPass = "";
        $dbName = "vincine";
    }
    // === HOSTING (InfinityFree) ===
    else {
        $dbHost = "sql307.infinityfree.com";
        $dbUser = "if0_40205234";
        $dbPass = "hoangphuc901";
        $dbName = "if0_40205234_vincine";
    }

    // Kết nối MySQL
    $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("<b>Lỗi kết nối MySQL:</b> " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    // Ngăn define trùng
    if (!defined('QR_SECRET')) {
        define('QR_SECRET', 'VinCine_Secure_Key_2025');
    }

    date_default_timezone_set('Asia/Ho_Chi_Minh');
}
?>
