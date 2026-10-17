CREATE DATABASE IF NOT EXISTS HOU
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE HOU;

CREATE TABLE IF NOT EXISTS members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_number CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  area_of_operation VARCHAR(180) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_registration_number (registration_number),
  UNIQUE KEY uq_members_phone (phone),
  UNIQUE KEY uq_members_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_key CHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auth_attempts_key_time (attempt_key, attempted_at),
  KEY idx_auth_attempts_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  attachment_data MEDIUMBLOB NULL,
  attachment_filename VARCHAR(180) NULL,
  category ENUM('general', 'event', 'important', 'maintenance') NOT NULL DEFAULT 'general',
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_member_announcements_visibility (status, published_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_announcement_reads (
  member_id BIGINT UNSIGNED NOT NULL,
  announcement_id BIGINT UNSIGNED NOT NULL,
  opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id, announcement_id),
  KEY idx_member_announcement_reads_announcement (announcement_id),
  CONSTRAINT fk_member_announcement_reads_member
    FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE,
  CONSTRAINT fk_member_announcement_reads_announcement
    FOREIGN KEY (announcement_id) REFERENCES member_announcements (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_profiles (
  member_id BIGINT UNSIGNED NOT NULL,
  district VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  street_address VARCHAR(180) NULL,
  postal_address VARCHAR(180) NULL,
  business_name VARCHAR(180) NULL,
  trading_name VARCHAR(180) NULL,
  business_registration_number VARCHAR(80) NULL,
  tin VARCHAR(80) NULL,
  business_certificate_path VARCHAR(255) NULL,
  business_certificate_data MEDIUMBLOB NULL,
  business_certificate_mime VARCHAR(100) NULL,
  hotspot_count INT UNSIGNED NULL,
  estimated_subscribers INT UNSIGNED NULL,
  operation_areas VARCHAR(500) NULL,
  current_isps VARCHAR(500) NULL,
  national_id_number VARCHAR(80) NULL,
  national_id_photo_path VARCHAR(255) NULL,
  national_id_photo_data MEDIUMBLOB NULL,
  national_id_photo_mime VARCHAR(100) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_member_profiles_member
    FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  transaction_reference CHAR(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider_reference VARCHAR(120) NULL,
  payment_type ENUM('voluntary', 'membership', 'supervision') NOT NULL,
  payment_method ENUM('mobile_money') NOT NULL,
  amount_ugx DECIMAL(12, 0) UNSIGNED NOT NULL,
  payer_phone VARCHAR(20) NOT NULL,
  status ENUM('pending', 'successful', 'failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_payments_transaction_reference (transaction_reference),
  UNIQUE KEY uq_member_payments_provider_reference (provider_reference),
  KEY idx_member_payments_member_created (member_id, created_at, id),
  KEY idx_member_payments_member_status (member_id, status),
  CONSTRAINT fk_member_payments_member
    FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB;
