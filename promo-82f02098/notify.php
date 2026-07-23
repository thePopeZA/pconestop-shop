<?php
/**
 * Promo gallery notifier.
 *   /promo-82f02098/notify.php?key=2ce5b7f83a48a976
 * Emails the gallery URL + image count + one-line instructions to the
 * recipient list, then prints a plain-text confirmation. 403 without the key.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/mailer.php';

const NOTIFY_KEY = '2ce5b7f83a48a976';

// Easy-to-extend recipient list.
$recipients = [
    'jurgen@stratusnet.co.za',
];

header('Content-Type: text/plain; charset=utf-8');

if (!hash_equals(NOTIFY_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo "403 Forbidden\n";
    exit;
}

// Count published PNGs.
$imgDir = __DIR__ . '/img';
$count = 0;
if (is_dir($imgDir)) {
    foreach (scandir($imgDir) as $f) {
        if (preg_match('/\.png$/i', $f)) {
            $count++;
        }
    }
}

$galleryUrl = url('promo-82f02098/');
$from       = 'no-reply@pconestop.co.za';
$fromName   = 'PC One Stop Promos';
$subject    = "PCOS promo cards ready — {$count} to post";
$oneLiner   = "{$count} promo card" . ($count === 1 ? '' : 's') . " ready — open {$galleryUrl} on your phone and tap Share on each to post to WhatsApp status.";

$body = "<div style=\"font-family:Arial,sans-serif;color:#1a2233;max-width:520px\">"
    . "<h2 style=\"color:#0E63D8;margin-bottom:6px\">🔥 New promo cards are ready</h2>"
    . "<p><strong>{$count}</strong> promo card" . ($count === 1 ? '' : 's') . " " . ($count === 1 ? 'is' : 'are') . " ready to post to WhatsApp status.</p>"
    . "<p style=\"font-size:1.05em\">👉 <a href=\"" . e($galleryUrl) . "\">" . e($galleryUrl) . "</a></p>"
    . "<p style=\"color:#555\">On your phone: open the link, tap <strong>Share</strong> on each card to post the image with its caption to WhatsApp status. Posted cards dim automatically.</p>"
    . "<p style=\"color:#999;font-size:12px\">Automated notice from shop.pconestop.co.za</p>"
    . "</div>";

// SAFETY: only the real production host may send. Anywhere else (localhost,
// staging, an IP) is a DRY-RUN that emails nobody — it just shows the message.
$host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isProd = ($host === 'shop.pconestop.co.za');

if (!$isProd) {
    echo "DRY-RUN — host '{$host}' is not shop.pconestop.co.za, so no mail was sent.\n";
    echo "The message that WOULD have been sent:\n\n";
    echo "From:       {$fromName} <{$from}>\n";
    echo "To:         " . implode(', ', $recipients) . "\n";
    echo "Subject:    {$subject}\n";
    echo "\n{$oneLiner}\n";
    exit;
}

$sent = 0;
foreach ($recipients as $to) {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    dispatch_mail($to, $subject, $body, $from, $fromName);
    $sent++;
}

$mailOn = (bool)env('MAIL_ENABLED', false);
echo "OK\n";
echo "Gallery:    {$galleryUrl}\n";
echo "Images:     {$count}\n";
echo "Recipients: " . implode(', ', $recipients) . "\n";
echo "Dispatched: {$sent}\n";
echo "Mail mode:  " . ($mailOn ? 'live (SMTP/mail)' : 'DISABLED — logged to storage/logs/mail.log only') . "\n";
