<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/config.php";

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

$status   = 'error';
$msgTitle = '';
$msgText  = '';
$redirect = 'index.php?p=login';


/* ====================================================
   1) KIỂM TRA INPUT
==================================================== */
if ($email === '' || $password === '') {
    $msgTitle = 'Thiếu thông tin';
    $msgText  = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    goto output;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msgTitle = 'Email không hợp lệ';
    $msgText  = 'Vui lòng kiểm tra lại email.';
    goto output;
}


/* ====================================================
   2) ĐĂNG NHẬP USER (ADMIN / NHÂN VIÊN)
   ƯU TIÊN KIỂM TRA TRƯỚC
==================================================== */
$stmt = $conn->prepare("
    SELECT user_id, name, email, password, role
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$rsUser = $stmt->get_result();

if ($rsUser->num_rows > 0) {

    $u = $rsUser->fetch_assoc();

    if (!password_verify($password, $u['password'])) {
        $msgTitle = 'Sai mật khẩu';
        $msgText  = 'Vui lòng thử lại.';
        goto output;
    }

    // LOGIN USER OK
    $_SESSION['user_id']     = $u['user_id'];
    $_SESSION['name']        = $u['name'];
    $_SESSION['email']       = $u['email'];
    $_SESSION['role']        = $u['role'];
    $_SESSION['last_active'] = time();

    $status   = 'success';
    $msgTitle = 'Đăng nhập thành công';
    $msgText  = "Xin chào, " . htmlspecialchars($u['name']) . "!";

    $redirect = ($u['role'] === 'admin')
        ? "index.php?p=admin_dashboard"
        : "index.php?p=home";

    goto output;
}


/* ====================================================
   3) ĐĂNG NHẬP CUSTOMER (KHÁCH HÀNG)
==================================================== */
$stmt = $conn->prepare("
    SELECT customer_id, fullname, email, password
    FROM customers
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$rsCus = $stmt->get_result();

if ($rsCus->num_rows === 0) {
    $msgTitle = 'Email không tồn tại';
    $msgText  = 'Không tìm thấy tài khoản.';
    goto output;
}

$c = $rsCus->fetch_assoc();


/* ==========================================
   4) TK GOOGLE → PASSWORD RỖNG
========================================== */
if ($c['password'] === '' || $c['password'] === null) {
    $msgTitle = "Tài khoản Google";
    $msgText  = "Tài khoản này đăng ký bằng Google. 
Vui lòng đăng nhập bằng Google thay vì mật khẩu.";
    goto output;
}


/* ==========================================
   5) KIỂM TRA PASSWORD CUSTOMER
========================================== */
if (!password_verify($password, $c['password'])) {
    $msgTitle = 'Sai mật khẩu';
    $msgText  = 'Vui lòng thử lại.';
    goto output;
}


/* ==========================================
   6) LOGIN CUSTOMER OK
========================================== */
$_SESSION['customer_id'] = $c['customer_id'];
$_SESSION['fullname']    = $c['fullname'];
$_SESSION['email']       = $c['email'];
$_SESSION['role']        = "customer";
$_SESSION['last_active'] = time();

$status   = 'success';
$msgTitle = 'Đăng nhập thành công';
$msgText  = 'Chào mừng trở lại, ' . htmlspecialchars($c['fullname']) . '!';
$redirect = "index.php?p=home";


/* ====================================================
   7) XUẤT HTML HIỆU ỨNG
==================================================== */
output:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập...</title>
<link rel="stylesheet" href="public/assets/css/style.css">
<style>
 .loading-page{
  position: relative;
  min-height: 100vh;
  background: var(--bg);
  color: var(--text);
  font-family: 'Poppins', sans-serif;
  margin: 0;
}

.box {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  padding: 40px 60px;
  background: var(--card);
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.5);
  max-width: 420px;
  width: 90%;
}

h2 {
  color:var(--gold);
  font-size:24px;
  font-weight:700;
  margin:10px 0;
}

p {
  color:var(--muted);
  font-size:15px;
  margin-top:4px;
}

@keyframes spin { to { transform: rotate(360deg); } }

.spinner {
  width:50px;
  height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);
  border-radius:50%;
  animation:spin 0.9s linear infinite;
  margin:0 auto 25px;
}

.checkmark, .errormark {
  width:90px;
  height:90px;
  border-radius:50%;
  display:none;
  stroke-width:3;
  stroke-miterlimit:10;
  margin:0 auto 12px;
}

.checkmark { stroke:#4BB71B; animation:scale .3s ease-in-out .9s both; }
.checkmark__circle {
  stroke-dasharray:166;
  stroke-dashoffset:166;
  stroke-width:3;
  fill:none;
  stroke:#4BB71B;
  animation:stroke 0.6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.checkmark__check {
  stroke-dasharray:48;
  stroke-dashoffset:48;
  fill:none;
  stroke:#fff;
  animation:stroke 0.3s cubic-bezier(0.65,0,0.45,1) 0.6s forwards;
}

.errormark { stroke:#e74c3c; animation:scale .3s ease-in-out .9s both; }
.errormark__circle {
  stroke-dasharray:166;
  stroke-dashoffset:166;
  stroke-width:3;
  fill:none;
  stroke:#e74c3c;
  animation:stroke 0.6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.errormark__cross {
  stroke-dasharray:48;
  stroke-dashoffset:48;
  fill:none;
  stroke:#fff;
  animation:stroke 0.3s cubic-bezier(0.65,0,0.45,1) 0.6s forwards;
}

@keyframes stroke { 100% { stroke-dashoffset:0; } }
@keyframes scale {
  0%,100% { transform:none; }
  50% { transform:scale(1.05); }
}
</style>
</head>
<body class="loading-page">
  <div class="box">
    <div class="spinner" id="spinner"></div>

    <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark__circle" cx="26" cy="26" r="25"/>
      <path class="checkmark__check" d="M14 27l7 7 17-17"/>
    </svg>

    <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="errormark__circle" cx="26" cy="26" r="25"/>
      <path class="errormark__cross" d="M16 16 36 36 M36 16 16 36"/>
    </svg>

    <h2 id="msgTitle"><?= $msgTitle ?></h2>
    <p id="msgText"><?= $msgText ?></p>
  </div>

<script>
setTimeout(() => {
  document.getElementById('spinner').style.display = 'none';

  <?php if ($status === 'success'): ?>
    document.getElementById('checkmark').style.display = 'block';
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, 2500);
  <?php else: ?>
    document.getElementById('errormark').style.display = 'block';
    setTimeout(() => { window.location.href = "index.php?p=login"; }, 2500);
  <?php endif; ?>

}, 1500);
</script>

</body>
</html>
