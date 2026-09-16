-- =====================================================================
-- Vincent Cinemas — dọn vé đặt trùng ghế và thêm ràng buộc chống tái diễn
--
-- ⚠️ SAO LƯU TRƯỚC KHI CHẠY:
--     mysqldump -u root vincine > backup_truoc_dedupe.sql
--
-- Chạy:
--     mysql -u root vincine < DTB/migrations/2026-09-16_dedupe_tickets.sql
--
-- Script này KHÔNG xóa vé. Vé trùng bị chuyển sang status='cancelled'
-- để giữ nguyên dấu vết đối soát; vé được giữ lại là vé có ticket_id nhỏ
-- nhất (đặt sớm nhất) trong mỗi cặp (showtime_id, seat_id).
-- =====================================================================

-- ---------------------------------------------------------------------
-- BƯỚC 0 — Báo cáo trước khi sửa. Đọc kỹ kết quả rồi mới chạy tiếp.
-- ---------------------------------------------------------------------
SELECT showtime_id,
       seat_id,
       COUNT(*) AS so_ve,
       GROUP_CONCAT(CONCAT(ticket_id, ':', IFNULL(status, 'NULL'))
                    ORDER BY ticket_id) AS danh_sach_ve
FROM tickets
WHERE status IN ('pending', 'paid', 'confirmed')
GROUP BY showtime_id, seat_id
HAVING so_ve > 1;

-- ---------------------------------------------------------------------
-- BƯỚC 1 — Chuẩn hóa vé có status rỗng (dữ liệu cũ ghi sai) về 'cancelled'
-- ---------------------------------------------------------------------
UPDATE tickets
SET status = 'cancelled'
WHERE status IS NULL OR status = '';

-- ---------------------------------------------------------------------
-- BƯỚC 2 — Hủy vé trùng, giữ lại vé đặt sớm nhất của mỗi ghế
-- ---------------------------------------------------------------------
UPDATE tickets t
JOIN (
    SELECT showtime_id, seat_id, MIN(ticket_id) AS giu_lai
    FROM tickets
    WHERE status IN ('pending', 'paid', 'confirmed')
    GROUP BY showtime_id, seat_id
    HAVING COUNT(*) > 1
) d
  ON d.showtime_id = t.showtime_id
 AND d.seat_id     = t.seat_id
SET t.status = 'cancelled'
WHERE t.status IN ('pending', 'paid', 'confirmed')
  AND t.ticket_id <> d.giu_lai;

-- ---------------------------------------------------------------------
-- BƯỚC 3 — Ràng buộc chống đặt trùng ở tầng CSDL
--
-- Không dùng UNIQUE(showtime_id, seat_id) trực tiếp: như vậy một ghế đã
-- hủy sẽ không bao giờ bán lại được. Thay vào đó dùng cột sinh tự động
-- chỉ có giá trị khi vé còn hiệu lực — vé đã hủy nhận NULL và NULL không
-- xung đột trong unique index.
-- ---------------------------------------------------------------------
SET @has_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'active_seat_key'
);
SET @sql := IF(@has_col = 0,
    "ALTER TABLE `tickets` ADD COLUMN `active_seat_key` VARCHAR(32)
        AS (IF(`status` IN ('pending','paid','confirmed'),
               CONCAT(`showtime_id`, '-', `seat_id`),
               NULL)) VIRTUAL",
    'SELECT "tickets.active_seat_key đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND INDEX_NAME = 'uk_active_seat'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE `tickets` ADD UNIQUE KEY `uk_active_seat` (`active_seat_key`)',
    'SELECT "tickets.uk_active_seat đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- BƯỚC 4 — Xác nhận không còn trùng
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS con_lai_trung
FROM (
    SELECT showtime_id, seat_id
    FROM tickets
    WHERE status IN ('pending', 'paid', 'confirmed')
    GROUP BY showtime_id, seat_id
    HAVING COUNT(*) > 1
) x;

-- =====================================================================
-- PHẦN B — Ràng buộc UNIQUE cho ratings và email tài khoản
-- =====================================================================

-- ---------------------------------------------------------------------
-- B0 — Báo cáo dữ liệu đang vi phạm
-- ---------------------------------------------------------------------
SELECT 'ratings trùng' AS loai, movie_id, customer_id, COUNT(*) AS so_ban_ghi
FROM ratings GROUP BY movie_id, customer_id HAVING so_ban_ghi > 1;

SELECT 'customers trùng email' AS loai, email, COUNT(*) AS so_ban_ghi
FROM customers GROUP BY email HAVING so_ban_ghi > 1;

SELECT 'users trùng email' AS loai, email, COUNT(*) AS so_ban_ghi
FROM users GROUP BY email HAVING so_ban_ghi > 1;

-- ---------------------------------------------------------------------
-- B1 — ratings: mỗi khách chỉ giữ một đánh giá mới nhất cho mỗi phim
--      (bản ghi cũ hơn bị xóa — ratings không phải dữ liệu đối soát)
-- ---------------------------------------------------------------------
DELETE r FROM ratings r
JOIN (
    SELECT movie_id, customer_id, MAX(rating_id) AS giu_lai
    FROM ratings
    GROUP BY movie_id, customer_id
    HAVING COUNT(*) > 1
) d
  ON d.movie_id    = r.movie_id
 AND d.customer_id = r.customer_id
WHERE r.rating_id <> d.giu_lai;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ratings'
             AND INDEX_NAME = 'uk_rating_movie_customer');
SET @sql := IF(@i = 0,
    'ALTER TABLE `ratings` ADD UNIQUE KEY `uk_rating_movie_customer` (`movie_id`, `customer_id`)',
    'SELECT "uk_rating_movie_customer đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- B2 — Email tài khoản phải duy nhất
--
--      KHÔNG tự động gộp/xóa tài khoản trùng email: việc đó ảnh hưởng
--      tới vé và hóa đơn đã phát sinh. Nếu lệnh dưới báo lỗi 1062, xử lý
--      thủ công các bản ghi mà báo cáo B0 đã liệt kê rồi chạy lại.
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
             AND INDEX_NAME = 'uk_customers_email');
SET @sql := IF(@i = 0,
    'ALTER TABLE `customers` ADD UNIQUE KEY `uk_customers_email` (`email`)',
    'SELECT "uk_customers_email đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'uk_users_email');
SET @sql := IF(@i = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `uk_users_email` (`email`)',
    'SELECT "uk_users_email đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
