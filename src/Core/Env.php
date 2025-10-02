<?php
declare(strict_types=1);
namespace App\Core;

use Exception;

class Env {
    private static bool $loaded = false;
    private static array $data = [];

    public static function load($path = null): void {
        if (self::$loaded) {
            return;
        }

        $path = $path ?: __DIR__ . '/.env';

        if (!file_exists($path)) {
            throw new Exception('.env file not found: ' . $path);
        }

        $content = file_get_contents($path);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Удаляем кавычки
                $value = trim($value, '"\'');

                self::$data[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$data[$key] ?? $default;
    }
}