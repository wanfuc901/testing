🎬 Testing Project – Web Application

Author: Phạm Hoàng Phúc
Trường: Cao đẳng Cộng đồng Sóc Trăng – Khoa Kinh tế

<p align="left"> <img src="https://img.shields.io/badge/PHP-7.4%2B-blue?style=flat-square" /> <img src="https://img.shields.io/badge/MySQL-vincine-orange?style=flat-square" /> <img src="https://img.shields.io/badge/Version%20Control-GitHub-black?style=flat-square" /> <img src="https://img.shields.io/badge/Status-Active-success?style=flat-square" /> </p>
📘 Giới thiệu

Dự án Testing Web Application được xây dựng nhằm thực hành quy trình phát triển một ứng dụng web hoàn chỉnh, bao gồm frontend, backend, kết nối cơ sở dữ liệu và quản lý mã nguồn bằng GitHub.

Mục tiêu:

Xây dựng website chạy ổn định

Thành thạo thao tác CRUD với MySQL

Áp dụng mô hình tách thư mục rõ ràng

Sử dụng Git/GitHub để quản lý dự án

✨ Tính năng nổi bật
Frontend

HTML5 + CSS3 tùy chỉnh

JavaScript xử lý tương tác người dùng

Backend (PHP)

Xử lý request, routing đơn giản

CRUD với MySQL

Helper functions tách riêng theo nghiệp vụ

Admin Panel

Khu vực quản trị độc lập

Quản lý nội dung cơ bản

🧰 Tech Stack
Thành phần	Công nghệ
Frontend	HTML5, CSS3, JavaScript
Backend	PHP 7+
Database	MySQL (DB: vincine)
Realtime	Socket.io (optional)
Thư viện	PHPMailer, Composer vendor
Version Control	Git + GitHub
📂 Project Structure
testing/
│── admin/                  # Admin Panel
│── app/                    # Config, controllers, core logic
│── helpers/                # Helper PHP utilities
│── public/                 # CSS, JS, images
│── socket.io/              # Realtime server (optional)
│── vendor/                 # Composer dependencies
│── index.php               # App entry point
│── structure.txt           # Project structure description
└── README.md               # This documentation

🚀 Cài đặt & chạy thử
1. Clone repository
git clone https://github.com/wanfuc901/testing.git

2. Tạo database

Mở phpMyAdmin

Tạo database tên: vincine

Import file SQL (nếu có)

3. Cấu hình kết nối

Mở file:
app/config/config.php

Sửa thành:

$dbHost = "127.0.0.1";
$dbUser = "root";
$dbPass = "";
$dbName = "vincine";

4. Chạy dự án

Đưa vào htdocs (XAMPP)

Truy cập trình duyệt:

http://localhost/testing

🔧 Hướng phát triển

Nâng cấp UI/UX theo chuẩn hiện đại

Thêm phân quyền người dùng

Tăng cường bảo mật (SQL Injection, XSS)

Tách API riêng cho mobile/web client

Viết báo cáo kỹ thuật & tài liệu thiết kế hệ thống

🤝 Đóng góp

Pull Request & Issues được chào đón.
Hãy tạo branch mới khi gửi PR.

📄 License

Open for educational and personal portfolio use.
