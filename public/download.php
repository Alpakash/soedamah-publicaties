<?php
require __DIR__ . '/../app/bootstrap.php';

function download_error(string $title, string $message): void
{
    http_response_code(410);
    $pageTitle = $title;
    include APP_ROOT . '/app/templates/header.php';
    echo '<div class="empty-state"><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<p class="muted">Denk je dat dit niet klopt? Beantwoord de e-mail met je downloadlink, dan helpen we je verder.</p>'
        . '<p><a class="btn btn-secondary" href="' . e(url()) . '">Naar de publicaties</a></p></div>';
    include APP_ROOT . '/app/templates/footer.php';
    exit;
}

$token = (string) ($_GET['t'] ?? '');
$format = ($_GET['f'] ?? '') === 'epub' ? 'epub' : 'pdf';

if ($token === '' || !preg_match('/^[a-f0-9]{40}$/', $token)) {
    download_error('Ongeldige link', 'Deze downloadlink is niet geldig.');
}

$order = order_find_by_token($token);
if ($order === null || !in_array($order['status'], ['paid', 'free'], true)) {
    download_error('Ongeldige link', 'Deze downloadlink is niet (meer) geldig.');
}
if ($order['expires_at'] !== null && $order['expires_at'] < now()) {
    download_error('Link verlopen', 'Deze downloadlink is verlopen.');
}

$book = $order['book_id'] ? book_find((int) $order['book_id']) : null;
if ($book === null) {
    download_error('Niet beschikbaar', 'Deze publicatie is niet meer beschikbaar.');
}

$fileColumn = $format === 'epub' ? 'epub_file' : 'pdf_file';
if ($book[$fileColumn] === '') {
    download_error('Niet beschikbaar', 'Dit bestandsformaat is niet beschikbaar voor deze publicatie.');
}
$path = DATA_DIR . '/uploads/books/' . $book[$fileColumn];
if (!is_file($path)) {
    log_msg('Downloadbestand ontbreekt op schijf: ' . $path);
    download_error('Niet beschikbaar', 'Het bestand is tijdelijk niet beschikbaar. Probeer het later opnieuw.');
}

// Downloadteller atomair ophogen; bij bereiken van de limiet blokkeren.
$counterColumn = $format === 'epub' ? 'downloads_epub' : 'downloads_pdf';
$max = max(1, (int) config('download_max', 5));
$stmt = db()->prepare(
    "UPDATE orders SET {$counterColumn} = {$counterColumn} + 1 WHERE id = ? AND {$counterColumn} < ?"
);
$stmt->execute([$order['id'], $max]);
if ($stmt->rowCount() === 0) {
    download_error(
        'Downloadlimiet bereikt',
        'Deze link is al ' . $max . ' keer gebruikt voor dit bestand.'
    );
}

$filename = $book['slug'] . '.' . $format;
$mime = $format === 'epub' ? 'application/epub+zip' : 'application/pdf';

ignore_user_abort(true);
set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');

$fh = fopen($path, 'rb');
if ($fh === false) {
    exit;
}
while (!feof($fh)) {
    echo fread($fh, 65536);
    flush();
}
fclose($fh);
