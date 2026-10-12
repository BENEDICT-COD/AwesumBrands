<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body = requestBody();

const AWESUM_VAT_RATE = 0.15;
const AWESUM_MAX_ORDER_LINES = 50;
const AWESUM_MAX_LINE_QUANTITY = 1000;

/**
 * Built-in marketplace items that are written directly into marketplace.html.
 * Prices here MUST match marketplace.html. A product with the same name in the
 * database takes priority over these.
 */
function awesumStaticCatalogue(): array
{
    return [
        'premium business cards' => 299.00,
        'a5 flyers full colour' => 450.00,
        'custom t-shirt printing' => 180.00,
        'branded cap printing' => 220.00,
        'a1 poster printing' => 380.00,
        'outdoor vinyl signage' => 1200.00,
        'pull-up banner 2m' => 850.00,
        'tri-fold brochure' => 520.00,
    ];
}

/**
 * Rebuilds an order from trusted prices. The browser only supplies item names and
 * quantities; every price and the total are calculated here, in whole cents.
 */
function priceOrderItems(PDO $database, array $items): array
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
    foreach ($items as $item) {
        if (!is_array($item)) {
            jsonResponse(['error' => 'Invalid item in order.'], 422);
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
    $vatCents = (int) round($subtotalCents * AWESUM_VAT_RATE);

    return [
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
        $statement = $database->prepare("INSERT INTO users (name, email, password_hash, company, role) VALUES (?, ?, ?, ?, 'user')");
        $statement->execute([trim((string) $body['name']), $email, password_hash((string) $body['password'], PASSWORD_DEFAULT), trim((string) ($body['company'] ?? '')) ?: null]);
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

    if ($action === 'logout' && $method === 'POST') {
        session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'session' && $method === 'GET') {
        $current = user();
        jsonResponse(['user' => $current, 'isAdmin' => $current && ($current['role'] ?? '') === 'admin']);
    }

    if ($action === 'products' && $method === 'GET') {
        $database->exec('ALTER TABLE products MODIFY image LONGTEXT NULL');
        $statement = $database->query('SELECT id, name, category, description, price, image FROM products WHERE active = 1 ORDER BY created_at DESC');
        jsonResponse(['products' => $statement->fetchAll()]);
    }

    if ($action === 'products' && $method === 'POST') {
        requireAdmin();
        requireFields($body, ['name', 'category', 'price']);
        $database->exec('ALTER TABLE products MODIFY image LONGTEXT NULL');
        $image = saveUploadedImage($body['image'] ?? '');
        $statement = $database->prepare('INSERT INTO products (name, category, description, price, image) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([trim((string) $body['name']), trim((string) $body['category']), trim((string) ($body['description'] ?? '')), (float) $body['price'], $image]);
        jsonResponse(['id' => (int) $database->lastInsertId(), 'image' => $image], 201);
    }

    if ($action === 'products' && $method === 'DELETE') {
        requireAdmin();
        requireFields($body, ['id']);
        $statement = $database->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $statement->execute([(int) $body['id']]);
        jsonResponse(['ok' => true]);
    }

    if (in_array($action, ['adverts', 'advert'], true)) {
        $database->exec("CREATE TABLE IF NOT EXISTS adverts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL, description TEXT NOT NULL, media_type VARCHAR(20) NOT NULL DEFAULT 'image', media_url VARCHAR(500) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($action === 'adverts' && $method === 'GET') {
            $statement = $database->query('SELECT id, title, description, media_type AS mediaType, media_url AS mediaUrl, created_at AS createdAt FROM adverts WHERE active = 1 ORDER BY created_at DESC');
            jsonResponse(['adverts' => $statement->fetchAll()]);
        }
        if ($action === 'adverts' && $method === 'POST') {
            requireAdmin();
            requireFields($body, ['title', 'description', 'mediaType', 'mediaUrl']);
            $mediaType = in_array($body['mediaType'], ['video', 'image'], true) ? $body['mediaType'] : 'image';
            $statement = $database->prepare('INSERT INTO adverts (title, description, media_type, media_url) VALUES (?, ?, ?, ?)');
            $statement->execute([trim((string) $body['title']), trim((string) $body['description']), $mediaType, trim((string) $body['mediaUrl'])]);
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
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS design_image LONGTEXT NULL');
        $reference = 'Q-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $database->prepare('INSERT INTO quotes (reference_code, user_id, description, category, quantity, notes, design_image) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'], trim((string) $body['description']), trim((string) $body['category']), max(1, (int) $body['quantity']), trim((string) ($body['notes'] ?? '')), saveUploadedImage($body['designImage'] ?? '') ?? '']);
        jsonResponse(['reference' => $reference], 201);
    }

    if ($action === 'quotes' && $method === 'GET') {
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS response TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS design_image LONGTEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS draft_image LONGTEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_decision VARCHAR(20) NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_reason TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_changes TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_idea_image LONGTEXT NULL');
        $current = requireUser();
        if (($current['role'] ?? '') === 'admin') {
            $statement = $database->query('SELECT q.reference_code AS id, u.name AS customer, u.email, q.description, q.category, q.quantity, q.notes, q.status, q.response, q.design_image AS designImage, q.draft_image AS draftImage, q.client_decision AS clientDecision, q.client_reason AS clientReason, q.client_changes AS clientChanges, q.client_idea_image AS clientIdeaImage, q.created_at AS createdAt FROM quotes q JOIN users u ON u.id = q.user_id ORDER BY q.created_at DESC');
        } else {
            $statement = $database->prepare('SELECT reference_code AS id, description, category, quantity, notes, status, response, design_image AS designImage, draft_image AS draftImage, client_decision AS clientDecision, client_reason AS clientReason, client_changes AS clientChanges, client_idea_image AS clientIdeaImage, created_at AS createdAt FROM quotes WHERE user_id = ? ORDER BY created_at DESC');
            $statement->execute([$current['id']]);
        }
        jsonResponse(['quotes' => $statement->fetchAll()]);
    }

    if ($action === 'quote-status' && $method === 'PUT') {
        requireAdmin();
        requireFields($body, ['id', 'status']);
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS response TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS design_image LONGTEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS draft_image LONGTEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_decision VARCHAR(20) NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_reason TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_changes TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_idea_image LONGTEXT NULL');
        $draftImage = saveUploadedImage($body['draftImage'] ?? '');
        if ($draftImage === null) {
            // No new draft was sent, so keep the existing one instead of wiping it.
            $statement = $database->prepare('UPDATE quotes SET status = ?, response = ? WHERE reference_code = ?');
            $statement->execute([trim((string) $body['status']), trim((string) ($body['response'] ?? '')), trim((string) $body['id'])]);
        } else {
            $statement = $database->prepare('UPDATE quotes SET status = ?, response = ?, draft_image = ? WHERE reference_code = ?');
            $statement->execute([trim((string) $body['status']), trim((string) ($body['response'] ?? '')), $draftImage, trim((string) $body['id'])]);
        }
        jsonResponse(['ok' => true]);
    }

    if ($action === 'quote-client-review' && $method === 'PUT') {
        $current = requireUser();
        requireFields($body, ['id', 'decision']);
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS draft_image LONGTEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_decision VARCHAR(20) NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_reason TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_changes TEXT NULL');
        $database->exec('ALTER TABLE quotes ADD COLUMN IF NOT EXISTS client_idea_image LONGTEXT NULL');
        $decision = strtolower(trim((string) $body['decision'])) === 'approved' ? 'approved' : 'rejected';
        $status = $decision === 'approved' ? 'Approved' : 'Needs Revision';
        $statement = $database->prepare('UPDATE quotes SET client_decision = ?, client_reason = ?, client_changes = ?, client_idea_image = ?, status = ? WHERE reference_code = ? AND user_id = ?');
        $statement->execute([
            $decision,
            trim((string) ($body['reason'] ?? '')),
            trim((string) ($body['changes'] ?? '')),
            saveUploadedImage($body['ideaImage'] ?? '') ?? '',
            $status,
            trim((string) $body['id']),
            $current['id']
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
        $database->exec('ALTER TABLE messages ADD COLUMN IF NOT EXISTS sender_role VARCHAR(20) NOT NULL DEFAULT \'user\'');
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
            $database->exec('ALTER TABLE messages ADD COLUMN IF NOT EXISTS sender_role VARCHAR(20) NOT NULL DEFAULT \'user\'');
            $statement = $database->query('SELECT reference_code AS id, user_id AS userId, name, email, text, reply, status, sender_role AS senderRole, created_at AS createdAt, replied_at AS repliedAt FROM messages ORDER BY created_at DESC');
        } else {
            $database->exec('ALTER TABLE messages ADD COLUMN IF NOT EXISTS sender_role VARCHAR(20) NOT NULL DEFAULT \'user\'');
            $statement = $database->prepare('SELECT reference_code AS id, name, email, text, reply, status, sender_role AS senderRole, created_at AS createdAt, replied_at AS repliedAt FROM messages WHERE user_id = ? ORDER BY created_at DESC');
            $statement->execute([$current['id']]);
        }
        jsonResponse(['messages' => $statement->fetchAll()]);
    }

    if ($action === 'message-reply' && $method === 'PUT') {
        requireAdmin();
        requireFields($body, ['id', 'reply']);
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
        $address = trim((string) $body['address']);
        if (mb_strlen($address) > 500) {
            jsonResponse(['error' => 'The delivery address is too long (maximum 500 characters).'], 422);
        }
        $rawItems = $body['items'] ?? null;
        $order = priceOrderItems($database, is_array($rawItems) ? $rawItems : []);
        $reference = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $database->prepare('INSERT INTO orders (reference_code, user_id, items, total, address) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'], json_encode($order['items'], JSON_THROW_ON_ERROR), $order['total'], $address]);
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

    jsonResponse(['error' => 'Unknown API action.'], 404);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Database operation failed. Check server configuration.'], 500);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Request could not be completed.'], 500);
}