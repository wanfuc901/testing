<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_POST['credential'])) {
    header("Location: ../../index.php?p=login");
    exit;
}

$jwt = $_POST['credential'];

/* =============================
   GIẢI MÃ JWT GOOGLE
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
    goto render;
}

$email = $data['email'];
$name  = $data['name'] ?? "Người dùng Google";

$conn->set_charset('utf8mb4');

/* =============================
   1. KIỂM TRA CUSTOMER
============================= */
$stmt = $conn->prepare("
    SELECT customer_id, fullname 
    FROM customers 
    WHERE email=? LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$rs = $stmt->get_result();

/* =============================
   2. CHƯA CÓ → TẠO MỚI
============================= */
if ($rs->num_rows === 0) {
    $stmt = $conn->prepare("
        INSERT INTO customers(fullname, email, password)
        VALUES(?, ?, '')
    ");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();

    $customer_id = $stmt->insert_id;

} else {
    $row = $rs->fetch_assoc();
    $customer_id = $row['customer_id'];
}

/* =============================
   3. SET SESSION
============================= */
$_SESSION['customer_id']  = $customer_id;
$_SESSION['fullname']     = $name;
$_SESSION['email']        = $email;
$_SESSION['role']         = 'customer';
$_SESSION['last_active']  = time();

$status = 'success';
$msgTitle = 'Đăng nhập thành công';
$msgText  = 'Xin chào, ' . htmlspecialchars($name) . '!';
$redirect = '../../index.php?p=home';


/* =============================
   TRANG CHỜ ANIMATION
============================= */
render:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">

<style>
.loading-page{
  position:relative;
  min-height:100vh;
  background:var(--bg);
  color:var(--text);
  font-family:'Poppins',sans-serif;
  margin:0;
}

.box{
  position:absolute;
  top:50%; left:50%;
  transform:translate(-50%,-50%);
  text-align:center;
  padding:40px 60px;
  background:var(--card);
  border-radius:20px;
  border:1px solid rgba(255,255,255,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.5);
  max-width:420px;
  width:90%;
}

h2{color:var(--gold);font-size:24px;font-weight:700;margin:10px 0}
p{color:var(--muted);font-size:15px;margin-top:4px}

@keyframes spin{to{transform:rotate(360deg)}}
.spinner{
  width:50px;height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);
  border-radius:50%;
  animation:spin .9s linear infinite;
  margin:0 auto 25px;
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
  stroke-dasharray:48;stroke-dashoffset:48;fill:none;stroke:#fff;
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
    <path class="checkmark__check" d="M14 27l7 7 17-17"/>
  </svg>

  <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
    <circle class="errormark__circle" cx="26" cy="26" r="25"/>
    <path class="errormark__cross" d="M16 16 36 36 M36 16 16 36"/>
  </svg>

  <h2><?= htmlspecialchars($msgTitle) ?></h2>
  <p><?= htmlspecialchars($msgText) ?></p>
</div>

<script>
setTimeout(() => {
  document.getElementById('spinner').style.display = 'none';

  <?php if ($status === 'success'): ?>
    document.getElementById('checkmark').style.display = 'block';
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, 2500);
  <?php else: ?>
    document.getElementById('errormark').style.display = 'block';
    setTimeout(() => { window.location.href = "<?= $redirect ?>"; }, 2500);
  <?php endif; ?>

}, 1500);
</script>

</body>
</html>
