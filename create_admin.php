<?php
// Run this once from localhost, then DELETE this file.
require_once __DIR__ . '/config.php';
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Run locally only.');
}
$email = 'admin@gmail.com';
$password = '123';
$stmt = db()->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role)');
$stmt->execute(['VisionQC Admin', $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
echo "Admin created/updated.\nEmail: $email\nPassword: $password\nChange the password after first login and delete create_admin.php.\n";
