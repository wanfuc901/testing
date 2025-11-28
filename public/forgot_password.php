<?php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Quên mật khẩu - VinCine</title>

  <!-- Thư viện cục bộ -->
  <link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="login-wrapper">
    <h2><i class="bi bi-key-fill" style="color:var(--gold);margin-right:6px;"></i>Quên mật khẩu</h2>

    <form id="otpForm" class="login-form" method="post" action="app/controllers/send_otp.php">
      <!-- Lựa chọn phương thức -->
      <label style="color:var(--muted);font-weight:600;">phương thức xác thực:</label>
      <div style="display:flex;gap:16px;justify-content:center;margin:10px 0 20px;">
        <label>
          <input type="radio" name="method" value="email" checked style="margin-right:6px;">
          <i class="bi bi-envelope-fill" style="color:var(--gold);margin-right:4px;"></i>Email
        </label>
        <label>
          <input type="radio" name="method" value="phone" style="margin-right:6px;">
          <i class="bi bi-telephone-fill" style="color:var(--gold);margin-right:4px;"></i>Số điện thoại
        </label>
      </div>

      <!-- Nhập thông tin -->
      <div id="email-field" class="input-group">
        <span class="input-group-text bg-transparent border-0">
          <i class="bi bi-envelope-fill" style="color:var(--gold);font-size:18px;"></i>
        </span>
        <input type="email" name="email" class="form-control input" placeholder="Nhập email đăng ký">
      </div>

      <div id="phone-field" class="input-group" style="display:none;">
        <span class="input-group-text bg-transparent border-0">
          <i class="bi bi-phone-fill" style="color:var(--gold);font-size:18px;"></i>
        </span>
        <input type="tel" name="phone" class="form-control input" placeholder="Nhập số điện thoại" pattern="[0-9]{9,11}">
      </div>

      <button type="submit" class="btn-confirm">
        <i class="bi bi-send"></i> Gửi mã OTP
      </button>
    </form>

    <p style="margin-top:16px;">
      <a href="index.php?p=login">
        <i class="bi bi-arrow-left-circle"></i> Quay lại đăng nhập
      </a>
    </p>
  </div>

  <script>
    const emailField = document.getElementById('email-field');
    const phoneField = document.getElementById('phone-field');
    const otpForm = document.getElementById('otpForm');

    document.querySelectorAll('input[name="method"]').forEach(radio => {
      radio.addEventListener('change', e => {
        const method = e.target.value;
        if (method === 'email') {
          emailField.style.display = 'flex';
          phoneField.style.display = 'none';
          otpForm.action = 'app/controllers/send_otp.php';   // ✅ gửi qua email
        } else {
          emailField.style.display = 'none';
          phoneField.style.display = 'flex';
          otpForm.action = 'app/controllers/otp_zalo.php';   // ✅ gửi qua Zalo
        }
      });
    });
  </script>
</body>
</html>
