<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu chưa đăng nhập thì hiện thông báo
if (empty($_SESSION['user_id'])) {
    echo '<!doctype html>
    <html lang="vi">
    <head>
      <meta charset="utf-8">
      <title>Yêu cầu đăng nhập</title>
      <link rel="stylesheet" href="/VincentCinemas/public/assets/css/style.css">
      <style> 
        .alert-box {
          background: rgba(255,255,255,0.05);
          padding: 40px 50px;
          border-radius: 16px;
          text-align: center;
          max-width: 500px;
          box-shadow: 0 0 30px rgba(0,0,0,0.4);
          position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
        }
        h1 {
          color: #ff4444;
          margin-bottom: 10px;
        }
        a.btn {
          display: inline-block;
          margin-top: 25px;
          background: #e50914;
          color: #fff;
          text-decoration: none;
          padding: 12px 26px;
          border-radius: 8px;
          font-weight: 600;
        }
        a.btn:hover { background: #b00610; }
      </style>
    </head>
    <body>
      <div class="alert-box">
        <h1>Bạn cần đăng nhập để đặt vé</h1>
        <p>Vui lòng đăng nhập tài khoản của bạn trước khi tiếp tục.</p>
        <a class="btn" href="index.php?p=login">Đăng nhập ngay</a>
      </div>
    </body>
    </html>';
    exit;
}
?>
