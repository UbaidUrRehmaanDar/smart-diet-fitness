<?php
/**
 * Idempotent DDL patches after PDO connects (development-friendly).
 */
if (!isset($pdo) || !defined('DB_NAME')) {
    return;
}

try {
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $chk->execute([DB_NAME, 'profiles', 'profile_picture']);
    if ((int) $chk->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE `profiles` ADD COLUMN `profile_picture` VARCHAR(512) NULL DEFAULT NULL
             COMMENT \'Avatar filename in public/uploads/avatars\' AFTER `last_name`'
        );
    }
} catch (PDOException $e) {
    error_log('Schema patch profile_picture: ' . $e->getMessage());
}
