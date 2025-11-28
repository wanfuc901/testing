<?php
require_once __DIR__ . '/../../config/config.php';
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('forbidden');
}

$status = 'error';
$msgTitle = 'Thao tác thất bại';
$msgText = 'Đã xảy ra lỗi không xác định.';
$redirect = '../../../index.php?p=admin_users';

$act = $_POST['action'] ?? '';

try {
    if ($act === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'admin';

        if ($name === '' || $email === '' || $pass === '') {
            throw new Exception('Thiếu dữ liệu cần thiết.');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $st = $conn->prepare("INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)");
        $st->bind_param("ssss", $name, $email, $hash, $role);
        $st->execute();

        $status = 'success';
        $msgTitle = 'Thêm tài khoản thành công';
        $msgText = "Đã tạo tài khoản quản trị viên: $name.";
    }

    elseif ($act === 'update') {
        $id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'customer';

        $st = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE user_id=?");
        $st->bind_param("sssi", $name, $email, $role, $id);
        $st->execute();

        $status = 'success';
        $msgTitle = 'Cập nhật thành công';
        $msgText = "Thông tin người dùng #$id đã được cập nhật.";
    }

    elseif ($act === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM users WHERE user_id=?");
            $st->bind_param("i", $id);
            $st->execute();

            $status = 'success';
            $msgTitle = 'Đã xóa người dùng';
            $msgText = "Người dùng #$id đã bị xóa khỏi hệ thống.";
        } else {
            throw new Exception('Thiếu ID người dùng.');
        }
    }
} catch (Exception $e) {
    $msgText = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Xử lý tài khoản...</title>
<link rel="stylesheet" href="../../../public/assets/css/style.css">
<style>
.loading-page {
  position: relative;
  min-height: 100vh;
  background: var(--bg,#111);
  color: var(--text,#fff);
  font-family: 'Poppins', sans-serif;
}
.box {
  position: absolute; top:50%; left:50%;
  transform:translate(-50%,-50%);
  text-align:center; padding:40px 60px;
  background:#1e1e1e; border-radius:20px;
  border:1px solid rgba(255,255,255,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.5);
  max-width:420px; width:90%;
}
.spinner {
  width:50px; height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold,#e0a800);
  border-radius:50%;
  animation:spin 0.9s linear infinite;
  margin:0 auto 25px;
}
@keyframes spin { to { transform: rotate(360deg); } }

<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('forbidden');
}

$status = '';
$msgTitle = '';
$msgText = '';
$redirect = '/vincentcinemas/index.php?p=admin_users';

$act = $_POST['action'] ?? '';

try {
  if ($act === 'create_admin') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if ($name === '' || $email === '' || $pass === '') {
      throw new Exception('Thiếu dữ liệu, vui lòng nhập đầy đủ.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $st = $conn->prepare("INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)");
    $st->bind_param("ssss", $name, $email, $hash, $role);
    $st->execute();

    $status = 'success';
    $msgTitle = 'Tạo tài khoản thành công';
    $msgText = 'Đã thêm người dùng quản trị mới.';

  } elseif ($act === 'update') {
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'customer';

    $st = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE user_id=?");
    $st->bind_param("sssi", $name, $email, $role, $id);
    $st->execute();

    $status = 'success';
    $msgTitle = 'Cập nhật thành công';
    $msgText = 'Thông tin người dùng đã được lưu.';

  } elseif ($act === 'delete') {
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id > 0) {
      $st = $conn->prepare("DELETE FROM users WHERE user_id=?");
      $st->bind_param("i", $id);
      $st->execute();
    }

    $status = 'success';
    $msgTitle = 'Đã xóa người dùng';
    $msgText = 'Dữ liệu người dùng đã được gỡ bỏ.';

  } else {
    throw new Exception('Yêu cầu không hợp lệ.');
  }

} catch (Exception $e) {
  $status = 'error';
  $msgTitle = 'Thao tác thất bại';
  $msgText = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đang xử lý...</title>
<link rel="stylesheet" href="/vincentcinemas/public/assets/css/style.css">
<style>
.loading-page {
  position: relative;
  min-height: 100vh;
  background: var(--bg, #111);
  color: var(--text, #fff);
  font-family: 'Poppins', sans-serif;
}
.box {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  padding: 40px 60px;
  background: var(--card, #222);
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.5);
  max-width: 420px;
}
.spinner {
  width:50px;height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold,#f9d342);
  border-radius:50%;
  animation:spin .9s linear infinite;
  margin:0 auto 25px;
}
@keyframes spin { to { transform:rotate(360deg); } }
.checkmark, .errormark {
  width:90px;height:90px;display:none;
  margin:0 auto 12px;
}
.checkmark__circle, .errormark__circle {
  stroke-dasharray:166;stroke-dashoffset:166;
  stroke-width:3;stroke-miterlimit:10;fill:none;
  animation:stroke .6s cubic-bezier(0.65,0,0.45,1) forwards;
}
.checkmark__circle { stroke:#4BB71B; }
.errormark__circle { stroke:#e74c3c; }
.checkmark__check, .errormark__cross {
  transform-origin:50% 50%;
  stroke-dasharray:48;stroke-dashoffset:48;
  fill:none;stroke:#fff;
  animation:stroke .3s cubic-bezier(0.65,0,0.45,1) .6s forwards;
}
.errormark__cross { stroke:#fff; }
@keyframes stroke { to { stroke-dashoffset:0; } }
h2 { color:var(--gold,#f9d342); font-size:24px; margin:10px 0; }
p { color:#ccc; font-size:15px; }
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

  <h2 id="msgTitle">Đang xử lý...</h2>
  <p id="msgText">Vui lòng chờ trong giây lát</p>
</div>

<script>
const spinner = document.getElementById('spinner');
const checkmark = document.getElementById('checkmark');
const errormark = document.getElementById('errormark');
const title = document.getElementById('msgTitle');
const text = document.getElementById('msgText');

const loadingTime = 1500;
const holdTime = 2000;

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
    setTimeout(() => { history.back(); }, holdTime);
  <?php endif; ?>
}, loadingTime);
</script>
</body>
</html>
