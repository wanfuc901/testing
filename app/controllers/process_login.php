<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/config.php";

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $status = 'error';
    $msgTitle = 'Thiếu thông tin';
    $msgText = 'Vui lòng nhập đầy đủ email và mật khẩu.';
} else {
    $stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $status = 'error';
        $msgTitle = 'Email không tồn tại';
        $msgText = 'Vui lòng kiểm tra lại địa chỉ email.';
    } else {
        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            $status = 'error';
            $msgTitle = 'Sai mật khẩu';
            $msgText = 'Mật khẩu bạn nhập không chính xác.';
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['last_active'] = time();

            $status = 'success';
            $msgTitle = 'Đăng nhập thành công';
            $msgText = 'Chào mừng trở lại, ' . htmlspecialchars($user['name']) . '!';
            $redirect = ($user['role'] === 'admin')
                ? "index.php?p=admin_dashboard"
                : "index.php?p=home";
        }
    }
}
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

/* ===== Loading spinner ===== */
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

/* ===== Tick / X animation ===== */
.checkmark, .errormark {
  width:90px;
  height:90px;
  border-radius:50%;
  display:none;
  stroke-width:3;
  stroke-miterlimit:10;
  margin:0 auto 12px;
}

/* Tick xanh */
.checkmark {
  stroke:#4BB71B;
  animation:scale .3s ease-in-out .9s both;
}
.checkmark__circle {
  stroke-dasharray:166;
  stroke-dashoffset:166;
  stroke-width:3;
  stroke-miterlimit:10;
  fill:none;
  stroke:#4BB71B;
  animation:stroke 0.6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.checkmark__check {
  transform-origin:50% 50%;
  stroke-dasharray:48;
  stroke-dashoffset:48;
  fill:none;
  stroke:#fff;
  animation:stroke 0.3s cubic-bezier(0.65,0,0.45,1) 0.6s forwards;
}

/* Dấu X đỏ */
.errormark {
  stroke:#e74c3c;
  animation:scale .3s ease-in-out .9s both;
}
.errormark__circle {
  stroke-dasharray:166;
  stroke-dashoffset:166;
  stroke-width:3;
  stroke-miterlimit:10;
  fill:none;
  stroke:#e74c3c;
  animation:stroke 0.6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.errormark__cross {
  transform-origin:50% 50%;
  stroke-dasharray:48;
  stroke-dashoffset:48;
  fill:none;
  stroke:#fff;
  animation:stroke 0.3s cubic-bezier(0.65,0,0.45,1) 0.6s forwards;
}

/* Keyframes */
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

    <!-- Tick xanh -->
    <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark__circle" cx="26" cy="26" r="25"/>
      <path class="checkmark__check" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>

    <!-- Dấu X đỏ -->
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

// thời gian loading (hiển thị "Đang xử lý...")
const loadingTime = 1800;

// thời gian giữ hiệu ứng tick hoặc X trước khi chuyển trang
const holdTime = 3000;

setTimeout(() => {
  spinner.style.display = 'none';
  <?php if ($status === 'success'): ?>
    checkmark.style.display = 'block';
    title.innerText = "<?= $msgTitle ?>";
    text.innerText  = "<?= $msgText ?>";
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, holdTime);
  <?php else: ?>
    errormark.style.display = 'block';
    title.innerText = "<?= $msgTitle ?>";
    text.innerText  = "<?= $msgText ?>";
    setTimeout(() => { window.location.href = "index.php?p=login"; }, holdTime);
  <?php endif; ?>
}, loadingTime);
</script>
</body>
</html>
