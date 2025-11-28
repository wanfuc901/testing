<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../config/config.php";

$email    = $_SESSION['reset_email'] ?? '';
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm'] ?? '');

$status = 'error';
$msgTitle = 'Lỗi hệ thống';
$msgText = 'Không thể đặt lại mật khẩu.';

// --- Kiểm tra quyền hợp lệ ---
if (empty($email) || empty($_SESSION['otp_verified'])) {
    $msgTitle = 'Phiên hết hạn';
    $msgText  = 'Vui lòng xác minh lại OTP trước khi đặt lại mật khẩu.';
    goto render;
}

// --- Kiểm tra nhập liệu ---
if ($password === '' || $confirm === '') {
    $msgTitle = 'Thiếu thông tin';
    $msgText  = 'Vui lòng nhập đầy đủ mật khẩu.';
    goto render;
}
if ($password !== $confirm) {
    $msgTitle = 'Mật khẩu không khớp';
    $msgText  = 'Vui lòng nhập lại cho chính xác.';
    goto render;
}

// --- Cập nhật mật khẩu ---
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE users SET password=? WHERE email=? LIMIT 1");
$stmt->bind_param("ss", $hashed, $email);

if ($stmt->execute()) {
    // ✅ Lấy lại thông tin người dùng để đăng nhập ngay
    $stmt2 = $conn->prepare("SELECT user_id, name, email, role FROM users WHERE email=? LIMIT 1");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();

    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['last_active'] = time();
    }

    unset($_SESSION['otp_verified'], $_SESSION['reset_email']);

    $status = 'success';
    $msgTitle = 'Đặt lại mật khẩu thành công';
    $msgText  = 'Hệ thống đang đăng nhập cho bạn...';
    $redirect = ($user['role'] === 'admin')
        ? "../../index.php?p=admin_dashboard"
        : "../../index.php?p=home";
} else {
    $msgTitle = 'Không thể cập nhật';
    $msgText  = 'Vui lòng thử lại sau.';
}

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
    title.innerText = "<?= $msgTitle ?>";
    text.innerText  = "<?= $msgText ?>";
    setTimeout(() => window.location.href = "<?= $redirect ?>", holdTime);
  <?php else: ?>
    errormark.style.display = 'block';
    title.innerText = "<?= $msgTitle ?>";
    text.innerText  = "<?= $msgText ?>";
    setTimeout(() => window.location.href = "../../public/verify_otp.php", holdTime);
  <?php endif; ?>
}, loadingTime);
</script>
</body>
</html>
