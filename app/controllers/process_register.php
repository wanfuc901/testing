<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../config/config.php";

/* ===== DEBUG ===== */

$status   = 'error';
$msgTitle = '';
$msgText  = '';
$redirect = '../../index.php?p=rg';

/* ===== CHECK DB ===== */
if (!isset($conn) || $conn->connect_error) {
    die("<b>Lỗi kết nối MySQL:</b> " . ($conn->connect_error ?? 'Chưa khởi tạo kết nối.'));
}

/* ===== POST REQUEST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    // Validate
    if ($name==='' || $email==='' || $phone==='' || $password==='' || $confirm==='') {
        $msgTitle = 'Thiếu thông tin';
        $msgText  = 'Vui lòng nhập đầy đủ tất cả các trường.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msgTitle = 'Email không hợp lệ';
        $msgText  = 'Vui lòng kiểm tra lại địa chỉ email.';
    }
    elseif ($password !== $confirm) {
        $msgTitle = 'Mật khẩu không khớp';
        $msgText  = 'Hai mật khẩu bạn nhập không trùng khớp.';
    }
    else {

        /* ===== CHECK EMAIL TỒN TẠI TRONG CUSTOMERS ===== */
        $check = $conn->prepare("SELECT customer_id FROM customers WHERE email=?");
        if (!$check) die("❌ Lỗi prepare (check): " . $conn->error);

        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        $check->close();

        if ($result->num_rows > 0) {
            $msgTitle = 'Email đã tồn tại';
            $msgText  = 'Vui lòng chọn email khác để đăng ký.';
        }
        else {

            /* ===== INSERT CUSTOMER ===== */
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("
                INSERT INTO customers(fullname, email, phone, password, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            if (!$stmt) die("❌ Lỗi prepare (insert): " . $conn->error);

            $stmt->bind_param("ssss", $name, $email, $phone, $hash);

            if ($stmt->execute()) {
                // SET SESSION CUSTOMER
                $_SESSION['customer_id'] = $conn->insert_id;
                $_SESSION['fullname']    = $name;
                $_SESSION['email']       = $email;
                $_SESSION['last_active'] = time();

                $status   = 'success';
                $msgTitle = 'Đăng ký thành công';
                $msgText  = 'Chào mừng bạn đến với VinCine 🍿';

                $redirect = '../../index.php?p=home';
            } 
            else {
                die("❌ Lỗi execute(): " . $stmt->error);
            }

            $stmt->close();
        }
    }

} else {
    $msgTitle = 'Truy cập không hợp lệ';
    $msgText  = 'Chỉ chấp nhận yêu cầu POST từ form đăng ký.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng ký...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">
<style>
.loading-page{
  min-height:100vh;display:flex;justify-content:center;align-items:center;
  background:var(--bg);color:var(--text);font-family:'Poppins',sans-serif;
}
.box{
  text-align:center;padding:40px 60px;background:var(--card);
  border-radius:20px;border:1px solid rgba(255,255,255,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.5);width:90%;max-width:420px;
}
h2{color:var(--gold);font-size:24px;font-weight:700;margin:10px 0;}
p{color:var(--muted);font-size:15px;margin-top:4px;}
.spinner{
  width:50px;height:50px;border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);border-radius:50%;animation:spin .9s linear infinite;
  margin:0 auto 25px;
}
@keyframes spin{to{transform:rotate(360deg);}}
</style>
</head>
<body class="loading-page">
  <div class="box">
    <div class="spinner"></div>
    <h2><?= htmlspecialchars($msgTitle) ?></h2>
    <p><?= htmlspecialchars($msgText) ?></p>
  </div>

<script>
setTimeout(() => {
  window.location.href = "<?= $redirect ?>";
}, 3000);
</script>
</body>
</html>
