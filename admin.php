<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$pdo = database_connection();
$currentUser = auth_require_admin($pdo);
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_validate_csrf(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ((string) ($_POST['action'] ?? '') !== 'update_role') {
        http_response_code(400);
        $error = 'Choose a valid admin action.';
    } else {
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $role = (string) ($_POST['role'] ?? '');
        if ($userId === false || $userId === null || $userId < 1 || !in_array($role, ['Administrator', 'Factory manager'], true)) {
            http_response_code(400);
            $error = 'Choose a valid account and role.';
        } elseif ((int) $userId === (int) $currentUser['id'] && $role !== 'Administrator') {
            $error = 'You cannot remove administrator access from your own account.';
        } else {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT id, role FROM users WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $userId]);
            $targetUser = $statement->fetch();

            if (!$targetUser) {
                $pdo->rollBack();
                $error = 'That account could not be found.';
            } elseif ((string) $targetUser['role'] === $role) {
                $pdo->commit();
                $success = 'The account already has that role.';
            } else {
                $isAdmin = strcasecmp((string) $targetUser['role'], 'Administrator') === 0;
                if ($isAdmin && $role !== 'Administrator') {
                    $admins = $pdo->query("SELECT id FROM users WHERE role = 'Administrator' FOR UPDATE")->fetchAll();
                    if (count($admins) <= 1) {
                        $pdo->rollBack();
                        $error = 'You cannot remove the last administrator.';
                    }
                }

                if ($error === '') {
                    $update = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                    $update->execute(['role' => $role, 'id' => $userId]);
                    $pdo->commit();
                    $success = 'Account role updated.';
                }
            }
        }
    }
}

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator'")->fetchColumn();
$users = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC')->fetchAll();
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function admin_icon(string $name, int $size = 18): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'camera' => '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3"/><path d="m7 6 1.5-2h5L15 6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 6-7"/>',
        'box' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8m-8 4h8"/>',
        'wrench' => '<path d="M14.7 6.3a5 5 0 0 0-6.6 6.6L3 18l3 3 5.1-5.1a5 5 0 0 0 6.6-6.6L15 12l-3-3 2.7-2.7Z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.5-1.8-.6a8 8 0 0 1-1.8 1l-.3 1.9h-2.9l-.3-1.9a8 8 0 0 1-1.8-1l-1.8.6-1.4-2.5 1.5-1.2a7 7 0 0 1 0-2l-1.5-1.2 1.4-2.5 1.8.6a8 8 0 0 1 1.8-1l.3-1.9h2.9l.3 1.9a8 8 0 0 1 1.8 1l1.8-.6 1.4 2.5-1.5 1.2a7 7 0 0 1 0 2Z"/>',
        'spark' => '<path d="m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3Z"/>',
    ];
    $path = $paths[$name] ?? $paths['grid'];
    return '<svg aria-hidden="true" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#252521">
    <title>Admin panel · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root { color-scheme: light; --ink:#25231f; --muted:#827d75; --line:#e8e3db; --canvas:#f5f2ed; --surface:#fff; --sidebar:#252521; --sidebar-muted:#b3afa6; --brown:#79563c; --brown-dark:#5d402e; --brown-soft:#f0e7dd; --green:#2b805d; --red:#a83d36; --shadow:0 8px 24px rgba(54,44,33,.045); }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--canvas); color:var(--ink); font:14px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        button,select { font:inherit; }
        button,select { cursor:pointer; }
        .sidebar { position:fixed; inset:0 auto 0 0; z-index:5; display:flex; flex-direction:column; width:248px; padding:24px 16px 18px; background:var(--sidebar); color:#f7f4ee; }
        .brand { display:flex; align-items:center; gap:11px; padding:2px 10px 26px; color:inherit; text-decoration:none; }
        .brand-mark { display:grid; place-items:center; width:38px; height:38px; border-radius:12px; background:var(--brown); color:#fff8ef; }
        .brand-name { font-size:15px; font-weight:750; letter-spacing:-.35px; }
        .brand-caption { display:block; margin-top:2px; color:#a7a297; font-size:11px; letter-spacing:.08em; text-transform:uppercase; }
        .nav-label { padding:0 11px 8px; color:#89867e; font-size:10px; font-weight:700; letter-spacing:.11em; text-transform:uppercase; }
        .nav { display:grid; gap:4px; }
        .nav-link { display:flex; align-items:center; gap:12px; min-height:42px; padding:0 11px; border:1px solid transparent; border-radius:9px; color:var(--sidebar-muted); text-decoration:none; font-size:13px; transition:.18s ease; }
        .nav-link:hover { color:#fff; background:rgba(255,255,255,.06); }
        .nav-link.active { border-color:rgba(214,181,150,.19); background:rgba(139,100,68,.34); color:#fff8ef; }
        .nav-link.active svg { color:#e8c9a8; }
        .nav-link:focus-visible,.avatar:focus-visible,button:focus-visible,select:focus-visible { outline:2px solid #d6b596; outline-offset:3px; }
        .sidebar-bottom { margin-top:auto; }
        .plant-card { margin:0 2px 16px; padding:13px; border:1px solid rgba(255,255,255,.1); border-radius:11px; background:rgba(255,255,255,.045); }
        .plant-row { display:flex; align-items:center; justify-content:space-between; font-size:12px; font-weight:650; }
        .plant-sub { padding-top:4px; color:#a7a297; font-size:11px; }
        .plant-status { display:flex; align-items:center; gap:8px; margin-top:12px; color:#b8d8bf; font-size:11px; }
        .status-dot { width:7px; height:7px; border-radius:50%; background:#54b77c; box-shadow:0 0 0 3px rgba(84,183,124,.12); }
        .user-mini { display:flex; align-items:center; gap:10px; padding:12px 8px 0; border-top:1px solid rgba(255,255,255,.1); color:inherit; text-decoration:none; }
        .avatar { display:grid; place-items:center; width:36px; height:36px; flex:0 0 auto; border-radius:50%; background:#d9c3ae; color:#533c2e; font-size:12px; font-weight:750; text-decoration:none; }
        .user-name { font-size:12px; font-weight:650; }
        .user-role { margin-top:2px; color:#a7a297; font-size:11px; }
        .main { min-height:100vh; margin-left:248px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; min-height:72px; padding:0 34px; border-bottom:1px solid var(--brown-dark); background:var(--brown); color:#fff8ef; }
        .breadcrumb { color:#f4e8dc; font-size:12px; }
        .breadcrumb a { color:inherit; text-decoration:none; }
        .breadcrumb a:hover { color:#fff; }
        .breadcrumb strong { color:#fff8ef; font-weight:650; }
        .top-actions { display:flex; align-items:center; gap:14px; }
        .top-date { color:#f4e8dc; font-size:12px; }
        .content { max-width:1480px; margin:0 auto; padding:30px 34px 44px; }
        .page-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:25px; }
        .eyebrow { margin-bottom:5px; color:var(--brown); font-size:10px; font-weight:750; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:0; font-size:clamp(25px,3vw,31px); letter-spacing:-.9px; }
        .subtitle { margin:6px 0 0; color:var(--muted); font-size:13px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:0 13px; border:1px solid var(--brown); border-radius:8px; background:var(--brown); color:#fff; font-size:12px; font-weight:650; text-decoration:none; }
        .button:hover { border-color:var(--brown-dark); background:var(--brown-dark); color:#fff; }
        .metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:17px; }
        .metric { min-height:112px; padding:17px 18px; border:1px solid var(--line); border-radius:11px; background:var(--surface); box-shadow:var(--shadow); }
        .metric-icon { float:right; display:grid; place-items:center; width:33px; height:33px; border-radius:9px; background:var(--brown-soft); color:var(--brown); }
        .metric-label { color:#777168; font-size:11px; font-weight:600; }
        .metric-value { margin-top:9px; font-size:25px; font-weight:750; letter-spacing:-.7px; }
        .metric-note { margin-top:3px; color:#938d84; font-size:11px; }
        .section-heading { display:flex; align-items:end; justify-content:space-between; gap:14px; margin:26px 0 12px; }
        .section-heading h2 { margin:0; font-size:15px; letter-spacing:-.2px; }
        .section-heading p { margin:4px 0 0; color:var(--muted); font-size:12px; }
        .quick-actions { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:25px; }
        .quick-action { display:flex; align-items:flex-start; gap:12px; min-height:112px; padding:16px; border:1px solid var(--line); border-radius:11px; background:var(--surface); box-shadow:var(--shadow); color:inherit; text-decoration:none; transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease; }
        .quick-action:hover { transform:translateY(-2px); border-color:#d8c4b1; box-shadow:0 10px 24px rgba(54,44,33,.08); }
        .quick-action:focus-visible { outline:2px solid var(--brown); outline-offset:3px; }
        .quick-action-icon { display:grid; place-items:center; width:36px; height:36px; flex:0 0 auto; border-radius:10px; background:var(--brown-soft); color:var(--brown); }
        .quick-action-title { display:block; margin:1px 0 4px; font-size:12px; font-weight:700; }
        .quick-action-description { display:block; color:var(--muted); font-size:11px; line-height:1.5; }
        .panel-header { flex-wrap:wrap; }
        .account-tools { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .account-filter { min-height:35px; padding:0 10px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); font-size:11px; }
        .account-search { width:min(230px,100%); }
        .account-results { padding:0 19px 12px; color:var(--muted); font-size:11px; }
        .filter-empty td { padding:22px; color:var(--muted); text-align:center; }
        .visually-hidden { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .panel { border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:var(--shadow); }
        .panel-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 19px 15px; }
        .panel-title { margin:0; font-size:14px; font-weight:700; }
        .panel-kicker { margin-top:3px; color:#918b82; font-size:11px; }
        .notice { margin:0 19px 14px; padding:10px 12px; border-radius:8px; font-size:12px; }
        .notice.error { background:#fff0ee; color:var(--red); }
        .notice.success { background:#edf5ef; color:var(--green); }
        .table-wrap { overflow-x:auto; border-top:1px solid var(--line); }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th { padding:11px 19px; background:#faf9f7; color:#8b857c; font-size:10px; font-weight:700; letter-spacing:.055em; text-transform:uppercase; white-space:nowrap; }
        td { padding:12px 19px; border-top:1px solid #f0ede8; color:#5f5a52; font-size:12px; white-space:nowrap; }
        tbody tr:hover { background:#fdfcfb; }
        .account { display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:650; }
        .account-avatar { display:grid; place-items:center; width:34px; height:34px; border-radius:10px; background:#f0e7dd; color:var(--brown); font-size:11px; font-weight:750; }
        .email { color:#777168; }
        .role-tag { display:inline-flex; padding:5px 9px; border-radius:20px; background:#f5f2ed; color:#716b62; font-size:10px; font-weight:650; }
        .role-tag.admin { background:#f0e7dd; color:var(--brown-dark); }
        .role-form { display:flex; align-items:center; gap:7px; }
        .role-select { min-height:33px; padding:0 8px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); font-size:11px; }
        .save-button { min-height:33px; padding:0 10px; border:1px solid var(--brown); border-radius:7px; background:var(--brown); color:#fff; font-size:11px; font-weight:650; }
        .save-button:hover { background:var(--brown-dark); }
        .account-actions { display:flex; align-items:center; gap:6px; }
        .action-button { display:inline-flex; align-items:center; justify-content:center; min-height:32px; padding:0 9px; border:1px solid var(--line); border-radius:7px; background:#fff; color:#514b42; font-size:11px; font-weight:650; text-decoration:none; cursor:pointer; }
        .action-button:hover { border-color:#d8c4b1; background:#faf7f4; }
        .action-button.danger { border-color:#e6c8c5; color:var(--red); }
        .action-button.danger:hover { background:#fff0ee; }
        .account-actions form { margin:0; }
        .self-label { color:#938d84; font-size:11px; }
        .empty { padding:30px; color:var(--muted); text-align:center; }
        .security-note { margin-top:16px; padding:14px 16px; border:1px solid #e9ddce; border-radius:10px; background:#f5eee6; color:#6e5a46; font-size:12px; }
        @media(max-width:1000px) {
            .quick-actions { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .sidebar { width:72px; padding:20px 10px; align-items:center; }
            .brand { padding:0 0 25px; }
            .brand-text,.nav-label,.nav-link span,.plant-card,.user-info { display:none; }
            .nav { width:100%; }
            .nav-link { justify-content:center; padding:0; }
            .user-mini { justify-content:center; padding:12px 0 0; }
            .main { margin-left:72px; }
            .topbar { padding:0 22px; }
            .content { padding:25px 22px 35px; }
        }
        @media(max-width:640px) {
            .quick-actions { grid-template-columns:1fr; gap:8px; }
            .quick-action { min-height:0; }
            .account-search { width:100%; }
            .account-tools { width:100%; }
            .sidebar { width:58px; padding:15px 7px; }
            .brand-mark { width:34px; height:34px; }
            .main { margin-left:58px; }
            .topbar { min-height:60px; padding:0 13px; }
            .content { padding:19px 13px 28px; }
            .metrics { grid-template-columns:1fr; gap:8px; }
            .metric { min-height:95px; padding:13px 15px; }
            .page-heading { align-items:flex-start; }
            .panel-header { padding-right:13px; padding-left:13px; }
            .notice { margin-right:13px; margin-left:13px; }
            th,td { padding-right:13px; padding-left:13px; }
        }
    </style>
</head>
<body>
<aside class="sidebar" aria-label="Admin navigation">
    <a class="brand" href="admin.php">
        <span class="brand-mark"><?= admin_icon('spark', 21) ?></span>
        <span class="brand-text"><span class="brand-name">VisionQC</span><span class="brand-caption">Administration</span></span>
    </a>
    <div class="nav-label">Administration</div>
    <nav class="nav">
        <a class="nav-link active" href="admin.php" aria-current="page"><?= admin_icon('grid') ?><span>Admin panel</span></a>
        <a class="nav-link" href="quality_control_dashboard.php"><?= admin_icon('grid') ?><span>Quality dashboard</span></a>
        <a class="nav-link" href="records.php"><?= admin_icon('settings') ?><span>Record management</span></a>
        <a class="nav-link" href="workspace.php?view=live-monitoring"><span>Live monitoring</span></a>
        <a class="nav-link" href="workspace.php?view=defect-analysis"><span>Defect analysis</span></a>
        <a class="nav-link" href="workspace.php?view=product-inspection"><span>Product inspection</span></a>
        <a class="nav-link" href="workspace.php?view=alerts"><span>Alerts</span></a>
        <a class="nav-link" href="workspace.php?view=reports"><span>Reports</span></a>
        <a class="nav-link" href="workspace.php?view=machines"><span>Machines &amp; health</span></a>
        <a class="nav-link" href="operators.php"><?= admin_icon('users') ?><span>Operators</span></a>
    </nav>
    <div class="sidebar-bottom">
        <nav class="nav"><a class="nav-link" href="profile.php"><?= admin_icon('settings') ?><span>Profile &amp; settings</span></a></nav>
        <div class="plant-card">
            <div class="plant-row"><span>Plant 01</span><span aria-hidden="true">⌄</span></div>
            <div class="plant-sub">Assembly &amp; finishing</div>
            <div class="plant-status"><span class="status-dot"></span> All systems operational</div>
        </div>
        <a class="user-mini" href="profile.php">
            <span class="avatar"><?= $escape(auth_initials((string) $currentUser['name'])) ?></span>
            <span class="user-info"><span class="user-name"><?= $escape((string) $currentUser['name']) ?></span><span class="user-role">Administrator</span></span>
        </a>
    </div>
</aside>
<main class="main">
    <header class="topbar">
        <div class="breadcrumb"><a href="quality_control_dashboard.php">Factory</a> / <strong>Admin panel</strong></div>
        <div class="top-actions"><span class="top-date"><?= $escape(date('D, M j, Y')) ?></span><a class="avatar" href="profile.php" aria-label="Open administrator profile"><?= $escape(auth_initials((string) $currentUser['name'])) ?></a></div>
    </header>
    <div class="content">
        <section class="page-heading">
            <div>
                <div class="eyebrow">System administration</div>
                <h1>Admin panel</h1>
                <p class="subtitle">Manage VisionQC accounts, access, and operational records.</p>
            </div>
            <a class="button button-primary" href="records.php">Edit or delete records</a>
        </section>

        <section class="metrics" aria-label="Account summary">
            <article class="metric">
                <span class="metric-icon"><?= admin_icon('users', 17) ?></span>
                <div class="metric-label">Registered accounts</div>
                <div class="metric-value"><?= number_format($userCount) ?></div>
                <div class="metric-note">All VisionQC users</div>
            </article>
            <article class="metric">
                <span class="metric-icon"><?= admin_icon('settings', 17) ?></span>
                <div class="metric-label">Administrators</div>
                <div class="metric-value"><?= number_format($adminCount) ?></div>
                <div class="metric-note">Accounts with admin access</div>
            </article>
            <article class="metric">
                <span class="metric-icon"><?= admin_icon('spark', 17) ?></span>
                <div class="metric-label">Signed in as</div>
                <div class="metric-value" style="font-size:19px"><?= $escape((string) $currentUser['name']) ?></div>
                <div class="metric-note">Administrator account</div>
            </article>
        </section>

        <section aria-labelledby="quick-actions-title">
            <div class="section-heading">
                <div>
                    <h2 id="quick-actions-title">Quick access</h2>
                    <p>Go straight to the tools you use to run the factory.</p>
                </div>
            </div>
            <div class="quick-actions">
                <a class="quick-action" href="quality_control_dashboard.php">
                    <span class="quick-action-icon"><?= admin_icon('chart', 18) ?></span>
                    <span><span class="quick-action-title">Quality dashboard</span><span class="quick-action-description">Review production performance and quality trends.</span></span>
                </a>
                <a class="quick-action" href="records.php">
                    <span class="quick-action-icon"><?= admin_icon('file', 18) ?></span>
                    <span><span class="quick-action-title">Manage records</span><span class="quick-action-description">Edit or delete operational records and accounts.</span></span>
                </a>
                <a class="quick-action" href="workspace.php?view=live-monitoring">
                    <span class="quick-action-icon"><?= admin_icon('camera', 18) ?></span>
                    <span><span class="quick-action-title">Live monitoring</span><span class="quick-action-description">Open camera views and product inspection records.</span></span>
                </a>
                <a class="quick-action" href="operators.php">
                    <span class="quick-action-icon"><?= admin_icon('users', 18) ?></span>
                    <span><span class="quick-action-title">Operators</span><span class="quick-action-description">Review the team and manage operator details.</span></span>
                </a>
            </div>
        </section>

        <section class="panel" aria-labelledby="accounts-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="accounts-title">Account management</h2>
                    <div class="panel-kicker">Only administrators can grant or remove admin access.</div>
                </div>
                <div class="account-tools">
                    <label class="visually-hidden" for="account-search">Search accounts</label>
                    <input class="account-filter account-search" id="account-search" type="search" placeholder="Search name or email">
                    <label class="visually-hidden" for="account-role-filter">Filter by role</label>
                    <select class="account-filter" id="account-role-filter">
                        <option value="">All roles</option>
                        <option value="Administrator">Administrators</option>
                        <option value="Factory manager">Factory managers</option>
                    </select>
                </div>
            </div>
            <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="notice success" role="status"><?= $escape($success) ?></div><?php endif; ?>
            <div class="account-results" id="account-results" aria-live="polite"></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Account</th><th scope="col">Email</th><th scope="col">Current role</th><th scope="col">Joined</th><th scope="col">Manage access</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $account): ?>
                        <?php $isAdmin = strcasecmp((string) $account['role'], 'Administrator') === 0; ?>
                        <tr class="account-row" data-account-role="<?= $escape((string) $account['role']) ?>">
                            <td><span class="account"><span class="account-avatar"><?= $escape(auth_initials((string) $account['name'])) ?></span><?= $escape((string) $account['name']) ?><?= (int) $account['id'] === (int) $currentUser['id'] ? ' (you)' : '' ?></span></td>
                            <td class="email"><?= $escape((string) $account['email']) ?></td>
                            <td><span class="role-tag<?= $isAdmin ? ' admin' : '' ?>"><?= $escape((string) $account['role']) ?></span></td>
                            <td><?= $escape(date('M j, Y', strtotime((string) $account['created_at']))) ?></td>
                            <td>
                                <?php if ((int) $account['id'] === (int) $currentUser['id']): ?>
                                    <span class="self-label">Your admin access is protected</span>
                                <?php else: ?>
                                    <form class="role-form" method="post" action="admin.php">
                                        <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?= $escape((string) $account['id']) ?>">
                                        <label style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0" for="role-<?= $escape((string) $account['id']) ?>">Role for <?= $escape((string) $account['name']) ?></label>
                                        <select class="role-select" id="role-<?= $escape((string) $account['id']) ?>" name="role">
                                            <option value="Factory manager"<?= !$isAdmin ? ' selected' : '' ?>>Factory manager</option>
                                            <option value="Administrator"<?= $isAdmin ? ' selected' : '' ?>>Administrator</option>
                                        </select>
                                        <button class="save-button" type="submit">Save</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="account-actions">
                                    <a class="action-button" href="records.php?type=accounts&amp;edit=<?= rawurlencode((string) $account['id']) ?>">Edit</a>
                                    <?php if ((int) $account['id'] !== (int) $currentUser['id']): ?>
                                        <form method="post" action="records.php?type=accounts" onsubmit="return confirm('Delete this account and all of its uploaded photo records? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                                            <input type="hidden" name="type" value="accounts">
                                            <input type="hidden" name="record_id" value="<?= $escape((string) $account['id']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="action-button danger" type="submit">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="self-label">Cannot delete yourself</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="filter-empty" id="filter-empty" hidden><td colspan="5">No accounts match your search.</td></tr>
                    </tbody>
                </table>
                <?php if ($users === []): ?><div class="empty">No accounts found.</div><?php endif; ?>
            </div>
        </section>
        <p class="security-note">For security, you cannot remove administrator access from your own account or demote the last remaining administrator.</p>
    </div>
    <?php require __DIR__ . '/site_footer.php'; ?>
</main>
<script>
    (() => {
        const search = document.getElementById('account-search');
        const roleFilter = document.getElementById('account-role-filter');
        const resultLabel = document.getElementById('account-results');
        const emptyRow = document.getElementById('filter-empty');
        const rows = Array.from(document.querySelectorAll('.account-row'));
        const total = rows.length;

        const filterAccounts = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const selectedRole = roleFilter.value;
            let visible = 0;

            rows.forEach((row) => {
                const matchesQuery = row.textContent.toLocaleLowerCase().includes(query);
                const matchesRole = selectedRole === '' || row.dataset.accountRole === selectedRole;
                const show = matchesQuery && matchesRole;
                row.hidden = !show;
                if (show) visible += 1;
            });

            emptyRow.hidden = visible !== 0 || total === 0;
            resultLabel.textContent = `Showing ${visible} of ${total} accounts`;
        };

        search.addEventListener('input', filterAccounts);
        roleFilter.addEventListener('change', filterAccounts);
        filterAccounts();
    })();
</script>
</body>
</html>
