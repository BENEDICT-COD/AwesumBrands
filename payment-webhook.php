<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$secret = getenv('AWESUM_PAYMENT_WEBHOOK_SECRET') ?: '';
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_AWESUM_SIGNATURE'] ?? '';

if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
    jsonResponse(['error' => 'Invalid webhook signature.'], 401);
}

try {
    $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if (($event['type'] ?? '') !== 'payment.completed' || empty($event['reference'])) {
        jsonResponse(['error' => 'Unsupported webhook event.'], 422);
    }
    $statement = db()->prepare("UPDATE orders SET status = 'Payment received' WHERE reference_code = ?");
    $statement->execute([trim((string) $event['reference'])]);
    jsonResponse(['ok' => true]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Webhook could not be processed.'], 400);
}
