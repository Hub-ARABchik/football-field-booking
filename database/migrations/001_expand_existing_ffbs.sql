-- FFBS migration 001: expand the legacy database created by database/database.sql.
-- BACK UP FIRST. Run once on a staging copy before applying to production.
-- This migration deliberately does not DROP tables or delete existing rows.
SET NAMES utf8mb4;

-- Map the old roles without losing existing accounts.
ALTER TABLE users
  MODIFY role ENUM('client','admin','CUSTOMER','OWNER','ADMIN') NOT NULL DEFAULT 'client';
UPDATE users SET role = 'CUSTOMER' WHERE role = 'client';
UPDATE users SET role = 'ADMIN' WHERE role = 'admin';
ALTER TABLE users
  MODIFY role ENUM('CUSTOMER','OWNER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
  MODIFY email VARCHAR(190) NULL,
  ADD COLUMN status ENUM('active','blocked','pending') NOT NULL DEFAULT 'active' AFTER role,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uq_users_email (email),
  ADD KEY idx_users_role_status (role, status);

-- Add owner and moderation/location metadata. Existing fields stay in the catalogue.
ALTER TABLE fields
  ADD COLUMN owner_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Хуҷанд' AFTER name,
  ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '' AFTER city,
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER address,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
  ADD COLUMN capacity SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER description,
  ADD COLUMN status ENUM('draft','pending','approved','rejected','suspended') NOT NULL DEFAULT 'approved' AFTER evening_from,
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY idx_fields_catalog (status, is_active, city),
  ADD KEY idx_fields_owner (owner_id),
  ADD CONSTRAINT fk_fields_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE bookings
  ADD COLUMN booking_code VARCHAR(32) NULL AFTER id,
  ADD COLUMN duration_hours TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER start_hour,
  ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER note,
  ADD COLUMN confirmed_by INT UNSIGNED NULL AFTER cancelled_at,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uq_booking_code (booking_code),
  ADD KEY idx_bookings_field_day (field_id, booking_date, status),
  ADD CONSTRAINT fk_bookings_confirmer FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE payments
  ADD COLUMN reference VARCHAR(120) NULL AFTER status,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY idx_payments_status (status, created_at);

CREATE TABLE field_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  field_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(180) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_field_images_field FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE,
  KEY idx_field_images_order (field_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  field_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  open_time TIME NOT NULL,
  close_time TIME NOT NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_field_schedules_field FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE,
  UNIQUE KEY uq_field_day (field_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  field_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_reviews_field FOREIGN KEY (field_id) REFERENCES fields(id),
  UNIQUE KEY uq_review_booking (booking_id),
  KEY idx_reviews_field_status (field_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'general',
  related_type VARCHAR(40) NULL,
  related_id INT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor_time (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing active fields were admin-managed. Keep them visible during migration.
UPDATE fields SET status = 'approved' WHERE is_active = 1;
