<?php
declare(strict_types=1);

function auth_is_https(): bool
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => auth_is_https(),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !isset($user['id'], $user['name'], $user['email'], $user['role'])) {
        return null;
    }

    return $user;
}

function auth_require_user(): array
{
    $user = auth_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function auth_require_admin(PDO $pdo): array
{
    $user = auth_user();
    if ($user === null) {
        header('Location: login.php?mode=admin');
        exit;
    }

    $statement = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $user['id']]);
    $role = $statement->fetchColumn();

    if (!is_string($role)) {
        auth_logout();
        header('Location: login.php?mode=admin');
        exit;
    }

    $_SESSION['user']['role'] = $role;
    if (strcasecmp($role, 'Administrator') !== 0) {
        http_response_code(403);
        exit('Administrator access only.');
    }

    $user['role'] = $role;
    return $user;
}

function auth_set_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function auth_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function auth_validate_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function auth_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $initials !== '' ? $initials : 'U';
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => auth_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}
