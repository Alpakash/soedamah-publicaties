<?php
require __DIR__ . '/../app/bootstrap.php';

$book = book_find((int) ($_GET['b'] ?? 0));
if ($book === null || $book['cover_file'] === '') {
    http_response_code(404);
    exit;
}
$path = DATA_DIR . '/uploads/covers/' . $book['cover_file'];
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
if (!isset($mimes[$ext])) {
    http_response_code(404);
    exit;
}

$etag = '"' . md5($book['cover_file'] . '-' . (string) filemtime($path)) . '"';
header('Cache-Control: public, max-age=604800');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mimes[$ext]);
header('Content-Length: ' . (string) filesize($path));
readfile($path);
