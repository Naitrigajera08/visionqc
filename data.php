<?php
declare(strict_types=1);

/**
 * data.php - loads everything the dashboard needs from the `detection` database.
 * Included from quality_control_dashboard.php with: require __DIR__ . '/data.php';
 */

require_once __DIR__ . '/database.php';

try {
    $pdo = database_connection();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check database.php settings and import detection.sql.');
}

function pct(int $part, int $whole): string
{
    return $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '0.0%';
}

function defect_label(array $type): string
{
    return $type['class_code'] ? $type['name'] . ' · ' . $type['class_code'] : $type['name'];
}

/* ---------- Defect types ---------- */
$types = [];
foreach ($pdo->query('SELECT * FROM defect_types ORDER BY sort_order') as $row) {
    $types[$row['code']] = $row;
}

/* ---------- Today's totals ---------- */
$sum = $pdo->query(
    'SELECT COALESCE(SUM(passed),0)  AS passed,
            COALESCE(SUM(damaged),0) AS damaged,
            COALESCE(SUM(cracked),0) AS cracked,
            COALESCE(SUM(burned),0)  AS burned
     FROM hourly_production
     WHERE stat_date = CURDATE()'
)->fetch();

$passed  = (int) $sum['passed'];
$damaged = (int) $sum['damaged'];
$cracked = (int) $sum['cracked'];
$burned  = (int) $sum['burned'];
$defectsTotal = $damaged + $cracked + $burned;
$total        = $passed + $defectsTotal;

$prev = (int) $pdo->query(
    'SELECT total_inspected FROM daily_summary WHERE stat_date = CURDATE() - INTERVAL 1 DAY'
)->fetchColumn();
$vsYesterday = $prev > 0
    ? sprintf('%+.1f%% vs. yesterday', ($total - $prev) / $prev * 100)
    : 'No data for yesterday';

$metrics = [
    ['label' => 'Products inspected', 'value' => number_format($total),        'detail' => $vsYesterday,                              'tone' => 'neutral', 'icon' => 'box'],
    ['label' => 'Passed inspection',  'value' => number_format($passed),       'detail' => pct($passed, $total) . ' pass rate',        'tone' => 'green',   'icon' => 'check'],
    ['label' => defect_label($types['DAMAGED']), 'value' => number_format($damaged), 'detail' => pct($damaged, $total) . ' of production', 'tone' => 'red',    'icon' => 'alert'],
    ['label' => defect_label($types['CRACKED']), 'value' => number_format($cracked), 'detail' => pct($cracked, $total) . ' of production', 'tone' => 'amber',  'icon' => 'crack'],
    ['label' => defect_label($types['SCRATCHES']), 'value' => number_format($burned), 'detail' => pct($burned, $total) . ' of production', 'tone' => 'orange', 'icon' => 'crack'],
    ['label' => 'Total defects',      'value' => number_format($defectsTotal), 'detail' => pct($defectsTotal, $total) . ' defect rate', 'tone' => 'purple',  'icon' => 'chart'],
];

/* ---------- Defect distribution (donut) ---------- */
$distribution = [];
$stops = [];
$cursor = 0.0;
foreach ([['DAMAGED', $damaged], ['CRACKED', $cracked], ['SCRATCHES', $burned]] as [$code, $count]) {
    $share = $defectsTotal > 0 ? $count / $defectsTotal * 100 : 0;
    $stops[] = sprintf('%s %.1f%% %.1f%%', $types[$code]['color'], $cursor, $cursor + $share);
    $cursor += $share;
    $distribution[] = [
        'label' => defect_label($types[$code]),
        'count' => $count,
        'share' => number_format($share, 1) . '%',
        'color' => $types[$code]['color'],
    ];
}
$donutGradient = $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'conic-gradient(#e8e3db 0 100%)';

/* ---------- Recent inspections ---------- */
$defects = [];
$stmt = $pdo->query(
    'SELECT i.product_code, i.inspected_at, i.confidence,
            d.name, d.class_code, d.tone, c.name AS camera
     FROM inspections i
     JOIN defect_types d ON d.id = i.defect_type_id
     JOIN cameras c      ON c.id = i.camera_id
     ORDER BY i.inspected_at DESC
     LIMIT 4'
);
foreach ($stmt as $r) {
    $defects[] = [
        'time'       => date('H:i:s', strtotime($r['inspected_at'])),
        'product'    => $r['product_code'],
        'category'   => defect_label($r),
        'tone'       => $r['tone'],
        'confidence' => (int) $r['confidence'],
        'camera'     => $r['camera'],
    ];
}

/* ---------- Alerts ---------- */
$alerts = [];
$stmt = $pdo->query(
    'SELECT title, description, tone, alerted_at
     FROM alerts
     WHERE is_active = 1 AND DATE(alerted_at) = CURDATE()
     ORDER BY alerted_at DESC
     LIMIT 4'
);
foreach ($stmt as $r) {
    $alerts[] = [
        'title'       => $r['title'],
        'description' => $r['description'],
        'time'        => date('h:i A', strtotime($r['alerted_at'])),
        'tone'        => $r['tone'],
    ];
}
$activeAlerts = (int) $pdo->query(
    "SELECT COUNT(*) FROM alerts
     WHERE is_active = 1 AND tone <> 'blue' AND DATE(alerted_at) = CURDATE()"
)->fetchColumn();

/* ---------- Machines ---------- */
$machines = [];
foreach ($pdo->query('SELECT code, process, health, state, tone FROM machines ORDER BY code') as $r) {
    $machines[] = [
        'name'   => $r['code'] . ' · ' . $r['process'],
        'health' => (int) $r['health'],
        'state'  => $r['state'],
        'tone'   => $r['tone'],
    ];
}

/* ---------- Operators ---------- */
$operators = [];
$stmt = $pdo->query(
    'SELECT o.name, o.shift, p.accuracy
     FROM operators o
     JOIN operator_performance p ON p.operator_id = o.id AND p.perf_date = CURDATE()
     ORDER BY FIELD(o.shift, "Morning", "Afternoon", "Night")'
);
foreach ($stmt as $r) {
    $operators[] = [
        'label' => $r['name'] . ' · ' . $r['shift'],
        'score' => (int) round((float) $r['accuracy']),
    ];
}

/* ---------- Cost & material ---------- */
$cost = $pdo->query(
    'SELECT estimated_loss, material_waste_kg FROM daily_summary WHERE stat_date = CURDATE()'
)->fetch() ?: ['estimated_loss' => 0, 'material_waste_kg' => 0];
$costLoss  = '₹' . number_format((float) $cost['estimated_loss']);
$costWaste = rtrim(rtrim(number_format((float) $cost['material_waste_kg'], 1), '0'), '.') . ' kg';

/* ---------- Hourly chart (SVG coordinates) ---------- */
$hours = $pdo->query(
    'SELECT hour_of_day, passed, damaged, cracked, burned
     FROM hourly_production
     WHERE stat_date = CURDATE()
     ORDER BY hour_of_day'
)->fetchAll();

$maxValue = 50;
foreach ($hours as $h) {
    $maxValue = max($maxValue, (int) $h['passed']);
}
$maxValue = (int) (ceil($maxValue / 20) * 20);   // axis max, divisible by 4

$chartLeft = 52.0;
$chartWidth = 462.0;
$count = count($hours);
$points = ['passed' => [], 'damaged' => [], 'cracked' => [], 'burned' => []];
$xlabels = [];
foreach ($hours as $i => $h) {
    $x = $count > 1 ? $chartLeft + $i * ($chartWidth / ($count - 1)) : $chartLeft;
    foreach ($points as $key => $_) {
        $points[$key][] = [round($x, 1), round(185 - ((int) $h[$key] / $maxValue) * 160, 1)];
    }
    if ($i % 2 === 0) {
        $xlabels[] = ['x' => round($x - 9, 1), 'text' => date('g A', mktime((int) $h['hour_of_day'], 0))];
    }
}

function chart_path(array $pts): string
{
    $d = '';
    foreach ($pts as $i => [$x, $y]) {
        $d .= ($i === 0 ? 'M' : 'L') . $x . ' ' . $y;
    }
    return $d;
}

$ticks = [];
for ($k = 0; $k <= 4; $k++) {
    $ticks[] = ['y' => 185 - 40 * $k, 'label' => (int) round($maxValue * $k / 4)];
}

$chartLines = [
    ['key' => 'passed',  'd' => chart_path($points['passed']),  'color' => '#428765', 'width' => 2.5],
    ['key' => 'damaged', 'd' => chart_path($points['damaged']), 'color' => '#c65a50', 'width' => 2],
    ['key' => 'cracked', 'd' => chart_path($points['cracked']), 'color' => '#d49a38', 'width' => 2],
    ['key' => 'burned',  'd' => chart_path($points['burned']),  'color' => '#d47b46', 'width' => 2],
];
