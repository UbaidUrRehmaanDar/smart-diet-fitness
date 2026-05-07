<?php
// app/config/db.php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'smart_diet_fyp';
$DB_USER = 'root';
$DB_PASS = '1122'; // Match includes/config.php for local Laragon

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}