<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$user = auth_require_user();
$pdo = database_connection();
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_validate_csrf(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'logout') {
            auth_logout();
            header('Location: login.php');
            exit;
        }

        if ($action === 'update_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 60) {
                $error = 'Enter your name using no more than 60 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
                $error = 'Enter a valid email address.';
            } else {
                $statement = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
                try {
                    $statement->execute(['name' => $name, 'email' => $email, 'id' => $user['id']]);
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $success = 'Your profile has been updated.';
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        $error = 'That email address is already in use.';
                    } else {
                        throw $exception;
                    }
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            if (strlen($newPassword) < 10) {
                $error = 'Choose a new password with at least 10 characters.';
            } else {
                $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
                $statement->execute(['id' => $user['id']]);
                $passwordHash = $statement->fetchColumn();
                if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
                    $error = 'Your current password is incorrect.';
                } else {
                    $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $update->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
                    session_regenerate_id(true);
                    $success = 'Your password has been changed.';
                }
            }
        } else {
            http_response_code(400);
            $error = 'Choose a valid profile action.';
        }
    }
}

$statement = $pdo->prepare('SELECT name, email, role, created_at FROM users WHERE id = :id');
$statement->execute(['id' => $user['id']]);
$profile = $statement->fetch();
if (!$profile) {
    auth_logout();
    header('Location: login.php');
    exit;
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My profile · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root { color-scheme:light; --ink:#25231f; --muted:#827d75; --line:#e8e3db; --canvas:#f5f2ed; --surface:#fff; --brown:#79563c; --brown-dark:#5d402e; --red:#a83d36; --green:#286b4e; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--canvas); color:var(--ink); font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        header { display:flex; align-items:center; justify-content:space-between; min-height:72px; padding:0 max(24px,calc((100vw - 1080px)/2)); border-bottom:1px solid var(--line); background:#e9e4d7; }
        .brand { color:var(--brown); font-size:13px; font-weight:750; letter-spacing:.08em; text-transform:uppercase; }
        header a, .back { color:var(--brown); font-weight:650; text-decoration:none; }
        main { width:min(100% - 36px,900px); margin:38px auto; }
        .back { display:inline-block; margin-bottom:18px; font-size:13px; }
        h1 { margin:0; font-size:29px; letter-spacing:-.8px; }
        .subtitle { margin:5px 0 24px; color:var(--muted); }
        .grid { display:grid; grid-template-columns:1.25fr .75fr; gap:16px; }
        .card { padding:23px; border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:0 8px 24px rgba(54,44,33,.045); }
        h2 { margin:0 0 17px; font-size:16px; }
        label { display:block; margin:14px 0 6px; font-size:12px; font-weight:650; }
        input { width:100%; min-height:41px; padding:9px 11px; border:1px solid var(--line); border-radius:8px; color:var(--ink); font:inherit; }
        input:focus { outline:2px solid rgba(121,86,60,.2); border-color:var(--brown); }
        button { min-height:39px; padding:0 14px; border:1px solid var(--brown); border-radius:8px; background:var(--brown); color:#fff; font:inherit; font-size:12px; font-weight:700; cursor:pointer; }
        button:hover { background:var(--brown-dark); }
        .card button { margin-top:17px; }
        .account-list { margin:0; }
        .account-list div { padding:11px 0; border-bottom:1px solid var(--line); }
        .account-list div:last-child { border:0; }
        dt { color:var(--muted); font-size:11px; }
        dd { margin:3px 0 0; font-weight:600; overflow-wrap:anywhere; }
        .message { margin:0 0 17px; padding:10px 12px; border-radius:8px; }
        .error { background:#fff0ee; color:var(--red); }
        .success { background:#eaf5ee; color:var(--green); }
        .logout { margin-top:16px; }
        .logout button { border-color:var(--line); background:var(--surface); color:var(--ink); }
        .logout button:hover { border-color:var(--brown); color:var(--brown); }
        @media(max-width:680px) { header { padding:0 18px; } main { margin:26px auto; } .grid { grid-template-columns:1fr; } .card { padding:19px; } }
    </style>
</head>
<body>
<header>
    <div class="brand">VisionQC</div>
    <a href="quality_control_dashboard.php">Quality dashboard</a>
</header>
<main>
    <a class="back" href="quality_control_dashboard.php">← Back to dashboard</a>
    <h1>My profile</h1>
    <p class="subtitle">Manage your account details, password, and sign-in session.</p>
    <?php if ($error !== ''): ?><div class="message error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="message success" role="status"><?= $escape($success) ?></div><?php endif; ?>
    <div class="grid">
        <section class="card">
            <h2>Personal information</h2>
            <form method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" maxlength="60" autocomplete="name" value="<?= $escape((string) $profile['name']) ?>" required>
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="120" autocomplete="email" value="<?= $escape((string) $profile['email']) ?>" required>
                <button type="submit">Save changes</button>
            </form>
        </section>
        <section class="card">
            <h2>Account details</h2>
            <dl class="account-list">
                <div><dt>Role</dt><dd><?= $escape((string) $profile['role']) ?></dd></div>
                <div><dt>Member since</dt><dd><?= $escape(date('M j, Y', strtotime((string) $profile['created_at']))) ?></dd></div>
                <div><dt>Signed in as</dt><dd><?= $escape((string) $profile['email']) ?></dd></div>
            </dl>
        </section>
        <section class="card">
            <h2>Change password</h2>
            <form method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <label for="current-password">Current password</label>
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                <label for="new-password">New password (10 characters minimum)</label>
                <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="10" required>
                <button type="submit">Update password</button>
            </form>
        </section>
        <section class="card">
            <h2>Sign-in session</h2>
            <p class="subtitle">Sign out of VisionQC on this browser.</p>
            <form class="logout" method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Sign out</button>
            </form>
        </section>
    </div>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
