# 🎬 Vincent Cinemas– Web Application  
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
**Testing Web Application** là một dự án được xây dựng nhằm thực hành toàn bộ quy trình phát triển ứng dụng web từ frontend → backend → database → deploy local.  
Dự án giúp người học rèn luyện kỹ năng:

- Phát triển website chạy ổn định bằng PHP & MySQL  
- Thành thạo thao tác CRUD  
- Tổ chức thư mục theo chuẩn MVC đơn giản  
- Sử dụng Git/GitHub để quản lý mã nguồn  
- Thử nghiệm thêm tính năng realtime bằng Socket.io  

Dự án phù hợp làm **bài tập lớn**, **đồ án tốt nghiệp**, hoặc **portfolio cá nhân**.

---

## ✨ Tính năng nổi bật

### 🎨 Frontend
- HTML5 + CSS3 tùy chỉnh  
- JavaScript xử lý thao tác người dùng  
- Giao diện đơn giản, dễ mở rộng  

### 🧩 Backend (PHP)
- Xử lý request, routing cơ bản  
- Chức năng CRUD đầy đủ  
- Kết nối MySQL và truy vấn bảo mật hơn  
- Các helper được chia theo nghiệp vụ  

### 🔐 Admin Panel
- Giao diện quản trị độc lập  
- Quản lý nội dung / dữ liệu dễ dàng  

### ⚡ Realtime (Optional)
- Tích hợp Socket.io cho các tính năng realtime thử nghiệm

---

## 🧰 Tech Stack

| Thành phần      | Công nghệ |
|-----------------|-----------|
| Frontend        | HTML5, CSS3, JavaScript |
| Backend         | PHP 7+ |
| Database        | MySQL (DB: **vincine**) |
| Realtime        | Socket.io (tùy chọn) |
| Thư viện        | PHPMailer, Composer vendor |
| Version Control | Git + GitHub |

---

## 📂 Cấu trúc thư mục

```text
testing/
│── admin/                  # Admin Panel
│── app/                    # Config, controllers, core logic
│── helpers/                # Helper PHP utilities
│── public/                 # CSS, JS, images
│── socket.io/              # Realtime server (optional)
│── vendor/                 # Composer dependencies
│── index.php               # App entry point
│── structure.txt           # Mô tả cấu trúc dự án
└── README.md               # Tài liệu dự án
