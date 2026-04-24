-- ============================================================
-- DATABASE SCHEMA — Sistem Undian Slot
-- Database : undian_slot_acara
-- Charset  : utf8mb4 / utf8mb4_general_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `undian_slot_acara`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `undian_slot_acara`;

-- ------------------------------------------------------------
-- table_setting
-- Konfigurasi slot per tanggal undian
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `table_setting` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `date_slot`  DATE         NOT NULL,
    `min_slot`   INT(11)      NOT NULL DEFAULT 0,
    `max_slot`   INT(11)      NOT NULL DEFAULT 0,
    `total_slot` INT(11)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_date_slot` (`date_slot`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- table_brand
-- Master brand peserta undian beserta aturan larangan pertemuan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `table_brand` (
    `id`              INT(11)      NOT NULL AUTO_INCREMENT,
    `name_brand`      VARCHAR(100) NOT NULL,
    `group_brand`     VARCHAR(10)  NOT NULL,
    `not_allow_brand` TEXT         DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- table_result
-- Hasil undian slot per tanggal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `table_result` (
    `id`             INT(11)    NOT NULL AUTO_INCREMENT,
    `date_slot`      DATE       NOT NULL,
    `slot_number`    INT(11)    NOT NULL,
    `slot_data`      LONGTEXT   DEFAULT NULL,
    `collision_info` TEXT       DEFAULT NULL,
    `is_relaxed`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_date_slot` (`date_slot`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- table_log
-- Log aktivitas sistem (undian, import, hapus)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `table_log` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `action`     VARCHAR(50)  NOT NULL COMMENT 'draw_success, draw_fail, import_brand, import_setting, delete_result',
    `date_slot`  DATE         DEFAULT NULL,
    `detail`     TEXT         DEFAULT NULL COMMENT 'JSON: total_slot, collision_count, mode, dsb',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_action`     (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
