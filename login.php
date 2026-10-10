<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$requestedMode = (string) ($_GET['mode'] ?? $_POST['mode'] ?? 'login');
$mode = in_array($requestedMode, ['signup', 'admin'], true) ? $requestedMode : 'login';
$signedInUser = auth_user();
if ($signedInUser !== null) {
    $destination = $mode === 'admin' && strcasecmp((string) $signedInUser['role'], 'Administrator') === 0
        ? 'admin.php'
        : 'quality_control_dashboard.php';
    header('Location: ' . $destination);
    exit;
}

$error = '';
$name = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_validate_csrf(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $error = 'Enter a valid email address.';
        } elseif ($mode === 'signup' && ($name === '' || mb_strlen($name, 'UTF-8') > 60)) {
            $error = 'Enter your name using no more than 60 characters.';
        } elseif ($password === '' || ($mode === 'signup' && strlen($password) < 10)) {
            $error = $mode === 'signup'
                ? 'Choose a password with at least 10 characters.'
                : 'Enter your password.';
        } else {
            $pdo = database_connection();
            if ($mode === 'signup') {
                $statement = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
                );
                try {
                    $statement->execute([
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    auth_set_user([
                        'id' => (int) $pdo->lastInsertId(),
                        'name' => $name,
                        'email' => $email,
                        'role' => 'Factory manager',
                    ]);
                    $_SESSION['show_welcome_popup'] = true;
                    header('Location: quality_control_dashboard.php');
                    exit;
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        $error = 'An account with that email address already exists.';
                    } else {
                        throw $exception;
                    }
                }
            } else {
                $statement = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1'
                );
                $statement->execute(['email' => $email]);
                $user = $statement->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = 'Email or password is incorrect.';
                } elseif ($mode === 'admin' && strcasecmp((string) $user['role'], 'Administrator') !== 0) {
                    $error = 'This sign-in is restricted to administrators.';
                } else {
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $rehash = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                        $rehash->execute([
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'id' => $user['id'],
                        ]);
                    }
                    auth_set_user($user);
                    if ($mode !== 'admin') {
                        $_SESSION['show_welcome_popup'] = true;
                    }
                    header('Location: ' . ($mode === 'admin' ? 'admin.php' : 'quality_control_dashboard.php'));
                    exit;
                }
            }
        }
    }
}

$isSignup = $mode === 'signup';
$isAdminLogin = $mode === 'admin';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isSignup ? 'Create account' : ($isAdminLogin ? 'Administrator sign in' : 'Sign in') ?> · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root { color-scheme: light; --ink:#25231f; --muted:#827d75; --line:#e8e3db; --canvas:#f5f2ed; --surface:#fff; --brown:#79563c; --brown-dark:#5d402e; --red:#a83d36; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; justify-content:center; padding:24px 0 0; background:var(--canvas); color:var(--ink); font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .site-header { width:100%; margin-top:-24px; margin-bottom:auto; }
        .card { width:min(100%,440px); padding:34px; border:1px solid var(--line); border-radius:16px; background:var(--surface); box-shadow:0 16px 48px rgba(54,44,33,.08); }
        .brand { margin-bottom:28px; color:var(--brown); font-size:12px; font-weight:750; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:0; font-size:27px; letter-spacing:-.7px; }
        .intro { margin:7px 0 24px; color:var(--muted); }
        label { display:block; margin:16px 0 6px; font-size:12px; font-weight:650; }
        input { width:100%; min-height:43px; padding:10px 12px; border:1px solid var(--line); border-radius:8px; color:var(--ink); font:inherit; }
        input:focus { outline:2px solid rgba(121,86,60,.2); border-color:var(--brown); }
        button { width:100%; min-height:43px; margin-top:22px; border:0; border-radius:8px; background:var(--brown); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        button:hover { background:var(--brown-dark); }
        .error { margin:16px 0; padding:10px 12px; border-radius:8px; background:#fff0ee; color:var(--red); }
        .switch { margin:20px 0 0; color:var(--muted); text-align:center; }
        a { color:var(--brown); font-weight:650; }
        @media (max-width:480px) { body { padding-top:0; } .site-header { margin-top:0; } .card { padding:26px 21px; } }
    </style>
</head>
<body>
<header class="site-header">
    <a class="site-header-brand" href="login.php">VisionQC</a>
    <nav class="site-header-nav" aria-label="Main navigation">
        <a href="login.php">Sign in</a>
        <a href="login.php?mode=signup">Create account</a>
        <a href="login.php?mode=admin">Administrator</a>
    </nav>
</header>
<main class="card">
    <div class="brand">VisionQC · <?= $isAdminLogin ? 'Administrator access' : 'Quality intelligence' ?></div>
    <h1><?= $isSignup ? 'Create your account' : ($isAdminLogin ? 'Administrator sign in' : 'Welcome back') ?></h1>
    <p class="intro"><?= $isSignup ? 'Set up your account to access the quality dashboard.' : ($isAdminLogin ? 'Sign in with an administrator account to manage VisionQC.' : 'Sign in to continue to your quality dashboard.') ?></p>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
    <form method="post" action="login.php?mode=<?= $isSignup ? 'signup' : ($isAdminLogin ? 'admin' : 'login') ?>">
        <input type="hidden" name="mode" value="<?= $isSignup ? 'signup' : ($isAdminLogin ? 'admin' : 'login') ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
        <?php if ($isSignup): ?>
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" maxlength="60" autocomplete="name" value="<?= $escape($name) ?>" required>
        <?php endif; ?>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" maxlength="120" autocomplete="email" value="<?= $escape($email) ?>" required>
        <label for="password">Password<?= $isSignup ? ' (10 characters minimum)' : '' ?></label>
        <input id="password" name="password" type="password" autocomplete="<?= $isSignup ? 'new-password' : 'current-password' ?>" <?= $isSignup ? 'minlength="10"' : '' ?> required>
        <button type="submit"><?= $isSignup ? 'Create account' : 'Sign in' ?></button>
    </form>
    <p class="switch">
        <?php if ($isAdminLogin): ?>
            <a href="login.php">Back to regular sign in</a>
        <?php else: ?>
            <?= $isSignup ? 'Already have an account?' : 'New to VisionQC?' ?>
            <a href="login.php?mode=<?= $isSignup ? 'login' : 'signup' ?>"><?= $isSignup ? 'Sign in' : 'Create an account' ?></a>
            <?php if (!$isSignup): ?> · <a href="login.php?mode=admin">Administrator sign in</a><?php endif; ?>
        <?php endif; ?>
    </p>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
