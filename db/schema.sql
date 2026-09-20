-- Booking App schema. Independent database, no cross-DB references.
-- Run once against a fresh `booking_app` database.

SET NAMES utf8mb4;

CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'admin',
  status TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Failed admin sign-in attempts, used by core/LoginThrottle.php.
CREATE TABLE login_attempt (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempted_at),
  INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description TEXT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  image_url VARCHAR(500) NULL,
  notification_email VARCHAR(190) NULL,
  client_email_field_key VARCHAR(64) NULL,
  booking_window_days SMALLINT UNSIGNED NULL,
  lead_time_hours SMALLINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_weekly_slot (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY uq_slot (service_id, weekday, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marker: presence of a row means this (service, week) has its own schedule
-- instead of inheriting service_weekly_slot. A week is identified by the
-- calendar date its day-chunk starts on (1, 8, 15, 22, 29 of the month).
CREATE TABLE service_week_override (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  week_start_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY uq_week (service_id, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The actual slots for a customized week. A week_override row with zero
-- child slots here means that week was deliberately cleared blank - distinct
-- from no service_week_override row at all, which means "inherit default".
CREATE TABLE service_week_override_slot (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_override_id INT UNSIGNED NOT NULL,
  override_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (week_override_id) REFERENCES service_week_override(id) ON DELETE CASCADE,
  UNIQUE KEY uq_override_slot (week_override_id, override_date, start_time),
  INDEX idx_date (override_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_blackout_date (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  blackout_date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY uq_blackout (service_id, blackout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_field (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  field_key VARCHAR(64) NOT NULL,
  label VARCHAR(190) NOT NULL,
  field_type ENUM('text','textarea','number','date','time','date_dropdown','time_dropdown','email','phone','select','radio','checkbox') NOT NULL,
  is_required TINYINT NOT NULL DEFAULT 0,
  placeholder VARCHAR(190) NULL,
  help_text VARCHAR(255) NULL,
  options_json TEXT NULL,
  validation_json TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY uq_field_key (service_id, field_key),
  INDEX idx_sort (service_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE one_time_link (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL UNIQUE,
  status ENUM('unused','used','disabled') NOT NULL DEFAULT 'unused',
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  booking_id INT UNSIGNED NULL,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_status (service_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE booking (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_reference VARCHAR(32) NOT NULL UNIQUE,
  service_id INT UNSIGNED NOT NULL,
  link_id INT UNSIGNED NOT NULL UNIQUE,
  service_name_snapshot VARCHAR(190) NOT NULL,
  service_duration_snapshot SMALLINT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  status ENUM('confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed',
  admin_notes TEXT NULL,
  client_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  deleted_at DATETIME NULL,
  occupancy_key VARCHAR(64) GENERATED ALWAYS AS (
    CASE WHEN status = 'cancelled' THEN NULL
         ELSE CONCAT(service_id, '_', appointment_date, '_', appointment_time) END
  ) STORED,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  FOREIGN KEY (link_id) REFERENCES one_time_link(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_booking_occupancy (occupancy_key),
  INDEX idx_service_date (service_id, appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE booking_field_value (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  field_key VARCHAR(64) NOT NULL,
  field_label_snapshot VARCHAR(190) NOT NULL,
  field_type_snapshot VARCHAR(32) NOT NULL,
  value_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE,
  INDEX idx_booking (booking_id),
  INDEX idx_field_search (field_key, value_text(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('business_name', 'Booking App'),
  ('business_phone', ''),
  ('business_email', ''),
  ('business_logo_url', ''),
  ('default_booking_window_days', '30'),
  ('default_lead_time_hours', '24'),
  ('email_subject_template', 'Booking Confirmed: {{service_name}}'),
  ('email_body_template', '<p>Hello,</p><p>This confirms a booking for <strong>{{service_name}}</strong>.</p><p>Date: {{appointment_date}}<br>Time: {{appointment_time}}<br>Reference: {{booking_reference}}</p><p>{{submitted_fields}}</p>');
