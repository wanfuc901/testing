<?php
require_once __DIR__ . '/../../include/require_admin.php';
/**
 * Cập nhật tài khoản nhận tiền (VietQR)
 * - Chỉ admin đã đăng nhập mới được phép
 * - Mỗi lần cập nhật: vô hiệu hóa account cũ, thêm bản ghi mới active
 */


/* ============================
   CHỈ CHO PHÉP POST
============================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

/* ============================
   LẤY & VALIDATE INPUT
============================ */
$bank_code      = trim($_POST['bank_code'] ?? '');
$account_name   = trim($_POST['account_name'] ?? '');
$account_number = trim($_POST['account_number'] ?? '');

if ($bank_code === '' || $account_name === '' || $account_number === '') {
    die('Thiếu dữ liệu bắt buộc');
}

/* ============================
   MAP BANK CODE → BANK NAME
   (chuẩn VietQR BIN)
============================ */
$bankNames = [
    '970422' => 'MB Bank',
    '970436' => 'Vietcombank',
    '970415' => 'VietinBank',
    '970407' => 'Techcombank',
    '970418' => 'BIDV',
];

$bank_name = $bankNames[$bank_code] ?? 'Ngân hàng';

/* ============================
   TRANSACTION AN TOÀN
============================ */
$conn->begin_transaction();

try {

    /* 1. Disable tất cả tài khoản cũ */
    $conn->query("UPDATE payment_accounts SET is_active = 0");

    /* 2. Insert tài khoản mới */
    $stmt = $conn->prepare("
        INSERT INTO payment_accounts
        (account_name, account_number, bank_code, bank_name, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");

    if (!$stmt) {
        throw new Exception('Prepare failed');
    }

    $stmt->bind_param(
        "ssss",
        $account_name,
        $account_number,
        $bank_code,
        $bank_name
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed');
    }

    $stmt->close();

    /* 3. Commit */
    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();
    die('Không thể cập nhật tài khoản thanh toán');

}

/* ============================
   REDIRECT VỀ DASHBOARD
============================ */
header("Location: ../../../index.php?p=admin_dashboard");
exit;
