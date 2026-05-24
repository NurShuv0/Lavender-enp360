-- ==========================================================================
-- LAVENDER’S GLAM STUDIO — MySQL Database Schema
-- Optimized InnoDB Relational Design with Cascades & Core Indices
-- ==========================================================================

-- CREATE DATABASE IF NOT EXISTS `lavender_glam_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `lavender_glam_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Dynamic Services Table
CREATE TABLE IF NOT EXISTS `services` (
    `id` VARCHAR(50) PRIMARY KEY, -- Unique slug identifier (e.g., 'bridal-couture')
    `title` VARCHAR(100) NOT NULL,
    `tag` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `base_price` DECIMAL(10,2) NOT NULL,
    `duration_minutes` INT NOT NULL,
    `location_type` ENUM('studio', 'location', 'both') DEFAULT 'both',
    `image_path` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Time Slots Table
CREATE TABLE IF NOT EXISTS `time_slots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slot_time` TIME NOT NULL UNIQUE, -- Unique slot (e.g., '08:30:00')
    `max_capacity` INT DEFAULT 1
) ENGINE=InnoDB;

-- 4. Master Bookings Table
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_reference` VARCHAR(30) NOT NULL UNIQUE, -- LGS-XXXXXXXX-REF
    `user_id` INT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `event_date` DATE NOT NULL,
    `travel_fee` DECIMAL(10,2) DEFAULT 0.00,
    `total_price` DECIMAL(10,2) NOT NULL,
    `event_address` TEXT NULL,
    `skin_profile` VARCHAR(100) NULL,
    `special_notes` TEXT NULL,
    `booking_status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_event_date` (`event_date`),
    INDEX `idx_booking_status` (`booking_status`)
) ENGINE=InnoDB;

-- 5. Booking Line Items Relation
CREATE TABLE IF NOT EXISTS `booking_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `service_id` VARCHAR(50) NOT NULL,
    `selected_time` TIME NOT NULL,
    `base_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    INDEX `idx_selected_time` (`selected_time`)
) ENGINE=InnoDB;

-- 6. Administrative Users Table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
