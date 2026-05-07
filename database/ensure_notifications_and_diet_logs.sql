-- Smart Diet & Fitness — core logging tables (matches PHP / smart_diet_fyp.sql)
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS).
-- NOTE: This intentionally differs from ad-hoc schemas using `type` / `food_name`:
--       the application expects notification_type, meal_type, food_item, protein_g, etc.

USE `smart_diet_fyp`;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `notification_type` ENUM(
        'meal_reminder',
        'workout_reminder',
        'hydration_reminder',
        'achievement_unlock',
        'system'
    ) DEFAULT 'system' COMMENT 'Type of notification',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL COMMENT 'Notification message content',
    `is_read` BOOLEAN DEFAULT 0 COMMENT 'Read status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_user_id_is_read (`user_id`, `is_read`),
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `diet_logs` (
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
