<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * PayFast ITN (Instant Transaction Notification) receiver.
 * PayFast calls this page after every payment attempt. An order is only marked "Payment received" when
 * the signature, the amount and PayFast's own confirmation all check out (the sender address is logged).
 */

function itnReply(int $status, string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    itnReply(405, 'POST only');
}

try {
    $data = $_POST;
    if (empty($data['signature']) || empty($data['m_payment_id'])) {
        itnReply(400, 'Invalid notification');
    }
    foreach ($data as $value) {
        if (!is_scalar($value)) {
            itnReply(400, 'Invalid notification');
        }
    }

    // 1. Signature: rebuild it from the fields in the order PayFast sent them.
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature') {
            break;
        }
        $pairs[] = $key . '=' . urlencode((string) $value);
    }
    $paramString = implode('&', $pairs);
    $signedString = PAYFAST_PASSPHRASE !== '' ? $paramString . '&passphrase=' . urlencode(PAYFAST_PASSPHRASE) : $paramString;
    if (!hash_equals(md5($signedString), strtolower((string) $data['signature']))) {
        error_log('PayFast ITN rejected: signature mismatch');
        itnReply(400, 'Bad signature');
    }

    // 2. Sender: note it in the log if it is not a known PayFast address. PayFast has extended its IP ranges, so an
    //    address that is new to us is not rejected here; the signature (which needs your secret passphrase) and
    //    PayFast's own confirmation in step 4 are what prove the notification is genuine.
    $validIps = [];
    foreach (['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'] as $host) {
        $found = @gethostbynamel($host);
        if ($found) {
            $validIps = array_merge($validIps, $found);
        }
    }
    if ($validIps && !in_array(clientIp(), $validIps, true)) {
        error_log('PayFast ITN notice: sender ' . clientIp() . ' is not in the resolved PayFast address list; continuing with signature and PayFast confirmation checks.');
    }

    // 3. Order and amount.
    $reference = trim((string) $data['m_payment_id']);
    $statement = db()->prepare('SELECT total, status FROM orders WHERE reference_code = ? LIMIT 1');
    $statement->execute([$reference]);
    $order = $statement->fetch();
    if (!$order) {
        error_log('PayFast ITN: unknown order ' . $reference);
        itnReply(200, 'Unknown order');
    }
    if (abs((float) $order['total'] - (float) ($data['amount_gross'] ?? 0)) > 0.01) {
        error_log('PayFast ITN rejected: amount mismatch for ' . $reference);
        itnReply(400, 'Amount mismatch');
    }

    // 4. Ask PayFast to confirm the notification is genuine.
    if (!function_exists('curl_init')) {
        error_log('PayFast ITN: the PHP curl extension is not enabled');
        itnReply(500, 'Server setup problem');
    }
    $curl = curl_init('https://' . (PAYFAST_MODE === 'live' ? 'www' : 'sandbox') . '.payfast.co.za/eng/query/validate');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $paramString,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $confirmation = curl_exec($curl);
    curl_close($curl);
    if (trim((string) $confirmation) !== 'VALID') {
        error_log('PayFast ITN rejected: PayFast did not confirm ' . $reference);
        itnReply(400, 'Not confirmed');
    }

    // 5. Mark the order as paid (only once, and only while it is still waiting for payment).
    if (($data['payment_status'] ?? '') === 'COMPLETE') {
        $update = db()->prepare("UPDATE orders SET status = 'Payment received' WHERE reference_code = ? AND status = 'Payment pending'");
        $update->execute([$reference]);
    }
    itnReply(200, 'OK');
} catch (Throwable $exception) {
    error_log('PayFast ITN error: ' . $exception->getMessage());
    itnReply(500, 'Error');
}