-- =====================================================================
-- Vincent Cinemas — bảng ghi nhận đăng nhập sai (chống dò mật khẩu)
--
-- Chạy:
--     mysql -u root vincine < DTB/migrations/2026-09-17_login_attempts.sql
--
-- app/include/auth.php đọc bảng này để giới hạn số lần đăng nhập sai.
-- Nếu bảng chưa tồn tại, throttle tự tắt và ghi cảnh báo vào log —
-- đăng nhập vẫn chạy bình thường, chỉ mất lớp bảo vệ này.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    -- Email người dùng nhập vào, không phải khoá ngoại: cần đếm cả email
    -- không tồn tại để chặn kiểu dò theo danh sách email.
    `identifier`   VARCHAR(190) NOT NULL,
    `ip`           VARCHAR(45)  NOT NULL,
    `attempted_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attempt_identifier` (`identifier`, `attempted_at`),
    KEY `idx_attempt_ip` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
