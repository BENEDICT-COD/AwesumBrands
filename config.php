<?php
declare(strict_types=1);

// Copy this file's values into environment variables on the server; do not commit production secrets.
define('DB_HOST', getenv('AWESUM_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('AWESUM_DB_PORT') ?: '3306');
define('DB_NAME', getenv('AWESUM_DB_NAME') ?: 'awesum_brands');
define('DB_USER', getenv('AWESUM_DB_USER') ?: 'root');
define('DB_PASS', getenv('AWESUM_DB_PASS') ?: '');
define('ADMIN_EMAIL', strtolower(getenv('AWESUM_ADMIN_EMAIL') ?: 'mpilonhlebmemela@gmail.com'));
define('APP_ORIGIN', getenv('AWESUM_APP_ORIGIN') ?: '');

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $connection = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $connection;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : $_POST;
}

function requireFields(array $body, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($body[$field]) || trim((string) $body[$field]) === '') {
            jsonResponse(['error' => "Missing field: {$field}"], 422);
        }
    }
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireUser(): array
{
    $current = user();
    if (!$current) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }
    return $current;
}

function requireAdmin(): array
{
    $current = requireUser();
    if (($current['role'] ?? '') !== 'admin') {
        jsonResponse(['error' => 'Administrator access required.'], 403);
    }
    return $current;
}

if (APP_ORIGIN !== '') {
    header('Access-Control-Allow-Origin: ' . APP_ORIGIN);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
