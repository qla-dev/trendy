<?php

/**
 * Shared SQL Server connection for /public/phptest scripts.
 * Reads credentials from project .env (falls back to the previous hard-coded defaults).
 */

function phptest_env(string $key, ?string $default = null): ?string
{
    $root = dirname(__DIR__, 2);
    $path = $root . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($path) || !is_readable($path)) {
        return $default;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $default;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $k = trim((string) $k);

        if ($k !== $key) {
            continue;
        }

        $v = trim((string) $v);

        if ($v !== '' && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
            $v = substr($v, 1, -1);
        }

        return $v;
    }

    return $default;
}

$host = (string) phptest_env('DB_HOST', 'hostBApa1.datalab.ba');
$port = (string) phptest_env('DB_PORT', '50387');
$server = $host . ',' . $port;

$database = (string) phptest_env('DB_DATABASE', 'BA_TRENDY');
$username = (string) phptest_env('DB_USERNAME', 'SQLTREN_ADM2');
$password = (string) phptest_env('DB_PASSWORD', '#4^Sdgfx3VHy5G');
$defaultSchema = (string) phptest_env('DB_SCHEMA', 'dbo');

$conn = sqlsrv_connect($server, [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $password,
    'CharacterSet' => 'UTF-8',
    // Avoid ODBC Driver 18 default-encrypt behavior in CLI environments.
    // This matches the existing app behavior (no TLS validation requirement).
    'Encrypt' => false,
    'TrustServerCertificate' => true,
    'LoginTimeout' => 10,
]);

if (!$conn) {
    die('<pre>' . htmlspecialchars(print_r(sqlsrv_errors(), true)) . '</pre>');
}
