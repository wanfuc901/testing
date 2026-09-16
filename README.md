# 🎬 Vincent Cinemas – Web Application  
**Author:** Phạm Hoàng Phúc  
**Trường:** Cao đẳng Cộng đồng Sóc Trăng – Khoa Kinh tế  

<p align="left">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-blue?style=flat-square" />
  <img src="https://img.shields.io/badge/MySQL-vincine-orange?style=flat-square" />
  <img src="https://img.shields.io/badge/Version%20Control-GitHub-black?style=flat-square" />
  <img src="https://img.shields.io/badge/Status-Active-success?style=flat-square" />
</p>

---

## 📘 Giới thiệu
**Vincent Cinemas Application** là dự án mô phỏng hệ thống đặt vé xem phim, được xây dựng để thực hành quy trình phát triển web từ frontend → backend → database → realtime → deploy local.  
Dự án giúp người phát triển rèn luyện các kỹ năng nền tảng:

- Phát triển website chạy ổn định bằng PHP & MySQL  
- Thành thạo thao tác CRUD với cơ sở dữ liệu  
- Tổ chức thư mục theo mô hình MVC đơn giản  
- Quản lý mã nguồn bằng Git/GitHub  
- Tích hợp thử nghiệm tính năng realtime bằng Socket.io  
- Phù hợp cho bài tập lớn, đồ án tốt nghiệp hoặc portfolio cá nhân  

---

## ✨ Tính năng nổi bật

### 🎨 Frontend
- HTML5 + CSS3 tùy chỉnh  
- JavaScript xử lý tương tác người dùng  
- Giao diện đơn giản, dễ mở rộng và nâng cấp  

### 🧩 Backend (PHP)
- Routing và xử lý request cơ bản  
- Chức năng CRUD đầy đủ  
- Kết nối MySQL với cấu trúc chuẩn, dễ bảo trì  
- Helper functions tách riêng theo nghiệp vụ để tối ưu codebase  

### 🔐 Admin Panel
- Khu vực quản trị độc lập  
- Quản lý nội dung, dữ liệu và tác vụ hệ thống  

### ⚡ Realtime (Optional)
- Socket.io dùng để thử nghiệm các tính năng realtime như trạng thái ghế, thông báo,…

---

## 🧰 Tech Stack

| Thành phần      | Công nghệ |
|-----------------|-----------|
| Frontend        | HTML5, CSS3, JavaScript |
| Backend         | PHP 7+ |
| Database        | MySQL (DB: **vincine**) |
| Realtime        | Socket.io (optional) |
| Thư viện        | PHPMailer, Composer vendor |
| Version Control | Git + GitHub |

---

## 📂 Cấu trúc thư mục

```text
VincentCinemas/
│── admin/                  # Admin Panel
│── app/                    # Config, controllers, core logic
│── helpers/                # Helper PHP utilities
│── public/                 # CSS, JS, images
│── socket.io/              # Realtime server (optional)
│── vendor/                 # Composer dependencies
│── index.php               # App entry point
│── structure.txt           # Mô tả cấu trúc dự án
└── README.md               # Tài liệu dự án
```

---

## ⚙️ Cài đặt

### 1. Cấu hình môi trường

Toàn bộ thông tin nhạy cảm (mật khẩu DB, App Password Gmail, Google Client ID)
nằm trong `app/config/env.php` — file này **nằm trong `.gitignore` và không bao
giờ được commit**.

```bash
cp app/config/env.example.php app/config/env.php
# mở app/config/env.php và điền giá trị thật
```

Trên hosting, có thể đặt các khóa tương ứng bằng biến môi trường hệ thống thay
cho file; `env.php` có độ ưu tiên cao hơn.

Đặt `'APP_DEBUG' => false` khi chạy thật: ở chế độ này lỗi PHP được ghi vào
`app/storage/logs/php-error.log` thay vì in ra trình duyệt.

### 2. Cơ sở dữ liệu

```bash
# Tạo database và nạp schema
mysql -u root -e "CREATE DATABASE vincine CHARACTER SET utf8mb4"
mysql -u root vincine < "DTB/vincine .sql"

# Đồng bộ schema với phiên bản code hiện tại (chỉ thêm, an toàn chạy lại)
mysql -u root vincine < DTB/migrations/2026-09-16_production_hardening.sql
```

Script dọn dữ liệu trùng chạy riêng vì nó **sửa dữ liệu**. Sao lưu trước:

```bash
mysqldump -u root vincine > backup.sql
mysql -u root vincine < DTB/migrations/2026-09-16_dedupe_tickets.sql
```

### 3. Thư viện

```bash
composer install
```

---

## 🔐 Ghi chú bảo mật

- Mọi endpoint quản trị (`app/controllers/admin/*`, `app/api/*`, `admin/*`)
  đều nạp `app/include/require_admin.php` ở dòng đầu. Router `main.php` không
  đủ để bảo vệ vì các file này gọi trực tiếp bằng URL được.
- Đăng nhập Google xác thực ID token qua endpoint `tokeninfo` của Google và
  kiểm tra `aud` khớp `GOOGLE_CLIENT_ID`. Không tự giải mã payload JWT.
- Các trang gắn với một đơn hàng (`payment_qr.php`, `booking_pending.php`,
  `ticket_detail.php`) đều lọc theo `customer_id` của phiên đăng nhập.
- `users.user_id` và `customers.customer_id` là hai dãy số độc lập — không
  dùng chung một biến ID cho cả hai bảng.

---

## 🛡️ Chống CSRF

Mọi biểu mẫu POST nhúng `<?= vincine_csrf_input() ?>`, mọi handler POST gọi
`vincine_verify_csrf()`. Endpoint quản trị nhận kiểm tra này tự động qua
`app/include/require_admin.php` nên không thể bỏ sót khi thêm file mới.

JavaScript gọi `fetch` gửi token qua header `X-CSRF-Token`:

```js
fetch('app/controllers/admin/showtimes_controller.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    'X-CSRF-Token': <?= json_encode(vincine_csrf_token()) ?>
  },
  body: 'action=delete&showtime_id=' + id
});
```

Request thiếu token nhận HTTP 419.

## 🚦 Giới hạn đăng nhập sai

`login_attempts` ghi lại mỗi lần đăng nhập sai theo email và IP. Quá 5 lần cho
một email hoặc 20 lần từ một IP trong 15 phút thì bị chặn, trả HTTP 429.
Các ngưỡng nằm ở đầu phần throttle trong `app/include/auth.php`.

Nếu chưa chạy `DTB/migrations/2026-09-17_login_attempts.sql`, tính năng này tự
tắt và ghi cảnh báo vào log — đăng nhập vẫn hoạt động bình thường.

Mã OTP đặt lại mật khẩu bị khoá sau 5 lần nhập sai, phải xin mã mới.

---

## 💳 Thanh toán PayOS

Tiền vào tài khoản là vé chốt tự động, không cần nhân viên đối soát thủ công.

### Cấu hình

Thêm vào `app/config/env.php` (lấy ở https://my.payos.vn → Kênh thanh toán → Thông tin xác thực):

```php
'PAYOS_CLIENT_ID'    => '...',
'PAYOS_API_KEY'      => '...',
'PAYOS_CHECKSUM_KEY' => '...',   // 64 ký tự
'PAYOS_WEBHOOK_URL'  => 'https://ten-mien/app/api/payos_webhook.php',
'APP_BASE_URL'       => 'https://ten-mien',
```

> Ô Checksum Key trên dashboard PayOS hiển thị **thiếu ký tự**. Phải bấm nút
> copy, không đọc bằng mắt. Key ngắn hơn 64 ký tự thì mọi chữ ký đều sai và
> PayOS trả lỗi `201 - Mã kiểm tra(signature) không hợp lệ`.

Kiểm tra cấu hình và đăng ký webhook:

```bash
php app/cron/payos_register_webhook.php
```

Script tự báo nếu khóa sai độ dài hoặc URL không đăng ký được. URL webhook phải
công khai trên Internet và dùng HTTPS — localhost không dùng được, khi dev hãy
chạy `ngrok http 80`.

### Luồng

1. `checkout_online.php` tạo link PayOS, lưu `payos_order_code`, `payos_payment_link_id`
   và chuỗi QR vào bảng `payments`. Tạo link thất bại thì ghế được nhả ngay.
2. `payment_qr.php` vẽ mã QR bằng thư viện cục bộ và hỏi trạng thái mỗi 3 giây.
3. Khách chuyển khoản → PayOS gọi `app/api/payos_webhook.php`.
4. Webhook **verify chữ ký HMAC-SHA256 trước mọi thứ khác**, đối chiếu số tiền
   với đơn trong DB, rồi mới đánh dấu đã trả và xuất vé.

### Vì sao không có nút "tôi đã chuyển khoản"

Nút cũ đánh dấu đơn đã trả mà không kiểm chứng gì — bấm là được vé. Giờ chỉ hai
đường dẫn tới trạng thái `paid`:

- webhook PayOS đã verify chữ ký, hoặc
- ứng dụng chủ động hỏi `GET /v2/payment-requests/{orderCode}` và PayOS trả về
  `status=PAID` với `amountPaid` đủ số tiền.

Cả hai đều idempotent: `vincine_mark_payment_paid()` khoá dòng đơn hàng bằng
`SELECT ... FOR UPDATE`, và `payos_webhook_log.reference` có khoá UNIQUE nên một
giao dịch chỉ được xử lý đúng một lần dù PayOS gửi lại bao nhiêu lần.

### Đối soát

Bảng `payos_webhook_log` lưu mọi gói tin nhận được, kể cả gói có chữ ký sai
(`verified = 0`). Khi có tranh chấp, tra bảng này trước.
