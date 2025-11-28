<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_POST['credential'])) {
    header("Location: ../../index.php?p=login");
    exit;
}

$jwt = $_POST['credential'];

/* =============================
   GIẢI MÃ JWT GOOGLE (không cần thư viện)
============================= */
function decodeJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;

    $payload = $parts[1];
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    $payload .= str_repeat('=', 3 - (strlen($payload) % 4));
    return json_decode(base64_decode($payload), true);
}

$data = decodeJWT($jwt);

if (!$data || !isset($data['email'])) {
    $status = 'error';
    $msgTitle = 'Lỗi đăng nhập';
    $msgText  = 'Không thể xác thực tài khoản Google.';
    $redirect = '../../index.php?p=login';
    goto render_page;
}

$email = $data['email'];
$name  = $data['name'] ?? "Google User";

$conn->set_charset('utf8mb4');

/* =============================
   KIỂM TRA USER ĐÃ TỒN TẠI?
============================= */
$stmt = $conn->prepare("SELECT user_id, name, role FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$rs = $stmt->get_result();

if ($rs->num_rows === 0) {

    $stmt = $conn->prepare("
        INSERT INTO users(name, email, password, role)
        VALUES(?, ?, '', 'customer')
    ");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();

    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['role'] = 'customer';

} else {
    $row = $rs->fetch_assoc();
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['role'] = $row['role'];
}

/* =============================
   TẠO SESSION ĐĂNG NHẬP
============================= */
$_SESSION['name']  = $name;
$_SESSION['email'] = $email;
$_SESSION['last_active'] = time();

$status = 'success';
$msgTitle = 'Đăng nhập thành công';
$msgText  = 'Chào mừng trở lại, ' . htmlspecialchars($name) . '!';
$redirect = '../../index.php?p=home';


/* =============================
   TRANG HIỂN THỊ CHỜ CHUYỂN HƯỚNG
============================= */
render_page:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">
<style>
body.loading-page {
  min-height:100vh;background:var(--bg);color:var(--text);
  font-family:'Poppins',sans-serif;margin:0;
  display:flex;align-items:center;justify-content:center;
}
.box {
  text-align:center;padding:40px 60px;background:var(--card);
  border-radius:20px;border:1px solid rgba(255,255,255,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.5);
  max-width:420px;width:90%;
}
h2{color:var(--gold);font-size:24px;font-weight:700;margin:10px 0}
p{color:var(--muted);font-size:15px;margin-top:4px}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{
  width:50px;height:50px;border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);border-radius:50%;
  animation:spin .9s linear infinite;margin:0 auto 25px;
}
.checkmark,.errormark{
  width:90px;height:90px;border-radius:50%;
  display:none;stroke-width:3;stroke-miterlimit:10;margin:0 auto 12px;
}
.checkmark{stroke:#4BB71B;animation:scale .3s ease-in-out .9s both;}
.checkmark__circle{
  stroke-dasharray:166;stroke-dashoffset:166;stroke-width:3;fill:none;stroke:#4BB71B;
  animation:stroke .6s cubic-bezier(.65,0,.45,1) forwards;
}
.checkmark__check{
  transform-origin:50% 50%;stroke-dasharray:48;stroke-dashoffset:48;fill:none;stroke:#fff;
  animation:stroke .3s cubic-bezier(.65,0,.45,1) .6s forwards;
}
.errormark{stroke:#e74c3c;animation:scale .3s ease-in-out .9s both;}
.errormark__circle{
  stroke-dasharray:166;stroke-dashoffset:166;stroke-width:3;fill:none;stroke:#e74c3c;
  animation:stroke .6s cubic-bezier(.65,0,.45,1) forwards;
}
.errormark__cross{
  stroke-dasharray:48;stroke-dashoffset:48;fill:none;stroke:#fff;
  animation:stroke .3s cubic-bezier(.65,0,.45,1) .6s forwards;
}
@keyframes stroke{100%{stroke-dashoffset:0}}
@keyframes scale{0%,100%{transform:none}50%{transform:scale(1.05)}}
</style>
</head>
<body class="loading-page">
  <div class="box">
    <div class="spinner" id="spinner"></div>

    <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark__circle" cx="26" cy="26" r="25"/>
      <path class="checkmark__check" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>

    <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="errormark__circle" cx="26" cy="26" r="25"/>
      <path class="errormark__cross" d="M16 16 36 36 M36 16 16 36"/>
    </svg>

    <h2 id="msgTitle"><?= htmlspecialchars($msgTitle) ?></h2>
    <p id="msgText"><?= htmlspecialchars($msgText) ?></p>
  </div>

<script>
const spinner=document.getElementById('spinner');
const checkmark=document.getElementById('checkmark');
const errormark=document.getElementById('errormark');
const loadingTime=1300, holdTime=2000;

setTimeout(()=>{
  spinner.style.display='none';
  <?php if($status==='success'): ?>
    checkmark.style.display='block';
    setTimeout(()=>{window.location.href="<?= $redirect ?>";},holdTime);
  <?php else: ?>
    errormark.style.display='block';
    setTimeout(()=>{window.location.href="<?= $redirect ?>";},holdTime);
  <?php endif; ?>
},loadingTime);
</script>
</body>
</html>