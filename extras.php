<?php
declare(strict_types=1);

/**
 * Extra helpers: e-mail sending, password-reset links and Google sign-in checks.
 * Loaded by api.php after config.php. Settings can be overridden in config.php, for example:
 *   define('GOOGLE_CLIENT_ID', 'xxxxxxxx.apps.googleusercontent.com');
 *   define('MAIL_FROM', 'no-reply@awesumbrands.co.za');
 */
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://awesumbrands.co.za');
}
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', trim((string) (getenv('AWESUM_GOOGLE_CLIENT_ID') ?: '')));
}
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', (string) (getenv('AWESUM_MAIL_FROM') ?: 'no-reply@' . preg_replace('#^https?://(www\.)?#i', '', SITE_URL)));
}

/** Sends a plain-text e-mail from the site. Returns false if the server refused to send it. */
function sendSiteEmail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
        return false;
    }
    $headers = 'From: AweSum Brands <' . MAIL_FROM . ">\r\n"
        . 'Reply-To: ' . MAIL_FROM . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . 'X-Mailer: AweSum Brands';
    return (bool) @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . MAIL_FROM);
}

/** Creates the password_resets table the first time it is needed. */
function ensurePasswordResetsTable(PDO $database): void
{
    try {
        $database->query('SELECT 1 FROM password_resets LIMIT 1');
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') {
            throw $error;
        }
        $database->exec('CREATE TABLE IF NOT EXISTS password_resets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY token_hash (token_hash), KEY user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

/** Makes a one-hour, single-use reset token for a user. Only a hash of it is stored; the raw token goes in the e-mail link. */
function createPasswordResetToken(PDO $database, int $userId): string
{
    ensurePasswordResetsTable($database);
    $database->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([$userId]);
    $token = bin2hex(random_bytes(32));
    $database->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$userId, hash('sha256', $token)]);
    return $token;
}

function passwordResetLink(string $token): string
{
    return SITE_URL . '/reset-password.html?token=' . $token;
}

function sendPasswordResetEmail(string $email, string $name, string $link): bool
{
    $parts = explode(' ', trim($name));
    $first = trim((string) ($parts[0] ?? ''));
    $body = 'Hi ' . ($first !== '' ? $first : 'there') . ",\r\n\r\n"
        . "We received a request to reset the password for your AweSum Brands account.\r\n\r\n"
        . "Open the link below to choose a new password. It is valid for 1 hour and can be used once:\r\n\r\n"
        . $link . "\r\n\r\n"
        . "If you did not ask for this, you can ignore this email. Your password will stay the same.\r\n\r\n"
        . "AweSum Brands\r\n" . SITE_URL . "\r\n";
    return sendSiteEmail($email, 'Reset your AweSum Brands password', $body);
}

/**
 * Checks a Google sign-in token with Google. Returns the verified details (email, name) or null when the token is
 * invalid, expired, meant for another website, or the e-mail address is not verified by Google.
 */
function verifyGoogleIdToken(string $credential): ?array
{
    if (GOOGLE_CLIENT_ID === '' || $credential === '' || strlen($credential) > 4096 || !function_exists('curl_init')) {
        return null;
    }
    $curl = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $status !== 200) {
        return null;
    }
    $claims = json_decode((string) $response, true);
    if (!is_array($claims)) {
        return null;
    }
    $issuerOk = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
    $emailVerified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';
    if (!$issuerOk || ($claims['aud'] ?? '') !== GOOGLE_CLIENT_ID || !$emailVerified || empty($claims['email']) || (int) ($claims['exp'] ?? 0) < time()) {
        return null;
    }
    return $claims;
}

/** Adds the price and order-link columns to the quotes table the first time they are needed (older databases lack them). */
function ensureQuotePriceColumns(PDO $database): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $statement = $database->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotes' AND COLUMN_NAME IN ('quoted_price', 'order_reference')");
    $present = array_column($statement->fetchAll(), 'COLUMN_NAME');
    if (!in_array('quoted_price', $present, true)) {
        $database->exec('ALTER TABLE quotes ADD COLUMN quoted_price DECIMAL(12,2) NULL DEFAULT NULL');
    }
    if (!in_array('order_reference', $present, true)) {
        $database->exec('ALTER TABLE quotes ADD COLUMN order_reference VARCHAR(32) NULL DEFAULT NULL');
    }
}

/** Splits a quoted price (excluding VAT) into subtotal, VAT and total, worked out in whole cents. */
function quoteTotals(float $price): array
{
    $subtotalCents = (int) round($price * 100);
    $vatCents = (int) round($subtotalCents * 0.15);
    return ['subtotal' => $subtotalCents / 100, 'vat' => $vatCents / 100, 'total' => ($subtotalCents + $vatCents) / 100];
}