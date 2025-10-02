<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
Env::load(getcwd() . '/.env');

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );

    $sql = file_get_contents(__DIR__ . '/../db/seeds/init.sql');
    $pdo->exec($sql);

    echo "Database seeded successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}