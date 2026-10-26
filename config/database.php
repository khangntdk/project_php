<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('TECHZONE_DB_HOST') ?: '127.0.0.1';
    $port = getenv('TECHZONE_DB_PORT') ?: '3306';
    $database = getenv('TECHZONE_DB_NAME') ?: 'techzone_db';
    $username = getenv('TECHZONE_DB_USER') ?: 'root';
    $password = getenv('TECHZONE_DB_PASS') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $exception) {
        error_log('TechZone database connection failed: ' . $exception->getMessage());
        throw new RuntimeException(
            'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL và cấu hình trong config/database.php.',
            0
        );
    }

    return $connection;
}
