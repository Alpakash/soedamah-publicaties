<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

if (!admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Niet ingelogd.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Ongeldige aanvraag.']);
    exit;
}
$token = (string) ($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Verlopen sessie, herlaad de pagina en probeer opnieuw.']);
    exit;
}

if (empty($_FILES['afbeelding']) || $_FILES['afbeelding']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload mislukt.']);
    exit;
}
$file = $_FILES['afbeelding'];

$ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Alleen JPG, PNG of WebP toegestaan.']);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dit lijkt geen geldige afbeelding te zijn.']);
    exit;
}

$dir = APP_ROOT . '/public/uploads/articles';
ensure_dir($dir);
$name = 'inline-' . bin2hex(random_bytes(6)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    http_response_code(500);
    echo json_encode(['error' => 'Opslaan is mislukt.']);
    exit;
}

echo json_encode(['url' => url('uploads/articles/' . $name)]);
