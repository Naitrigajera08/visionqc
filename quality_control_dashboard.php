<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$currentUser = auth_require_user();
$showWelcomePopup = !empty($_SESSION['show_welcome_popup']);
unset($_SESSION['show_welcome_popup']);
require __DIR__ . '/data.php';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function icon(string $name, int $size = 18): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'camera' => '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3"/><path d="m7 6 1.5-2h5L15 6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 6-7"/>',
        'box' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        'alert' => '<path d="m12 3 9 16H3l9-16Z"/><path d="M12 9v4m0 3h.01"/>',
        'crack' => '<path d="m13 2-3 7h5l-4 13 1-9H7l6-11Z"/>',
        'flame' => '<path d="M12 22c4.5 0 7-3.1 7-7 0-3-1.5-5-4-7-.2 2-1.2 3-2 3-1-4-3-6-5-8 0 4-1 6-3 9-2 4 0 10 7 10Z"/><path d="M12 22c-2 0-3-1.5-3-3.3 0-1.5 1-2.8 2.5-4.2.2 1.4 1 2.1 1.5 2.3 1.3 1.2 1 5.2-1 5.2Z"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.5-1.8-.6a8 8 0 0 1-1.8 1l-.3 1.9h-2.9l-.3-1.9a8 8 0 0 1-1.8-1l-1.8.6-1.4-2.5 1.5-1.2a7 7 0 0 1 0-2l-1.5-1.2 1.4-2.5 1.8.6a8 8 0 0 1 1.8-1l.3-1.9h2.9l.3 1.9a8 8 0 0 1 1.8 1l1.8-.6 1.4 2.5-1.5 1.2a7 7 0 0 1 0 2Z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8m-8 4h8"/>',
        'wrench' => '<path d="M14.7 6.3a5 5 0 0 0-6.6 6.6L3 18l3 3 5.1-5.1a5 5 0 0 0 6.6-6.6L15 12l-3-3 2.7-2.7Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5m-5 5V3"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'expand' => '<path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/>',
        'spark' => '<path d="m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3Z"/><path d="m19 14 1.1 2.9L23 18l-2.9 1.1L19 22l-1.1-2.9L15 18l2.9-1.1L19 14Z"/>',
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
    <meta name="theme-color" content="#f5f2ed">
    <title>Quality Control · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root {
            color-scheme: light;
            --ink: #25231f;
            --muted: #827d75;
            --line: #e8e3db;
            --canvas: #f5f2ed;
            --surface: #E9E4D7;
            --sidebar: #252521;
            --sidebar-muted: #b3afa6;
            --brown: #79563c;
            --brown-dark: #5d402e;
            --brown-soft: #f0e7dd;
            --green: #2b805d;
            --red: #c34f4a;
            --amber: #bd8429;
            --orange: #ca7044;
            --purple: #8064aa;
            --shadow: 0 8px 24px rgba(54, 44, 33, .045);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--canvas); color: var(--ink); font: 14px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        button, select { font: inherit; }
        select { appearance: none; -webkit-appearance: none; cursor: pointer; }
        button { cursor: pointer; }
        .app { min-height: 100vh; }
        .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 5; display: flex; flex-direction: column; width: 248px; padding: 24px 16px 18px; background: var(--sidebar); color: #f7f4ee; }
        .brand { display: flex; align-items: center; gap: 11px; padding: 2px 10px 26px; }
        .brand-mark { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; color: #fff8ef; background: var(--brown); }
        .brand-name { font-size: 15px; font-weight: 750; letter-spacing: -.35px; }
        .brand-caption { margin-top: 2px; color: #a7a297; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
        .nav-label { padding: 0 11px 8px; color: #89867e; font-size: 10px; font-weight: 700; letter-spacing: .11em; text-transform: uppercase; }
        .nav { display: grid; gap: 4px; }
        .nav-link { display: flex; align-items: center; gap: 12px; min-height: 42px; padding: 0 11px; border: 1px solid transparent; border-radius: 9px; color: var(--sidebar-muted); text-decoration: none; font-size: 13px; transition: .18s ease; }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
        .nav-link.active { color: #fff8ef; border-color: rgba(214, 181, 150, .19); background: rgba(139, 100, 68, .34); }
        .nav-link.active svg { color: #e8c9a8; }
        .nav-link:focus-visible, .avatar:focus-visible { outline: 2px solid #d6b596; outline-offset: 3px; }
        .sidebar-bottom { margin-top: auto; }
        .plant-card { margin: 0 2px 16px; padding: 13px; border: 1px solid rgba(255,255,255,.1); border-radius: 11px; background: rgba(255,255,255,.045); }
        .plant-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 650; }
        .plant-sub { padding-top: 4px; color: #a7a297; font-size: 11px; }
        .plant-status { display: flex; align-items: center; gap: 6px; margin-top: 12px; color: #b8d8bf; font-size: 11px; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #54b77c; box-shadow: 0 0 0 3px rgba(84,183,124,.12); }
        .user-mini { display: flex; align-items: center; gap: 10px; padding: 12px 8px 0; border-top: 1px solid rgba(255,255,255,.1); color: inherit; text-decoration: none; }
        .user-mini:hover .user-name { color: #e8c9a8; }
        .avatar { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 50%; background: #d9c3ae; color: #533c2e; font-size: 12px; font-weight: 750; }
        .user-name { font-size: 12px; font-weight: 650; }
        .user-role { margin-top: 2px; color: #a7a297; font-size: 11px; }
        .main { min-height: 100vh; margin-left: 248px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; min-height: 72px; padding: 0 34px; border-bottom: 1px solid var(--brown-dark); background: var(--brown); color: #fff8ef; }
        .breadcrumb { color: #f4e8dc; font-size: 12px; }
        .breadcrumb strong { color: #fff8ef; font-weight: 650; }
        .top-actions { display: flex; align-items: center; gap: 14px; }
        .top-actions .avatar { text-decoration: none; }
        .top-date { display: flex; align-items: center; gap: 7px; color: #f4e8dc; font-size: 12px; }
        .icon-button { position: relative; display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface); color: #676159; }
        .icon-button:hover { border-color: #c9b7a5; color: var(--brown); }
        .notification-count { position: absolute; top: -4px; right: -4px; display: grid; place-items: center; width: 16px; height: 16px; border: 2px solid var(--surface); border-radius: 50%; background: var(--red); color: white; font-size: 9px; font-weight: 700; }
        .content { max-width: 1680px; margin: 0 auto; padding: 27px 34px 40px; }
        .page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 22px; }
        .eyebrow { margin-bottom: 5px; color: var(--brown); font-size: 10px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(23px, 2vw, 29px); font-weight: 720; letter-spacing: -.85px; }
        .subtitle { margin-top: 6px; color: var(--muted); font-size: 13px; }
        .heading-actions { display: flex; align-items: center; gap: 9px; }
        .select, .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 37px; padding: 0 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); color: #504b43; font-size: 12px; font-weight: 600; }
        .select { padding-right: 9px; }
        .button:hover, .select:hover { border-color: #cbb7a4; color: var(--brown-dark); }
        .button-primary { border-color: var(--brown); background: var(--brown); color: white; }
        .button-primary:hover { border-color: var(--brown-dark); background: var(--brown-dark); color: white; }
        .metrics { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 15px; }
        .metric { min-width: 0; padding: 15px 15px 14px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); box-shadow: var(--shadow); }
        .metric-top { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
        .metric-label { overflow: hidden; color: #777168; font-size: 11px; font-weight: 550; text-overflow: ellipsis; white-space: nowrap; }
        .metric-icon { display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 auto; border-radius: 8px; }
        .metric-value { margin-top: 9px; font-size: 23px; font-weight: 750; letter-spacing: -.7px; line-height: 1; }
        .metric-detail { margin-top: 7px; color: #8a857d; font-size: 10px; }
        .tone-neutral .metric-icon { color: var(--brown); background: var(--brown-soft); }
        .tone-green .metric-icon { color: var(--green); background: #e7f3ec; }
        .tone-red .metric-icon { color: var(--red); background: #fae9e7; }
        .tone-amber .metric-icon { color: var(--amber); background: #faf0da; }
        .tone-orange .metric-icon { color: var(--orange); background: #faece4; }
        .tone-purple .metric-icon { color: var(--purple); background: #f0eaf7; }
        .dashboard-grid { display: grid; grid-template-columns: minmax(250px, 1.15fr) minmax(360px, 1.55fr) minmax(260px, 1fr); gap: 14px; }
        .panel { min-width: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); box-shadow: var(--shadow); }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 15px 16px 0; }
        .panel-title { margin: 0; font-size: 13px; font-weight: 700; letter-spacing: -.15px; }
        .panel-kicker { margin-top: 3px; color: var(--muted); font-size: 10px; }
        .panel-action { padding: 4px 7px; border: 0; border-radius: 6px; background: transparent; color: var(--brown); font-size: 11px; font-weight: 650; text-decoration: none; }
        .panel-action:hover { background: var(--brown-soft); }
        .camera-card { display: flex; flex-direction: column; }
        .camera-feed { position: relative; height: 220px; overflow: hidden; margin: 13px 13px 0; border-radius: 8px; background: #3e4140; }
        .camera-art { position: absolute; inset: 0; overflow: hidden; background: linear-gradient(180deg, #a9aca9 0%, #737876 43%, #383e3b 44%, #505654 100%); }
        .camera-art:before { position: absolute; top: 37%; left: -8%; width: 120%; height: 49%; transform: rotate(-4deg); border: 13px solid #252b29; border-right-width: 21px; border-left-width: 21px; background: repeating-linear-gradient(90deg, #aeb1ad 0 25px, #797e7a 27px 31px, #b9bbb6 34px 58px); box-shadow: 0 7px 15px #151817aa; content: ""; }
        .camera-art:after { position: absolute; top: 40%; right: 9%; width: 25px; height: 105px; border-radius: 5px; background: linear-gradient(90deg, #1d201f, #7a807b 45%, #282b2a); box-shadow: -180px 3px 0 -2px #1d201f, -181px 5px 0 1px #818680; content: ""; }
        .part { position: absolute; z-index: 1; top: 47%; width: 52px; height: 24px; border: 4px solid #d2d4cf; border-radius: 50%; background: linear-gradient(140deg, #858a86, #343a38 48%, #a7aaa5); box-shadow: 0 6px 7px #10141388; }
        .part-one { left: 13%; transform: rotate(-7deg); }.part-two { left: 43%; top: 44%; width: 65px; height: 31px; }.part-three { right: 12%; top: 51%; width: 45px; height: 21px; }
        .detect-box { position: absolute; z-index: 2; top: 37%; left: 39%; width: 79px; height: 67px; border: 2px solid #e1785d; border-radius: 3px; box-shadow: 0 0 0 999px #171b1a20; }
        .detect-label { position: absolute; z-index: 3; top: calc(37% - 23px); left: 39%; padding: 3px 7px; border-radius: 4px 4px 0 0; background: #c7604e; color: white; font-size: 9px; font-weight: 700; }
        .feed-vignette { position: absolute; inset: 0; background: linear-gradient(180deg, #10131145, transparent 28%, transparent 74%, #10131166); }
        .feed-label { position: absolute; z-index: 3; top: 11px; left: 11px; color: white; font-size: 10px; font-weight: 650; }
        .live-pill { position: absolute; z-index: 3; top: 10px; right: 10px; display: flex; align-items: center; gap: 6px; padding: 4px 8px; border: 1px solid #ffffff30; border-radius: 20px; background: #202824bb; color: #d8f0dd; font-size: 9px; font-weight: 650; }
        .live-pill .status-dot { width: 6px; height: 6px; }
        .camera-controls { position: relative; z-index: 3; display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding: 11px 14px 13px; color: #6d675f; font-size: 10px; }
        .camera-controls button { display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; background: #E9E4D7; color: var(--brown); font-size: 10px; font-weight: 650; }
        .production { grid-column: 2; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: 13px; padding: 7px 16px 13px; color: #716b63; font-size: 10px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--dot); }
        .chart-wrap { padding: 11px 10px 0; }
        .chart-wrap svg { display: block; width: 100%; height: 180px; overflow: visible; }
        .donut-content { display: flex; align-items: center; justify-content: center; gap: 22px; min-height: 224px; padding: 10px 14px 18px; }
        .donut { position: relative; display: grid; place-items: center; width: 138px; height: 138px; flex: 0 0 auto; border-radius: 50%; background: conic-gradient(#bd514b 0 50.9%, #d68b35 50.9% 81.7%, #edac45 81.7% 100%); }
        .donut:before { position: absolute; width: 99px; height: 99px; border-radius: 50%; background: var(--surface); content: ""; }
        .donut-center { position: relative; z-index: 1; text-align: center; }
        .donut-center strong { display: block; font-size: 25px; letter-spacing: -.8px; }
        .donut-center span { color: var(--muted); font-size: 10px; }
        .distribution-legend { display: grid; gap: 13px; }
        .distribution-row { display: flex; gap: 8px; align-items: flex-start; color: #6e6961; font-size: 10px; }
        .distribution-row strong { display: block; margin-top: 2px; color: #37342f; font-size: 11px; }
        .distribution-row .legend-dot { margin-top: 4px; flex: 0 0 auto; }
        .bottom-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(300px, 1fr); gap: 14px; margin-top: 14px; }
        .table-wrap { overflow-x: auto; padding: 11px 13px 14px; }
        table { width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap; }
        th { padding: 9px 9px; border-bottom: 1px solid var(--line); color: #8a847b; font-size: 9px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        td { padding: 10px 9px; border-bottom: 1px solid #f0ede8; color: #5f5a52; font-size: 10px; }
        tr:last-child td { border-bottom: 0; }
        .time-cell { color: #89837a; font-variant-numeric: tabular-nums; }
        .product-cell { color: #39362f; font-weight: 600; }
        .badge { display: inline-flex; align-items: center; padding: 3px 7px; border-radius: 5px; font-size: 9px; font-weight: 650; }
        .badge.red { color: #a84943; background: #f9e9e7; }.badge.amber { color: #96671c; background: #fbf1da; }.badge.orange { color: #a85c37; background: #faece5; }.badge.green { color: #28744f; background: #e7f3eb; }
        .view-button { padding: 4px 9px; border: 1px solid #dfd4c8; border-radius: 5px; background: #E9E4D7; color: var(--brown); font-size: 9px; font-weight: 650; }
        .view-button:hover { background: var(--brown-soft); }
        .alerts-list { display: grid; padding: 5px 14px 11px; }
        .alert-row { display: grid; grid-template-columns: 29px minmax(0,1fr) auto; align-items: start; gap: 9px; padding: 11px 0; border-bottom: 1px solid #f0ede8; }
        .alert-row:last-child { border-bottom: 0; }
        .alert-icon { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 8px; }
        .alert-icon.red { color: var(--red); background: #fae9e7; }.alert-icon.amber { color: var(--amber); background: #faf1dd; }.alert-icon.blue { color: #4f7eae; background: #eaf1f8; }
        .alert-title { color: #37342f; font-size: 10px; font-weight: 700; }
        .alert-description { margin-top: 3px; color: #817b73; font-size: 9px; line-height: 1.45; }
        .alert-time { padding-top: 2px; color: #928c83; font-size: 9px; white-space: nowrap; }
        .footer-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; margin-top: 14px; }
        .machine-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 13px; }
        .machine { min-width: 0; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: #E9E4D7; }
        .machine-name { overflow: hidden; color: #706a62; font-size: 9px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .machine-health { display: flex; align-items: baseline; gap: 4px; margin-top: 7px; color: var(--machine-color); font-size: 19px; font-weight: 750; letter-spacing: -.5px; }
        .machine-health small { color: #938d84; font-size: 9px; font-weight: 500; }
        .progress { height: 4px; overflow: hidden; margin-top: 8px; border-radius: 9px; background: #eeeae3; }
        .progress span { display: block; height: 100%; width: var(--value); border-radius: inherit; background: var(--machine-color); }
        .machine-state { margin-top: 6px; color: var(--machine-color); font-size: 9px; }
        .operator-list { display: grid; gap: 12px; padding: 15px 16px; }
        .operator-row { display: grid; grid-template-columns: minmax(70px, 1fr) minmax(70px, 1.5fr) 28px; align-items: center; gap: 9px; color: #69635b; font-size: 10px; }
        .operator-bar { height: 8px; overflow: hidden; border-radius: 9px; background: #eeeae4; }
        .operator-bar span { display: block; width: var(--value); height: 100%; border-radius: inherit; background: linear-gradient(90deg, #8c6749, #bb946e); }
        .operator-score { color: #413c35; font-size: 10px; font-weight: 700; text-align: right; }
        .cost-content { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; padding: 13px; }
        .cost-tile { padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: #E9E4D7; }
        .cost-label { color: #817b72; font-size: 9px; }
        .cost-value { margin-top: 5px; color: #a94b42; font-size: 20px; font-weight: 750; letter-spacing: -.4px; }
        .cost-value.waste { color: var(--brown); }
        .cost-note { margin-top: 3px; color: #928c84; font-size: 9px; }
        .toast { position: fixed; right: 24px; bottom: 24px; z-index: 10; padding: 12px 16px; transform: translateY(15px); border-radius: 9px; background: #302d28; color: white; box-shadow: 0 10px 25px #1a181533; font-size: 12px; opacity: 0; pointer-events: none; transition: .2s ease; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .detail-dialog { width: min(100% - 32px, 480px); max-height: min(80vh, 620px); padding: 0; overflow: auto; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); color: var(--ink); box-shadow: 0 24px 70px rgba(28,25,21,.24); }
        .detail-dialog::backdrop { background: rgba(26,24,21,.56); backdrop-filter: blur(2px); }
        .dialog-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 21px 16px; border-bottom: 1px solid var(--line); }
        .dialog-eyebrow { margin: 0 0 4px; color: var(--brown); font-size: 10px; font-weight: 750; letter-spacing: .1em; text-transform: uppercase; }
        .dialog-title { margin: 0; font-size: 18px; letter-spacing: -.3px; }
        .dialog-close { display: grid; place-items: center; width: 32px; height: 32px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); color: #706a62; font-size: 20px; line-height: 1; }
        .dialog-close:hover { border-color: #c9b7a5; color: var(--brown); }
        .dialog-content { padding: 7px 21px 20px; }
        .detail-row { display: grid; grid-template-columns: minmax(115px,.8fr) minmax(0,1.2fr); gap: 14px; padding: 12px 0; border-bottom: 1px solid #f0ede8; font-size: 12px; }
        .detail-row:last-child { border-bottom: 0; }
        .detail-label { color: #827d75; }
        .detail-value { color: var(--ink); font-weight: 600; overflow-wrap: anywhere; }
        .detail-empty { padding: 20px 0; color: var(--muted); font-size: 12px; }
        .scan-dialog { width: min(100% - 32px, 500px); max-width: 500px; padding: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 16px; background: var(--surface); color: var(--ink); box-shadow: 0 24px 70px rgba(28,25,21,.24); }
        .scan-dialog::backdrop { background: rgba(26,24,21,.58); backdrop-filter: blur(3px); }
        .scan-dialog-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:21px 22px 17px; border-bottom:1px solid var(--line); }
        .scan-dialog-copy { margin:7px 0 0; color:var(--muted); font-size:12px; line-height:1.6; }
        .scan-dialog-body { padding:18px 22px 22px; }
        .scan-option { display:flex; align-items:center; gap:14px; padding:16px; border:1px solid #e7d9cc; border-radius:12px; background:#fbf8f5; }
        .scan-option-icon { display:grid; place-items:center; width:42px; height:42px; flex:0 0 auto; border-radius:11px; background:var(--brown-soft); color:var(--brown); }
        .scan-option-title { margin:0; font-size:13px; font-weight:700; }
        .scan-option-copy { margin:4px 0 0; color:var(--muted); font-size:11px; line-height:1.5; }
        .scan-dialog-action { display:flex; margin-top:15px; }
        .scan-dialog-action .button { width:100%; min-height:42px; }
        .scan-dialog-note { margin:14px 2px 0; color:#817b72; font-size:11px; line-height:1.6; }
        .scan-dialog-note strong { color:var(--brown-dark); }
        .data-updated { color: #827d75; font-size: 10px; white-space: nowrap; }
        .welcome-dialog { width: min(100% - 32px, 620px); max-width: 620px; max-height: min(90vh, 720px); padding: 0; overflow: auto; border: 1px solid rgba(255,255,255,.62); border-radius: 20px; background: #fbfaf8; color: var(--ink); box-shadow: 0 32px 100px rgba(20,18,15,.3); }
        .welcome-dialog[open] { animation: welcome-enter .32s cubic-bezier(.2,.8,.2,1) both; }
        .welcome-dialog::backdrop { background: rgba(25,24,21,.62); backdrop-filter: blur(7px); animation: welcome-backdrop .22s ease-out both; }
        .welcome-hero { position: relative; min-height: 174px; overflow: hidden; padding: 26px 30px; background: radial-gradient(ellipse at 82% 20%, rgba(224,190,151,.25), transparent 38%), linear-gradient(125deg,#302e29,#484035 58%,#77563e); color: #fffaf2; }
        .welcome-hero::after { position: absolute; right: -42px; bottom: -128px; width: 290px; height: 230px; border: 1px solid rgba(255,247,231,.13); border-radius: 50%; box-shadow: 0 0 0 22px rgba(255,247,231,.035),0 0 0 48px rgba(255,247,231,.025); content: ""; }
        .welcome-brand { display: inline-flex; align-items: center; gap: 9px; color: #ead4bb; font-size: 10px; font-weight: 750; letter-spacing: .14em; text-transform: uppercase; }
        .welcome-brand-mark { display: grid; place-items: center; width: 30px; height: 30px; border: 1px solid rgba(255,255,255,.16); border-radius: 9px; background: rgba(255,255,255,.09); color: #f8e6d2; }
        .welcome-close { position: absolute; top: 18px; right: 18px; z-index: 1; display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(255,255,255,.18); border-radius: 10px; background: rgba(255,255,255,.08); color: #fffaf2; font-size: 20px; line-height: 1; }
        .welcome-close:hover { background: rgba(255,255,255,.16); }
        .welcome-stickers { position: absolute; top: 47px; right: 48px; width: 190px; height: 108px; }
        .welcome-sticker-card { position: absolute; top: 8px; right: 23px; display: grid; grid-template-columns: 36px auto; align-items: center; gap: 9px; min-width: 143px; padding: 10px 12px; border: 3px solid #fff; border-radius: 14px 14px 17px 12px; background: #fbfaf8; color: var(--ink); box-shadow: 0 7px 0 rgba(24,21,18,.16); transform: rotate(6deg); }
        .welcome-sticker-check { display: grid; place-items: center; width: 36px; height: 36px; border: 2px solid #fff; border-radius: 50%; background: #72a77d; color: #fff; box-shadow: 0 0 0 2px #72a77d; }
        .welcome-sticker-title { font-size: 10px; font-weight: 850; letter-spacing: .045em; line-height: 1.1; }
        .welcome-sticker-caption { margin-top: 3px; color: #827d75; font-size: 8px; font-weight: 650; letter-spacing: .08em; text-transform: uppercase; }
        .welcome-sticker-spark { position: absolute; top: -4px; right: 2px; display: grid; place-items: center; width: 37px; height: 37px; border: 3px solid #fff; border-radius: 13px 50% 50% 50%; background: #e6a85f; color: #fff; box-shadow: 0 4px 0 rgba(24,21,18,.15); transform: rotate(14deg); }
        .welcome-sticker-tag { position: absolute; right: 78px; bottom: -2px; padding: 5px 10px; border: 2px solid #fff; border-radius: 20px; background: #d9c3ae; color: #533c2e; box-shadow: 0 4px 0 rgba(24,21,18,.14); font-size: 8px; font-weight: 850; letter-spacing: .1em; transform: rotate(-5deg); }
        .welcome-main { padding: 26px 30px 28px; }
        .welcome-kicker { margin: 0 0 6px; color: var(--brown); font-size: 10px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        .welcome-title { margin: 0; font-size: clamp(25px,5vw,32px); font-weight: 750; letter-spacing: -1.1px; line-height: 1.15; }
        .welcome-copy { margin: 9px 0 21px; color: #777168; font-size: 13px; }
        .welcome-stats { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
        .welcome-stat { min-width: 0; padding: 13px 14px; border: 1px solid var(--line); border-radius: 11px; background: #fff; }
        .welcome-stat-label { color: #827d75; font-size: 10px; }
        .welcome-stat-value { margin-top: 5px; color: var(--ink); font-size: 19px; font-weight: 750; letter-spacing: -.45px; }
        .welcome-stat-value.is-good { color: var(--green); }
        .welcome-footer { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 22px; }
        .welcome-date { color: #918b82; font-size: 11px; }
        .welcome-enter { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 43px; padding: 0 17px; border: 1px solid var(--brown); border-radius: 9px; background: var(--brown); color: #fff; font-size: 12px; font-weight: 700; transition: background .18s ease,transform .18s ease; }
        .welcome-enter:hover { transform: translateY(-1px); border-color: var(--brown-dark); background: var(--brown-dark); }
        .welcome-enter:focus-visible,.welcome-close:focus-visible { outline: 3px solid #d8b895; outline-offset: 3px; }
        @keyframes welcome-enter { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes welcome-backdrop { from { opacity: 0; } to { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .welcome-dialog[open],.welcome-dialog::backdrop { animation: none; } .welcome-enter { transition: none; } }
        @media (max-width: 640px) {
            .detail-dialog { width: calc(100% - 24px); }
            .dialog-header { padding: 17px 16px 14px; }
            .dialog-content { padding-right: 16px; padding-left: 16px; }
            .detail-row { grid-template-columns: 1fr; gap: 4px; }
            .data-updated { display: none; }
            .welcome-dialog { width: calc(100% - 24px); border-radius: 16px; }
            .welcome-hero { min-height: 148px; padding: 21px 20px; }
            .welcome-close { top: 14px; right: 14px; }
            .welcome-stickers { top: 59px; right: 13px; transform: scale(.78); transform-origin: top right; }
            .welcome-main { padding: 22px 20px 21px; }
            .welcome-stats { gap: 7px; }
            .welcome-stat { padding: 11px 9px; }
            .welcome-stat-label { font-size: 9px; }
            .welcome-stat-value { font-size: 17px; }
            .welcome-footer { align-items: flex-start; flex-direction: column; }
            .welcome-enter { width: 100%; }
        }
        @media (max-width: 1320px) {
            .metrics { grid-template-columns: repeat(3, minmax(0,1fr)); }
            .dashboard-grid { grid-template-columns: minmax(250px, 1fr) minmax(360px, 1.4fr); }
            .production { grid-column: 2; grid-row: 1; }
            .donut-panel { grid-column: 1 / -1; }
            .donut-content { min-height: 190px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .machine-panel { grid-column: 1 / -1; }
        }
        @media (max-width: 900px) {
            .sidebar { width: 72px; padding: 20px 10px; align-items: center; }
            .brand { padding: 0 0 25px; }
            .brand-text, .nav-label, .nav-link span, .plant-card, .user-info { display: none; }
            .nav { width: 100%; }
            .nav-link { justify-content: center; padding: 0; }
            .user-mini { justify-content: center; padding: 12px 0 0; }
            .main { margin-left: 72px; }
            .topbar { padding: 0 22px; }
            .content { padding: 24px 22px 32px; }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .sidebar { width: 58px; padding: 15px 7px; }
            .brand-mark { width: 34px; height: 34px; }
            .main { margin-left: 58px; }
            .topbar { min-height: 60px; padding: 0 14px; }
            .top-actions { gap: 7px; }
            .top-date { gap: 4px; font-size: 10px; }
            .content { padding: 19px 13px 25px; }
            .page-heading { display: block; }
            .heading-actions { flex-wrap: wrap; margin-top: 15px; }
            .heading-actions .select { flex: 1; }
            .heading-actions .button { flex: 1; }
            .metrics { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; }
            .metric { padding: 12px; }
            .metric-value { font-size: 21px; }
            .metric-label { font-size: 10px; }
            .dashboard-grid { display: flex; flex-direction: column; }
            .production { order: 2; }
            .camera-card { order: 1; }
            .donut-panel { order: 3; }
            .camera-feed { height: 195px; }
            .donut-content { gap: 18px; padding-left: 8px; padding-right: 8px; }
            .donut { width: 118px; height: 118px; }
            .donut:before { width: 83px; height: 83px; }
            .donut-center strong { font-size: 22px; }
            .footer-grid { grid-template-columns: 1fr; }
            .machine-panel { grid-column: auto; }
            .machine-list { grid-template-columns: repeat(2, 1fr); }
            .panel-header { padding: 13px 13px 0; }
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Main navigation">
        <div class="brand">
            <div class="brand-mark"><?= icon('spark', 21) ?></div>
            <div class="brand-text">
                <div class="brand-name">VisionQC</div>
                <div class="brand-caption">Quality intelligence</div>
            </div>
        </div>
        <div class="nav-label">Workspace</div>
        <nav class="nav">
            <a class="nav-link active" href="#overview" aria-label="Overview" aria-current="page"><?= icon('grid') ?><span>Overview</span></a>
            <a class="nav-link" href="workspace.php?view=live-monitoring" aria-label="Live monitoring"><?= icon('camera') ?><span>Live monitoring</span></a>
            <a class="nav-link" href="workspace.php?view=defect-analysis" aria-label="Defect analysis"><?= icon('chart') ?><span>Defect analysis</span></a>
            <a class="nav-link" href="workspace.php?view=product-inspection" aria-label="Product inspection"><?= icon('box') ?><span>Product inspection</span></a>
            <a class="nav-link" href="workspace.php?view=alerts" aria-label="Alerts"><?= icon('bell') ?><span>Alerts</span></a>
            <a class="nav-link" href="workspace.php?view=reports" aria-label="Reports"><?= icon('file') ?><span>Reports</span></a>
            <a class="nav-link" href="workspace.php?view=machines" aria-label="Machines and health"><?= icon('wrench') ?><span>Machines &amp; health</span></a>
            <a class="nav-link" href="operators.php" aria-label="Operators"><?= icon('users') ?><span>Operators</span></a>
        </nav>
        <div class="sidebar-bottom">
            <div class="nav">
                <?php if (strcasecmp((string) $currentUser['role'], 'Administrator') === 0): ?>
                    <a class="nav-link" href="admin.php" aria-label="Admin panel"><?= icon('settings') ?><span>Admin panel</span></a>
                <?php endif; ?>
                <a class="nav-link" href="profile.php" aria-label="Profile and settings"><?= icon('settings') ?><span>Profile &amp; settings</span></a>
            </div>
            <div class="plant-card">
                <div class="plant-row"><span>Plant 01</span><span>⌄</span></div>
                <div class="plant-sub">Assembly &amp; finishing</div>
                <div class="plant-status"><span class="status-dot"></span> All systems operational</div>
            </div>
            <a class="user-mini" href="profile.php" aria-label="Open profile for <?= esc($currentUser['name']) ?>">
                <div class="avatar"><?= esc(auth_initials($currentUser['name'])) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= esc($currentUser['name']) ?></div>
                    <div class="user-role"><?= esc($currentUser['role']) ?></div>
                </div>
            </a>
        </div>
    </aside>

    <main class="main" id="overview">
        <header class="topbar">
            <div class="breadcrumb">Factory / <strong>Quality overview</strong></div>
            <div class="top-actions">
                <div class="top-date"><?= icon('clock', 15) ?><time id="current-date" datetime="<?= esc(date('Y-m-d')) ?>"><?= esc(date('D, M j, Y')) ?></time></div>
                <span class="data-updated" id="data-updated" aria-live="polite">Data loaded</span>
                <button class="icon-button" id="refresh-dashboard-button" type="button" aria-label="Refresh dashboard data" title="Refresh dashboard data">
                    <?= icon('clock', 17) ?>
                </button>
                <button class="icon-button" id="alert-notification-button" type="button" aria-label="View alerts">
                    <?= icon('bell', 17) ?><span class="notification-count" id="notification-count"><?= esc((string) $activeAlerts) ?></span>
                </button>
                <a class="avatar" href="profile.php" aria-label="Open profile for <?= esc($currentUser['name']) ?>"><?= esc(auth_initials($currentUser['name'])) ?></a>
            </div>
        </header>

        <div class="content">
            <section class="page-heading">
                <div>
                    <div class="eyebrow">Production intelligence</div>
                    <h1>Quality control overview</h1>
                    <div class="subtitle">Monitor inspections, spot emerging defects, and keep your line moving.</div>
                </div>
                <div class="heading-actions">
                    <label class="select" for="period"><?= icon('clock', 15) ?>
                        <select id="period" aria-label="Select reporting period" style="border:0;outline:0;background:transparent;color:inherit;font-size:12px;font-weight:600">
                            <option value="today">Today</option>
                            <option value="week">This week</option>
                            <option value="month">This month</option>
                        </select><?= icon('chevron', 14) ?>
                    </label>
                    <button class="button button-primary" id="scan-product-button" type="button"><?= icon('camera', 15) ?> Scan product</button>
                    <button class="button button-primary" id="export-button" type="button"><?= icon('download', 15) ?> Export report</button>
                </div>
            </section>

            <section class="metrics" aria-label="Production summary">
                <?php foreach ($metrics as $metricIndex => $metric): ?>
                    <article class="metric tone-<?= esc($metric['tone']) ?>" data-metric-index="<?= esc((string) $metricIndex) ?>">
                        <div class="metric-top">
                            <div class="metric-label"><?= esc($metric['label']) ?></div>
                            <div class="metric-icon"><?= icon($metric['icon'], 15) ?></div>
                        </div>
                        <div class="metric-value"><?= esc($metric['value']) ?></div>
                        <div class="metric-detail"><?= esc($metric['detail']) ?></div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="dashboard-grid" aria-label="Quality monitoring dashboard">
                <article class="panel camera-card" id="camera">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Camera preview</h2><div class="panel-kicker">Assembly line · Camera 01</div></div>
                        <button class="panel-action" id="camera-settings-button" type="button">Camera details</button>
                    </div>
                    <div class="camera-feed" id="camera-feed" aria-label="Illustrated camera preview; no live stream is connected">
                        <div class="camera-art"></div>
                        <div class="part part-one"></div><div class="part part-two"></div><div class="part part-three"></div>
                        <div class="detect-box"></div><div class="detect-label">Cracked · ABC · 92%</div>
                        <div class="feed-vignette"></div>
                        <div class="feed-label">ASSEMBLY LINE / CAM-01</div>
                        <div class="live-pill"><span class="status-dot"></span> PREVIEW</div>
                    </div>
                    <div class="camera-controls">
                        <span>Camera preview · stream not connected</span>
                        <button type="button" id="fullscreen-button"><?= icon('expand', 13) ?> Full screen</button>
                    </div>
                </article>

                <article class="panel production" id="production">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Production output</h2><div class="panel-kicker">Inspected units by hour</div></div>
                        <span class="badge green">On target</span>
                    </div>
                    <div class="chart-wrap">
                        <svg id="production-chart" viewBox="0 0 540 205" role="img" aria-label="Hourly output chart showing good products and three defect categories">
                            <g id="chart-grid" stroke="#eeeae4" stroke-width="1">
                                <?php foreach ($ticks as $tick): ?>
                                    <path d="M48 <?= esc((string) $tick['y']) ?>H525"/>
                                <?php endforeach; ?>
                            </g>
                            <g id="chart-labels" fill="#958f86" font-size="10" font-family="system-ui,sans-serif">
                                <?php foreach ($ticks as $tick): ?>
                                    <text x="8" y="<?= esc((string) ($tick['y'] + 3)) ?>"><?= esc((string) $tick['label']) ?></text>
                                <?php endforeach; ?>
                                <?php foreach ($xlabels as $label): ?>
                                    <text x="<?= esc((string) $label['x']) ?>" y="202"><?= esc($label['text']) ?></text>
                                <?php endforeach; ?>
                            </g>
                            <?php foreach ($chartLines as $line): ?>
                                <path data-series="<?= esc($line['key']) ?>" d="<?= esc($line['d']) ?>" fill="none" stroke="<?= esc($line['color']) ?>" stroke-width="<?= esc((string) $line['width']) ?>" stroke-linecap="round" stroke-linejoin="round"/>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="legend-dot" style="--dot:#428765"></span>Passed</span>
                        <span class="legend-item"><span class="legend-dot" style="--dot:#c65a50"></span>Damaged</span>
                        <span class="legend-item"><span class="legend-dot" style="--dot:#d49a38"></span>Cracked</span>
                        <span class="legend-item"><span class="legend-dot" style="--dot:#d47b46"></span>Scratches</span>
                    </div>
                </article>

                <article class="panel donut-panel" id="distribution">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Defect distribution</h2><div class="panel-kicker">Share of detected defects</div></div>
                        <button class="panel-action" id="distribution-details-button" type="button">Details</button>
                    </div>
                    <div class="donut-content">
                        <div class="donut" id="defect-donut" role="img" aria-label="Defect distribution chart" style="background: <?= esc($donutGradient) ?>;">
                            <div class="donut-center"><strong id="donut-total"><?= esc((string) array_sum(array_map(static fn ($item) => (int) $item['count'], $distribution))) ?></strong><span>defects</span></div>
                        </div>
                        <div class="distribution-legend" id="distribution-legend">
                            <?php foreach ($distribution as $item): ?>
                                <div class="distribution-row"><span class="legend-dot" style="--dot:<?= esc($item['color']) ?>"></span><span><?= esc($item['label']) ?><strong><?= esc((string) $item['count']) ?> · <?= esc($item['share']) ?></strong></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
            </section>

            <section class="bottom-grid">
                <article class="panel" id="recent-defects">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Recent inspections</h2><div class="panel-kicker">Latest products passing through quality control</div></div>
                        <button class="panel-action" id="view-all-inspections" type="button">View all →</button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Time</th><th>Product ID</th><th>Classification</th><th>Confidence</th><th>Camera</th><th>Record</th></tr></thead>
                            <tbody id="inspection-rows">
                            <?php foreach ($defects as $defect): ?>
                                <tr>
                                    <td class="time-cell"><?= esc($defect['time']) ?></td>
                                    <td class="product-cell"><?= esc($defect['product']) ?></td>
                                    <td><span class="badge <?= esc($defect['tone']) ?>"><?= esc($defect['category']) ?></span></td>
                                    <td><?= esc((string)$defect['confidence']) ?>%</td>
                                    <td><?= esc($defect['camera']) ?></td>
                                    <td><button class="view-button" type="button" data-product="<?= esc($defect['product']) ?>">Inspect</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel" id="alerts">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Live alerts</h2><div class="panel-kicker">Items that may need attention</div></div>
                        <span class="badge red" id="active-alert-count"><?= esc((string) $activeAlerts) ?> active</span>
                    </div>
                    <div class="alerts-list" id="alerts-list">
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-row">
                                <div class="alert-icon <?= esc($alert['tone']) ?>"><?= icon($alert['tone'] === 'blue' ? 'clock' : 'alert', 14) ?></div>
                                <div><div class="alert-title"><?= esc($alert['title']) ?></div><div class="alert-description"><?= esc($alert['description']) ?></div></div>
                                <div class="alert-time"><?= esc($alert['time']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </section>

            <section class="footer-grid">
                <article class="panel machine-panel" id="machines">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Machine health</h2><div class="panel-kicker">Operational readiness across the line</div></div>
                        <a class="panel-action" href="workspace.php?view=machines">Machines →</a>
                    </div>
                    <div class="machine-list">
                        <?php foreach ($machines as $machine): ?>
                            <div class="machine" style="--machine-color:<?= $machine['tone'] === 'amber' ? 'var(--amber)' : 'var(--green)' ?>">
                                <div class="machine-name"><?= esc($machine['name']) ?></div>
                                <div class="machine-health"><?= esc((string)$machine['health']) ?><small>%</small></div>
                                <div class="progress"><span style="--value:<?= esc((string)$machine['health']) ?>%"></span></div>
                                <div class="machine-state"><?= esc($machine['state']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel" id="operators">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Operator performance</h2><div class="panel-kicker">Inspection accuracy · today</div></div>
                        <a class="panel-action" href="operators.php">View all →</a>
                    </div>
                    <div class="operator-list">
                        <?php foreach ($operators as $operator): ?>
                            <div class="operator-row">
                                <span><?= esc($operator['label']) ?></span>
                                <div class="operator-bar"><span style="--value:<?= esc((string) $operator['score']) ?>%"></span></div>
                                <span class="operator-score"><?= esc((string) $operator['score']) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel" id="reports">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Cost &amp; material impact</h2><div class="panel-kicker">Estimated quality-related waste</div></div>
                        <button class="panel-action" id="cost-period-button" type="button">Today⌄</button>
                    </div>
                    <div class="cost-content">
                        <div class="cost-tile"><div class="cost-label">Estimated loss</div><div class="cost-value" id="cost-loss"><?= esc($costLoss) ?></div><div class="cost-note">From defective products</div></div>
                        <div class="cost-tile"><div class="cost-label">Material waste</div><div class="cost-value waste" id="cost-waste"><?= esc($costWaste) ?></div><div class="cost-note">Estimated scrap material</div></div>
                    </div>
                </article>
            </section>
        </div>
        <?php require __DIR__ . '/site_footer.php'; ?>
    </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<dialog class="detail-dialog" id="dashboard-dialog" aria-labelledby="dialog-title">
    <div class="dialog-header">
        <div>
            <p class="dialog-eyebrow" id="dialog-eyebrow">VisionQC details</p>
            <h2 class="dialog-title" id="dialog-title"></h2>
        </div>
        <button class="dialog-close" id="dialog-close" type="button" aria-label="Close details">&times;</button>
    </div>
    <div class="dialog-content" id="dialog-content"></div>
</dialog>
<dialog class="scan-dialog" id="scan-dialog" aria-labelledby="scan-dialog-title" aria-describedby="scan-dialog-description">
    <div class="scan-dialog-header">
        <div>
            <p class="dialog-eyebrow">Product inspection</p>
            <h2 class="dialog-title" id="scan-dialog-title">Check a product</h2>
            <p class="scan-dialog-copy" id="scan-dialog-description">Upload a clear product photo to save it for inspection and record a review.</p>
        </div>
        <button class="dialog-close" id="scan-dialog-close" type="button" aria-label="Close scan options">&times;</button>
    </div>
    <div class="scan-dialog-body">
        <div class="scan-option">
            <span class="scan-option-icon"><?= icon('camera', 20) ?></span>
            <div>
                <h3 class="scan-option-title">Upload a product photo</h3>
                <p class="scan-option-copy">Choose Food, Drinks, Cosmetics, or Other, then add the product image.</p>
            </div>
        </div>
        <div class="scan-dialog-action">
            <a class="button button-primary" href="workspace.php?view=live-monitoring#photo-upload-title"><?= icon('camera', 15) ?> Continue to photo upload</a>
        </div>
        <p class="scan-dialog-note"><strong>Camera scanning and automatic defect detection are not connected yet.</strong> You can upload a photo and manually mark it Passed or Defect, with details such as scratches or a crack in the notes.</p>
    </div>
</dialog>
<dialog class="welcome-dialog" id="welcome-dialog" aria-labelledby="welcome-title" aria-describedby="welcome-copy">
    <section class="welcome-hero">
        <div class="welcome-brand"><span class="welcome-brand-mark"><?= icon('spark', 17) ?></span> VisionQC · Quality intelligence</div>
        <div class="welcome-stickers" aria-hidden="true">
            <div class="welcome-sticker-card">
                <span class="welcome-sticker-check"><?= icon('check', 20) ?></span>
                <span><span class="welcome-sticker-title">QUALITY<br>CREW</span><span class="welcome-sticker-caption">You're all set</span></span>
            </div>
            <span class="welcome-sticker-spark"><?= icon('spark', 19) ?></span>
            <span class="welcome-sticker-tag">PLANT 01</span>
        </div>
        <button class="welcome-close" id="welcome-close" type="button" aria-label="Close welcome message">&times;</button>
    </section>
    <section class="welcome-main">
        <p class="welcome-kicker" id="welcome-greeting">Welcome back</p>
        <h2 class="welcome-title" id="welcome-title">Good to see you, <?= esc($currentUser['name']) ?>.</h2>
        <p class="welcome-copy" id="welcome-copy">Here’s your quality snapshot for today. Your production overview is ready.</p>
        <div class="welcome-stats" aria-label="Today's production summary">
            <div class="welcome-stat"><div class="welcome-stat-label">Products inspected</div><div class="welcome-stat-value"><?= esc(number_format($total)) ?></div></div>
            <div class="welcome-stat"><div class="welcome-stat-label">Pass rate</div><div class="welcome-stat-value is-good"><?= esc(pct($passed, $total)) ?></div></div>
            <div class="welcome-stat"><div class="welcome-stat-label">Active alerts</div><div class="welcome-stat-value"><?= esc((string) $activeAlerts) ?></div></div>
        </div>
        <div class="welcome-footer">
            <span class="welcome-date"><?= esc(date('l, F j')) ?> · Plant 01</span>
            <button class="welcome-enter" id="welcome-enter" type="button">Open my dashboard <?= icon('chart', 15) ?></button>
        </div>
    </section>
</dialog>
<script>
(() => {
    const toast = document.getElementById('toast');
    const periodSelect = document.getElementById('period');
    const dialog = document.getElementById('dashboard-dialog');
    const dialogContent = document.getElementById('dialog-content');
    const scanDialog = document.getElementById('scan-dialog');
    const welcomeDialog = document.getElementById('welcome-dialog');
    let currentPeriod = periodSelect.value;
    let toastTimeout;
    let dashboardRequestId = 0;
    const dateElement = document.getElementById('current-date');
    const updateCurrentDate = () => {
        const today = new Date();
        dateElement.dateTime = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0'),
        ].join('-');
        dateElement.textContent = new Intl.DateTimeFormat('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(today);
    };
    updateCurrentDate();
    window.setInterval(updateCurrentDate, 60_000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) updateCurrentDate();
    });
    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(toastTimeout);
        toastTimeout = window.setTimeout(() => toast.classList.remove('show'), 2600);
    };

    const dismissWelcome = () => {
        if (welcomeDialog.open) welcomeDialog.close();
    };
    document.getElementById('welcome-enter').addEventListener('click', dismissWelcome);
    document.getElementById('welcome-close').addEventListener('click', dismissWelcome);
    welcomeDialog.addEventListener('click', (event) => {
        if (event.target === welcomeDialog) dismissWelcome();
    });

    if (<?= $showWelcomePopup ? 'true' : 'false' ?>) {
        const hour = new Date().getHours();
        const greeting = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
        document.getElementById('welcome-greeting').textContent = `${greeting} · Welcome back`;
        window.requestAnimationFrame(() => welcomeDialog.showModal());
    }

    const requestJson = async (url) => {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('The server returned an invalid response. Please try again.');
        }
        if (!response.ok) {
            throw new Error(data.error || 'The request could not be completed.');
        }
        return data;
    };

    const openDetails = (title, eyebrow, entries) => {
        document.getElementById('dialog-title').textContent = title;
        document.getElementById('dialog-eyebrow').textContent = eyebrow;
        dialogContent.replaceChildren();
        if (entries.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'detail-empty';
            empty.textContent = 'No details are available.';
            dialogContent.append(empty);
        } else {
            entries.forEach(([label, value]) => {
                const row = document.createElement('div');
                row.className = 'detail-row';
                const labelElement = document.createElement('span');
                labelElement.className = 'detail-label';
                labelElement.textContent = label;
                const valueElement = document.createElement('span');
                valueElement.className = 'detail-value';
                valueElement.textContent = String(value);
                row.append(labelElement, valueElement);
                dialogContent.append(row);
            });
        }
        if (!dialog.open) dialog.showModal();
    };

    const addCell = (row, text, className = '') => {
        const cell = document.createElement('td');
        cell.textContent = text;
        if (className) cell.className = className;
        row.append(cell);
        return cell;
    };

    const renderInspections = (inspections) => {
        const body = document.getElementById('inspection-rows');
        body.replaceChildren();
        if (inspections.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(row, 'No inspections found for this period.');
            cell.colSpan = 6;
            body.append(row);
            return;
        }
        inspections.forEach((inspection) => {
            const row = document.createElement('tr');
            addCell(row, inspection.time, 'time-cell');
            addCell(row, inspection.product, 'product-cell');
            const categoryCell = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `badge ${inspection.tone}`;
            badge.textContent = inspection.category;
            categoryCell.append(badge);
            row.append(categoryCell);
            addCell(row, `${inspection.confidence}%`);
            addCell(row, inspection.camera);
            const actionCell = document.createElement('td');
            const button = document.createElement('button');
            button.className = 'view-button';
            button.type = 'button';
            button.dataset.product = inspection.product;
            button.textContent = 'Inspect';
            actionCell.append(button);
            row.append(actionCell);
            body.append(row);
        });
    };

    const renderAlerts = (alerts, activeCount) => {
        const list = document.getElementById('alerts-list');
        list.replaceChildren();
        if (alerts.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'alert-row';
            empty.textContent = 'No active alerts for this period.';
            list.append(empty);
        }
        alerts.forEach((alert) => {
            const row = document.createElement('div');
            row.className = 'alert-row';
            const icon = document.createElement('div');
            icon.className = `alert-icon ${alert.tone}`;
            icon.textContent = alert.tone === 'blue' ? '◷' : '!';
            const content = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'alert-title';
            title.textContent = alert.title;
            const description = document.createElement('div');
            description.className = 'alert-description';
            description.textContent = alert.description;
            content.append(title, description);
            const time = document.createElement('div');
            time.className = 'alert-time';
            time.textContent = alert.time;
            row.append(icon, content, time);
            list.append(row);
        });
        document.getElementById('active-alert-count').textContent = `${activeCount} active`;
        document.getElementById('notification-count').textContent = String(activeCount);
    };

    const renderDistribution = (distribution, gradient, total) => {
        const donut = document.getElementById('defect-donut');
        donut.style.background = gradient;
        donut.setAttribute('aria-label', `${total} total defects`);
        document.getElementById('donut-total').textContent = total.toLocaleString();
        const legend = document.getElementById('distribution-legend');
        legend.replaceChildren();
        distribution.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'distribution-row';
            const dot = document.createElement('span');
            dot.className = 'legend-dot';
            dot.style.setProperty('--dot', item.color);
            const label = document.createElement('span');
            label.textContent = item.label;
            const detail = document.createElement('strong');
            detail.textContent = `${item.count.toLocaleString()} · ${item.share}`;
            label.append(detail);
            row.append(dot, label);
            legend.append(row);
        });
    };

    const renderChart = ({ rows, max }) => {
        const svg = document.getElementById('production-chart');
        const grid = document.getElementById('chart-grid');
        const labels = document.getElementById('chart-labels');
        grid.replaceChildren();
        labels.replaceChildren();
        for (let index = 0; index <= 4; index += 1) {
            const y = 185 - 40 * index;
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            line.setAttribute('d', `M48 ${y}H525`);
            grid.append(line);
            const tick = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            tick.setAttribute('x', '8');
            tick.setAttribute('y', String(y + 3));
            tick.textContent = String(Math.round(max * index / 4));
            labels.append(tick);
        }
        const series = ['passed', 'damaged', 'cracked', 'burned'];
        const chartLeft = 52;
        const chartWidth = 462;
        rows.forEach((row, index) => {
            if (index % 2 !== 0) return;
            const x = rows.length > 1 ? chartLeft + index * chartWidth / (rows.length - 1) : chartLeft;
            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('x', String(x - 9));
            label.setAttribute('y', '202');
            label.textContent = row.label;
            labels.append(label);
        });
        series.forEach((key) => {
            const path = svg.querySelector(`[data-series="${key}"]`);
            const d = rows.map((row, index) => {
                const x = rows.length > 1 ? chartLeft + index * chartWidth / (rows.length - 1) : chartLeft;
                const y = 185 - (row[key] / max) * 160;
                return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
            }).join(' ');
            path.setAttribute('d', d);
        });
    };

    const renderDashboard = (data) => {
        document.querySelectorAll('.metric').forEach((card, index) => {
            const metric = data.metrics[index];
            if (!metric) return;
            card.querySelector('.metric-label').textContent = metric.label;
            card.querySelector('.metric-value').textContent = metric.value;
            card.querySelector('.metric-detail').textContent = metric.detail;
        });
        renderDistribution(data.distribution, data.donutGradient, data.totalDefects);
        renderInspections(data.inspections);
        renderAlerts(data.alerts, data.activeAlerts);
        document.getElementById('cost-loss').textContent = data.cost.loss;
        document.getElementById('cost-waste').textContent = data.cost.waste;
        document.getElementById('cost-period-button').textContent =
            `${periodSelect.options[periodSelect.selectedIndex].text}⌄`;
        renderChart(data.chart);
    };

    const updateDataTimestamp = () => {
        document.getElementById('data-updated').textContent = `Updated ${new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date())}`;
    };
    updateDataTimestamp();

    const loadDashboard = async (period, notify = false) => {
        const requestId = ++dashboardRequestId;
        const data = await requestJson(`api.php?action=dashboard&period=${encodeURIComponent(period)}`);
        if (requestId !== dashboardRequestId) return;
        renderDashboard(data);
        currentPeriod = period;
        periodSelect.value = period;
        updateDataTimestamp();
        if (notify) {
            showToast(`Dashboard updated for ${periodSelect.options[periodSelect.selectedIndex].text}.`);
        }
    };

    document.getElementById('dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    document.getElementById('scan-product-button').addEventListener('click', () => scanDialog.showModal());
    document.getElementById('scan-dialog-close').addEventListener('click', () => scanDialog.close());
    scanDialog.addEventListener('click', (event) => {
        if (event.target === scanDialog) scanDialog.close();
    });

    document.querySelectorAll('[data-toast]').forEach((button) => {
        button.addEventListener('click', () => showToast(button.dataset.toast));
    });

    document.getElementById('refresh-dashboard-button').addEventListener('click', async () => {
        const button = document.getElementById('refresh-dashboard-button');
        button.disabled = true;
        try {
            await loadDashboard(currentPeriod);
            showToast('Dashboard data refreshed.');
        } catch (error) {
            showToast(error.message);
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('alert-notification-button').addEventListener('click', () => {
        document.getElementById('alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    document.getElementById('cost-period-button').addEventListener('click', () => {
        const periods = Array.from(periodSelect.options, (option) => option.value);
        const currentIndex = periods.indexOf(currentPeriod);
        periodSelect.value = periods[(currentIndex + 1) % periods.length];
        periodSelect.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.getElementById('camera-settings-button').addEventListener('click', async () => {
        try {
            const data = await requestJson('api.php?action=cameras');
            const entries = [];
            data.cameras.forEach((camera) => {
                entries.push(
                    [`${camera.name} · Line`, camera.line_name],
                    [`${camera.name} · Model`, camera.model_name],
                    [`${camera.name} · Status`, camera.status]
                );
            });
            openDetails('Connected cameras', 'Camera information', entries);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById('distribution-details-button').addEventListener('click', async () => {
        try {
            const data = await requestJson(`api.php?action=dashboard&period=${encodeURIComponent(currentPeriod)}`);
            const entries = data.distribution.map((item) => [
                item.label,
                `${item.count.toLocaleString()} products · ${item.share}`,
            ]);
            entries.push(['Total defects', data.totalDefects.toLocaleString()]);
            openDetails('Defect breakdown', `Reporting period · ${periodSelect.options[periodSelect.selectedIndex].text}`, entries);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById('inspection-rows').addEventListener('click', async (event) => {
        const button = event.target.closest('.view-button');
        if (!button) return;
        try {
            const data = await requestJson(`api.php?action=inspection&product=${encodeURIComponent(button.dataset.product)}`);
            const inspection = data.inspection;
            const category = inspection.class_code
                ? `${inspection.defect_name} · ${inspection.class_code}`
                : inspection.defect_name;
            openDetails(`Inspection · ${inspection.product_code}`, 'Product quality record', [
                ['Classification', category],
                ['Confidence', `${inspection.confidence}%`],
                ['Inspection time', inspection.inspected_at],
                ['Camera', inspection.camera],
                ['Production line', inspection.line_name],
                ['Camera model', inspection.model_name],
                ['Camera status', inspection.camera_status],
            ]);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById('view-all-inspections').addEventListener('click', async () => {
        try {
            const data = await requestJson(`api.php?action=inspections&period=${encodeURIComponent(currentPeriod)}&limit=100`);
            renderInspections(data.inspections);
            showToast(`Showing ${data.inspections.length} inspection records.`);
        } catch (error) {
            showToast(error.message);
        }
    });

    periodSelect.addEventListener('change', async () => {
        try {
            await loadDashboard(periodSelect.value, true);
        } catch (error) {
            periodSelect.value = currentPeriod;
            showToast(error.message);
        }
    });

    document.getElementById('fullscreen-button').addEventListener('click', async () => {
        const feed = document.getElementById('camera-feed');
        try {
            if (!document.fullscreenElement) {
                await feed.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch (error) {
            showToast('Full screen is not available in this browser.');
        }
    });

    document.getElementById('export-button').addEventListener('click', async () => {
        try {
            const response = await fetch(`api.php?action=export&period=${encodeURIComponent(currentPeriod)}`);
            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Unable to export the report.');
            }
            const blob = await response.blob();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `quality-inspections-${currentPeriod}.csv`;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            showToast('Inspection report exported.');
        } catch (error) {
            showToast(error.message);
        }
    });

    document.querySelectorAll('.nav-link[href^="#"]').forEach((link) => {
        link.addEventListener('click', () => {
            document.querySelectorAll('.nav-link').forEach((item) => {
                item.classList.remove('active');
                item.removeAttribute('aria-current');
            });
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        });
    });
})();
</script>
</body>
</html>
