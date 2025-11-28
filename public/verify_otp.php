<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Xác nhận OTP - VinCine</title>
<link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
body {
  background: var(--bg,#0d0d0d);
  color: var(--text,#fff);
  font-family: 'Poppins',sans-serif;
  display:flex;justify-content:center;align-items:center;
  height:100vh;
}
.otp-wrapper {
  background: var(--card,#111);
  padding:40px 50px;
  border-radius:16px;
  box-shadow:0 10px 25px rgba(0,0,0,.5);
  text-align:center;
  border:1px solid rgba(255,255,255,.08);
}
h2 {
  color:var(--gold,#d4af37);
  font-size:22px;
  margin-bottom:18px;
}
.otp-inputs {
  display:flex;
  justify-content:center;
  gap:10px;
  margin-bottom:14px;
}
.otp-input {
  width:42px;height:52px;
  text-align:center;
  font-size:20px;
  border:2px solid #ccc;
  border-radius:8px;
  transition:all .3s;
}
.otp-input:focus {
  border-color:var(--gold,#d4af37);
  outline:none;
}
.otp-input.error {
  border-color:#e74c3c;
  color:#e74c3c;
}

/* Nút */
.btn-otp {
  width:100%;
  border:none;
  background:var(--gold,#d4af37);
  color:#000;
  padding:10px 0;
  border-radius:10px;
  font-weight:600;
  transition:.2s;
}
.btn-otp:hover { opacity:.85; }
.otp-hint { color:#bbb;font-size:14px;margin-top:6px; }

/* Sai: rung + rơi */
.shake { animation:shake .4s ease; }
@keyframes shake {
  0%,100%{transform:translateX(0);}
  20%,60%{transform:translateX(-8px);}
  40%,80%{transform:translateX(8px);}
}
.fall {
  animation: fallDown .8s ease forwards;
}
@keyframes fallDown {
  0% { transform: translateY(0) rotate(0); opacity: 1; }
  50% { transform: translateY(40px) rotate(10deg); opacity: .6; }
  100% { transform: translateY(120px) rotate(25deg); opacity: 0; }
}

/* Đúng: sáng xanh + mờ dần */
.success-glow {
  border-color:#4BB71B !important;
  box-shadow:0 0 12px #4BB71B;
  animation:fadeOut 0.8s ease forwards;
}
@keyframes fadeOut {
  0% { opacity:1; transform:scale(1); }
  100% { opacity:0; transform:scale(0.9); }
}
</style>
</head>
<body>
<div class="otp-wrapper">
  <h2><i class="bi bi-shield-lock-fill" style="margin-right:6px;"></i>Nhập mã xác nhận OTP</h2>
  <form class="otp-form" method="post" action="../app/controllers/verify_otp.php">
    <div class="otp-inputs">
      <input type="text" maxlength="1" class="otp-input" name="otp1" required>
      <input type="text" maxlength="1" class="otp-input" name="otp2" required>
      <input type="text" maxlength="1" class="otp-input" name="otp3" required>
      <input type="text" maxlength="1" class="otp-input" name="otp4" required>
      <input type="text" maxlength="1" class="otp-input" name="otp5" required>
      <input type="text" maxlength="1" class="otp-input" name="otp6" required>
    </div>
    <button type="submit" class="btn-otp">Xác nhận</button>
    <p class="otp-hint"><i class="bi bi-clock-history"></i> Mã OTP có hiệu lực trong 5 phút.</p>
  </form>
</div>

<script>
const inputs = document.querySelectorAll('.otp-input');
const form = document.querySelector('.otp-form');

inputs.forEach((input, index) => {
  input.addEventListener('input', e => {
    if (e.target.value && index < inputs.length - 1)
      inputs[index + 1].focus();
    if ([...inputs].every(i => i.value.trim() !== ''))
      submitOTP();
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && index > 0)
      inputs[index - 1].focus();
  });
});

function submitOTP() {
  const otp = [...inputs].map(i => i.value).join('');
  fetch('../app/controllers/verify_otp.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'otp=' + encodeURIComponent(otp)
  })
  .then(res => res.text())
  .then(res => {
    if (res.trim() === 'success') correctOTP();
    else wrongOTP();
  })
  .catch(wrongOTP);
}

function wrongOTP() {
  const wrap = document.querySelector('.otp-inputs');
  wrap.classList.add('shake');
  inputs.forEach((i, idx) => {
    i.classList.add('error');
    setTimeout(() => i.classList.add('fall'), idx * 60);
  });
  setTimeout(() => {
    wrap.classList.remove('shake');
    inputs.forEach(i => {
      i.value = '';
      i.classList.remove('error','fall');
    });
    inputs[0].focus();
  }, 1200);
}

function correctOTP() {
  inputs.forEach((i, idx) => {
    setTimeout(() => i.classList.add('success-glow'), idx * 80);
  });
  setTimeout(() => window.location.href = 'reset_password.php', 1000);
}
</script>
</body>
</html>
