<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$connection = null;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $required = ['host', 'port', 'database', 'user', 'pass'];
        foreach ($required as $key) {
            if (!array_key_exists($key, self::$config) || self::$config[$key] === '') {
                throw new RuntimeException('Database configuration is incomplete: ' . $key);
            }
        }

        $driver = (string) (self::$config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException('FFBS currently supports MySQL only.');
        }

        $charset = (string) (self::$config['charset'] ?? 'utf8mb4');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
            throw new RuntimeException('Invalid database character set.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) self::$config['host'],
            (int) self::$config['port'],
            (string) self::$config['database'],
            $charset
        );

        try {
            self::$connection = new PDO($dsn, (string) self::$config['user'], (string) self::$config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('FFBS database connection failed: ' . $exception->getMessage());
            throw new RuntimeException('Database connection is unavailable.');
        }

        return self::$connection;
    }
}
