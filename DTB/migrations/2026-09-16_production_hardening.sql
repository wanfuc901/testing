-- =====================================================================
-- Vincent Cinemas — migration đồng bộ schema với code sau đợt hardening
--
-- Chạy trên database `vincine`:
--     mysql -u root vincine < DTB/migrations/2026-09-16_production_hardening.sql
--
-- An toàn khi chạy lại: mọi lệnh đều kiểm tra sự tồn tại trước.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. payments: bổ sung trạng thái 'canceled' và mốc thời gian hủy
--    app/controllers/payment_callback.php ghi hai giá trị này khi hết
--    thời gian giữ ghế.
-- ---------------------------------------------------------------------
ALTER TABLE `payments`
    MODIFY `status` ENUM('pending','paid','success','fail','canceled')
    NOT NULL DEFAULT 'pending';

SET @has_canceled_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'canceled_at'
);
SET @sql := IF(@has_canceled_at = 0,
    'ALTER TABLE `payments` ADD COLUMN `canceled_at` DATETIME NULL DEFAULT NULL AFTER `paid_at`',
    'SELECT "payments.canceled_at đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. tickets: ràng buộc chống đặt trùng ghế
--
--    ĐÃ TÁCH sang DTB/migrations/2026-09-16_dedupe_tickets.sql vì dữ liệu
--    hiện tại đang có ghế bị đặt trùng thật, phải dọn trước khi thêm index.
--    Chạy script đó sau khi đã rà soát và sao lưu.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- 3. Chỉ mục cho các cột thường xuyên lọc/join nhưng chưa có index
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_customer');
SET @sql := IF(@i = 0,
    'ALTER TABLE `tickets` ADD KEY `idx_tickets_customer` (`customer_id`)',
    'SELECT "idx_tickets_customer đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_payment');
SET @sql := IF(@i = 0,
    'ALTER TABLE `tickets` ADD KEY `idx_tickets_payment` (`payment_id`)',
    'SELECT "idx_tickets_payment đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_booked_at');
SET @sql := IF(@i = 0,
    'ALTER TABLE `tickets` ADD KEY `idx_tickets_booked_at` (`booked_at`)',
    'SELECT "idx_tickets_booked_at đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_customer');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD KEY `idx_payments_customer` (`customer_id`)',
    'SELECT "idx_payments_customer đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'showtimes' AND INDEX_NAME = 'idx_showtimes_movie_start');
SET @sql := IF(@i = 0,
    'ALTER TABLE `showtimes` ADD KEY `idx_showtimes_movie_start` (`movie_id`, `start_time`)',
    'SELECT "idx_showtimes_movie_start đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seats' AND INDEX_NAME = 'idx_seats_room');
SET @sql := IF(@i = 0,
    'ALTER TABLE `seats` ADD KEY `idx_seats_room` (`room_id`)',
    'SELECT "idx_seats_room đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 4. Ràng buộc UNIQUE (ratings, customers.email, users.email)
--
--    ĐÃ TÁCH sang DTB/migrations/2026-09-16_dedupe_tickets.sql: dữ liệu
--    hiện tại đang vi phạm các ràng buộc này nên phải dọn trước.
--    File migration này chỉ chứa thay đổi cộng thêm, chạy được ngay.
-- ---------------------------------------------------------------------
