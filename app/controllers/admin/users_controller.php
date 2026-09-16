<?php
require_once __DIR__ . '/../../include/require_admin.php';

$status   = 'error';
$msgTitle = 'Thao tác thất bại';
$msgText  = '';
$redirect = '../../../index.php?p=admin_users';

$act = $_POST['action'] ?? '';

try {

    /* ===== CREATE USER (ADMIN / STAFF) ===== */
    if ($act === 'create_admin') {

        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'staff';

        if ($name === '' || $email === '' || $pass === '') {
            throw new Exception('Thiếu dữ liệu bắt buộc.');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $st = $conn->prepare(
            "INSERT INTO users (name,email,password,role)
             VALUES (?,?,?,?)"
        );
        $st->bind_param("ssss", $name, $email, $hash, $role);
        $st->execute();

        $status   = 'success';
        $msgTitle = 'Tạo thành công';
        $msgText  = 'Tài khoản đã được tạo.';
    }

    /* ===== UPDATE USER / CUSTOMER ===== */
    elseif ($act === 'update') {

        $id    = (int)($_POST['user_id'] ?? 0);
        $type  = $_POST['type'] ?? 'user';
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'staff';

        if ($id <= 0) {
            throw new Exception('ID không hợp lệ.');
        }

        if ($type === 'customer') {
            $st = $conn->prepare(
                "UPDATE customers SET fullname=?, email=? WHERE customer_id=?"
            );
            $st->bind_param("ssi", $name, $email, $id);
        } else {
            $st = $conn->prepare(
                "UPDATE users SET name=?, email=?, role=? WHERE user_id=?"
            );
            $st->bind_param("sssi", $name, $email, $role, $id);
        }

        $st->execute();

        $status   = 'success';
        $msgTitle = 'Cập nhật thành công';
        $msgText  = 'Thông tin đã được lưu.';
    }

    /* ===== DELETE USER / CUSTOMER ===== */
    elseif ($act === 'delete') {

        $id   = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? '';

        if ($id <= 0) {
            throw new Exception('ID không hợp lệ.');
        }

        if ($type === 'user') {
            $st = $conn->prepare("DELETE FROM users WHERE user_id=?");
        } elseif ($type === 'customer') {
            $st = $conn->prepare("DELETE FROM customers WHERE customer_id=?");
        } else {
            throw new Exception('Loại dữ liệu không hợp lệ.');
        }

        $st->bind_param("i", $id);
        $st->execute();

        $status   = 'success';
        $msgTitle = 'Đã xóa';
        $msgText  = 'Bản ghi đã được xóa.';
    }

    else {
        throw new Exception('Action không hợp lệ.');
    }

} catch (Exception $e) {
    $msgText = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Vincent Cinemas</title>
<link rel="stylesheet" href="../../../public/assets/css/style.css">

<style>
/* === SIGNATURE LOADING VINCENT CINEMAS === */
.loading-page{
  position: relative;
  min-height: 100vh;
  background: var(--bg);
  color: var(--text);
  font-family: 'Poppins', sans-serif;
}
.box{
  position:absolute;
  top:50%;
  left:50%;
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
.spinner{
  width:50px;height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);
  border-radius:50%;
  animation:spin .9s linear infinite;
  margin:0 auto 25px;
}
@keyframes spin{to{transform:rotate(360deg)}}

.checkmark,.errormark{width:90px;height:90px;display:none;margin:0 auto 12px}
.checkmark{stroke:#4BB71B}
.errormark{stroke:#e74c3c}
</style>
</head>

<body class="loading-page">
<div class="box">
  <div class="spinner" id="spinner"></div>

  <svg class="checkmark" id="checkmark" viewBox="0 0 52 52">
    <circle cx="26" cy="26" r="25" fill="none"/>
    <path d="M14 27l7 7 17-17" fill="none"/>
  </svg>

  <svg class="errormark" id="errormark" viewBox="0 0 52 52">
    <circle cx="26" cy="26" r="25" fill="none"/>
    <path d="M16 16 36 36 M36 16 16 36" fill="none"/>
  </svg>

  <h2><?= htmlspecialchars($msgTitle) ?></h2>
  <p><?= htmlspecialchars($msgText) ?></p>
</div>

<script>
setTimeout(() => {
  document.getElementById('spinner').style.display = 'none';

  <?php if ($status === 'success'): ?>
    document.getElementById('checkmark').style.display = 'block';
    setTimeout(() => location.href = "<?= $redirect ?>", 2000);
  <?php else: ?>
    document.getElementById('errormark').style.display = 'block';
    setTimeout(() => history.back(), 2000);
  <?php endif; ?>
}, 1200);
</script>
</body>
</html>
