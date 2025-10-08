<?php

namespace App\Core\Data;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModelFactory
{
    public static function create(string $tableName)
    {
        if ($className = self::findModel($tableName)) {
            $reflection = new \ReflectionClass($className);
            $reflection->setStaticPropertyValue('table', $tableName);

            return $reflection->newInstance();
        }

        return null;
    }

    public static function fields(string $tableName): array
    {
        if ($className = self::findModel($tableName)) {
            $reflection = new \ReflectionClass($className);
            $reflection->getProperty('fields')->setAccessible(true);

            return array_keys($reflection->getProperty('fields')->getValue($reflection->newInstance()));
        }

        return [];
    }

    private static function findModel(string $tableName): ?string
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(__DIR__ . '/../../')));
        $models = [];

        while ($iterator->valid()) {
            if (str_contains($iterator->key(), 'Models')) {
                $name = $iterator->current()->getFileName();
                if (str_ends_with($name, '.php')) {
                    $models[] = substr($name, 0, -4);
                }
            }

            $iterator->next();
        }

        $className = array_filter($models, fn ($model) => str_contains($tableName, strtolower($model)));

        if (empty($className)) {
            return null;
        }

        return "App\\Models\\" . reset($className);
    }
}