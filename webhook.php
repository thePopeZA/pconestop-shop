<?php
/**
 * Yoco webhook receiver.
 * Register this URL (https://shop.pconestop.co.za/webhook.php) via the Yoco
 * webhooks API; store the returned secret in YOCO_WEBHOOK_SECRET.
 *
 * Handles payment.succeeded / payment.failed events and updates the order.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/orders.php';
require_once BASE_PATH . '/includes/Yoco.php';

$raw = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Always log the raw event for debugging/audit.
@file_put_contents(
    BASE_PATH . '/storage/logs/webhook.log',
    '[' . date('Y-m-d H:i:s') . '] ' . $raw . "\n",
    FILE_APPEND
);

$secret = (string)env('YOCO_WEBHOOK_SECRET', '');
$verified = Yoco::verifyWebhook($secret, $headers, $raw);

if ($secret !== '' && !$verified) {
    http_response_code(401);
    echo 'invalid signature';
    exit;
}
// If no secret configured yet (pre-registration), accept but confirm via API below.

$event = json_decode($raw, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'bad payload';
    exit;
}

$type = $event['type'] ?? '';
$payload = $event['payload'] ?? [];
$metadata = $payload['metadata'] ?? ($payload['checkout']['metadata'] ?? []);
$orderNumber = $metadata['order_number'] ?? '';
$orderId = (int)($metadata['order_id'] ?? 0);
$paymentId = $payload['id'] ?? ($payload['paymentId'] ?? null);
$checkoutId = $payload['checkoutId'] ?? ($payload['checkout']['id'] ?? null);

// Resolve the order.
$order = null;
if ($orderId) {
    $order = order_by_id($orderId);
} elseif ($orderNumber) {
    $order = order_by_number($orderNumber);
}

if (!$order) {
    // Nothing to do, but acknowledge so Yoco stops retrying.
    http_response_code(200);
    echo 'no matching order';
    exit;
}

try {
    if (str_contains($type, 'succeeded') || str_contains($type, 'success')) {
        // Confirm with the API when we couldn't verify the signature.
        $ok = $verified;
        if (!$ok && $order['yoco_checkout_id']) {
            $co = (new Yoco())->getCheckout($order['yoco_checkout_id']);
            $ok = in_array(strtolower((string)($co['status'] ?? '')), ['completed', 'succeeded', 'successful'], true);
        }
        if ($ok || $verified) {
            mark_order_paid((int)$order['id'], $paymentId);
        }
    } elseif (str_contains($type, 'failed') || str_contains($type, 'cancel')) {
        mark_order_failed((int)$order['id'], 'failed');
    }
} catch (Throwable $e) {
    error_log('Webhook processing error: ' . $e->getMessage());
}

http_response_code(200);
echo 'ok';
