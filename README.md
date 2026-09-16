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
