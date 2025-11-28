<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/../config/config.php";
require __DIR__ . "/../../vendor/phpmailer/PHPMailer.php";
require __DIR__ . "/../../vendor/phpmailer/SMTP.php";
require __DIR__ . "/../../vendor/phpmailer/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = trim($_POST['email'] ?? '');
$msg = "";

if (empty($email)) {
    $msg = "❌ Email không hợp lệ.";
    goto render;
}

// ✅ Kiểm tra tài khoản tồn tại
$stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $msg = "❌ Không tìm thấy tài khoản với email này.";
    goto render;
}

// ✅ Tạo OTP
$otp = rand(100000, 999999);
$_SESSION['reset_email'] = $email;
$_SESSION['reset_otp'] = $otp;
$_SESSION['otp_expire'] = time() + 300; // 5 phút

// ✅ Gửi email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "phuc.pham.vst@gmail.com"; // ⚠️ đổi thành email gửi thật
    $mail->Password   = "fvde ashj zbgq ohtr";     // ⚠️ App password Gmail
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;

    $mail->CharSet = "UTF-8";
    $mail->Encoding = "base64";

    $mail->setFrom("phuc.pham.vst@gmail.com", "VinCine Support");
    $mail->addAddress($email, $user['name'] ?? 'Người dùng');
    $mail->isHTML(true);
    $mail->Subject = "VinCine";

    // ✅ Nội dung HTML phong cách VinCine
    $mail->Body = "
<!DOCTYPE html>
<html lang='vi'>
<head>
<meta charset='UTF-8'>
<title>Mã OTP khôi phục mật khẩu</title>
</head>
<body style='margin:0;padding:0;background-color:#f4f4f4;font-family:Segoe UI,Arial,sans-serif;color:#333;'>
  <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;
              box-shadow:0 4px 10px rgba(0,0,0,0.1);overflow:hidden;'>

    <div style='background:linear-gradient(135deg,#e50914,#b20710);padding:20px 30px;text-align:center;'>
      <h1 style='color:#fff;font-size:22px;margin:0;'>VINCINE</h1>
    </div>

    <div style='padding:30px;'>
      <h2 style='color:#d4af37;font-size:20px;margin-top:0;'>Xin chào <span style='color:#000;'>{$user['name']}</span>,</h2>
      <p style='font-size:15px;line-height:1.6;color:#333;'>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản 
         <b style='color:#e50914;'>VinCine</b> của bạn.</p>

      <div style='background:#fafafa;border-radius:8px;padding:20px 30px;margin:20px 0;
                  border:1px solid #eee;text-align:center;'>
        <p style='font-size:16px;color:#555;margin-bottom:8px;'>Mã xác thực OTP của bạn là:</p>
        <h1 style='font-size:38px;letter-spacing:8px;margin:10px 0;color:#f5c518;'>{$otp}</h1>
        <p style='font-size:14px;color:#777;'>Mã này có hiệu lực trong <b>5 phút</b>.</p>
      </div>

      <p style='font-size:15px;line-height:1.6;color:#333;'>
        Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng <b>bỏ qua email này</b> để đảm bảo an toàn cho tài khoản.
      </p>

      <div style='margin-top:30px;text-align:center;'>
        <a href='verify_otp.php' 
           style='background:#e50914;color:#fff;text-decoration:none;padding:12px 24px;
           border-radius:999px;font-weight:600;display:inline-block;'>Xác nhận OTP ngay</a>
      </div>
    </div>

    <div style='background:#111;color:#ccc;font-size:12px;text-align:center;padding:12px 0;margin-top:20px;'>
      © 2025 VinCine. Email được gửi tự động — vui lòng không phản hồi lại thư này.
    </div>
  </div>
</body>
</html>
";

    $mail->send();
    header("Location:../../public/verify_otp.php");
    exit;

} catch (Exception $e) {
    $msg = "⚠️ Không thể gửi email: " . htmlspecialchars($mail->ErrorInfo);
}

render:
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Kết quả gửi OTP</title>
  <style>
    body {
      background: #0e0e11;
      color: #eee;
      font-family: 'Poppins', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
    }
    .result-box {
      background: rgba(255,255,255,0.06);
      padding: 40px 50px;
      border-radius: 16px;
      text-align: center;
      max-width: 480px;
      box-shadow: 0 0 30px rgba(0,0,0,0.4);
    }
    h1 {
      color: #f5c518;
      margin-bottom: 14px;
      font-size: 24px;
      font-weight: 800;
    }
    p {
      color: #ccc;
      font-size: 15px;
      line-height: 1.5;
    }
    .btn {
      display: inline-block;
      margin-top: 25px;
      background: #e50914;
      color: #fff;
      text-decoration: none;
      padding: 12px 26px;
      border-radius: 999px;
      font-weight: 600;
      transition: 0.25s;
    }
    .btn:hover {
      background: #b00610;
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <div class="result-box">
    <h1>📩 Thông báo</h1>
    <p><?= htmlspecialchars($msg) ?></p>
    <a class="btn" href="javascript:history.back()">← Quay lại</a>
  </div>
</body>
</html>
