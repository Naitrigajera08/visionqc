
<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$csrf = htmlspecialchars(
    csrf_token(),
    ENT_QUOTES,
    'UTF-8'
);