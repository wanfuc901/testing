-- =====================================================================
-- Vincent Cinemas — cột phục vụ tích hợp PayOS
--
-- Chạy:
--     mysql -u root vincine < DTB/migrations/2026-09-17_payos.sql
--
-- An toàn khi chạy lại: mọi lệnh đều kiểm tra sự tồn tại trước.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. payments.payos_order_code
--
--    PayOS yêu cầu orderCode là SỐ NGUYÊN DƯƠNG, duy nhất vĩnh viễn trên
--    mỗi kênh thanh toán. Cột provider_txn_id hiện có là chuỗi dạng
--    "#HD00234417092026920" nên không dùng làm orderCode được.
--
--    Giá trị sinh từ mốc thời gian nên luôn tăng và không đụng nhau kể cả
--    khi database bị tạo lại từ đầu.
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
             AND COLUMN_NAME = 'payos_order_code');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD COLUMN `payos_order_code` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `provider_txn_id`',
    'SELECT "payments.payos_order_code đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
             AND INDEX_NAME = 'uk_payments_payos_order_code');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD UNIQUE KEY `uk_payments_payos_order_code` (`payos_order_code`)',
    'SELECT "uk_payments_payos_order_code đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. payments.payos_payment_link_id — id link thanh toán do PayOS cấp,
--    dùng để tra cứu và hủy link.
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
             AND COLUMN_NAME = 'payos_payment_link_id');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD COLUMN `payos_payment_link_id` VARCHAR(64) NULL DEFAULT NULL AFTER `payos_order_code`',
    'SELECT "payments.payos_payment_link_id đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3. payments.payos_reference — mã giao dịch ngân hàng PayOS trả về khi
--    tiền đã vào. Đây là bằng chứng đối soát, phải lưu lại.
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
             AND COLUMN_NAME = 'payos_reference');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD COLUMN `payos_reference` VARCHAR(64) NULL DEFAULT NULL AFTER `payos_payment_link_id`',
    'SELECT "payments.payos_reference đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3b. payments.payos_qr_code — chuỗi EMV của mã QR.
--
--     API tra cứu của PayOS không trả lại chuỗi này, chỉ có ở response lúc
--     tạo link. Không lưu thì mỗi lần khách tải lại trang thanh toán sẽ
--     không vẽ lại được mã QR.
-- ---------------------------------------------------------------------
SET @i := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
             AND COLUMN_NAME = 'payos_qr_code');
SET @sql := IF(@i = 0,
    'ALTER TABLE `payments` ADD COLUMN `payos_qr_code` TEXT NULL DEFAULT NULL AFTER `payos_reference`',
    'SELECT "payments.payos_qr_code đã tồn tại"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 4. payments.status — thêm 'paid' cho trạng thái ĐÃ NHẬN ĐƯỢC TIỀN.
--
--    Phân biệt rõ ba mức:
--      pending  : đã tạo đơn, chưa nhận tiền
--      paid     : PayOS xác nhận tiền đã vào (webhook đã verify chữ ký)
--      success  : nhân viên đã xuất vé / hoàn tất
--      canceled : hết hạn hoặc bị hủy
-- ---------------------------------------------------------------------
ALTER TABLE `payments`
    MODIFY `status` ENUM('pending','paid','success','fail','canceled')
    NOT NULL DEFAULT 'pending';

-- ---------------------------------------------------------------------
-- 5. Nhật ký webhook — PayOS gửi lại webhook nhiều lần cho cùng một giao
--    dịch. Bảng này vừa để chống xử lý trùng, vừa để đối soát khi có
--    tranh chấp.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payos_webhook_log` (
    `id`            INT(11)             NOT NULL AUTO_INCREMENT,
    `order_code`    BIGINT UNSIGNED     NULL DEFAULT NULL,
    `reference`     VARCHAR(64)         NULL DEFAULT NULL,
    `payment_id`    INT(11)             NULL DEFAULT NULL,
    -- verified: chữ ký HMAC hợp lệ. processed: đã cập nhật đơn hàng.
    `verified`      TINYINT(1)          NOT NULL DEFAULT 0,
    `processed`     TINYINT(1)          NOT NULL DEFAULT 0,
    `note`          VARCHAR(255)        NULL DEFAULT NULL,
    `raw_body`      TEXT                NULL DEFAULT NULL,
    `received_at`   DATETIME            NOT NULL,
    PRIMARY KEY (`id`),
    -- Một reference của PayOS chỉ được xử lý đúng một lần.
    UNIQUE KEY `uk_webhook_reference` (`reference`),
    KEY `idx_webhook_order` (`order_code`),
    KEY `idx_webhook_time` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
