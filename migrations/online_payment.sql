ALTER TABLE orders
  MODIFY payment_method enum('cash','transfer','vnpay') NOT NULL DEFAULT 'cash',
  MODIFY payment_status enum('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid';

SET @sql := IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'payment_transaction_id'
  ),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_transaction_id varchar(100) DEFAULT NULL AFTER payment_status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'payment_bank_code'
  ),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_bank_code varchar(30) DEFAULT NULL AFTER payment_transaction_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'payment_response_code'
  ),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_response_code varchar(10) DEFAULT NULL AFTER payment_bank_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'payment_paid_at'
  ),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_paid_at datetime DEFAULT NULL AFTER payment_response_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
