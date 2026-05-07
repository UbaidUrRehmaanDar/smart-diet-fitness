-- =====================================================
-- Smart Diet & Fitness Recommendation System
-- Complete Database Schema for FYP Application
-- =====================================================
-- Database: smart_diet_fyp
-- Charset: utf8mb4
-- Engine: InnoDB
-- =====================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `smart_diet_fyp` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `smart_diet_fyp`;

-- =====================================================
-- 1. USERS TABLE (Authentication)
-- =====================================================
CREATE TABLE `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique user identifier',
    `email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Email address (login credential)',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed password',
    `role` ENUM('user', 'admin') DEFAULT 'user' COMMENT 'User role for access control',
    `is_active` BOOLEAN DEFAULT 1 COMMENT 'Account active status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation time',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update time',
    
    INDEX idx_email (`email`),
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. PROFILES TABLE (User Health & Goals)
-- =====================================================
CREATE TABLE `profiles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL UNIQUE COMMENT 'Foreign key to users table',
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `profile_picture` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Avatar filename stored under public/uploads/avatars',
    `date_of_birth` DATE COMMENT 'Age calculated from this field',
    `gender` ENUM('male', 'female', 'other') COMMENT 'Biological gender for BMR calculation',
    `height_cm` DECIMAL(5, 2) NOT NULL COMMENT 'Height in centimeters',
    `current_weight_kg` DECIMAL(6, 2) NOT NULL COMMENT 'Current weight in kg',
    `target_weight_kg` DECIMAL(6, 2) COMMENT 'Goal weight in kg',
    `bmi` DECIMAL(5, 2) COMMENT 'Calculated BMI (updated on each measurement)',
    `activity_level` ENUM('sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active') 
        COMMENT '1.2, 1.375, 1.55, 1.725, 1.9 multipliers for TDEE',
    `fitness_goal` ENUM('weight_loss', 'muscle_gain', 'maintenance') NOT NULL 
        COMMENT 'Primary objective: affects calorie targets',
    `onboarding_completed` BOOLEAN DEFAULT 0 COMMENT 'Tracks if user finished 3-step onboarding',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id (`user_id`),
    INDEX idx_fitness_goal (`fitness_goal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. PREFERENCES TABLE (Diet & Settings)
-- =====================================================
CREATE TABLE `preferences` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL UNIQUE,
    `diet_type` ENUM('omnivore', 'vegetarian', 'vegan', 'keto', 'paleo') DEFAULT 'omnivore' 
        COMMENT 'Dietary restriction for meal recommendations',
    `allergies` JSON COMMENT 'JSON array of food allergies: ["peanuts", "dairy"]',
    `medical_conditions` JSON COMMENT 'JSON array: ["diabetes", "hypertension"]',
    `theme` ENUM('light', 'dark') DEFAULT 'light' COMMENT 'UI theme preference',
    `notifications_enabled` BOOLEAN DEFAULT 1 COMMENT 'Global notification toggle',
    `meal_reminders` BOOLEAN DEFAULT 1,
    `workout_reminders` BOOLEAN DEFAULT 1,
    `hydration_reminders` BOOLEAN DEFAULT 1,
    `reminder_frequency` ENUM('hourly', 'twice_daily', 'daily') DEFAULT 'daily' 
        COMMENT 'How often to send reminders',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. RECOMMENDATIONS TABLE (Daily Plan Cache)
-- =====================================================
CREATE TABLE `recommendations` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `recommendation_date` DATE NOT NULL COMMENT 'Date this recommendation applies to',
    `kcal_target` INT NOT NULL COMMENT 'Daily calorie target',
    `protein_g` INT NOT NULL COMMENT 'Daily protein target in grams',
    `carbs_g` INT NOT NULL COMMENT 'Daily carbs target in grams',
    `fats_g` INT NOT NULL COMMENT 'Daily fats target in grams',
    `workout_plan` JSON COMMENT 'JSON: [{"exercise":"running", "duration":30, "intensity":"moderate"}]',
    `meal_plan` JSON COMMENT 'JSON: [{"meal_type":"breakfast", "calories":400}]',
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this was calculated',
    `is_active` BOOLEAN DEFAULT 1,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (`user_id`, `recommendation_date`),
    INDEX idx_user_date (`user_id`, `recommendation_date`),
    INDEX idx_recommendation_date (`recommendation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. DIET LOGS TABLE (Meal Tracking)
-- =====================================================
CREATE TABLE `diet_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `meal_type` ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL COMMENT 'Type of meal',
    `food_item` VARCHAR(255) NOT NULL COMMENT 'Description of food consumed',
    `kcal` INT NOT NULL COMMENT 'Calories in this meal',
    `protein_g` DECIMAL(5, 2) DEFAULT 0 COMMENT 'Protein content in grams',
    `carbs_g` DECIMAL(5, 2) DEFAULT 0 COMMENT 'Carbohydrates in grams',
    `fats_g` DECIMAL(5, 2) DEFAULT 0 COMMENT 'Fats in grams',
    `fiber_g` DECIMAL(5, 2) DEFAULT 0,
    `logged_date` DATE NOT NULL COMMENT 'Date of meal consumption',
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT COMMENT 'User notes about the meal',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id_date (`user_id`, `logged_date`),
    INDEX idx_logged_date (`logged_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. WORKOUT LOGS TABLE (Activity Tracking)
-- =====================================================
CREATE TABLE `workout_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `exercise_name` VARCHAR(255) NOT NULL COMMENT 'e.g., Running, Weight Lifting',
    `exercise_type` ENUM('cardio', 'strength', 'flexibility', 'sports') DEFAULT 'cardio',
    `duration_mins` INT NOT NULL COMMENT 'Duration in minutes',
    `intensity` ENUM('light', 'moderate', 'vigorous') NOT NULL COMMENT 'Exercise intensity',
    `kcal_burned` INT COMMENT 'Calories burned (can be auto-calculated)',
    `logged_date` DATE NOT NULL,
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT COMMENT 'User notes: how they felt, etc.',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id_date (`user_id`, `logged_date`),
    INDEX idx_logged_date (`logged_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. HYDRATION LOGS TABLE (Water Intake Tracking)
-- =====================================================
CREATE TABLE `hydration_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `amount_ml` INT NOT NULL COMMENT 'Amount of water in milliliters',
    `logged_date` DATE NOT NULL COMMENT 'Date of hydration log',
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id_date (`user_id`, `logged_date`),
    INDEX idx_logged_date (`logged_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. PROGRESS METRICS TABLE (Body Measurements)
-- =====================================================
CREATE TABLE `progress_metrics` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `weight_kg` DECIMAL(6, 2),
    `waist_cm` DECIMAL(5, 2),
    `chest_cm` DECIMAL(5, 2),
    `hips_cm` DECIMAL(5, 2),
    `body_fat_percent` DECIMAL(5, 2),
    `muscle_mass_kg` DECIMAL(6, 2),
    `recorded_date` DATE NOT NULL COMMENT 'Date of measurement',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT COMMENT 'Notes about measurements',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (`user_id`, `recorded_date`),
    INDEX idx_user_id_date (`user_id`, `recorded_date`),
    INDEX idx_recorded_date (`recorded_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. NOTIFICATIONS TABLE (Alerts & Reminders)
-- =====================================================
CREATE TABLE `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `notification_type` ENUM('meal_reminder', 'workout_reminder', 'hydration_reminder', 'achievement_unlock', 'system') 
        DEFAULT 'system' COMMENT 'Type of notification',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL COMMENT 'Notification message content',
    `is_read` BOOLEAN DEFAULT 0 COMMENT 'Read status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id_is_read (`user_id`, `is_read`),
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. ACHIEVEMENTS TABLE (Badges & Streaks)
-- =====================================================
CREATE TABLE `achievements` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `badge_name` VARCHAR(100) NOT NULL COMMENT 'e.g., "7-Day Streak", "500 Cals Deficit"',
    `badge_icon` VARCHAR(50) COMMENT 'Font Awesome icon class or emoji',
    `description` TEXT NOT NULL COMMENT 'Description of the achievement',
    `achievement_type` ENUM('streak', 'milestone', 'goal_met', 'consistency') DEFAULT 'milestone',
    `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id (`user_id`),
    INDEX idx_unlocked_at (`unlocked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. PASSWORD RESET TOKENS (Optional)
-- =====================================================
CREATE TABLE `password_reset_tokens` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Token expiry time (1 hour from creation)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_token (`token`),
    INDEX idx_expires_at (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. AUDIT LOG TABLE (Optional - for compliance)
-- =====================================================
CREATE TABLE `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL COMMENT 'e.g., login, update_profile, delete_meal',
    `table_name` VARCHAR(100),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_user_id (`user_id`),
    INDEX idx_action (`action`),
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
-- Already created above, but summary:
-- - idx_user_id on all tables with user_id for fast lookups
-- - idx_logged_date on logs for range queries
-- - idx_email on users for login
-- - idx_is_read on notifications for unread filtering
-- - unique constraints on email (users) and user_id (profiles, preferences)

-- =====================================================
-- SAMPLE DATA (Optional - for testing)
-- =====================================================
-- You can uncomment the following to insert test data:

/*
INSERT INTO `users` (`email`, `password_hash`, `role`) VALUES 
('test@example.com', '$2y$10$OIJbkLNQmFVWe2V6YqQCe.1Y9dFv6.QlYv.JsC3a1M2eYEqKQXG9q', 'user');
-- Password: password123

INSERT INTO `profiles` (`user_id`, `first_name`, `last_name`, `gender`, `height_cm`, `current_weight_kg`, `target_weight_kg`, `activity_level`, `fitness_goal`) VALUES
(1, 'John', 'Doe', 'male', 180, 85, 75, 'moderately_active', 'weight_loss');

INSERT INTO `preferences` (`user_id`) VALUES (1);
*/

-- =====================================================
-- END OF SCHEMA
-- =====================================================
