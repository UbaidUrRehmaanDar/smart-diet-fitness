<?php
/**
 * CLI/local helper: creates notifications + diet_logs if missing (PDO, matches app schema).
 * Usage from project root: php database/bootstrap_tables.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

$sqlFile = __DIR__ . '/ensure_notifications_and_diet_logs.sql';
if (!is_readable($sqlFile)) {
    fwrite(STDERR, "Missing SQL file: {$sqlFile}\n");
    exit(1);
}

$raw = file_get_contents($sqlFile);
if ($raw === false) {
    fwrite(STDERR, "Could not read SQL file.\n");
    exit(1);
}

// Strip full-line SQL comments and USE statement (connection already targets DB_NAME).
$lines = preg_split('/\R/', $raw) ?: [];
$buf = '';
foreach ($lines as $line) {
    $trim = ltrim($line);
    if ($trim === '' || str_starts_with($trim, '--')) {
        continue;
    }
    if (preg_match('/^\s*USE\s+/i', $trim)) {
        continue;
    }
    $buf .= $line . "\n";
}

$chunks = array_filter(array_map('trim', preg_split('/;\s*\n/s', $buf)));

foreach ($chunks as $stmt) {
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt . ';');
}

echo "bootstrap_tables: notifications + diet_logs ensured.\n";
