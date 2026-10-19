<?php
declare(strict_types=1);

// Fail with a clear message (instead of a blank 500 page) if the server's PHP setup is not suitable.
$awesumMissing = [];
if (PHP_VERSION_ID < 70400) {
    $awesumMissing[] = 'PHP 7.4 or newer is required (this server runs ' . PHP_VERSION . '). Change it in cPanel > Select PHP Version.';
}
foreach (['pdo_mysql', 'mbstring', 'json'] as $awesumExtension) {
    if (!extension_loaded($awesumExtension)) {
        $awesumMissing[] = 'The PHP extension "' . $awesumExtension . '" is not enabled. Enable it in cPanel > Select PHP Version > Extensions.';
    }
}
if ($awesumMissing) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server setup problem: ' . implode(' ', $awesumMissing)]);
    exit;
}
unset($awesumMissing, $awesumExtension);

// Never print PHP warnings into a response (they would corrupt the JSON the site reads); log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

// Copy this file's values into environment variables on the server; do not commit production secrets.
define('DB_HOST', getenv('AWESUM_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('AWESUM_DB_PORT') ?: '3306');
define('DB_NAME', getenv('AWESUM_DB_NAME') ?: '');
define('DB_USER', getenv('AWESUM_DB_USER') ?: '');
define('DB_PASS', getenv('AWESUM_DB_PASS') ?: '');
define('ADMIN_EMAIL', strtolower(getenv('AWESUM_ADMIN_EMAIL') ?: 'mpilonhlebmemela@gmail.com'));
define('APP_ORIGIN', getenv('AWESUM_APP_ORIGIN') ?: '');
define('PAYFAST_MODE', 'live');
define('PAYFAST_MERCHANT_ID', ' ');
define('PAYFAST_MERCHANT_KEY', '');
define('PAYFAST_PASSPHRASE', '');
define('SITE_URL', 'https://awesumbrands.co.za');
define('GOOGLE_CLIENT_ID', '');

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

/** Sends a JSON response and stops the script (never returns). */
function jsonResponse(array $payload, int $status = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // throw away any stray output (spaces, warnings) so the JSON stays clean
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

const AWESUM_MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const AWESUM_IMAGE_DIR = 'uploads/images';

/**
 * Turns an image sent by the browser (a base64 "data:image/..." string) into a real file on disk and
 * returns its relative URL, e.g. "uploads/images/<random>.jpg". Returns null when nothing was sent.
 * A path to an image that was already saved is accepted unchanged. Anything else is rejected.
 */
function saveUploadedImage($value): ?string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return null;
    }

    if (preg_match('#^' . preg_quote(AWESUM_IMAGE_DIR, '#') . '/[a-f0-9]{32}\.(?:jpg|png|gif|webp)$#', $value)) {
        if (is_file(__DIR__ . '/' . $value)) {
            return $value;
        }
        jsonResponse(['error' => 'That image is no longer available. Please upload it again.'], 422);
    }

    if (!preg_match('#^data:image/(?:png|jpe?g|gif|webp);base64,#i', $value, $prefix)) {
        jsonResponse(['error' => 'Unsupported image. Please upload a JPG, PNG, GIF or WebP file.'], 422);
    }
    $binary = base64_decode(substr($value, strlen($prefix[0])), true);
    if ($binary === false || $binary === '') {
        jsonResponse(['error' => 'The image could not be read. Please try another file.'], 422);
    }
    if (strlen($binary) > AWESUM_MAX_IMAGE_BYTES) {
        jsonResponse(['error' => 'The image is too large (maximum 5MB after resizing).'], 422);
    }

    // Trust the file contents, not the label the browser put on it.
    $info = @getimagesizefromstring($binary);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($extensions[$info[2]])) {
        jsonResponse(['error' => 'Unsupported image. Please upload a JPG, PNG, GIF or WebP file.'], 422);
    }
    if ($info[0] * $info[1] > 40000000) {
        jsonResponse(['error' => 'The image dimensions are too large.'], 422);
    }

    $directory = __DIR__ . '/' . AWESUM_IMAGE_DIR;
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        jsonResponse(['error' => 'The server could not create the uploads folder. Please contact support.'], 500);
    }
    // Defence in depth: never let the uploads folder run scripts or list its files.
    $guard = dirname($directory) . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n</FilesMatch>\n");
    }

    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$info[2]];
    if (@file_put_contents($directory . '/' . $name, $binary) === false) {
        jsonResponse(['error' => 'The image could not be saved on the server. Please try again.'], 500);
    }
    @chmod($directory . '/' . $name, 0644);
    return AWESUM_IMAGE_DIR . '/' . $name;
}

/** The visitor's IP address as seen by the server (forwarded headers are ignored because visitors can fake them). */
function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

/**
 * Runs a rate-limit database action. Creates the rate_limits table the first time it is needed and
 * never lets a rate-limit problem block real visitors (it logs the error and carries on).
 */
function rateLimitRun(PDO $database, callable $action)
{
    try {
        return $action();
    } catch (PDOException $error) {
        if ($error->getCode() === '42S02') {
            try {
                $database->exec('CREATE TABLE IF NOT EXISTS rate_limits (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bucket VARCHAR(120) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY bucket_time (bucket, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
                return $action();
            } catch (Throwable $retryError) {
                error_log('AweSum rate limit unavailable: ' . $retryError->getMessage());
                return null;
            }
        }
        error_log('AweSum rate limit error: ' . $error->getMessage());
        return null;
    }
}

/** Stops the request with a 429 error when $bucket has already been used $max times in the last $windowSeconds. */
function rateLimitGuard(PDO $database, string $bucket, int $max, int $windowSeconds, string $message): void
{
    $count = rateLimitRun($database, function () use ($database, $bucket, $windowSeconds) {
        $statement = $database->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > (NOW() - INTERVAL ' . (int) $windowSeconds . ' SECOND)');
        $statement->execute([$bucket]);
        return (int) $statement->fetchColumn();
    });
    if ($count !== null && $count >= $max) {
        header('Retry-After: ' . (int) $windowSeconds);
        jsonResponse(['error' => $message], 429);
    }
}

/** Records one use of $bucket (for example one failed login). Old records are cleaned up now and then. */
function rateLimitHit(PDO $database, string $bucket): void
{
    rateLimitRun($database, function () use ($database, $bucket) {
        $database->prepare('INSERT INTO rate_limits (bucket) VALUES (?)')->execute([$bucket]);
        if (random_int(1, 100) === 1) {
            $database->exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        }
        return true;
    });
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
/** True when the visitor reached the site over HTTPS, including behind a proxy/load balancer that terminates SSL. */
function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https' || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
}

ini_set('session.gc_maxlifetime', '28800');
session_set_cookie_params(['lifetime' => 28800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => isHttpsRequest()]);
session_start();