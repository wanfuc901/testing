<?php
/**
 * Đặt lại mật khẩu sau khi đã xác minh OTP.
 * Hỗ trợ cả tài khoản khách hàng (customers) lẫn nhân sự nội bộ (users).
 */

require_once __DIR__ . '/../include/auth.php';

/** Độ dài mật khẩu tối thiểu. */
const RESET_MIN_PASSWORD_LENGTH = 8;

$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm'] ?? '');

$account = $_SESSION['reset_account'] ?? null;
$email   = (string)($_SESSION['reset_email'] ?? '');

$status   = 'error';
$msgTitle = 'Lỗi hệ thống';
$msgText  = 'Không thể đặt lại mật khẩu.';
$redirect = '../../index.php?p=login';

/* --- Chỉ chấp nhận POST --- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $msgTitle = 'Yêu cầu không hợp lệ';
    $msgText  = 'Vui lòng gửi biểu mẫu đặt lại mật khẩu.';
    goto render;
}

vincine_verify_csrf(false);

/* --- Phải qua bước xác minh OTP --- */
if ($email === '' || empty($_SESSION['otp_verified']) || !is_array($account)) {
    $msgTitle = 'Phiên hết hạn';
    $msgText  = 'Vui lòng xác minh lại OTP trước khi đặt lại mật khẩu.';
    $redirect = '../../index.php?p=fp';
    goto render;
}

/* --- Kiểm tra nhập liệu --- */
if ($password === '' || $confirm === '') {
    $msgTitle = 'Thiếu thông tin';
    $msgText  = 'Vui lòng nhập đầy đủ mật khẩu.';
    goto render;
}

if (mb_strlen($password) < RESET_MIN_PASSWORD_LENGTH) {
    $msgTitle = 'Mật khẩu quá ngắn';
    $msgText  = 'Mật khẩu phải có ít nhất ' . RESET_MIN_PASSWORD_LENGTH . ' ký tự.';
    goto render;
}

if (!hash_equals($password, $confirm)) {
    $msgTitle = 'Mật khẩu không khớp';
    $msgText  = 'Vui lòng nhập lại cho chính xác.';
    goto render;
}

/* --- Cập nhật đúng bảng chứa tài khoản --- */
$isCustomer = ($account['table'] ?? '') === 'customers';
$sql = $isCustomer
    ? 'UPDATE customers SET password = ? WHERE customer_id = ? LIMIT 1'
    : 'UPDATE users SET password = ? WHERE user_id = ? LIMIT 1';

$hashed    = password_hash($password, PASSWORD_BCRYPT);
$accountId = (int)$account['id'];

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('[vincine] reset_password prepare failed: ' . $conn->error);
    $msgTitle = 'Không thể cập nhật';
    $msgText  = 'Vui lòng thử lại sau.';
    goto render;
}

$stmt->bind_param('si', $hashed, $accountId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    error_log('[vincine] reset_password update failed: ' . $conn->error);
    $msgTitle = 'Không thể cập nhật';
    $msgText  = 'Vui lòng thử lại sau.';
    goto render;
}

/* --- Đăng nhập lại bằng phiên mới --- */
vincine_start_authenticated_session();

unset($_SESSION['otp_verified'], $_SESSION['reset_email'], $_SESSION['reset_account']);

if ($isCustomer) {
    $stmt = $conn->prepare('SELECT customer_id, fullname, email FROM customers WHERE customer_id = ? LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $_SESSION['customer_id'] = $accountId;
    $_SESSION['fullname']    = (string)($row['fullname'] ?? '');
    $_SESSION['email']       = (string)($row['email'] ?? $email);
    $_SESSION['role']        = VINCINE_ROLE_CUSTOMER;
    $redirect                = '../../index.php?p=home';
} else {
    $stmt = $conn->prepare('SELECT user_id, name, email, role FROM users WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $role = in_array($row['role'] ?? '', [VINCINE_ROLE_ADMIN, VINCINE_ROLE_STAFF], true)
        ? (string)$row['role']
        : VINCINE_ROLE_STAFF;

    $_SESSION['user_id'] = $accountId;
    $_SESSION['name']    = (string)($row['name'] ?? '');
    $_SESSION['email']   = (string)($row['email'] ?? $email);
    $_SESSION['role']    = $role;
    $redirect            = ($role === VINCINE_ROLE_ADMIN)
        ? '../../index.php?p=admin_dashboard'
        : '../../index.php?p=home';
}

$_SESSION['last_active'] = time();

$status   = 'success';
$msgTitle = 'Đặt lại mật khẩu thành công';
$msgText  = 'Hệ thống đang đăng nhập cho bạn...';

render:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đặt lại mật khẩu...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">
<style>
body {
  margin:0;
  font-family:'Poppins',sans-serif;
  background:var(--bg,#0d0d0d);
  color:var(--text,#fff);
}
.loading-page {
  display:flex;
  justify-content:center;
  align-items:center;
  height:100vh;
}
.box {
  text-align:center;
  background:var(--card,#111);
  border-radius:20px;
  padding:40px 60px;
  border:1px solid rgba(255,255,255,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.5);
  max-width:420px;
  width:90%;
}
h2 {color:var(--gold,#d4af37);font-size:22px;margin:12px 0;}
p {color:#bbb;font-size:15px;margin-top:4px;}

/* Loading spinner */
.spinner {
  width:50px;height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold,#d4af37);
  border-radius:50%;
  animation:spin .9s linear infinite;
  margin:0 auto 25px;
}
@keyframes spin {to{transform:rotate(360deg);}}

/* Tick / X animation */
.checkmark, .errormark {
  width:90px;height:90px;
  border-radius:50%;
  display:none;
  stroke-width:3;
  stroke-miterlimit:10;
  margin:0 auto 12px;
}
.checkmark {stroke:#4BB71B;}
.errormark {stroke:#e74c3c;}
.checkmark__circle, .errormark__circle {
  stroke-dasharray:166;stroke-dashoffset:166;
  fill:none;
  animation:stroke 0.6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.checkmark__check, .errormark__cross {
  transform-origin:50% 50%;
  stroke-dasharray:48;stroke-dashoffset:48;
  fill:none;stroke:#fff;
  animation:stroke 0.3s cubic-bezier(0.65,0,0.45,1) 0.6s forwards;
}
@keyframes stroke {100%{stroke-dashoffset:0;}}
</style>
</head>
<body class="loading-page">
  <div class="box">
    <div class="spinner" id="spinner"></div>

    <!-- Tick xanh -->
    <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark__circle" cx="26" cy="26" r="25"/>
      <path class="checkmark__check" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>

    <!-- X đỏ -->
    <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="errormark__circle" cx="26" cy="26" r="25"/>
      <path class="errormark__cross" d="M16 16 36 36 M36 16 16 36"/>
    </svg>

    <h2 id="msgTitle">Đang xử lý...</h2>
    <p id="msgText">Vui lòng chờ trong giây lát</p>
  </div>

<script>
const spinner = document.getElementById('spinner');
const checkmark = document.getElementById('checkmark');
const errormark = document.getElementById('errormark');
const title = document.getElementById('msgTitle');
const text = document.getElementById('msgText');

const loadingTime = 1800;
const holdTime = 2800;

setTimeout(() => {
  spinner.style.display = 'none';
  <?php if ($status === 'success'): ?>
    checkmark.style.display = 'block';
    title.innerText = <?= json_encode($msgTitle, JSON_UNESCAPED_UNICODE) ?>;
    text.innerText  = <?= json_encode($msgText, JSON_UNESCAPED_UNICODE) ?>;
    setTimeout(() => window.location.href = <?= json_encode($redirect) ?>, holdTime);
  <?php else: ?>
    errormark.style.display = 'block';
    title.innerText = <?= json_encode($msgTitle, JSON_UNESCAPED_UNICODE) ?>;
    text.innerText  = <?= json_encode($msgText, JSON_UNESCAPED_UNICODE) ?>;
    setTimeout(() => window.location.href = "../../public/verify_otp.php", holdTime);
  <?php endif; ?>
}, loadingTime);
</script>
</body>
</html>
