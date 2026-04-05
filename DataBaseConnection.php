<?php
declare(strict_types=1);

if (!function_exists('load_project_env')) {
    function load_project_env(string $baseDir): void
    {
        $envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

load_project_env(__DIR__);

$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'earthcafe';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$appEnv = getenv('APP_ENV') ?: 'development';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    if ($appEnv === 'development') {
        die("Connection failed: " . $conn->connect_error);
    }
    die("Service temporarily unavailable.");
}
$conn->set_charset($charset);
?>
