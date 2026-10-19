<?php
declare(strict_types=1);

define('SITE_URL', 'https://example.test');
require __DIR__ . '/../extras.php';

$checks = 0;
$assertSame = static function ($expected, $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $checks++;
};

$assertSame(
    ['subtotal' => 100, 'vat' => 15, 'total' => 115],
    quoteTotals(100),
    'Quote VAT totals'
);
$assertSame(
    ['subtotal' => 19.99, 'vat' => 3, 'total' => 22.99],
    quoteTotals(19.99),
    'Quote totals round VAT to whole cents'
);
$assertSame(
    'https://example.test/reset-password.html?token=abc123',
    passwordResetLink('abc123'),
    'Password reset link'
);
$assertSame(null, verifyGoogleIdToken(''), 'Empty Google credentials are rejected');
$assertSame(false, sendSiteEmail("invalid\r\n@example.test", 'Subject', 'Body'), 'Invalid email addresses are rejected');

echo "PHP unit tests passed ({$checks} checks).\n";
