<?php
namespace App\Core;

use PDO;
use PDOException;

// Instead of every student writing their own connection like this:

// $conn = new mysqli(...);

// Everyone should use:

// $db = Database::connection();
// NOTE: CLASS:FUNCTION calls the function within a class


class Database
{
    
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );

            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // pdo_mysql emulates prepares by default, which means it builds
                // the SQL string client-side instead of sending parameters
                // separately. Real server-side prepares keep values out of the
                // statement entirely, and also return ints/floats as PHP types
                // rather than strings.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }

    /**
     * Test seam: install a PDO for connection() to hand out, or pass null to
     * drop the cached handle so the next call reconnects from config.
     *
     * Application code must not call this — it exists because connection() is a
     * static singleton, which is what lets every repository reach the database
     * without being handed one. That same design means a test cannot inject a
     * handle any other way, and swapping the singleton reaches all of them at
     * once. See tests/IntegrationTestCase.php.
     */
    public static function swap(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }
}
