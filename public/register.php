<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
  header("Location: index.php?p=home");
  exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng ký - VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
  <link href="public/assets/boxicons-free/free/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>
  <div class="login-wrapper">
    <h2>Đăng ký</h2>

    <form class="login-form" method="post" action="app/controllers/process_register.php">
      <input type="text" name="name" class="input" placeholder="Họ và tên" required>
      <input type="email" name="email" class="input" placeholder="Email" required>
      <input type="tel" name="phone" class="input" placeholder="Số điện thoại" required>

      <div class="password-wrapper">
        <input type="password" name="password" id="password1" class="input" placeholder="Mật khẩu" required>
        <i class='bx bx-hide' id="eye1"></i>
      </div>

      <div class="password-wrapper">
        <input type="password" name="confirm_password" id="password2" class="input" placeholder="Nhập lại mật khẩu" required>
        <i class='bx bx-hide' id="eye2"></i>
      </div>

      <button type="submit" class="btn-confirm">Tạo tài khoản</button>
    </form>

    <p>Đã có tài khoản? <a href="index.php?p=login">Đăng nhập</a></p>
  </div>

  <script>
    function togglePassword(inputId, eyeId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(eyeId);
      icon.addEventListener('click', () => {
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bx-hide');
        icon.classList.toggle('bx-show');
      });
    }
    togglePassword('password1', 'eye1');
    togglePassword('password2', 'eye2');
  </script>
</body>
</html>
