-- ============================================================
-- VrakeIT – OCR Identity Verification Migration
-- Run this script once against the `vrakeit` database.
-- Safe to run on an existing installation.
-- ============================================================

-- 1. Add verification tracking columns to the `users` table
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `verification_status` ENUM('Unverified','Pending','Verified','Rejected') NOT NULL DEFAULT 'Unverified' AFTER `account_verified`,
  ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL DEFAULT NULL AFTER `verification_status`,
  ADD COLUMN IF NOT EXISTS `verification_method` VARCHAR(50) NULL DEFAULT NULL AFTER `verified_at`,
  ADD COLUMN IF NOT EXISTS `verification_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `verification_method`;

-- Back-fill existing users: if account_verified=1 treat as Verified (admin-approved), else Unverified
UPDATE `users` SET `verification_status` = 'Verified', `verification_method` = 'admin_manual' WHERE `account_verified` = 1 AND `verification_status` = 'Unverified';

-- 2. Extend `id_verifications` status to include 'verified'
-- MariaDB/MySQL: safe ALTER ENUM to add new value
ALTER TABLE `id_verifications`
  MODIFY COLUMN `status` ENUM('pending','approved','rejected','verified') NOT NULL DEFAULT 'pending';

-- 3. Add OCR result columns to `id_verifications`
ALTER TABLE `id_verifications`
  ADD COLUMN IF NOT EXISTS `ocr_extracted_name` VARCHAR(200) NULL DEFAULT NULL AFTER `selfie_file`,
  ADD COLUMN IF NOT EXISTS `ocr_extracted_dob` VARCHAR(50) NULL DEFAULT NULL AFTER `ocr_extracted_name`,
  ADD COLUMN IF NOT EXISTS `ocr_extracted_doc_number` VARCHAR(100) NULL DEFAULT NULL AFTER `ocr_extracted_dob`,
  ADD COLUMN IF NOT EXISTS `ocr_extracted_expiry` VARCHAR(50) NULL DEFAULT NULL AFTER `ocr_extracted_doc_number`,
  ADD COLUMN IF NOT EXISTS `ocr_confidence_score` DECIMAL(5,2) NULL DEFAULT NULL AFTER `ocr_extracted_expiry`,
  ADD COLUMN IF NOT EXISTS `ocr_document_valid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ocr_confidence_score`,
  ADD COLUMN IF NOT EXISTS `ocr_name_match` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ocr_document_valid`,
  ADD COLUMN IF NOT EXISTS `ocr_dob_match` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ocr_name_match`,
  ADD COLUMN IF NOT EXISTS `ocr_failure_reason` TEXT NULL DEFAULT NULL AFTER `ocr_dob_match`,
  ADD COLUMN IF NOT EXISTS `ocr_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `ocr_failure_reason`,
  ADD COLUMN IF NOT EXISTS `image_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `ocr_reference`,
  ADD COLUMN IF NOT EXISTS `source` ENUM('registration','portal') NOT NULL DEFAULT 'portal' AFTER `image_hash`;

-- 4. Index on image_hash for duplicate document detection
ALTER TABLE `id_verifications`
  ADD INDEX IF NOT EXISTS `idx_image_hash` (`image_hash`);
