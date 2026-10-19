<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/extras.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body = requestBody();

/** Creates the site_settings table (used for the homepage statistics) the first time it is needed. */
function ensureSettingsTable(PDO $database): void
{
    try {
        $database->query('SELECT 1 FROM site_settings LIMIT 1');
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') {
            throw $error;
        }
        $database->exec('CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(60) NOT NULL PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

/** Creates the projects table the first time it is needed (older databases do not have it yet). */
function ensureProjectsTable(PDO $database): void
{
    try {
        $database->query('SELECT 1 FROM projects LIMIT 1');
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') {
            throw $error;
        }
        $database->exec("CREATE TABLE IF NOT EXISTS projects (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL, category VARCHAR(40) NOT NULL, image VARCHAR(500) NOT NULL, size VARCHAR(10) NOT NULL DEFAULT 'narrow', active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

const AWESUM_VAT_RATE = 0.15;
const AWESUM_MAX_ORDER_LINES = 50;
const AWESUM_MAX_LINE_QUANTITY = 1000;

function awesumStaticCatalogue(): array
{
    return []; // Only products added by the admin (stored in the database) can be ordered.
}

/**
 * Rebuilds an order from trusted prices. The browser only supplies product names and
 * quantities (or the reference of an accepted custom quote); every price and the total
 * are calculated here, in whole cents. Quote prices come from the quotes table.
 */
function priceOrderItems(PDO $database, array $items, int $userId = 0): array
{
    if (!$items || count($items) > AWESUM_MAX_ORDER_LINES) {
        jsonResponse(['error' => 'The order must contain between 1 and ' . AWESUM_MAX_ORDER_LINES . ' items.'], 422);
    }

    $prices = [];
    foreach (awesumStaticCatalogue() as $name => $price) {
        $prices[$name] = (int) round($price * 100);
    }
    foreach ($database->query('SELECT name, price FROM products WHERE active = 1') as $row) {
        $prices[mb_strtolower(trim((string) $row['name']))] = (int) round(((float) $row['price']) * 100);
    }

    $lines = [];
    $quoteLines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            jsonResponse(['error' => 'Invalid item in order.'], 422);
        }
        if (isset($item['quoteId'])) {
            $quoteRef = trim((string) $item['quoteId']);
            if (isset($quoteLines[$quoteRef])) {
                continue;
            }
            ensureQuotePriceColumns($database);
            $statement = $database->prepare('SELECT id, reference_code, category, quantity, status, client_decision, quoted_price, order_reference FROM quotes WHERE reference_code = ? AND user_id = ? LIMIT 1');
            $statement->execute([$quoteRef, $userId]);
            $quoteRow = $statement->fetch();
            if (!$quoteRow || $quoteRow['status'] !== 'Approved' || $quoteRow['client_decision'] !== 'approved' || $quoteRow['quoted_price'] === null) {
                jsonResponse(['error' => 'Quote ' . mb_substr($quoteRef, 0, 20) . ' cannot be paid yet. Please accept its design and price on the Custom Quote page first.'], 422);
            }
            if (!empty($quoteRow['order_reference'])) {
                jsonResponse(['error' => 'Quote ' . $quoteRow['reference_code'] . ' already has an order. Please remove it from your cart and pay for it from your Orders page.'], 422);
            }
            $quoteLines[$quoteRef] = [
                'id' => (int) $quoteRow['id'],
                'reference' => $quoteRow['reference_code'],
                'name' => 'Custom quote ' . $quoteRow['reference_code'] . ' (' . mb_substr((string) $quoteRow['category'], 0, 60) . ', qty ' . (int) $quoteRow['quantity'] . ')',
                'cents' => (int) round(((float) $quoteRow['quoted_price']) * 100),
            ];
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        $key = mb_strtolower($name);
        if ($name === '' || !isset($prices[$key])) {
            jsonResponse(['error' => '"' . mb_substr($name, 0, 80) . '" is no longer available. Please remove it from your cart.'], 422);
        }
        $quantity = filter_var($item['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => AWESUM_MAX_LINE_QUANTITY]]);
        if ($quantity === false) {
            jsonResponse(['error' => 'Invalid quantity for "' . mb_substr($name, 0, 80) . '".'], 422);
        }
        if (isset($lines[$key])) {
            $lines[$key]['quantity'] += $quantity;
        } else {
            $lines[$key] = ['name' => $name, 'unitCents' => $prices[$key], 'quantity' => $quantity];
        }
    }

    $subtotalCents = 0;
    $priced = [];
    foreach ($lines as $line) {
        if ($line['quantity'] > AWESUM_MAX_LINE_QUANTITY) {
            jsonResponse(['error' => 'Quantity for "' . mb_substr($line['name'], 0, 80) . '" is too large.'], 422);
        }
        $lineCents = $line['unitCents'] * $line['quantity'];
        $subtotalCents += $lineCents;
        $priced[] = [
            'name' => $line['name'],
            'price' => $line['unitCents'] / 100,
            'quantity' => $line['quantity'],
            'lineTotal' => $lineCents / 100,
        ];
    }
    $quoteIds = [];
    foreach ($quoteLines as $quoteLine) {
        $subtotalCents += $quoteLine['cents'];
        $priced[] = ['name' => $quoteLine['name'], 'price' => $quoteLine['cents'] / 100, 'quantity' => 1, 'lineTotal' => $quoteLine['cents'] / 100, 'quoteId' => $quoteLine['reference']];
        $quoteIds[] = $quoteLine['id'];
    }
    $vatCents = (int) round($subtotalCents * AWESUM_VAT_RATE);

    return [
        'quoteIds' => $quoteIds,
        'items' => $priced,
        'subtotal' => $subtotalCents / 100,
        'vat' => $vatCents / 100,
        'total' => ($subtotalCents + $vatCents) / 100,
    ];
}

try {
    $database = db();
    if ($action === 'signup' && $method === 'POST') {
        requireFields($body, ['name', 'email', 'password']);
        rateLimitGuard($database, 'signup-ip:' . clientIp(), 5, 3600, 'Too many sign-up attempts from your network. Please try again in an hour.');
        rateLimitHit($database, 'signup-ip:' . clientIp());
        $email = strtolower(trim((string) $body['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $body['password']) < 8) {
            jsonResponse(['error' => 'Use a valid email and a password of at least 8 characters.'], 422);
        }
        $signupName = trim((string) $body['name']);
        $signupCompany = trim((string) ($body['company'] ?? ''));
        if ($signupName === '' || mb_strlen($signupName) > 160 || mb_strlen($signupCompany) > 160 || mb_strlen($email) > 255 || strlen((string) $body['password']) > 72) {
            jsonResponse(['error' => 'Please enter your name (up to 160 characters) and a password of 8 to 72 characters.'], 422);
        }
        try {
            $statement = $database->prepare("INSERT INTO users (name, email, password_hash, company, role) VALUES (?, ?, ?, ?, 'user')");
            $statement->execute([$signupName, $email, password_hash((string) $body['password'], PASSWORD_DEFAULT), $signupCompany ?: null]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                jsonResponse(['error' => 'That email address is already registered. Please sign in instead.'], 409);
            }
            throw $error;
        }
        jsonResponse(['ok' => true], 201);
    }

    if ($action === 'login' && $method === 'POST') {
        requireFields($body, ['email', 'password']);
        $loginEmail = strtolower(trim((string) $body['email']));
        $loginMessage = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
        rateLimitGuard($database, 'login-ip:' . clientIp(), 10, 900, $loginMessage);
        rateLimitGuard($database, 'login-email:' . sha1($loginEmail), 8, 900, $loginMessage);
        $statement = $database->prepare('SELECT id, name, email, password_hash, company, role FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$loginEmail]);
        $record = $statement->fetch();
        if (!$record || !password_verify((string) $body['password'], $record['password_hash'])) {
            rateLimitHit($database, 'login-ip:' . clientIp());
            rateLimitHit($database, 'login-email:' . sha1($loginEmail));
            jsonResponse(['error' => 'Email or password is incorrect.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $record['id'], 'name' => $record['name'], 'email' => $record['email'], 'company' => $record['company'] ?? '', 'role' => $record['role']];
        jsonResponse(['user' => $_SESSION['user']]);
    }

    if ($action === 'auth-config' && $method === 'GET') {
        jsonResponse(['googleClientId' => GOOGLE_CLIENT_ID]);
    }

    if ($action === 'forgot-password' && $method === 'POST') {
        requireFields($body, ['email']);
        $resetEmail = strtolower(trim((string) $body['email']));
        $genericReply = ['ok' => true, 'message' => 'If that email address is registered, we have sent a link to reset your password. It is valid for 1 hour, so please check your inbox and spam folder.'];
        rateLimitGuard($database, 'forgot-ip:' . clientIp(), 8, 3600, 'Too many reset requests from your network. Please try again later.');
        rateLimitHit($database, 'forgot-ip:' . clientIp());
        if (!filter_var($resetEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($resetEmail) > 255) {
            jsonResponse($genericReply);
        }
        rateLimitGuard($database, 'forgot-email:' . sha1($resetEmail), 3, 3600, 'A reset link was already requested for this email address. Please check your inbox and spam folder, or try again in an hour.');
        rateLimitHit($database, 'forgot-email:' . sha1($resetEmail));
        $statement = $database->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$resetEmail]);
        $resetUser = $statement->fetch();
        if ($resetUser) {
            $token = createPasswordResetToken($database, (int) $resetUser['id']);
            if (!sendPasswordResetEmail((string) $resetUser['email'], (string) $resetUser['name'], passwordResetLink($token))) {
                error_log('AweSum: the password reset email could not be sent');
            }
        }
        jsonResponse($genericReply);
    }

    if ($action === 'reset-password' && $method === 'POST') {
        requireFields($body, ['token', 'newPassword']);
        rateLimitGuard($database, 'reset-ip:' . clientIp(), 10, 900, 'Too many attempts. Please wait 15 minutes and try again.');
        $token = trim((string) $body['token']);
        $newPassword = (string) $body['newPassword'];
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            jsonResponse(['error' => 'Your new password must be between 8 and 72 characters.'], 422);
        }
        $reset = false;
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            ensurePasswordResetsTable($database);
            $statement = $database->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
            $statement->execute([hash('sha256', $token)]);
            $reset = $statement->fetch();
        }
        if (!$reset) {
            rateLimitHit($database, 'reset-ip:' . clientIp());
            jsonResponse(['error' => 'This reset link is invalid or has expired. Please request a new one.'], 400);
        }
        $statement = $database->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $reset['user_id']]);
        $database->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $reset['user_id']]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'customers' && $method === 'GET') {
        requireAdmin();
        $statement = $database->query('SELECT id, name, email, company, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 1000');
        jsonResponse(['customers' => $statement->fetchAll()]);
    }

    if ($action === 'admin-reset-password' && $method === 'POST') {
        requireAdmin();
        requireFields($body, ['userId']);
        $statement = $database->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $body['userId']]);
        $target = $statement->fetch();
        if (!$target) {
            jsonResponse(['error' => 'Customer not found.'], 404);
        }
        $link = passwordResetLink(createPasswordResetToken($database, (int) $target['id']));
        $sent = sendPasswordResetEmail((string) $target['email'], (string) $target['name'], $link);
        jsonResponse(['emailSent' => $sent, 'link' => $link, 'email' => $target['email']]);
    }

    if ($action === 'google-login' && $method === 'POST') {
        rateLimitGuard($database, 'google-ip:' . clientIp(), 20, 900, 'Too many sign-in attempts. Please wait a few minutes and try again.');
        rateLimitHit($database, 'google-ip:' . clientIp());
        $claims = verifyGoogleIdToken((string) ($body['credential'] ?? ''));
        if (!$claims) {
            jsonResponse(['error' => 'Google sign-in could not be verified. Please try again, or sign in with your email and password.'], 401);
        }
        $googleEmail = strtolower(trim((string) $claims['email']));
        $statement = $database->prepare('SELECT id, name, email, company, role FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$googleEmail]);
        $record = $statement->fetch();
        if (!$record) {
            $googleName = trim((string) ($claims['name'] ?? ''));
            if ($googleName === '') {
                $googleName = (string) strstr($googleEmail, '@', true);
            }
            $googleName = mb_substr($googleName, 0, 160);
            // Google accounts get a long random password nobody knows; they can set their own with "Forgot password".
            $statement = $database->prepare("INSERT INTO users (name, email, password_hash, company, role) VALUES (?, ?, ?, NULL, 'user')");
            $statement->execute([$googleName, $googleEmail, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
            $record = ['id' => (int) $database->lastInsertId(), 'name' => $googleName, 'email' => $googleEmail, 'company' => null, 'role' => 'user'];
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $record['id'], 'name' => $record['name'], 'email' => $record['email'], 'company' => $record['company'] ?? '', 'role' => $record['role']];
        jsonResponse(['user' => $_SESSION['user']]);
    }

    if ($action === 'change-password' && $method === 'POST') {
        $current = requireUser();
        requireFields($body, ['currentPassword', 'newPassword']);
        $bucket = 'pwchange-user:' . (int) $current['id'];
        rateLimitGuard($database, $bucket, 5, 900, 'Too many incorrect attempts. Please wait 15 minutes and try again.');
        $oldPassword = (string) $body['currentPassword'];
        $newPassword = (string) $body['newPassword'];
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            jsonResponse(['error' => 'Your new password must be between 8 and 72 characters.'], 422);
        }
        $statement = $database->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $current['id']]);
        $record = $statement->fetch();
        if (!$record || !password_verify($oldPassword, $record['password_hash'])) {
            rateLimitHit($database, $bucket);
            jsonResponse(['error' => 'Your current password is incorrect.'], 401);
        }
        if (hash_equals($oldPassword, $newPassword)) {
            jsonResponse(['error' => 'Your new password must be different from your current one.'], 422);
        }
        $statement = $database->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $current['id']]);
        session_regenerate_id(true);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'payfast-checkout' && $method === 'POST') {
        $current = requireUser();
        requireFields($body, ['reference']);
        if (PAYFAST_MODE === 'live' && (PAYFAST_MERCHANT_ID === '10000100' || PAYFAST_PASSPHRASE === '')) {
            jsonResponse(['error' => 'Online payment is not set up yet. Please contact AweSum Brands.'], 503);
        }
        $statement = $database->prepare('SELECT reference_code, total, status FROM orders WHERE reference_code = ? AND user_id = ? LIMIT 1');
        $statement->execute([trim((string) $body['reference']), (int) $current['id']]);
        $payOrder = $statement->fetch();
        if (!$payOrder) {
            jsonResponse(['error' => 'Order not found.'], 404);
        }
        if ($payOrder['status'] !== 'Payment pending') {
            jsonResponse(['error' => 'This order is not waiting for payment.'], 422);
        }
        $nameParts = preg_split('/\s+/', trim((string) $current['name']), 2);
        // The order of these fields matters: PayFast checks the signature in this exact order.
        $fields = [
            'merchant_id' => PAYFAST_MERCHANT_ID,
            'merchant_key' => PAYFAST_MERCHANT_KEY,
            'return_url' => SITE_URL . '/orders.html?payment=return',
            'cancel_url' => SITE_URL . '/orders.html?payment=cancelled',
            'notify_url' => SITE_URL . '/payment-webhook.php',
            'name_first' => mb_substr((string) ($nameParts[0] ?? ''), 0, 100),
            'name_last' => mb_substr((string) ($nameParts[1] ?? ''), 0, 100),
            'email_address' => (string) $current['email'],
            'm_payment_id' => (string) $payOrder['reference_code'],
            'amount' => number_format((float) $payOrder['total'], 2, '.', ''),
            'item_name' => 'AweSum Brands order ' . $payOrder['reference_code'],
        ];
        $fields = array_filter($fields, static function ($value) { return $value !== ''; });
        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = $key . '=' . urlencode(trim((string) $value));
        }
        if (PAYFAST_PASSPHRASE !== '') {
            $pairs[] = 'passphrase=' . urlencode(PAYFAST_PASSPHRASE);
        }
        $fields['signature'] = md5(implode('&', $pairs));
        jsonResponse([
            'url' => PAYFAST_MODE === 'live' ? 'https://www.payfast.co.za/eng/process' : 'https://sandbox.payfast.co.za/eng/process',
            'fields' => $fields,
        ]);
    }

    if ($action === 'logout' && $method === 'POST') {
        session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'session' && $method === 'GET') {
        $current = user();
        jsonResponse(['user' => $current, 'isAdmin' => $current && ($current['role'] ?? '') === 'admin']);
    }

    if ($action === 'products' && $method === 'GET') {
        $statement = $database->query('SELECT id, name, category, description, price, image FROM products WHERE active = 1 ORDER BY created_at DESC');
        jsonResponse(['products' => $statement->fetchAll()]);
    }

    if ($action === 'products' && $method === 'POST') {
        requireAdmin();
        requireFields($body, ['name', 'category', 'price']);
        $productName = trim((string) $body['name']);
        $productCategory = trim((string) $body['category']);
        $productDescription = trim((string) ($body['description'] ?? ''));
        $productPrice = filter_var($body['price'], FILTER_VALIDATE_FLOAT);
        if ($productName === '' || mb_strlen($productName) > 160 || $productCategory === '' || mb_strlen($productCategory) > 80 || mb_strlen($productDescription) > 5000 || $productPrice === false || $productPrice <= 0 || $productPrice > 1000000) {
            jsonResponse(['error' => 'Please enter a product name, a category and a price above zero.'], 422);
        }
        $image = saveUploadedImage($body['image'] ?? '');
        $statement = $database->prepare('INSERT INTO products (name, category, description, price, image) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$productName, $productCategory, $productDescription, round($productPrice, 2), $image]);
        jsonResponse(['id' => (int) $database->lastInsertId(), 'image' => $image], 201);
    }

    if ($action === 'products' && $method === 'DELETE') {
        requireAdmin();
        requireFields($body, ['id']);
        $statement = $database->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $statement->execute([(int) $body['id']]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'stats' && $method === 'GET') {
        ensureSettingsTable($database);
        $statement = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $statement->execute(['homepage_stats']);
        $stored = $statement->fetchColumn();
        $decoded = $stored !== false ? json_decode((string) $stored, true) : null;
        jsonResponse(['stats' => is_array($decoded) ? $decoded : null]);
    }

    if ($action === 'stats' && $method === 'PUT') {
        requireAdmin();
        ensureSettingsTable($database);
        $submitted = $body['stats'] ?? null;
        if (!is_array($submitted) || count($submitted) !== 4) {
            jsonResponse(['error' => 'Please provide all four statistics.'], 422);
        }
        $cleanStats = [];
        foreach ($submitted as $stat) {
            $statValue = is_array($stat) ? trim((string) ($stat['value'] ?? '')) : '';
            $statLabel = is_array($stat) ? trim((string) ($stat['label'] ?? '')) : '';
            if ($statValue === '' || $statLabel === '' || mb_strlen($statValue) > 40 || mb_strlen($statLabel) > 60) {
                jsonResponse(['error' => 'Each statistic needs a value (up to 40 characters) and a label (up to 60 characters).'], 422);
            }
            $cleanStats[] = ['value' => $statValue, 'label' => $statLabel];
        }
        $statement = $database->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $statement->execute(['homepage_stats', json_encode($cleanStats, JSON_UNESCAPED_UNICODE)]);
        jsonResponse(['stats' => $cleanStats]);
    }

    if ($action === 'projects' && $method === 'GET') {
        ensureProjectsTable($database);
        $statement = $database->query('SELECT id, title, category, image, size FROM projects WHERE active = 1 ORDER BY created_at DESC, id DESC');
        jsonResponse(['projects' => $statement->fetchAll()]);
    }

    if ($action === 'projects' && $method === 'POST') {
        requireAdmin();
        ensureProjectsTable($database);
        requireFields($body, ['title', 'category', 'image']);
        $wideCategories = ['business-cards', 'flyers', 'banners'];
        $projectCategories = array_merge($wideCategories, ['t-shirts', 'signage', 'caps', 'posters']);
        $projectTitle = trim((string) $body['title']);
        $projectCategory = trim((string) $body['category']);
        if ($projectTitle === '' || mb_strlen($projectTitle) > 160 || !in_array($projectCategory, $projectCategories, true)) {
            jsonResponse(['error' => 'Please enter a project title (up to 160 characters) and choose a valid category.'], 422);
        }
        $imageInput = trim((string) $body['image']);
        if (preg_match('#^https?://#i', $imageInput)) {
            if (!filter_var($imageInput, FILTER_VALIDATE_URL) || mb_strlen($imageInput) > 500) {
                jsonResponse(['error' => 'That image link is not valid.'], 422);
            }
            $projectImage = $imageInput;
        } else {
            $projectImage = saveUploadedImage($imageInput);
        }
        $statement = $database->prepare('INSERT INTO projects (title, category, image, size) VALUES (?, ?, ?, ?)');
        $statement->execute([$projectTitle, $projectCategory, $projectImage, in_array($projectCategory, $wideCategories, true) ? 'wide' : 'narrow']);
        jsonResponse(['id' => (int) $database->lastInsertId(), 'image' => $projectImage], 201);
    }

    if ($action === 'projects' && $method === 'DELETE') {
        requireAdmin();
        ensureProjectsTable($database);
        requireFields($body, ['id']);
        $statement = $database->prepare('UPDATE projects SET active = 0 WHERE id = ?');
        $statement->execute([(int) $body['id']]);
        jsonResponse(['ok' => true]);
    }

    if (in_array($action, ['adverts', 'advert'], true)) {
        if ($action === 'adverts' && $method === 'GET') {
            $statement = $database->query('SELECT id, title, description, media_type AS mediaType, media_url AS mediaUrl, created_at AS createdAt FROM adverts WHERE active = 1 ORDER BY created_at DESC');
            jsonResponse(['adverts' => $statement->fetchAll()]);
        }
        if ($action === 'adverts' && $method === 'POST') {
            requireAdmin();
            requireFields($body, ['title', 'description', 'mediaType', 'mediaUrl']);
            $mediaType = in_array($body['mediaType'], ['video', 'image'], true) ? $body['mediaType'] : 'image';
            $advertTitle = trim((string) $body['title']);
            $advertUrl = trim((string) $body['mediaUrl']);
            if ($advertTitle === '' || mb_strlen($advertTitle) > 160 || mb_strlen((string) $body['description']) > 5000 || !preg_match('#^https?://#i', $advertUrl) || !filter_var($advertUrl, FILTER_VALIDATE_URL) || mb_strlen($advertUrl) > 500) {
                jsonResponse(['error' => 'Please enter a title and a valid image or video link starting with https://.'], 422);
            }
            $statement = $database->prepare('INSERT INTO adverts (title, description, media_type, media_url) VALUES (?, ?, ?, ?)');
            $statement->execute([$advertTitle, trim((string) $body['description']), $mediaType, $advertUrl]);
            jsonResponse(['id' => (int) $database->lastInsertId()], 201);
        }
        if ($action === 'advert' && $method === 'DELETE') {
            requireAdmin();
            requireFields($body, ['id']);
            $statement = $database->prepare('UPDATE adverts SET active = 0 WHERE id = ?');
            $statement->execute([(int) $body['id']]);
            jsonResponse(['ok' => true]);
        }
    }

    if ($action === 'quote' && $method === 'POST') {
        $current = requireUser();
        requireFields($body, ['description', 'category', 'quantity']);
        $quoteDescription = trim((string) $body['description']);
        $quoteCategory = trim((string) $body['category']);
        $quoteNotes = trim((string) ($body['notes'] ?? ''));
        $quoteQuantity = filter_var($body['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($quoteQuantity === false || mb_strlen($quoteCategory) > 80 || mb_strlen($quoteDescription) > 5000 || mb_strlen($quoteNotes) > 5000) {
            jsonResponse(['error' => 'Please check your quote: the quantity must be a whole number from 1 to 1,000,000 and the text fields are too long if over 5,000 characters.'], 422);
        }
        rateLimitGuard($database, 'quote-user:' . (int) $current['id'], 10, 3600, 'You have sent several quote requests recently. Please wait a while before sending another.');
        $reference = 'Q-' . strtoupper(bin2hex(random_bytes(4)));
        $designImage = saveUploadedImage($body['designImage'] ?? '') ?? '';
        $statement = $database->prepare('INSERT INTO quotes (reference_code, user_id, description, category, quantity, notes, design_image) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'], $quoteDescription, $quoteCategory, $quoteQuantity, $quoteNotes, $designImage]);
        rateLimitHit($database, 'quote-user:' . (int) $current['id']);
        jsonResponse(['reference' => $reference], 201);
    }

    if ($action === 'quotes' && $method === 'GET') {
        $current = requireUser();
        ensureQuotePriceColumns($database);
        $quoteColumns = 'q.reference_code AS id, q.description, q.category, q.quantity, q.notes, q.status, q.response, q.design_image AS designImage, q.draft_image AS draftImage, q.client_decision AS clientDecision, q.client_reason AS clientReason, q.client_changes AS clientChanges, q.client_idea_image AS clientIdeaImage, q.created_at AS createdAt, q.quoted_price AS quotedPrice, q.order_reference AS orderReference, o.status AS orderStatus';
        if (($current['role'] ?? '') === 'admin') {
            $statement = $database->query('SELECT ' . $quoteColumns . ', u.name AS customer, u.email FROM quotes q JOIN users u ON u.id = q.user_id LEFT JOIN orders o ON o.reference_code = q.order_reference ORDER BY q.created_at DESC');
        } else {
            $statement = $database->prepare('SELECT ' . $quoteColumns . ' FROM quotes q LEFT JOIN orders o ON o.reference_code = q.order_reference WHERE q.user_id = ? ORDER BY q.created_at DESC');
            $statement->execute([$current['id']]);
        }
        $quoteRows = $statement->fetchAll();
        foreach ($quoteRows as &$quoteRow) {
            if ($quoteRow['quotedPrice'] !== null) {
                $rowTotals = quoteTotals((float) $quoteRow['quotedPrice']);
                $quoteRow['quotedPrice'] = (float) $quoteRow['quotedPrice'];
                $quoteRow['quotedVat'] = $rowTotals['vat'];
                $quoteRow['quotedTotal'] = $rowTotals['total'];
            }
        }
        unset($quoteRow);
        jsonResponse(['quotes' => $quoteRows]);
    }

    if ($action === 'quote-status' && $method === 'PUT') {
        requireAdmin();
        ensureQuotePriceColumns($database);
        requireFields($body, ['id', 'status']);
        $quoteRef = trim((string) $body['id']);
        $newStatus = trim((string) $body['status']);
        $newResponse = trim((string) ($body['response'] ?? ''));
        if (mb_strlen($newStatus) > 40 || mb_strlen($newResponse) > 5000) {
            jsonResponse(['error' => 'The status or response is too long.'], 422);
        }
        $statement = $database->prepare('SELECT q.quoted_price, q.order_reference, u.name, u.email FROM quotes q JOIN users u ON u.id = q.user_id WHERE q.reference_code = ? LIMIT 1');
        $statement->execute([$quoteRef]);
        $existing = $statement->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Quote not found.'], 404);
        }
        if (!empty($existing['order_reference']) && $newStatus !== 'Approved') {
            jsonResponse(['error' => 'The customer has accepted this quote and an order exists, so it can no longer be changed. Manage it from the Orders page.'], 422);
        }
        $newPrice = $existing['quoted_price'] !== null ? (float) $existing['quoted_price'] : null;
        $priceInput = trim((string) ($body['price'] ?? ''));
        if ($priceInput !== '') {
            $parsedPrice = filter_var($priceInput, FILTER_VALIDATE_FLOAT);
            if ($parsedPrice === false || $parsedPrice <= 0 || $parsedPrice > 10000000) {
                jsonResponse(['error' => 'Please enter a price above zero, for example 1250.00.'], 422);
            }
            if (!empty($existing['order_reference']) && $newPrice !== null && abs($parsedPrice - $newPrice) > 0.001) {
                jsonResponse(['error' => 'An order already exists for this quote, so the price can no longer be changed.'], 422);
            }
            $newPrice = round($parsedPrice, 2);
        }
        if ($newStatus === 'Draft Sent' && $newPrice === null) {
            jsonResponse(['error' => 'Please enter the price before sending the quote to the customer.'], 422);
        }
        $draftImage = saveUploadedImage($body['draftImage'] ?? '');
        $parameters = [$newStatus, $newResponse, $newPrice];
        $draftSql = '';
        if ($draftImage !== null) {
            $draftSql = ', draft_image = ?';
            $parameters[] = $draftImage;
        }
        $parameters[] = $quoteRef;
        $statement = $database->prepare('UPDATE quotes SET status = ?, response = ?, quoted_price = ?' . $draftSql . ' WHERE reference_code = ?');
        $statement->execute($parameters);
        if ($newStatus === 'Draft Sent' && $newPrice !== null) {
            // Best-effort heads-up email; the quote is already visible on the customer's Custom Quote page.
            $emailTotals = quoteTotals($newPrice);
            $nameParts = explode(' ', trim((string) $existing['name']));
            sendSiteEmail((string) $existing['email'], 'Your AweSum Brands quote ' . $quoteRef . ' is ready',
                'Hi ' . (trim((string) $nameParts[0]) !== '' ? trim((string) $nameParts[0]) : 'there') . ",\r\n\r\n"
                . 'Your custom quote ' . $quoteRef . " is ready to review.\r\n\r\n"
                . 'Price: R' . number_format($newPrice, 2, '.', ' ') . ' excluding VAT (R' . number_format($emailTotals['total'], 2, '.', ' ') . " including 15% VAT)\r\n\r\n"
                . "Please sign in to look at the design draft, then accept it to pay, or ask for changes:\r\n"
                . SITE_URL . "/quote.html\r\n\r\nAweSum Brands\r\n");
        }
        jsonResponse(['ok' => true]);
    }

    if ($action === 'quote-client-review' && $method === 'PUT') {
        $current = requireUser();
        ensureQuotePriceColumns($database);
        requireFields($body, ['id', 'decision']);
        $decision = strtolower(trim((string) $body['decision'])) === 'approved' ? 'approved' : 'rejected';
        $quoteRef = trim((string) $body['id']);
        $statement = $database->prepare('SELECT status, quoted_price, order_reference FROM quotes WHERE reference_code = ? AND user_id = ? LIMIT 1');
        $statement->execute([$quoteRef, (int) $current['id']]);
        $ownQuote = $statement->fetch();
        if (!$ownQuote) {
            jsonResponse(['error' => 'Quote not found.'], 404);
        }
        if (!empty($ownQuote['order_reference'])) {
            jsonResponse(['error' => 'This quote has already been accepted.'], 422);
        }
        if ($decision === 'approved') {
            if ($ownQuote['status'] !== 'Draft Sent') {
                jsonResponse(['error' => 'This quote is not waiting for your decision.'], 422);
            }
            if ($ownQuote['quoted_price'] === null) {
                jsonResponse(['error' => 'Our team has not added a price to this quote yet. Please wait for their response.'], 422);
            }
        } elseif (!in_array($ownQuote['status'], ['Draft Sent', 'Needs Revision'], true)) {
            jsonResponse(['error' => 'This quote is not waiting for your decision.'], 422);
        }
        $status = $decision === 'approved' ? 'Approved' : 'Needs Revision';
        $statement = $database->prepare('UPDATE quotes SET client_decision = ?, client_reason = ?, client_changes = ?, client_idea_image = ?, status = ? WHERE reference_code = ? AND user_id = ?');
        $statement->execute([
            $decision,
            trim((string) ($body['reason'] ?? '')),
            trim((string) ($body['changes'] ?? '')),
            saveUploadedImage($body['ideaImage'] ?? '') ?? '',
            $status,
            $quoteRef,
            (int) $current['id']
        ]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'quote' && $method === 'DELETE') {
        requireAdmin();
        requireFields($body, ['id']);
        $statement = $database->prepare('DELETE FROM quotes WHERE reference_code = ?');
        $statement->execute([trim((string) $body['id'])]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'message' && $method === 'POST') {
        $current = user();
        requireFields($body, ['name', 'email', 'text']);
        $reference = 'MSG-' . strtoupper(bin2hex(random_bytes(4)));
        // Hidden "website" field: real visitors never see or fill it, spam bots do. Pretend it worked and save nothing.
        if (trim((string) ($body['website'] ?? '')) !== '') {
            jsonResponse(['reference' => $reference], 201);
        }
        $messageName = trim((string) $body['name']);
        $messageEmail = strtolower(trim((string) $body['email']));
        $messageText = trim((string) $body['text']);
        if (mb_strlen($messageName) > 160 || !filter_var($messageEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($messageEmail) > 255) {
            jsonResponse(['error' => 'Please enter a valid name and email address.'], 422);
        }
        if (mb_strlen($messageText) > 5000) {
            jsonResponse(['error' => 'Your message is too long (maximum 5000 characters).'], 422);
        }
        rateLimitGuard($database, 'message-ip:' . clientIp(), 5, 600, 'You are sending messages too quickly. Please wait a few minutes and try again.');
        rateLimitHit($database, 'message-ip:' . clientIp());
        $statement = $database->prepare('INSERT INTO messages (reference_code, user_id, name, email, text) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'] ?? null, $messageName, $messageEmail, $messageText]);
        jsonResponse(['reference' => $reference], 201);
    }

    if ($action === 'admin-message' && $method === 'POST') {
        requireAdmin();
        requireFields($body, ['userId', 'text']);
        if (mb_strlen(trim((string) $body['text'])) > 5000) {
            jsonResponse(['error' => 'The message is too long (maximum 5000 characters).'], 422);
        }
        $recipient = $database->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $recipient->execute([(int) $body['userId']]);
        $userRecord = $recipient->fetch();
        if (!$userRecord) jsonResponse(['error' => 'User not found.'], 404);
        $reference = 'MSG-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $database->prepare("INSERT INTO messages (reference_code, user_id, name, email, text, status, sender_role) VALUES (?, ?, ?, ?, ?, 'Sent', 'admin')");
        $statement->execute([$reference, $userRecord['id'], 'AweSum Brands', $userRecord['email'], trim((string) $body['text'])]);
        jsonResponse(['reference' => $reference], 201);
    }

    if ($action === 'messages' && $method === 'GET') {
        $current = requireUser();
        if (($current['role'] ?? '') === 'admin') {
            $statement = $database->query('SELECT reference_code AS id, user_id AS userId, name, email, text, reply, status, sender_role AS senderRole, created_at AS createdAt, replied_at AS repliedAt FROM messages ORDER BY created_at DESC');
        } else {
            $statement = $database->prepare('SELECT reference_code AS id, name, email, text, reply, status, sender_role AS senderRole, created_at AS createdAt, replied_at AS repliedAt FROM messages WHERE user_id = ? ORDER BY created_at DESC');
            $statement->execute([$current['id']]);
        }
        jsonResponse(['messages' => $statement->fetchAll()]);
    }

    if ($action === 'message-reply' && $method === 'PUT') {
        requireAdmin();
        requireFields($body, ['id', 'reply']);
        if (mb_strlen(trim((string) $body['reply'])) > 5000) {
            jsonResponse(['error' => 'The reply is too long (maximum 5000 characters).'], 422);
        }
        $statement = $database->prepare("UPDATE messages SET reply = ?, status = 'Responded', replied_at = NOW() WHERE reference_code = ?");
        $statement->execute([trim((string) $body['reply']), trim((string) $body['id'])]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'message' && $method === 'DELETE') {
        $current = requireUser();
        requireFields($body, ['id']);
        if (($current['role'] ?? '') === 'admin') {
            $statement = $database->prepare('DELETE FROM messages WHERE reference_code = ?');
            $statement->execute([trim((string) $body['id'])]);
        } else {
            $statement = $database->prepare('DELETE FROM messages WHERE reference_code = ? AND user_id = ?');
            $statement->execute([trim((string) $body['id']), $current['id']]);
        }
        jsonResponse(['ok' => true]);
    }

    if ($action === 'order' && $method === 'POST') {
        $current = requireUser();
        requireFields($body, ['address']);
        rateLimitGuard($database, 'order-user:' . (int) $current['id'], 20, 3600, 'You have placed several orders recently. Please pay for your existing orders, or wait a while before ordering again.');
        $address = trim((string) $body['address']);
        if (mb_strlen($address) > 500) {
            jsonResponse(['error' => 'The delivery address is too long (maximum 500 characters).'], 422);
        }
        $rawItems = $body['items'] ?? null;
        $order = priceOrderItems($database, is_array($rawItems) ? $rawItems : [], (int) $current['id']);
        $reference = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        $database->beginTransaction();
        try {
            $statement = $database->prepare('INSERT INTO orders (reference_code, user_id, items, total, address) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$reference, $current['id'], json_encode($order['items'], JSON_THROW_ON_ERROR), $order['total'], $address]);
            // Each accepted quote can be linked to one order only, so a double click cannot charge it twice.
            foreach ($order['quoteIds'] as $quotePrimaryKey) {
                $link = $database->prepare('UPDATE quotes SET order_reference = ? WHERE id = ? AND order_reference IS NULL');
                $link->execute([$reference, $quotePrimaryKey]);
                if ($link->rowCount() === 0) {
                    $database->rollBack();
                    jsonResponse(['error' => 'A quote in your cart already has an order. Please remove it from your cart and refresh the page.'], 409);
                }
            }
            $database->commit();
        } catch (Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        rateLimitHit($database, 'order-user:' . (int) $current['id']);
        jsonResponse([
            'reference' => $reference,
            'subtotal' => $order['subtotal'],
            'vat' => $order['vat'],
            'total' => $order['total'],
        ], 201);
    }

    if ($action === 'orders' && $method === 'GET') {
        $current = requireUser();
        if (($current['role'] ?? '') === 'admin') {
            $statement = $database->query('SELECT o.reference_code AS id, o.items, o.total, o.address, o.status, o.created_at AS createdAt, u.name AS customer, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC');
        } else {
            $statement = $database->prepare('SELECT reference_code AS id, items, total, address, status, created_at AS createdAt FROM orders WHERE user_id = ? ORDER BY created_at DESC');
            $statement->execute([$current['id']]);
        }
        $orders = $statement->fetchAll();
        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items'], true, 512, JSON_THROW_ON_ERROR);
        }
        jsonResponse(['orders' => $orders]);
    }

    if ($action === 'order-status' && $method === 'PUT') {
        requireAdmin();
        requireFields($body, ['id', 'status']);
        $allowedStatuses = ['Payment pending', 'Payment received', 'Pending', 'Accepted', 'Rejected', 'Processing', 'Completed'];
        $status = trim((string) $body['status']);
        if (!in_array($status, $allowedStatuses, true)) {
            jsonResponse(['error' => 'Invalid order status.'], 422);
        }
        $statement = $database->prepare('UPDATE orders SET status = ? WHERE reference_code = ?');
        $statement->execute([$status, trim((string) $body['id'])]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'schema-check' && $method === 'GET') {
        // Admin-only health check: reports any table or column the website needs that this database does not have.
        requireAdmin();
        ensureProjectsTable($database);
        ensureSettingsTable($database);
        ensurePasswordResetsTable($database);
        ensureQuotePriceColumns($database);
        $expected = [
            'password_resets' => ['id', 'user_id', 'token_hash', 'expires_at', 'created_at'],
            'site_settings' => ['setting_key', 'setting_value', 'updated_at'],
            'projects' => ['id', 'title', 'category', 'image', 'size', 'active', 'created_at'],
            'users' => ['id', 'name', 'email', 'password_hash', 'company', 'role', 'created_at'],
            'products' => ['id', 'name', 'category', 'description', 'price', 'image', 'active', 'created_at'],
            'adverts' => ['id', 'title', 'description', 'media_type', 'media_url', 'active', 'created_at'],
            'quotes' => ['id', 'reference_code', 'user_id', 'description', 'category', 'quantity', 'notes', 'status', 'created_at', 'response', 'design_image', 'draft_image', 'client_decision', 'client_reason', 'client_changes', 'client_idea_image', 'quoted_price', 'order_reference'],
            'messages' => ['id', 'reference_code', 'user_id', 'name', 'email', 'text', 'reply', 'status', 'created_at', 'replied_at', 'sender_role'],
            'orders' => ['id', 'reference_code', 'user_id', 'items', 'total', 'address', 'status', 'created_at'],
        ];
        $statement = $database->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
        $found = [];
        foreach ($statement->fetchAll() as $row) {
            $found[strtolower((string) $row['TABLE_NAME'])][] = strtolower((string) $row['COLUMN_NAME']);
        }
        $missing = [];
        foreach ($expected as $table => $columns) {
            if (!isset($found[$table])) {
                $missing[] = 'Table "' . $table . '" is missing.';
                continue;
            }
            foreach (array_diff($columns, $found[$table]) as $column) {
                $missing[] = 'Column "' . $table . '.' . $column . '" is missing.';
            }
        }
        jsonResponse(['ok' => !$missing, 'missing' => $missing, 'note' => $missing ? 'Import the matching table definition from awesum_combined_full.sql, or ask for the ALTER TABLE statement.' : 'All required tables and columns are present.']);
    }

    jsonResponse(['error' => 'Unknown API action.'], 404);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Database operation failed. Check server configuration.'], 500);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Request could not be completed.'], 500);
}