<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đặt lại mật khẩu - VinCine</title>

  <!-- Cục bộ: Bootstrap & Icons -->
  <link rel="stylesheet" href="../public/assets/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../public/assets/css/style.css">
</head>
<body>
  <div class="reset-wrapper">
    <h2><i class="bi bi-shield-lock-fill" style="color:var(--gold);"></i>Đặt lại mật khẩu</h2>
    <form class="reset-form" method="post" action="../app/controllers/reset_password.php">
      <div class="password-wrapper">
        <input type="password" name="password" class="input" placeholder="Mật khẩu mới" required>
        <i class="bi bi-eye-slash vc-eye" onclick="toggleEye(this)"></i>
      </div>
      <div class="password-wrapper">
        <input type="password" name="confirm" class="input" placeholder="Nhập lại mật khẩu" required>
        <i class="bi bi-eye-slash vc-eye" onclick="toggleEye(this)"></i>
      </div>
      <button type="submit" class="btn-confirm">
        <i class="bi bi-arrow-repeat"></i> Cập nhật
      </button>
    </form>
  </div>

  <div class="otp-overlay" id="loadingOverlay">
    <div class="loader"></div>
  </div>

  <script>
  const form = document.querySelector('.reset-form');
  form.addEventListener('submit', () => {
    document.getElementById('loadingOverlay').classList.add('active');
  });

  function toggleEye(icon) {
    const input = icon.previousElementSibling;
    const hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
  }
  </script>
</body>
</html>
