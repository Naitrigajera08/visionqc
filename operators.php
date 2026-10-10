<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$currentUser = auth_require_user();
$pdo = database_connection();
$statement = $pdo->query(
    'SELECT o.id, o.name, o.shift, p.accuracy
     FROM operators o
     LEFT JOIN operator_performance p
       ON p.operator_id = o.id AND p.perf_date = CURDATE()
     ORDER BY FIELD(o.shift, "Morning", "Afternoon", "Night"), o.name'
);
$operators = $statement->fetchAll();

$shifts = ['Morning', 'Afternoon', 'Night'];
$shiftSummary = [];
foreach ($shifts as $shift) {
    $shiftSummary[$shift] = ['count' => 0, 'scores' => []];
}

$scores = [];
foreach ($operators as $operator) {
    $shift = (string) $operator['shift'];
    if (!isset($shiftSummary[$shift])) {
        continue;
    }
    $shiftSummary[$shift]['count']++;
    if ($operator['accuracy'] !== null) {
        $score = (float) $operator['accuracy'];
        $scores[] = $score;
        $shiftSummary[$shift]['scores'][] = $score;
    }
}

$averageAccuracy = $scores !== [] ? array_sum($scores) / count($scores) : null;
$bestOperator = null;
foreach ($operators as $operator) {
    if ($operator['accuracy'] !== null && (
        $bestOperator === null || (float) $operator['accuracy'] > (float) $bestOperator['accuracy']
    )) {
        $bestOperator = $operator;
    }
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function operators_icon(string $name, int $size = 18): string
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
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5m-5 5V3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
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
    <title>Operators · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root {
            color-scheme: light;
            --ink: #25231f;
            --muted: #827d75;
            --line: #e8e3db;
            --canvas: #f5f2ed;
            --surface: #fff;
            --sidebar: #252521;
            --sidebar-muted: #b3afa6;
            --brown: #79563c;
            --brown-dark: #5d402e;
            --brown-soft: #f0e7dd;
            --green: #2b805d;
            --amber: #bd8429;
            --shadow: 0 8px 24px rgba(54, 44, 33, .045);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--canvas); color: var(--ink); font: 14px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        button, input, select { font: inherit; }
        button, select { cursor: pointer; }
        .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 5; display: flex; flex-direction: column; width: 248px; padding: 24px 16px 18px; background: var(--sidebar); color: #f7f4ee; }
        .brand { display: flex; align-items: center; gap: 11px; padding: 2px 10px 26px; }
        .brand-mark { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; background: var(--brown); color: #fff8ef; }
        .brand-name { font-size: 15px; font-weight: 750; letter-spacing: -.35px; }
        .brand-caption { margin-top: 2px; color: #a7a297; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
        .nav-label { padding: 0 11px 8px; color: #89867e; font-size: 10px; font-weight: 700; letter-spacing: .11em; text-transform: uppercase; }
        .nav { display: grid; gap: 4px; }
        .nav-link { display: flex; align-items: center; gap: 12px; min-height: 42px; padding: 0 11px; border: 1px solid transparent; border-radius: 9px; color: var(--sidebar-muted); text-decoration: none; font-size: 13px; transition: .18s ease; }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
        .nav-link.active { border-color: rgba(214,181,150,.19); background: rgba(139,100,68,.34); color: #fff8ef; }
        .nav-link.active svg { color: #e8c9a8; }
        .nav-link:focus-visible, .avatar:focus-visible { outline: 2px solid #d6b596; outline-offset: 3px; }
        .sidebar-bottom { margin-top: auto; }
        .plant-card { margin: 0 2px 16px; padding: 13px; border: 1px solid rgba(255,255,255,.1); border-radius: 11px; background: rgba(255,255,255,.045); }
        .plant-row { display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 650; }
        .plant-sub { padding-top: 4px; color: #a7a297; font-size: 11px; }
        .plant-status { display: flex; align-items: center; gap: 8px; margin-top: 12px; color: #b8d8bf; font-size: 11px; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #54b77c; box-shadow: 0 0 0 3px rgba(84,183,124,.12); }
        .user-mini { display: flex; align-items: center; gap: 10px; padding: 12px 8px 0; border-top: 1px solid rgba(255,255,255,.1); color: inherit; text-decoration: none; }
        .user-mini:hover .user-name { color: #e8c9a8; }
        .avatar { display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 auto; border-radius: 50%; background: #d9c3ae; color: #533c2e; font-size: 12px; font-weight: 750; text-decoration: none; }
        .user-name { font-size: 12px; font-weight: 650; }
        .user-role { margin-top: 2px; color: #a7a297; font-size: 11px; }
        .main { min-height: 100vh; margin-left: 248px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; min-height: 72px; padding: 0 34px; border-bottom: 1px solid var(--brown-dark); background: var(--brown); color: #fff8ef; }
        .breadcrumb { color: #f4e8dc; font-size: 12px; }
        .breadcrumb a { color: inherit; text-decoration: none; }
        .breadcrumb a:hover { color: #fff; }
        .breadcrumb strong { color: #fff8ef; font-weight: 650; }
        .top-actions { display: flex; align-items: center; gap: 14px; }
        .top-date { display: flex; align-items: center; gap: 7px; color: #f4e8dc; font-size: 12px; }
        .content { max-width: 1480px; margin: 0 auto; padding: 30px 34px 44px; }
        .page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 25px; }
        .eyebrow { margin-bottom: 5px; color: var(--brown); font-size: 10px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(25px, 3vw, 31px); letter-spacing: -.9px; }
        .subtitle { margin: 6px 0 0; color: var(--muted); font-size: 13px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 39px; padding: 0 14px; border: 1px solid var(--brown); border-radius: 8px; background: var(--brown); color: white; font-size: 12px; font-weight: 650; transition: .18s ease; }
        .button:hover { border-color: var(--brown-dark); background: var(--brown-dark); }
        .button:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid #bb946e; outline-offset: 2px; }
        .metrics { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin-bottom: 17px; }
        .metric { min-height: 117px; padding: 17px 18px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); box-shadow: var(--shadow); }
        .metric-label { color: #777168; font-size: 11px; font-weight: 600; }
        .metric-value { margin-top: 8px; font-size: 26px; font-weight: 750; letter-spacing: -.7px; }
        .metric-note { margin-top: 3px; color: #938d84; font-size: 11px; }
        .metric-icon { float: right; display: grid; place-items: center; width: 33px; height: 33px; border-radius: 9px; background: var(--brown-soft); color: var(--brown); }
        .panel { border: 1px solid var(--line); border-radius: 12px; background: var(--surface); box-shadow: var(--shadow); }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 19px 15px; }
        .panel-title { margin: 0; font-size: 14px; font-weight: 700; }
        .panel-kicker { margin-top: 3px; color: #918b82; font-size: 11px; }
        .table-tools { display: flex; align-items: center; gap: 9px; padding: 0 19px 15px; }
        .search-wrap { position: relative; flex: 1; max-width: 330px; }
        .search-wrap svg { position: absolute; top: 50%; left: 11px; color: #938d84; transform: translateY(-50%); pointer-events: none; }
        .search-input, .shift-select { min-height: 37px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink); font-size: 12px; }
        .search-input { width: 100%; padding: 0 11px 0 35px; }
        .shift-select { min-width: 142px; padding: 0 10px; }
        .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 11px 19px; background: #faf9f7; color: #8b857c; font-size: 10px; font-weight: 700; letter-spacing: .055em; text-transform: uppercase; white-space: nowrap; }
        td { padding: 14px 19px; border-top: 1px solid #f0ede8; color: #5f5a52; font-size: 12px; white-space: nowrap; }
        tbody tr:hover { background: #fdfcfb; }
        .operator-cell { display: flex; align-items: center; gap: 10px; color: var(--ink); font-weight: 650; }
        .operator-avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: #f0e7dd; color: var(--brown); font-size: 11px; font-weight: 750; }
        .shift-tag, .record-tag { display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 20px; background: #f5f2ed; color: #716b62; font-size: 10px; font-weight: 650; }
        .record-tag { background: #edf5ef; color: var(--green); }
        .record-tag.pending { background: #f5f2ed; color: #8b857c; }
        .accuracy { display: flex; align-items: center; gap: 10px; min-width: 150px; }
        .accuracy strong { min-width: 38px; color: var(--ink); font-size: 12px; }
        .accuracy-track { width: 74px; height: 6px; overflow: hidden; border-radius: 9px; background: #eeeae4; }
        .accuracy-track span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,#8c6749,#bb946e); }
        .empty-state { padding: 32px 18px; color: var(--muted); text-align: center; }
        .empty-state[hidden], tr[hidden] { display: none; }
        .table-footer { padding: 12px 19px; border-top: 1px solid var(--line); color: #8b857c; font-size: 11px; }
        .shift-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin-top: 17px; }
        .shift-card { padding: 16px 18px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); box-shadow: var(--shadow); }
        .shift-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .shift-name { font-size: 12px; font-weight: 700; }
        .shift-count { color: #938d84; font-size: 10px; }
        .shift-average { margin-top: 13px; font-size: 20px; font-weight: 750; letter-spacing: -.4px; }
        .shift-caption { margin-top: 2px; color: #938d84; font-size: 10px; }
        @media (max-width: 1000px) {
            .sidebar { width: 72px; padding: 20px 10px; align-items: center; }
            .brand { padding: 0 0 25px; }
            .brand-text, .nav-label, .nav-link span, .plant-card, .user-info { display: none; }
            .nav { width: 100%; }
            .nav-link { justify-content: center; padding: 0; }
            .user-mini { justify-content: center; padding: 12px 0 0; }
            .main { margin-left: 72px; }
            .topbar { padding: 0 22px; }
            .content { padding: 25px 22px 35px; }
        }
        @media (max-width: 640px) {
            .sidebar { width: 58px; padding: 15px 7px; }
            .brand-mark { width: 34px; height: 34px; }
            .main { margin-left: 58px; }
            .topbar { min-height: 60px; padding: 0 13px; }
            .top-actions { gap: 6px; }
            .top-date { gap: 4px; font-size: 10px; }
            .content { padding: 19px 13px 28px; }
            .page-heading { display: block; }
            .page-heading .button { margin-top: 15px; }
            .metrics { grid-template-columns: 1fr; gap: 8px; }
            .metric { min-height: 97px; padding: 13px 15px; }
            .metric-value { font-size: 23px; }
            .table-tools { flex-wrap: wrap; padding-right: 13px; padding-left: 13px; }
            .search-wrap { max-width: none; flex-basis: 100%; }
            .shift-select { flex: 1; }
            .panel-header { padding-right: 13px; padding-left: 13px; }
            th, td { padding-right: 13px; padding-left: 13px; }
            .table-footer { padding-right: 13px; padding-left: 13px; }
            .shift-grid { grid-template-columns: 1fr; gap: 8px; }
        }
    </style>
</head>
<body>
<aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="quality_control_dashboard.php" style="color:inherit;text-decoration:none">
        <span class="brand-mark"><?= operators_icon('spark', 21) ?></span>
        <span class="brand-text">
            <span class="brand-name">VisionQC</span>
            <span class="brand-caption" style="display:block">Quality intelligence</span>
        </span>
    </a>
    <div class="nav-label">Workspace</div>
    <nav class="nav">
        <a class="nav-link" href="quality_control_dashboard.php#overview"><?= operators_icon('grid') ?><span>Overview</span></a>
        <a class="nav-link" href="workspace.php?view=live-monitoring"><?= operators_icon('camera') ?><span>Live monitoring</span></a>
        <a class="nav-link" href="workspace.php?view=defect-analysis"><?= operators_icon('chart') ?><span>Defect analysis</span></a>
        <a class="nav-link" href="workspace.php?view=product-inspection"><?= operators_icon('box') ?><span>Product inspection</span></a>
        <a class="nav-link" href="workspace.php?view=alerts"><?= operators_icon('bell') ?><span>Alerts</span></a>
        <a class="nav-link" href="workspace.php?view=reports"><?= operators_icon('file') ?><span>Reports</span></a>
        <a class="nav-link" href="workspace.php?view=machines"><?= operators_icon('wrench') ?><span>Machines &amp; health</span></a>
        <a class="nav-link active" href="operators.php" aria-current="page"><?= operators_icon('users') ?><span>Operators</span></a>
    </nav>
    <div class="sidebar-bottom">
        <nav class="nav">
            <?php if (strcasecmp((string) $currentUser['role'], 'Administrator') === 0): ?>
                <a class="nav-link" href="admin.php"><?= operators_icon('settings') ?><span>Admin panel</span></a>
            <?php endif; ?>
            <a class="nav-link" href="profile.php"><?= operators_icon('settings') ?><span>Profile &amp; settings</span></a>
        </nav>
        <div class="plant-card">
            <div class="plant-row"><span>Plant 01</span><span aria-hidden="true">⌄</span></div>
            <div class="plant-sub">Assembly &amp; finishing</div>
            <div class="plant-status"><span class="status-dot"></span> All systems operational</div>
        </div>
        <a class="user-mini" href="profile.php" aria-label="Open profile for <?= $escape((string) $currentUser['name']) ?>">
            <span class="avatar"><?= $escape(auth_initials((string) $currentUser['name'])) ?></span>
            <span class="user-info">
                <span class="user-name"><?= $escape((string) $currentUser['name']) ?></span>
                <span class="user-role"><?= $escape((string) $currentUser['role']) ?></span>
            </span>
        </a>
    </div>
</aside>
<main class="main">
    <header class="topbar">
        <div class="breadcrumb"><a href="quality_control_dashboard.php">Factory</a> / <strong>Operators</strong></div>
        <div class="top-actions">
            <div class="top-date"><?= operators_icon('clock', 15) ?><time datetime="<?= $escape(date('Y-m-d')) ?>"><?= $escape(date('D, M j, Y')) ?></time></div>
            <a class="avatar" href="profile.php" aria-label="Open profile for <?= $escape((string) $currentUser['name']) ?>"><?= $escape(auth_initials((string) $currentUser['name'])) ?></a>
        </div>
    </header>
    <div class="content">
        <section class="page-heading">
            <div>
                <div class="eyebrow">Team management</div>
                <h1>Operators</h1>
                <p class="subtitle">Review shift coverage and today's inspection accuracy across your team.</p>
            </div>
            <button class="button" id="export-button" type="button"><?= operators_icon('download', 15) ?> Export CSV</button>
        </section>

        <section class="metrics" aria-label="Operator summary">
            <article class="metric">
                <span class="metric-icon"><?= operators_icon('users', 17) ?></span>
                <div class="metric-label">Team members</div>
                <div class="metric-value"><?= number_format(count($operators)) ?></div>
                <div class="metric-note">Across all shifts</div>
            </article>
            <article class="metric">
                <span class="metric-icon"><?= operators_icon('chart', 17) ?></span>
                <div class="metric-label">Average accuracy</div>
                <div class="metric-value"><?= $averageAccuracy !== null ? number_format($averageAccuracy, 1) . '%' : '—' ?></div>
                <div class="metric-note"><?= count($scores) ?> operator<?= count($scores) === 1 ? '' : 's' ?> with today's data</div>
            </article>
            <article class="metric">
                <span class="metric-icon"><?= operators_icon('spark', 17) ?></span>
                <div class="metric-label">Top performer today</div>
                <div class="metric-value" style="font-size:21px"><?= $bestOperator !== null ? $escape((string) $bestOperator['name']) : '—' ?></div>
                <div class="metric-note"><?= $bestOperator !== null ? number_format((float) $bestOperator['accuracy'], 1) . '% inspection accuracy' : 'No performance data recorded' ?></div>
            </article>
        </section>

        <section class="panel" aria-labelledby="roster-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="roster-title">Operator roster</h2>
                    <div class="panel-kicker">Daily inspection accuracy · <?= $escape(date('F j, Y')) ?></div>
                </div>
            </div>
            <div class="table-tools">
                <label class="search-wrap">
                    <?= operators_icon('search', 16) ?>
                    <input class="search-input" id="operator-search" type="search" placeholder="Search operators..." aria-label="Search operators">
                </label>
                <label>
                    <span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">Filter by shift</span>
                    <select class="shift-select" id="shift-filter">
                        <option value="">All shifts</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?= $escape(strtolower($shift)) ?>"><?= $escape($shift) ?> shift</option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="table-wrap">
                <table id="operator-table">
                    <thead>
                        <tr><th scope="col">Operator</th><th scope="col">Shift</th><th scope="col">Today's accuracy</th><th scope="col">Performance data</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($operators as $operator): ?>
                        <?php $initials = auth_initials((string) $operator['name']); ?>
                        <tr data-name="<?= $escape(strtolower((string) $operator['name'])) ?>" data-shift="<?= $escape(strtolower((string) $operator['shift'])) ?>">
                            <td>
                                <span class="operator-cell">
                                    <span class="operator-avatar"><?= $escape($initials) ?></span>
                                    <?= $escape((string) $operator['name']) ?>
                                </span>
                            </td>
                            <td><span class="shift-tag"><?= $escape((string) $operator['shift']) ?> shift</span></td>
                            <td>
                                <?php if ($operator['accuracy'] !== null): ?>
                                    <?php $accuracy = max(0, min(100, (float) $operator['accuracy'])); ?>
                                    <span class="accuracy">
                                        <strong><?= number_format((float) $operator['accuracy'], 1) ?>%</strong>
                                        <span class="accuracy-track" aria-hidden="true"><span style="width:<?= number_format($accuracy, 1, '.', '') ?>%"></span></span>
                                    </span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="record-tag<?= $operator['accuracy'] === null ? ' pending' : '' ?>">
                                    <?= $operator['accuracy'] !== null ? 'Recorded today' : 'Awaiting today’s data' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($operators === []): ?>
                    <div class="empty-state">No operators have been added yet.</div>
                <?php else: ?>
                    <div class="empty-state" id="no-results" hidden>No operators match your search.</div>
                <?php endif; ?>
            </div>
            <div class="table-footer" id="result-count" aria-live="polite"><?= count($operators) ?> operator<?= count($operators) === 1 ? '' : 's' ?></div>
        </section>

        <section class="shift-grid" aria-label="Shift overview">
            <?php foreach ($shiftSummary as $shift => $summary): ?>
                <?php $shiftAverage = $summary['scores'] !== [] ? array_sum($summary['scores']) / count($summary['scores']) : null; ?>
                <article class="shift-card">
                    <div class="shift-top">
                        <div class="shift-name"><?= $escape($shift) ?> shift</div>
                        <div class="shift-count"><?= $summary['count'] ?> operator<?= $summary['count'] === 1 ? '' : 's' ?></div>
                    </div>
                    <div class="shift-average"><?= $shiftAverage !== null ? number_format($shiftAverage, 1) . '%' : '—' ?></div>
                    <div class="shift-caption">Average accuracy today</div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
    <?php require __DIR__ . '/site_footer.php'; ?>
</main>
<script>
    (() => {
        const search = document.getElementById('operator-search');
        const shiftFilter = document.getElementById('shift-filter');
        const rows = Array.from(document.querySelectorAll('#operator-table tbody tr'));
        const count = document.getElementById('result-count');
        const noResults = document.getElementById('no-results');

        const filterRows = () => {
            const query = search.value.trim().toLowerCase();
            const shift = shiftFilter.value;
            let visible = 0;
            rows.forEach((row) => {
                const matches = row.dataset.name.includes(query) && (!shift || row.dataset.shift === shift);
                row.hidden = !matches;
                if (matches) visible++;
            });
            count.textContent = `${visible} operator${visible === 1 ? '' : 's'}`;
            if (noResults) noResults.hidden = visible !== 0;
        };

        search.addEventListener('input', filterRows);
        shiftFilter.addEventListener('change', filterRows);
        document.getElementById('export-button').addEventListener('click', () => {
            const visibleRows = rows.filter((row) => !row.hidden);
            const csvRows = [['Operator', 'Shift', "Today's accuracy", 'Performance data']];
            visibleRows.forEach((row) => {
                const cells = Array.from(row.cells).map((cell) => cell.innerText.trim().replace(/\s+/g, ' '));
                csvRows.push(cells);
            });
            const csv = csvRows.map((row) => row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(',')).join('\r\n');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            link.download = 'factoryos-operators.csv';
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 0);
        });
    })();
</script>
</body>
</html>
