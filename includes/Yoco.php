<?php
/**
 * Minimal Yoco Online Checkout API client.
 * Docs: https://developer.yoco.com/  (POST https://payments.yoco.com/api/checkouts)
 */

declare(strict_types=1);

class Yoco
{
    private string $secret;
    private string $base = 'https://payments.yoco.com/api';

    public function __construct(?string $secretKey = null)
    {
        $this->secret = $secretKey ?? (string)env('YOCO_SECRET_KEY', '');
        if ($this->secret === '') {
            throw new RuntimeException('Yoco secret key not configured.');
        }
    }

    /**
     * Create a checkout. $amountCents integer, in cents.
     * Returns decoded response (contains id + redirectUrl).
     */
    public function createCheckout(int $amountCents, array $urls, array $metadata = [], array $lineItems = []): array
    {
        $body = [
            'amount'   => $amountCents,
            'currency' => 'ZAR',
        ];
        if (!empty($urls['success'])) $body['successUrl'] = $urls['success'];
        if (!empty($urls['cancel']))  $body['cancelUrl']  = $urls['cancel'];
        if (!empty($urls['failure'])) $body['failureUrl'] = $urls['failure'];
        if ($metadata) $body['metadata'] = $metadata;
        if ($lineItems) $body['lineItems'] = $lineItems;

        return $this->request('POST', '/checkouts', $body, [
            'Idempotency-Key: ' . ($metadata['order_number'] ?? bin2hex(random_bytes(12))),
        ]);
    }

    /** Retrieve a checkout by id (to confirm status server-side). */
    public function getCheckout(string $checkoutId): array
    {
        return $this->request('GET', '/checkouts/' . rawurlencode($checkoutId));
    }

    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $ch = curl_init($this->base . $path);
        $headers = array_merge([
            'Authorization: Bearer ' . $this->secret,
            'Content-Type: application/json',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("Yoco request failed: $err");
        }
        $data = json_decode($raw, true);
        if ($code >= 400) {
            $msg = $data['message'] ?? $data['description'] ?? $raw;
            throw new RuntimeException("Yoco API error ($code): $msg");
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Verify a Yoco webhook using the Standard Webhooks scheme.
     * Headers: webhook-id, webhook-timestamp, webhook-signature.
     * Secret format: "whsec_<base64>".
     */
    public static function verifyWebhook(string $secret, array $headers, string $payload): bool
    {
        if ($secret === '') {
            return false; // caller decides whether to allow unverified
        }
        // headers arrive with various casing
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = is_array($v) ? ($v[0] ?? '') : $v;
        }
        $id   = $h['webhook-id']        ?? '';
        $ts   = $h['webhook-timestamp'] ?? '';
        $sig  = $h['webhook-signature'] ?? '';
        if ($id === '' || $ts === '' || $sig === '') {
            return false;
        }
        // reject old timestamps (>5 min)
        if (abs(time() - (int)$ts) > 300) {
            return false;
        }
        $secretBytes = base64_decode(substr($secret, strpos($secret, '_') !== false ? strpos($secret, '_') + 1 : 0));
        $signedContent = "{$id}.{$ts}.{$payload}";
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
        // signature header may contain space-separated "v1,<sig>" tokens
        foreach (explode(' ', $sig) as $part) {
            $val = str_contains($part, ',') ? substr($part, strpos($part, ',') + 1) : $part;
            if (hash_equals($expected, $val)) {
                return true;
            }
        }
        return false;
    }
}
