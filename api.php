<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

function api_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (auth_user() === null) {
    api_json(['error' => 'Sign in to access dashboard data.'], 401);
}

function api_period(string $period, string $today): array
{
    $todayDate = new DateTimeImmutable($today);
    $start = match ($period) {
        'today' => $todayDate,
        'week' => $todayDate->modify('monday this week'),
        'month' => $todayDate->modify('first day of this month'),
        default => throw new InvalidArgumentException('Unsupported reporting period.'),
    };
    $days = (int) $start->diff($todayDate)->days + 1;
    $previousEnd = $start->modify('-1 day');
    $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $todayDate->modify('+1 day')->format('Y-m-d'),
        'previousStart' => $previousStart->format('Y-m-d'),
        'previousEnd' => $start->format('Y-m-d'),
        'days' => $days,
    ];
}

function api_csv_value(string $value): string
{
    if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
        return "'" . $value;
    }
    return $value;
}

function api_inspection_rows(PDO $pdo, array $range, int $limit): array
{
    $statement = $pdo->prepare(
        'SELECT i.product_code, i.inspected_at, i.confidence, d.name, d.class_code,
                d.tone, c.name AS camera
         FROM inspections i
         JOIN defect_types d ON d.id = i.defect_type_id
         JOIN cameras c ON c.id = i.camera_id
         WHERE i.inspected_at >= :start AND i.inspected_at < :end
         ORDER BY i.inspected_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':start', $range['start']);
    $statement->bindValue(':end', $range['end']);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $inspections = [];
    foreach ($statement as $row) {
        $inspections[] = [
            'time' => date('H:i:s', strtotime($row['inspected_at'])),
            'product' => $row['product_code'],
            'category' => $row['class_code']
                ? $row['name'] . ' · ' . $row['class_code']
                : $row['name'],
            'tone' => $row['tone'],
            'confidence' => (int) $row['confidence'],
            'camera' => $row['camera'],
        ];
    }
    return $inspections;
}

try {
    $pdo = database_connection();
    $action = (string) ($_GET['action'] ?? '');
    $period = (string) ($_GET['period'] ?? 'today');
    $today = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    $range = api_period($period, $today);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_json(['error' => 'Only GET requests are supported.'], 405);
    }

    if ($action === 'inspection') {
        $product = trim((string) ($_GET['product'] ?? ''));
        if ($product === '' || strlen($product) > 30) {
            api_json(['error' => 'A valid product code is required.'], 400);
        }
        $statement = $pdo->prepare(
            'SELECT i.product_code, i.inspected_at, i.confidence, i.image_path,
                    d.name AS defect_name, d.class_code, c.name AS camera, c.line_name,
                    c.model_name, c.status AS camera_status
             FROM inspections i
             JOIN defect_types d ON d.id = i.defect_type_id
             JOIN cameras c ON c.id = i.camera_id
             WHERE i.product_code = :product
             LIMIT 1'
        );
        $statement->execute(['product' => $product]);
        $inspection = $statement->fetch();
        if (!$inspection) {
            api_json(['error' => 'Inspection record not found.'], 404);
        }
        api_json(['inspection' => $inspection]);
    }

    if ($action === 'cameras') {
        $cameras = $pdo->query(
            'SELECT name, line_name, model_name, status
             FROM cameras
             ORDER BY name'
        )->fetchAll();
        api_json(['cameras' => $cameras]);
    }

    if ($action === 'inspections') {
        $limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            api_json(['error' => 'The inspection limit must be between 1 and 500.'], 400);
        }
        api_json(['inspections' => api_inspection_rows($pdo, $range, $limit)]);
    }

    if ($action === 'export') {
        $statement = $pdo->prepare(
            'SELECT i.inspected_at, i.product_code, d.name AS defect_name, d.class_code,
                    i.confidence, c.name AS camera
             FROM inspections i
             JOIN defect_types d ON d.id = i.defect_type_id
             JOIN cameras c ON c.id = i.camera_id
             WHERE i.inspected_at >= :start AND i.inspected_at < :end
             ORDER BY i.inspected_at DESC'
        );
        $statement->execute(['start' => $range['start'], 'end' => $range['end']]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="quality-inspections-' . $period . '.csv"');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Unable to open report output.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Time', 'Product ID', 'Classification', 'Confidence', 'Camera']);
        foreach ($statement as $row) {
            $category = $row['class_code']
                ? $row['defect_name'] . ' · ' . $row['class_code']
                : $row['defect_name'];
            fputcsv($output, [
                $row['inspected_at'],
                api_csv_value((string) $row['product_code']),
                api_csv_value((string) $category),
                (string) $row['confidence'] . '%',
                api_csv_value((string) $row['camera']),
            ]);
        }
        fclose($output);
        exit;
    }

    if ($action !== 'dashboard') {
        api_json(['error' => 'Unknown action.'], 404);
    }

    $totalsQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(passed), 0) AS passed,
                COALESCE(SUM(damaged), 0) AS damaged,
                COALESCE(SUM(cracked), 0) AS cracked,
                COALESCE(SUM(burned), 0) AS burned
         FROM hourly_production
         WHERE stat_date >= :start AND stat_date < :end'
    );
    $totalsQuery->execute(['start' => $range['start'], 'end' => $range['end']]);
    $totals = $totalsQuery->fetch();
    $passed = (int) $totals['passed'];
    $damaged = (int) $totals['damaged'];
    $cracked = (int) $totals['cracked'];
    $burned = (int) $totals['burned'];
    $defectCount = $damaged + $cracked + $burned;
    $total = $passed + $defectCount;

    $previousQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(total_inspected), 0)
         FROM daily_summary
         WHERE stat_date >= :start AND stat_date < :end'
    );
    $previousQuery->execute([
        'start' => $range['previousStart'],
        'end' => $range['previousEnd'],
    ]);
    $previousTotal = (int) $previousQuery->fetchColumn();
    $comparison = $previousTotal > 0
        ? sprintf('%+.1f%% vs. previous period', ($total - $previousTotal) / $previousTotal * 100)
        : 'No data for previous period';

    $types = $pdo->query(
        'SELECT code, name, class_code, tone, color FROM defect_types ORDER BY sort_order'
    )->fetchAll();
    $typeByCode = [];
    foreach ($types as $type) {
        $typeByCode[$type['code']] = $type;
    }
    $distribution = [];
    $cursor = 0.0;
    $gradient = [];
    foreach (['DAMAGED' => $damaged, 'CRACKED' => $cracked, 'SCRATCHES' => $burned] as $code => $count) {
        $type = $typeByCode[$code] ?? null;
        if ($type === null) {
            throw new RuntimeException('Missing defect type: ' . $code);
        }
        $share = $defectCount > 0 ? $count / $defectCount * 100 : 0;
        $distribution[] = [
            'label' => $type['class_code']
                ? $type['name'] . ' · ' . $type['class_code']
                : $type['name'],
            'count' => $count,
            'share' => number_format($share, 1) . '%',
            'color' => $type['color'],
        ];
        $gradient[] = sprintf('%s %.1f%% %.1f%%', $type['color'], $cursor, $cursor + $share);
        $cursor += $share;
    }

    $inspections = api_inspection_rows($pdo, $range, 4);

    $alertsQuery = $pdo->prepare(
        'SELECT title, description, tone, alerted_at
         FROM alerts
         WHERE is_active = 1 AND alerted_at >= :start AND alerted_at < :end
         ORDER BY alerted_at DESC
         LIMIT 4'
    );
    $alertsQuery->execute(['start' => $range['start'], 'end' => $range['end']]);
    $alerts = [];
    foreach ($alertsQuery as $row) {
        $alerts[] = [
            'title' => $row['title'],
            'description' => $row['description'],
            'time' => date('h:i A', strtotime($row['alerted_at'])),
            'tone' => $row['tone'],
        ];
    }
    $activeAlertsQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM alerts
         WHERE is_active = 1 AND tone <> 'blue'
           AND alerted_at >= :start AND alerted_at < :end"
    );
    $activeAlertsQuery->execute(['start' => $range['start'], 'end' => $range['end']]);
    $activeAlerts = (int) $activeAlertsQuery->fetchColumn();

    $costQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(estimated_loss), 0) AS estimated_loss,
                COALESCE(SUM(material_waste_kg), 0) AS material_waste_kg
         FROM daily_summary
         WHERE stat_date >= :start AND stat_date < :end'
    );
    $costQuery->execute(['start' => $range['start'], 'end' => $range['end']]);
    $cost = $costQuery->fetch();

    $chartQuery = $pdo->prepare(
        'SELECT hour_of_day, SUM(passed) AS passed, SUM(damaged) AS damaged,
                SUM(cracked) AS cracked, SUM(burned) AS burned
         FROM hourly_production
         WHERE stat_date >= :start AND stat_date < :end
         GROUP BY hour_of_day
         ORDER BY hour_of_day'
    );
    $chartQuery->execute(['start' => $range['start'], 'end' => $range['end']]);
    $chart = [];
    $chartMax = 50;
    foreach ($chartQuery as $row) {
        $chartRow = [
            'hour' => (int) $row['hour_of_day'],
            'label' => date('g A', mktime((int) $row['hour_of_day'], 0)),
            'passed' => (int) round((int) $row['passed'] / $range['days']),
            'damaged' => (int) round((int) $row['damaged'] / $range['days']),
            'cracked' => (int) round((int) $row['cracked'] / $range['days']),
            'burned' => (int) round((int) $row['burned'] / $range['days']),
        ];
        $chartMax = max($chartMax, $chartRow['passed']);
        $chart[] = $chartRow;
    }
    $chartMax = (int) (ceil($chartMax / 20) * 20);

    api_json([
        'period' => $period,
        'metrics' => [
            ['label' => 'Products inspected', 'value' => number_format($total), 'detail' => $comparison],
            ['label' => 'Passed inspection', 'value' => number_format($passed), 'detail' => ($total > 0 ? number_format($passed / $total * 100, 1) : '0.0') . '% pass rate'],
            ['label' => $typeByCode['DAMAGED']['name'] . ' · ' . $typeByCode['DAMAGED']['class_code'], 'value' => number_format($damaged), 'detail' => ($total > 0 ? number_format($damaged / $total * 100, 1) : '0.0') . '% of production'],
            ['label' => $typeByCode['CRACKED']['name'] . ' · ' . $typeByCode['CRACKED']['class_code'], 'value' => number_format($cracked), 'detail' => ($total > 0 ? number_format($cracked / $total * 100, 1) : '0.0') . '% of production'],
            ['label' => $typeByCode['SCRATCHES']['name'] . ' · ' . $typeByCode['SCRATCHES']['class_code'], 'value' => number_format($burned), 'detail' => ($total > 0 ? number_format($burned / $total * 100, 1) : '0.0') . '% of production'],
            ['label' => 'Total defects', 'value' => number_format($defectCount), 'detail' => ($total > 0 ? number_format($defectCount / $total * 100, 1) : '0.0') . '% defect rate'],
        ],
        'distribution' => $distribution,
        'donutGradient' => $gradient
            ? 'conic-gradient(' . implode(', ', $gradient) . ')'
            : 'conic-gradient(#e8e3db 0 100%)',
        'totalDefects' => $defectCount,
        'inspections' => $inspections,
        'alerts' => $alerts,
        'activeAlerts' => $activeAlerts,
        'cost' => [
            'loss' => '₹' . number_format((float) $cost['estimated_loss']),
            'waste' => rtrim(rtrim(number_format((float) $cost['material_waste_kg'], 1), '0'), '.') . ' kg',
        ],
        'chart' => ['rows' => $chart, 'max' => $chartMax],
    ]);
} catch (InvalidArgumentException $e) {
    api_json(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Dashboard API failure: ' . $e->getMessage());
    api_json(['error' => 'The request could not be completed. Check the database connection and server logs.'], 500);
}
