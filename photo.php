<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$user = auth_require_user();
$photoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($photoId === false || $photoId === null || $photoId < 1) {
    http_response_code(400);
    exit('A valid photo ID is required.');
}

$pdo = database_connection();
$statement = $pdo->prepare(
    'SELECT stored_name, mime_type FROM photo_uploads
     WHERE id = :id AND user_id = :user_id
     LIMIT 1'
);
$statement->execute(['id' => $photoId, 'user_id' => $user['id']]);
$photo = $statement->fetch();
if (!$photo) {
    http_response_code(404);
    exit('Photo not found.');
}

$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mimeType = (string) $photo['mime_type'];
if (!isset($extensions[$mimeType]) || preg_match('/\A[a-f0-9-]{36}\z/D', (string) $photo['stored_name']) !== 1) {
    http_response_code(404);
    exit('Photo not found.');
}

$path = __DIR__ . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR
    . $photo['stored_name'] . '.' . $extensions[$mimeType];
if (!is_file($path)) {
    http_response_code(404);
    exit('Photo file not found.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="inspection-photo.' . $extensions[$mimeType] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
