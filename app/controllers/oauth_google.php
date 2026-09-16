<?php
/**
 * Đăng nhập bằng Google Identity Services.
 *
 * BẮT BUỘC xác thực chữ ký của ID token với Google trước khi tin payload.
 * Tự giải mã base64 phần payload là lỗ hổng chiếm tài khoản: bất kỳ ai cũng
 * tạo được token chứa email tùy ý.
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/auth.php';

const GOOGLE_TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';
const GOOGLE_VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

$status   = 'error';
$msgTitle = 'Lỗi đăng nhập';
$msgText  = 'Không thể xác thực tài khoản Google.';
$redirect = '../../index.php?p=login';

/**
 * Gọi endpoint tokeninfo của Google để xác thực ID token.
 *
 * @return array<string,mixed>|null payload đã được Google xác nhận, null nếu không hợp lệ.
 */
function vincine_verify_google_token(string $idToken): ?array
{
    $ch = curl_init(GOOGLE_TOKENINFO_URL . '?id_token=' . urlencode($idToken));
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    try {
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $code !== 200) {
            error_log('[vincine] Google tokeninfo failed: HTTP ' . $code . ' ' . curl_error($ch));
            return null;
        }
    } finally {
        curl_close($ch);
    }

    $payload = json_decode((string)$body, true);
    if (!is_array($payload)) {
        return null;
    }

    // Token phải được phát hành cho đúng ứng dụng này.
    if (GOOGLE_CLIENT_ID === '' || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        error_log('[vincine] Google token aud mismatch');
        return null;
    }

    if (!in_array($payload['iss'] ?? '', GOOGLE_VALID_ISSUERS, true)) {
        return null;
    }

    if ((int)($payload['exp'] ?? 0) <= time()) {
        return null;
    }

    // Google trả về chuỗi "true"/"false" cho trường này.
    if (filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
        return null;
    }

    $email = trim((string)($payload['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $payload;
}

/* ====================================================
   1) NHẬN & XÁC THỰC TOKEN
==================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $msgText = 'Phương thức không hợp lệ.';
    goto render;
}

$idToken = trim((string)($_POST['credential'] ?? ''));
if ($idToken === '') {
    goto render;
}

$claims = vincine_verify_google_token($idToken);
if ($claims === null) {
    http_response_code(401);
    $msgText = 'Token Google không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.';
    goto render;
}

$email = (string)$claims['email'];
$name  = trim((string)($claims['name'] ?? '')) ?: 'Người dùng Google';

/* ====================================================
   2) TÌM HOẶC TẠO KHÁCH HÀNG
==================================================== */
$stmt = $conn->prepare("SELECT customer_id, fullname FROM customers WHERE email = ? LIMIT 1");
if (!$stmt) {
    error_log('[vincine] oauth_google prepare failed: ' . $conn->error);
    http_response_code(500);
    $msgText = 'Hệ thống đang bận. Vui lòng thử lại sau.';
    goto render;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $customerId = (int)$existing['customer_id'];
    $name       = (string)$existing['fullname'];
} else {
    // Tài khoản Google không có mật khẩu cục bộ -> password rỗng.
    $insert = $conn->prepare("INSERT INTO customers (fullname, email, password) VALUES (?, ?, '')");
    if (!$insert) {
        error_log('[vincine] oauth_google insert prepare failed: ' . $conn->error);
        http_response_code(500);
        $msgText = 'Không thể tạo tài khoản. Vui lòng thử lại sau.';
        goto render;
    }

    $insert->bind_param('ss', $name, $email);
    $insert->execute();
    $customerId = (int)$insert->insert_id;
    $insert->close();
}

/* ====================================================
   3) TẠO PHIÊN ĐĂNG NHẬP
==================================================== */
vincine_start_authenticated_session();

$_SESSION['customer_id'] = $customerId;
$_SESSION['fullname']    = $name;
$_SESSION['email']       = $email;
$_SESSION['role']        = VINCINE_ROLE_CUSTOMER;
$_SESSION['last_active'] = time();

$status   = 'success';
$msgTitle = 'Đăng nhập thành công';
$msgText  = 'Xin chào, ' . $name . '!';
$redirect = '../../index.php?p=home';

/* =============================
   TRANG CHỜ ANIMATION
============================= */
render:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập...</title>
<link rel="stylesheet" href="../../public/assets/css/style.css">

<style>
.loading-page{
  position:relative;
  min-height:100vh;
  background:var(--bg);
  color:var(--text);
  font-family:'Poppins',sans-serif;
  margin:0;
}

.box{
  position:absolute;
  top:50%; left:50%;
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

h2{color:var(--gold);font-size:24px;font-weight:700;margin:10px 0}
p{color:var(--muted);font-size:15px;margin-top:4px}

@keyframes spin{to{transform:rotate(360deg)}}
.spinner{
  width:50px;height:50px;
  border:4px solid rgba(255,255,255,.15);
  border-top-color:var(--gold);
  border-radius:50%;
  animation:spin .9s linear infinite;
  margin:0 auto 25px;
}

.checkmark,.errormark{
  width:90px;height:90px;border-radius:50%;
  display:none;stroke-width:3;stroke-miterlimit:10;margin:0 auto 12px;
}

.checkmark{stroke:#4BB71B;animation:scale .3s ease-in-out .9s both;}
.checkmark__circle{
  stroke-dasharray:166;stroke-dashoffset:166;stroke-width:3;fill:none;stroke:#4BB71B;
  animation:stroke .6s cubic-bezier(.65,0,.45,1) forwards;
}
.checkmark__check{
  stroke-dasharray:48;stroke-dashoffset:48;fill:none;stroke:#fff;
  animation:stroke .3s cubic-bezier(.65,0,.45,1) .6s forwards;
}

.errormark{stroke:#e74c3c;animation:scale .3s ease-in-out .9s both;}
.errormark__circle{
  stroke-dasharray:166;stroke-dashoffset:166;stroke-width:3;fill:none;stroke:#e74c3c;
  animation:stroke .6s cubic-bezier(.65,0,.45,1) forwards;
}
.errormark__cross{
  stroke-dasharray:48;stroke-dashoffset:48;fill:none;stroke:#fff;
  animation:stroke .3s cubic-bezier(.65,0,.45,1) .6s forwards;
}

@keyframes stroke{100%{stroke-dashoffset:0}}
@keyframes scale{0%,100%{transform:none}50%{transform:scale(1.05)}}
</style>
</head>

<body class="loading-page">

<div class="box">
  <div class="spinner" id="spinner"></div>

  <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
    <circle class="checkmark__circle" cx="26" cy="26" r="25"/>
    <path class="checkmark__check" d="M14 27l7 7 17-17"/>
  </svg>

  <svg class="errormark" id="errormark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
    <circle class="errormark__circle" cx="26" cy="26" r="25"/>
    <path class="errormark__cross" d="M16 16 36 36 M36 16 16 36"/>
  </svg>

  <h2><?= htmlspecialchars($msgTitle) ?></h2>
  <p><?= htmlspecialchars($msgText) ?></p>
</div>

<script>
setTimeout(() => {
  document.getElementById('spinner').style.display = 'none';

  <?php if ($status === 'success'): ?>
    document.getElementById('checkmark').style.display = 'block';
    setTimeout(() => { window.location.href = "<?= htmlspecialchars($redirect, ENT_QUOTES, "UTF-8") ?>"; }, 2500);
  <?php else: ?>
    document.getElementById('errormark').style.display = 'block';
    setTimeout(() => { window.location.href = "<?= htmlspecialchars($redirect, ENT_QUOTES, "UTF-8") ?>"; }, 2500);
  <?php endif; ?>

}, 1500);
</script>

</body>
</html>
