<?php
/**
 * Gửi mã OTP đặt lại mật khẩu cho khách hàng hoặc nhân sự nội bộ.
 */

require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../../helpers/mailer.php';

/** Thông báo dùng chung: không tiết lộ email nào có tài khoản. */
const OTP_SENT_NOTICE = '📧 Nếu email tồn tại trong hệ thống, mã OTP đã được gửi tới hộp thư của bạn.';

$email = trim((string)($_POST['email'] ?? ''));
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $msg = '❌ Yêu cầu không hợp lệ.';
    goto render;
}

vincine_verify_csrf(false);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = '❌ Email không hợp lệ.';
    goto render;
}

/**
 * Tìm tài khoản theo email trên cả hai bảng.
 *
 * @return array{table: string, id: int, name: string}|null
 */
function vincine_find_reset_account(mysqli $conn, string $email): ?array
{
    $lookups = [
        ['customers', 'SELECT customer_id AS id, fullname AS name FROM customers WHERE email = ? LIMIT 1'],
        ['users',     'SELECT user_id AS id, name FROM users WHERE email = ? LIMIT 1'],
    ];

    foreach ($lookups as [$table, $sql]) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('[vincine] send_otp prepare failed: ' . $conn->error);
            continue;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return ['table' => $table, 'id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
    }

    return null;
}

$account = vincine_find_reset_account($conn, $email);

if ($account === null) {
    // Trả lời giống hệt trường hợp thành công để tránh dò email.
    $msg = OTP_SENT_NOTICE;
    goto render;
}

/* OTP phải sinh bằng nguồn ngẫu nhiên mật mã, không dùng rand(). */
$otp = (string)random_int(100000, 999999);

$_SESSION['reset_email']    = $email;
$_SESSION['reset_account']  = $account;
$_SESSION['reset_otp']      = $otp;
$_SESSION['otp_expire']     = time() + VINCINE_OTP_LIFETIME;
$_SESSION['otp_attempts']   = 0;
unset($_SESSION['otp_verified']);

try {
    $mail = vincine_mailer();
    $mail->addAddress($email, $account['name'] ?: 'Người dùng');
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
      <h2 style='color:#d4af37;font-size:20px;margin-top:0;'>Xin chào <span style='color:#000;'>" . htmlspecialchars($account['name'] ?: 'bạn', ENT_QUOTES, 'UTF-8') . "</span>,</h2>
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
    header('Location: ../../public/verify_otp.php');
    exit;

} catch (Throwable $e) {
    $detail = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
    error_log('[vincine] send_otp mail failed: ' . $detail);
    $msg = '⚠️ Không gửi được email vào lúc này. Vui lòng thử lại sau.';
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
