<?php
/**
 * Chặn trang yêu cầu đăng nhập (bất kỳ vai trò hợp lệ nào).
 * Dùng cho các trang hiển thị HTML; endpoint quản trị dùng require_admin.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (vincine_is_logged_in()) {
    return;
}

http_response_code(401);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yêu cầu đăng nhập</title>
  <link rel="stylesheet" href="/public/assets/css/style.css">
  <style>
    .alert-box {
      background: rgba(255, 255, 255, .05);
      padding: 40px 50px;
      border-radius: 16px;
      text-align: center;
      max-width: 500px;
      box-shadow: 0 0 30px rgba(0, 0, 0, .4);
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }
    .alert-box h1 { color: #ff4444; margin-bottom: 10px; }
    .alert-box .btn {
      display: inline-block;
      margin-top: 25px;
      background: #e50914;
      color: #fff;
      text-decoration: none;
      padding: 12px 26px;
      border-radius: 8px;
      font-weight: 600;
    }
    .alert-box .btn:hover { background: #b00610; }
  </style>
</head>
<body>
  <div class="alert-box">
    <h1>Bạn cần đăng nhập để tiếp tục</h1>
    <p>Vui lòng đăng nhập tài khoản của bạn trước khi thao tác.</p>
    <a class="btn" href="/index.php?p=login">Đăng nhập ngay</a>
  </div>
</body>
</html>
<?php
exit;
