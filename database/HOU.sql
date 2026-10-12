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
  category ENUM('general', 'event', 'important', 'maintenance') NOT NULL DEFAULT 'general',
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_member_announcements_visibility (status, published_at)
) ENGINE=InnoDB;
