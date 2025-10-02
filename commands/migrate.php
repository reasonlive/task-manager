<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
Env::load(getcwd() . '/.env');

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']}", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/../db/migrations/init.sql');
    $pdo->exec($sql);

    echo "Database migrated successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}