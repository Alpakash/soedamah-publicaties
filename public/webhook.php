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

$findOrder = static function (array $session): ?array {
    $orderId = (int) ($session['metadata']['order_id'] ?? 0);
    if ($orderId > 0) {
        $order = order_find($orderId);
        if ($order !== null) {
            return $order;
        }
    }
    $sessionId = (string) ($session['id'] ?? '');
    return $sessionId !== '' ? order_find_by_session($sessionId) : null;
};

switch ($type) {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
        $order = $findOrder($session);
        if ($order === null) {
            // Vangnet: bestelling reconstrueren op basis van metadata.
            $bookId = (int) ($session['metadata']['book_id'] ?? 0);
            $book = $bookId > 0 ? book_find($bookId) : null;
            if ($book !== null && !empty($session['id'])) {
                $order = order_create($book, 'pending', '', (string) $session['id']);
            }
        }
        if ($order !== null && ($session['payment_status'] ?? '') === 'paid') {
            $email = (string) ($session['customer_details']['email'] ?? '');
            order_mark_paid($order, $email);
            log_msg('Webhook: bestelling ' . $order['id'] . ' betaald (' . $type . ')');
        }
        break;

    case 'checkout.session.async_payment_failed':
        $order = $findOrder($session);
        if ($order !== null) {
            order_mark_failed($order);
            log_msg('Webhook: betaling mislukt voor bestelling ' . $order['id']);
        }
        break;
}

echo json_encode(['received' => true]);
