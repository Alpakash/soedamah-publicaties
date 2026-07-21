<?php
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$secret = (string) config('stripe_webhook_secret', '');
if ($secret === '') {
    http_response_code(500);
    log_msg('Webhook aangeroepen maar stripe_webhook_secret is niet ingesteld.');
    echo json_encode(['error' => 'webhook niet geconfigureerd']);
    exit;
}

$payload = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if ($payload === '' || !stripe_verify_signature($payload, $signature, $secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'ongeldige handtekening']);
    exit;
}

$event = json_decode($payload, true);
$type = is_array($event) ? (string) ($event['type'] ?? '') : '';
$session = is_array($event) ? ($event['data']['object'] ?? []) : [];

$findOrders = static function (array $session): array {
    $sessionId = (string) ($session['id'] ?? '');
    $orders = $sessionId !== '' ? orders_find_by_session($sessionId) : [];
    if ($orders === []) {
        foreach (explode(',', (string) ($session['metadata']['order_ids'] ?? '')) as $id) {
            $order = order_find((int) $id);
            if ($order !== null) {
                $orders[] = $order;
            }
        }
    }
    return $orders;
};

switch ($type) {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
        $orders = $findOrders($session);
        if ($orders !== [] && ($session['payment_status'] ?? '') === 'paid') {
            $email = (string) ($session['customer_details']['email'] ?? '');
            orders_mark_paid($orders, $email);
            log_msg('Webhook: bestelling(en) ' . implode(',', array_column($orders, 'id')) . ' betaald (' . $type . ')');
        }
        break;

    case 'checkout.session.async_payment_failed':
        foreach ($findOrders($session) as $order) {
            order_mark_failed($order);
            log_msg('Webhook: betaling mislukt voor bestelling ' . $order['id']);
        }
        break;

    case 'checkout.session.expired':
        // De betaalsessie is verlopen zonder betaling; markeer de bijbehorende
        // openstaande bestelling(en) als 'verlopen' zodat het overzicht schoon blijft.
        foreach ($findOrders($session) as $order) {
            if ($order['status'] === 'pending') {
                $stmt = db()->prepare(
                    "UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'pending'"
                );
                $stmt->execute([$order['id']]);
                log_msg('Webhook: betaalsessie verlopen voor bestelling ' . $order['id']);
            }
        }
        break;
}

echo json_encode(['received' => true]);
