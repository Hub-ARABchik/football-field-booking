-- Футбол Арена: пойгоҳи додаҳо (MySQL 5.7+ / MariaDB 10.2+)
CREATE DATABASE IF NOT EXISTS futbol_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE futbol_booking;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, bookings, prices, fields, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120) NOT NULL,
  phone         VARCHAR(20)  NOT NULL UNIQUE,
  email         VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('client','admin') NOT NULL DEFAULT 'client',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE fields (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  size         VARCHAR(20)  NOT NULL,                 -- 5x5, 7x7, 11x11
  surface      ENUM('Алафи сунъӣ','Алафи табиӣ','Паркет','Резина') NOT NULL DEFAULT 'Алафи сунъӣ',
  photo        VARCHAR(255) NULL,
  description  TEXT NULL,
  open_time    TIME NOT NULL DEFAULT '08:00:00',      -- вақти кушодан
  close_time   TIME NOT NULL DEFAULT '23:00:00',      -- вақти пӯшидан
  evening_from TIME NOT NULL DEFAULT '18:00:00',      -- аз кадом соат нархи шом оғоз мешавад
  is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Нархи гуногун: рӯзи корӣ/истироҳат x рӯз/шом (барои як соат, сомонӣ)
CREATE TABLE prices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  field_id    INT UNSIGNED NOT NULL,
  day_type    ENUM('weekday','weekend') NOT NULL,
  time_of_day ENUM('day','evening') NOT NULL,
  price       DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uq_price (field_id, day_type, time_of_day),
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  field_id     INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  start_hour   TINYINT UNSIGNED NOT NULL,            -- 0..23, давомнокӣ 1 соат
  price        DECIMAL(10,2) NOT NULL,               -- нарх дар лаҳзаи банд
  status       ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  -- Пешгирии банди такрорӣ: банди фаъол (на бекоршуда) барои як майдонча+сана+соат танҳо як бор
  active_slot  TINYINT GENERATED ALWAYS AS (IF(status='cancelled', NULL, 1)) STORED,
  UNIQUE KEY uq_slot (field_id, booking_date, start_hour, active_slot),
  KEY idx_user (user_id),
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id   INT UNSIGNED NOT NULL UNIQUE,
  amount       DECIMAL(10,2) NOT NULL,
  method       ENUM('cash','transfer') NOT NULL DEFAULT 'cash',
  status       ENUM('pending','confirmed','rejected','refunded') NOT NULL DEFAULT 'pending',
  confirmed_by INT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id)   REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (confirmed_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== Маълумоти намунавӣ =====
-- Маъмур: телефон 900000000, парол: password
INSERT INTO users (full_name, phone, password_hash, role) VALUES
('Маъмури сомона', '900000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO fields (name, size, surface, description, open_time, close_time, evening_from) VALUES
('Арена Меркурий', '5x5',   'Алафи сунъӣ', 'Майдончаи сарпӯшида бо равшании LED ва ҷойи либосивазкунӣ.', '08:00', '23:00', '18:00'),
('Арена Олимп',    '7x7',   'Алафи сунъӣ', 'Майдончаи кушода бо алафи насли нав ва дарвозаҳои стандартӣ.', '08:00', '23:00', '18:00'),
('Стадион Хуҷанд', '11x11', 'Алафи табиӣ', 'Майдони калон барои бозиҳои пурра, бо трибунаи хурд.', '09:00', '22:00', '18:00');

INSERT INTO prices (field_id, day_type, time_of_day, price) VALUES
(1,'weekday','day',80),(1,'weekday','evening',120),(1,'weekend','day',100),(1,'weekend','evening',150),
(2,'weekday','day',110),(2,'weekday','evening',160),(2,'weekend','day',140),(2,'weekend','evening',200),
(3,'weekday','day',250),(3,'weekday','evening',350),(3,'weekend','day',300),(3,'weekend','evening',420);
