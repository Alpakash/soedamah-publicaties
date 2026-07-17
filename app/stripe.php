<?php
declare(strict_types=1);

class StripeError extends RuntimeException
{
}

/**
 * Minimale Stripe-client op basis van curl, zodat er geen Composer of SDK
 * nodig is op de (shared hosting) server.
 */
function stripe_request(string $method, string $path, array $params = []): array
{
    $key = (string) config('stripe_secret_key', '');
    if ($key === '') {
        throw new StripeError('Stripe is nog niet geconfigureerd: vul stripe_secret_key in app/config.php in.');
    }

    $ch = curl_init('https://api.stripe.com' . $path);
    if ($ch === false) {
        throw new StripeError('Kon geen verbinding voorbereiden.');
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $key . ':',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        log_msg('Stripe curl-fout: ' . $error);
        throw new StripeError('Kon Stripe niet bereiken. Probeer het over een moment opnieuw.');
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        log_msg('Stripe: onleesbaar antwoord (HTTP ' . $status . ')');
        throw new StripeError('Onverwacht antwoord van Stripe (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        $message = $data['error']['message'] ?? ('HTTP ' . $status);
        log_msg('Stripe API-fout: ' . $message);
        throw new StripeError($message);
    }
    return $data;
}

/**
 * Controleert de handtekening van een Stripe-webhook (Stripe-Signature header).
 */
function stripe_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $timestamp = 0;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = (int) $kv[1];
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }
    if ($timestamp === 0 || $signatures === []) {
        return false;
    }
    if (abs(time() - $timestamp) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}
