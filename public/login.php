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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập / Đăng ký - VinCine</title>

  <link rel="icon" href="public/assets/icons/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="public/assets/boxicon/css/boxicons.min.css">
  <link rel="stylesheet" href="public/assets/css/style.css">

  <!-- Google Identity Services -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body class="vc-auth-body">
  <div class="vc-auth-container">

    <!-- ========== LOGIN FORM ========== -->
    <div class="vc-form-box login">
      <form method="post" action="index.php?p=pcl" class="vc-auth-form">
        <h1>Đăng nhập</h1>

        <div class="vc-input-box">
          <input type="email" name="email" placeholder="Email" required>
          <i class='bx bx-envelope'></i>
        </div>

        <div class="vc-input-box">
          <input type="password" name="password" id="loginPassword" placeholder="Mật khẩu" required>
          <i class='bx bx-hide' id="loginEye"></i>
        </div>

        <div class="vc-forgot">
          <a href="index.php?p=fp">Quên mật khẩu?</a>
        </div>

        <button type="submit" class="vc-btn-main">Đăng nhập</button>

        <p>hoặc đăng nhập với</p>

        <!-- ========== SOCIAL LOGIN BUTTONS ========== -->
        <div class="vc-social">
          <a href="app/controllers/oauth_facebook.php" class="facebook">
            <i class='bx bxl-facebook-circle'></i>
          </a>

          <!-- GOOGLE BUTTON (MỚI) -->
          <div id="g_id_onload"
               data-client_id="691390725508-qegrhgp1q29s8vmd1tc6otv9jp3oupak.apps.googleusercontent.com"
               data-context="signin"
               data-ux_mode="popup"
               data-callback="handleCredentialResponse"
               data-auto_prompt="false">
          </div>

          <div class="vc-google">
            <div class="g_id_signin"
                data-type="icon"
                data-size="large"
                data-shape="circle"></div>
          </div>

        </div>
      </form>
    </div>

    <!-- ========== REGISTER FORM ========== -->
    <div class="vc-form-box register">
      <form method="post" action="app/controllers/process_register.php" class="vc-auth-form">
        <h1>Đăng ký</h1>

        <div class="vc-input-box">
          <input type="text" name="name" placeholder="Họ và tên" required>
          <i class='bx bxs-user'></i>
        </div>

        <div class="vc-input-box">
          <input type="email" name="email" placeholder="Email" required>
          <i class='bx bx-envelope'></i>
        </div>

        <div class="vc-input-box">
          <input type="tel" name="phone" placeholder="Số điện thoại" required>
          <i class='bx bx-phone'></i>
        </div>

        <div class="vc-input-box">
          <input type="password" name="password" id="regPass1" placeholder="Mật khẩu" required>
          <i class='bx bx-hide' id="eye1"></i>
        </div>

        <div class="vc-input-box">
          <input type="password" name="confirm_password" id="regPass2" placeholder="Nhập lại mật khẩu" required>
          <i class='bx bx-hide' id="eye2"></i>
        </div>

        <button type="submit" class="vc-btn-main">Tạo tài khoản</button>
      </form>
    </div>

    <!-- ========== TOGGLE PANELS ========== -->
    <div class="vc-toggle-box">
      <div class="vc-toggle-panel toggle-left">
        <h1>Xin chào!</h1>
        <p>Bạn chưa có tài khoản?</p>
        <button type="button" class="vc-btn-outline register-btn">Đăng ký</button>
      </div>

      <div class="vc-toggle-panel toggle-right">
        <h1>Chào mừng trở lại!</h1>
        <p>Bạn đã có tài khoản?</p>
        <button type="button" class="vc-btn-outline login-btn">Đăng nhập</button>
      </div>
    </div>
  </div>

  <!-- ========== GOOGLE SIGN-IN HANDLER ========== -->
  <script>
  function handleCredentialResponse(response){
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "app/controllers/oauth_google.php";

      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "credential";
      input.value = response.credential;

      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
  }
  </script>

  <!-- ========== UI JS ========== -->
  <script>
  const container=document.querySelector('.vc-auth-container');
  document.querySelector('.register-btn').onclick=()=>container.classList.add('active');
  document.querySelector('.login-btn').onclick=()=>container.classList.remove('active');

  function togglePassword(inputId, iconId){
    const input=document.getElementById(inputId);
    const icon=document.getElementById(iconId);
    icon.onclick=()=>{
      input.type = (input.type==='password') ? 'text' : 'password';
      icon.classList.toggle('bx-show');
      icon.classList.toggle('bx-hide');
    };
  }
  togglePassword('loginPassword','loginEye');
  togglePassword('regPass1','eye1');
  togglePassword('regPass2','eye2');
  </script>

</body>
</html>
