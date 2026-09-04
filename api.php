<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body = requestBody();

try {
    $database = db();
    if ($action === 'signup' && $method === 'POST') {
        requireFields($body, ['name', 'email', 'password']);
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
        $statement = $database->prepare('SELECT id, name, email, password_hash, company, role FROM users WHERE email = ? LIMIT 1');
        $statement->execute([strtolower(trim((string) $body['email']))]);
        $record = $statement->fetch();
        if (!$record || !password_verify((string) $body['password'], $record['password_hash'])) {
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
        $statement = $database->prepare('INSERT INTO products (name, category, description, price, image) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([trim((string) $body['name']), trim((string) $body['category']), trim((string) ($body['description'] ?? '')), (float) $body['price'], trim((string) ($body['image'] ?? '')) ?: null]);
        jsonResponse(['id' => (int) $database->lastInsertId()], 201);
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
        $statement->execute([$reference, $current['id'], trim((string) $body['description']), trim((string) $body['category']), max(1, (int) $body['quantity']), trim((string) ($body['notes'] ?? '')), (string) ($body['designImage'] ?? '')]);
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
        $statement = $database->prepare('UPDATE quotes SET status = ?, response = ?, draft_image = ? WHERE reference_code = ?');
        $statement->execute([trim((string) $body['status']), trim((string) ($body['response'] ?? '')), trim((string) ($body['draftImage'] ?? '')), trim((string) $body['id'])]);
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
            trim((string) ($body['ideaImage'] ?? '')),
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
        $statement = $database->prepare('INSERT INTO messages (reference_code, user_id, name, email, text) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'] ?? null, trim((string) $body['name']), strtolower(trim((string) $body['email'])), trim((string) $body['text'])]);
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
        requireFields($body, ['items', 'total', 'address']);
        $items = is_array($body['items']) ? $body['items'] : [];
        if (!$items) {
            jsonResponse(['error' => 'The order must contain at least one item.'], 422);
        }
        $reference = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $database->prepare('INSERT INTO orders (reference_code, user_id, items, total, address) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$reference, $current['id'], json_encode($items, JSON_THROW_ON_ERROR), (float) $body['total'], trim((string) $body['address'])]);
        jsonResponse(['reference' => $reference], 201);
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
        $allowedStatuses = ['Pending', 'Accepted', 'Rejected', 'Processing', 'Completed'];
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
