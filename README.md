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
