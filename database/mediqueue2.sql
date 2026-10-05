-- ============================================================
-- MediQueue Database Schema and Demo Data
-- Healthcare Queue and Appointment Management System
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create Database
CREATE DATABASE IF NOT EXISTS `mediqueue2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mediqueue2`;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('patient','receptionist','doctor','admin','nurse') NOT NULL DEFAULT 'patient',
    `profile_pic` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `role` (`role`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: patients
-- ============================================================
CREATE TABLE `patients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `patient_code` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `gender` ENUM('Male','Female','Other') DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `emergency_contact` VARCHAR(255) DEFAULT NULL,
    `emergency_phone` VARCHAR(20) DEFAULT NULL,
    `blood_group` VARCHAR(5) DEFAULT NULL,
    `allergies` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    UNIQUE KEY `patient_code` (`patient_code`),
    KEY `first_name` (`first_name`),
    KEY `last_name` (`last_name`),
    CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: doctors
-- ============================================================
CREATE TABLE `doctors` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `doctor_code` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `type` ENUM('doctor','nurse') NOT NULL DEFAULT 'doctor',
    `phone` VARCHAR(20) DEFAULT NULL,
    `specialization` VARCHAR(255) DEFAULT NULL,
    `qualification` VARCHAR(255) DEFAULT NULL,
    `license_number` VARCHAR(100) DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    UNIQUE KEY `doctor_code` (`doctor_code`),
    KEY `specialization` (`specialization`),
    CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: staff
-- ============================================================
CREATE TABLE `staff` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `staff_code` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `position` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    UNIQUE KEY `staff_code` (`staff_code`),
    CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: services
-- ============================================================
CREATE TABLE `services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `department` VARCHAR(255) DEFAULT NULL,
    `estimated_duration` INT UNSIGNED DEFAULT 15 COMMENT 'in minutes',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `name` (`name`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: doctor_services (many-to-many)
-- ============================================================
CREATE TABLE `doctor_services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doctor_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `doctor_service` (`doctor_id`, `service_id`),
    KEY `service_id` (`service_id`),
    CONSTRAINT `fk_ds_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ds_service` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: appointments
-- ============================================================
CREATE TABLE `appointments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` VARCHAR(20) NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('Pending','Confirmed','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Pending',
    `priority` ENUM('Normal','Urgent','Emergency') NOT NULL DEFAULT 'Normal',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `appointment_id` (`appointment_id`),
    KEY `patient_id` (`patient_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `service_id` (`service_id`),
    KEY `appointment_date` (`appointment_date`),
    KEY `status` (`status`),
    CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: patient_notes
-- ============================================================
CREATE TABLE `patient_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `note` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `patient_id` (`patient_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_patient_notes_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_patient_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: queue_entries
-- ============================================================
CREATE TABLE `queue_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_number` VARCHAR(20) NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED DEFAULT NULL,
    `queue_date` DATE NOT NULL,
    `status` ENUM('Waiting','Called','Serving','Served','Skipped','Cancelled') NOT NULL DEFAULT 'Waiting',
    `position` INT UNSIGNED DEFAULT NULL,
    `restore_position` INT UNSIGNED DEFAULT NULL,
    `priority` ENUM('Normal','Urgent','Emergency') NOT NULL DEFAULT 'Normal',
    `vitals_json` TEXT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `called_at` TIMESTAMP NULL DEFAULT NULL,
    `serving_at` TIMESTAMP NULL DEFAULT NULL,
    `served_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `queue_number` (`queue_number`),
    KEY `patient_id` (`patient_id`),
    KEY `service_id` (`service_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `queue_date` (`queue_date`),
    KEY `status` (`status`),
    KEY `queue_date_status` (`queue_date`, `status`),
    CONSTRAINT `fk_queue_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_service` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('appointment','queue','system','reminder','emergency') NOT NULL DEFAULT 'system',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `related_id` INT UNSIGNED DEFAULT NULL,
    `related_type` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`),
    KEY `type` (`type`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: system_settings
-- ============================================================
CREATE TABLE `system_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: sms_messages
-- ============================================================
CREATE TABLE IF NOT EXISTS `sms_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `channel` ENUM('sms','whatsapp') NOT NULL DEFAULT 'sms',
    `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    `error` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_phone` (`phone`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: password_resets
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INSERT DEMO DATA
-- ============================================================

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('clinic_name', 'MediQueue Health Center', 'Name of the healthcare facility'),
('clinic_phone', '+1-555-0100', 'Clinic phone number'),
('clinic_address', '123 Health Street, Medical City, MC 12345', 'Clinic address'),
('queue_prefix', 'A', 'Prefix for queue numbers'),
('max_queue_per_day', '100', 'Maximum queue entries per day'),
('avg_service_time', '15', 'Average service time in minutes'),
('operating_hours_start', '08:00', 'Clinic opening time'),
('operating_hours_end', '17:00', 'Clinic closing time');

-- Users (passwords are all 'password' hashed with PASSWORD_DEFAULT)
-- Admin: admin@mediqueue.com / password
-- Receptionist: receptionist@mediqueue.com / password
-- Doctor: doctor@mediqueue.com / password
-- Patient: patient@mediqueue.com / password

INSERT INTO `users` (`email`, `password`, `role`, `is_active`) VALUES
('admin@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
('receptionist@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', 1),
('doctor@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 1),
('patient@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 1),
('patient2@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 1),
('patient3@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 1),
('doctor2@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 1),
('receptionist2@mediqueue.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', 1);

-- Patients
INSERT INTO `patients` (`user_id`, `patient_code`, `first_name`, `last_name`, `phone`, `date_of_birth`, `gender`, `address`, `emergency_contact`, `emergency_phone`, `blood_group`) VALUES
(4, 'PAT001', 'John', 'Smith', '+1-555-0101', '1985-06-15', 'Male', '456 Oak Avenue, Health City', 'Jane Smith', '+1-555-0102', 'O+'),
(5, 'PAT002', 'Sarah', 'Johnson', '+1-555-0103', '1990-03-22', 'Female', '789 Pine Road, Medical Town', 'Bob Johnson', '+1-555-0104', 'A+'),
(6, 'PAT003', 'Michael', 'Williams', '+1-555-0105', '1978-11-08', 'Male', '321 Elm Street, Care City', 'Lisa Williams', '+1-555-0106', 'B+');

-- Doctors
INSERT INTO `doctors` (`user_id`, `doctor_code`, `first_name`, `last_name`, `phone`, `specialization`, `qualification`, `license_number`) VALUES
(3, 'DOC001', 'Dr. Emily', 'Brown', '+1-555-0201', 'General Practice', 'MD, MBBS', 'LIC001'),
(7, 'DOC002', 'Dr. Robert', 'Davis', '+1-555-0203', 'Pediatrics', 'MD, DCH', 'LIC002');

-- Staff
INSERT INTO `staff` (`user_id`, `staff_code`, `first_name`, `last_name`, `phone`, `position`) VALUES
(2, 'STF001', 'Alice', 'Cooper', '+1-555-0301', 'Senior Receptionist'),
(8, 'STF002', 'Tom', 'Anderson', '+1-555-0303', 'Receptionist');

-- Services
INSERT INTO `services` (`name`, `description`, `department`, `estimated_duration`, `is_active`) VALUES
('General Consultation', 'General medical consultation and checkup', 'General', 20, 1),
('Dental Care', 'Dental examination and treatment', 'Dental', 30, 1),
('Laboratory', 'Blood tests, urinalysis, and other lab work', 'Laboratory', 15, 1),
('Pharmacy', 'Medication dispensing and consultation', 'Pharmacy', 10, 1),
('Pediatrics', 'Children healthcare services', 'Pediatrics', 25, 1),
('Radiology', 'X-ray, ultrasound, and imaging services', 'Radiology', 20, 1),
('Maternal Health', 'Prenatal and postnatal care', 'Maternal Health', 30, 1);

-- Doctor Services
INSERT INTO `doctor_services` (`doctor_id`, `service_id`) VALUES
(1, 1),
(1, 5),
(2, 1),
(2, 5);

-- Demo Appointments
INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `service_id`, `appointment_date`, `appointment_time`, `reason`, `status`) VALUES
('APT00001', 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 'Annual checkup', 'Pending'),
('APT00002', 2, 1, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00', 'Child vaccination', 'Confirmed'),
('APT00003', 3, 2, 1, CURDATE(), '14:00:00', 'Follow-up consultation', 'Completed');

-- Demo Queue Entries
INSERT INTO `queue_entries` (`queue_number`, `patient_id`, `service_id`, `doctor_id`, `queue_date`, `status`, `position`, `joined_at`) VALUES
('A001', 1, 1, 1, CURDATE(), 'Served', 1, DATE_FORMAT(NOW(), '%Y-%m-%d 08:00:00')),
('A002', 2, 5, 1, CURDATE(), 'Served', 2, DATE_FORMAT(NOW(), '%Y-%m-%d 08:05:00')),
('A003', 3, 1, 2, CURDATE(), 'Serving', 3, DATE_FORMAT(NOW(), '%Y-%m-%d 08:10:00'));

COMMIT;


-- ============================================================
-- Table: contact_messages (website Contact Us form, admin-only)
-- ============================================================
CREATE TABLE `contact_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `is_handled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `is_handled` (`is_handled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
