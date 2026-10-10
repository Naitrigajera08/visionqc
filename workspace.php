<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/photo_sample_classifier.php';

$currentUser = auth_require_user();
$pdo = database_connection();
$views = [
    'live-monitoring' => ['title' => 'Live monitoring', 'eyebrow' => 'Production floor'],
    'defect-analysis' => ['title' => 'Defect analysis', 'eyebrow' => 'Quality intelligence'],
    'product-inspection' => ['title' => 'Product inspection', 'eyebrow' => 'Inspection records'],
    'alerts' => ['title' => 'Alerts', 'eyebrow' => 'Attention required'],
    'reports' => ['title' => 'Reports', 'eyebrow' => 'Production performance'],
    'machines' => ['title' => 'Machines & health', 'eyebrow' => 'Equipment status'],
];
$view = (string) ($_GET['view'] ?? '');
if (!isset($views[$view])) {
    http_response_code(404);
    exit('Page not found.');
}
$page = $views[$view];
$error = '';
$notice = '';
$uploadLimit = 8 * 1024 * 1024;
$photoCategories = [
    'food' => [
        'label' => 'Food',
        'criteria' => ['Packaging is sealed and intact', 'Product label and batch details are correct', 'Expiry or best-before date is readable and valid'],
    ],
    'drinks' => [
        'label' => 'Drinks',
        'criteria' => ['Cap and safety seal are intact', 'No visible leakage or damaged bottle/can', 'Label and fill level look correct'],
    ],
    'cosmetics' => [
        'label' => 'Cosmetics',
        'criteria' => ['Tamper seal is intact', 'Product label and ingredients are readable', 'Container is undamaged and has no leakage'],
    ],
    'other' => [
        'label' => 'Other',
        'criteria' => ['Packaging is intact', 'Product label is readable', 'No visible product damage'],
    ],
];
$photoCategoryFilter = (string) ($_GET['category'] ?? 'all');
if ($photoCategoryFilter !== 'all' && !isset($photoCategories[$photoCategoryFilter])) {
    $photoCategoryFilter = 'all';
}
$selectedProductCategory = isset($photoCategories[$photoCategoryFilter])
    ? $photoCategoryFilter
    : (string) ($_POST['product_category'] ?? '');
$reviewStatusLabels = [
    'pending' => 'Needs review',
    'passed' => 'Passed',
    'defect' => 'Defect',
    'damaged' => 'Defect',
    'cracked' => 'Cracked',
    'scratches' => 'Scratches',
    'burned' => 'Defect',
    'other_defect' => 'Defect',
];
$period = in_array($_GET['period'] ?? 'today', ['today', 'week', 'month'], true)
    ? (string) ($_GET['period'] ?? 'today')
    : 'today';

if ($view === 'live-monitoring' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    if (!auth_validate_csrf($token)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (($_POST['action'] ?? '') === 'edit_photo') {
        $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT);
        $productCategory = (string) ($_POST['product_category'] ?? '');
        $productCode = trim((string) ($_POST['product_code'] ?? ''));
        if ($photoId === false || $photoId === null || $photoId < 1) {
            http_response_code(400);
            $error = 'Choose a valid uploaded photo to edit.';
        } elseif (!isset($photoCategories[$productCategory])) {
            http_response_code(400);
            $error = 'Choose a valid product category.';
        } elseif (mb_strlen($productCode, 'UTF-8') > 30) {
            http_response_code(400);
            $error = 'Product ID must be 30 characters or fewer.';
        } else {
            $photoCheck = $pdo->prepare('SELECT id FROM photo_uploads WHERE id = :id AND user_id = :user_id LIMIT 1');
            $photoCheck->execute(['id' => $photoId, 'user_id' => $currentUser['id']]);
            if (!$photoCheck->fetchColumn()) {
                http_response_code(404);
                $error = 'That photo was not found in your uploads.';
            } else {
                $photoUpdate = $pdo->prepare(
                    'UPDATE photo_uploads
                     SET product_category = :product_category, product_code = :product_code
                     WHERE id = :id AND user_id = :user_id'
                );
                $photoUpdate->execute([
                    'product_category' => $productCategory,
                    'product_code' => $productCode !== '' ? $productCode : null,
                    'id' => $photoId,
                    'user_id' => $currentUser['id'],
                ]);
                header('Location: workspace.php?view=live-monitoring&category=' . rawurlencode($productCategory) . '&updated=1#uploaded-photos');
                exit;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_photo') {
        $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT);
        if ($photoId === false || $photoId === null || $photoId < 1) {
            http_response_code(400);
            $error = 'Choose a valid uploaded photo to delete.';
        } else {
            $photoCheck = $pdo->prepare(
                'SELECT id, stored_name, mime_type, product_category FROM photo_uploads
                 WHERE id = :id AND user_id = :user_id LIMIT 1'
            );
            $photoCheck->execute(['id' => $photoId, 'user_id' => $currentUser['id']]);
            $photoToDelete = $photoCheck->fetch();
            if (!$photoToDelete) {
                http_response_code(404);
                $error = 'That photo was not found in your uploads.';
            } else {
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $imagePath = null;
                if (isset($extensions[$photoToDelete['mime_type']])
                    && preg_match('/\A[a-f0-9-]{36}\z/D', (string) $photoToDelete['stored_name']) === 1) {
                    $imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR
                        . $photoToDelete['stored_name'] . '.' . $extensions[$photoToDelete['mime_type']];
                }
                $photoDelete = $pdo->prepare('DELETE FROM photo_uploads WHERE id = :id AND user_id = :user_id');
                $photoDelete->execute(['id' => $photoId, 'user_id' => $currentUser['id']]);
                if ($imagePath !== null && is_file($imagePath) && !unlink($imagePath)) {
                    error_log('Failed to remove deleted inspection photo file: ' . $imagePath);
                    http_response_code(500);
                    $error = 'The photo record was deleted, but its image file could not be removed. Contact the administrator.';
                } else {
                    header('Location: workspace.php?view=live-monitoring&category=' . rawurlencode((string) $photoToDelete['product_category']) . '&deleted=1#uploaded-photos');
                    exit;
                }
            }
        }
    } elseif (!isset($photoCategories[$selectedProductCategory])) {
        http_response_code(400);
        $error = 'Choose a valid product category.';
    } elseif (
        !isset($_FILES['inspection_photo'])
        || !is_array($_FILES['inspection_photo'])
        || !isset($_FILES['inspection_photo']['error'], $_FILES['inspection_photo']['tmp_name'], $_FILES['inspection_photo']['size'], $_FILES['inspection_photo']['name'])
    ) {
        http_response_code(400);
        $error = 'Choose an image to upload.';
    } else {
        $file = $_FILES['inspection_photo'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $error = match ((int) $file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The photo is too large. Maximum upload size is 8 MB.',
                UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
                default => 'The photo could not be uploaded. Please try again.',
            };
        } elseif ((int) $file['size'] < 1 || (int) $file['size'] > $uploadLimit) {
            $error = 'Choose a photo smaller than 8 MB.';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $error = 'The uploaded file could not be verified.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file((string) $file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $imageInfo = @getimagesize((string) $file['tmp_name']);

            if (!is_string($mimeType) || !isset($extensions[$mimeType]) || $imageInfo === false || ($imageInfo['mime'] ?? '') !== $mimeType) {
                $error = 'Use a valid JPG, PNG, or WebP image.';
            } elseif ((int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1 || (int) $imageInfo[0] * (int) $imageInfo[1] > 40000000) {
                $error = 'The image dimensions are too large. Maximum supported size is 40 megapixels.';
            } else {
                $productCode = trim((string) ($_POST['product_code'] ?? ''));
                if (strlen($productCode) > 30) {
                    $error = 'Product ID must be 30 characters or fewer.';
                } else {
                    $sampleMatch = photo_sample_classification((string) $file['tmp_name']);
                    $storageDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'private_uploads';
                    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
                        throw new RuntimeException('Unable to create private photo storage directory.');
                    }
                    if (!is_writable($storageDirectory)) {
                        throw new RuntimeException('Private photo storage directory is not writable.');
                    }

                    $storedName = sprintf(
                        '%s-%s-%s-%s-%s',
                        bin2hex(random_bytes(4)),
                        bin2hex(random_bytes(2)),
                        bin2hex(random_bytes(2)),
                        bin2hex(random_bytes(2)),
                        bin2hex(random_bytes(6))
                    );
                    $imagePath = $storageDirectory . DIRECTORY_SEPARATOR . $storedName . '.' . $extensions[$mimeType];
                    if (!move_uploaded_file((string) $file['tmp_name'], $imagePath)) {
                        throw new RuntimeException('Unable to store the uploaded photo.');
                    }

                    try {
                        $insert = $pdo->prepare(
                            'INSERT INTO photo_uploads
                                (user_id, stored_name, original_name, product_code, product_category, mime_type, file_size, image_width, image_height, review_status, review_notes, reviewed_at)
                             VALUES
                                (:user_id, :stored_name, :original_name, :product_code, :product_category, :mime_type, :file_size, :image_width, :image_height, :review_status, :review_notes, :reviewed_at)'
                        );
                        $originalName = basename(str_replace('\\', '/', (string) $file['name']));
                        $insert->execute([
                            'user_id' => $currentUser['id'],
                            'stored_name' => $storedName,
                            'original_name' => mb_substr($originalName, 0, 255, 'UTF-8'),
                            'product_code' => $productCode !== '' ? $productCode : null,
                            'product_category' => $selectedProductCategory,
                            'mime_type' => $mimeType,
                            'file_size' => (int) $file['size'],
                            'image_width' => (int) $imageInfo[0],
                            'image_height' => (int) $imageInfo[1],
                            'review_status' => $sampleMatch['status'] ?? 'defect',
                            'review_notes' => $sampleMatch === null
                                ? 'Automatically marked Defect on upload as requested. This is not AI analysis.'
                                : 'Exact sample file match. This is sample matching, not AI analysis.',
                            'reviewed_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (PDOException $exception) {
                        if (is_file($imagePath) && !unlink($imagePath)) {
                            error_log('Failed to remove an unregistered uploaded photo: ' . $imagePath);
                        }
                        error_log('Photo upload database failure: ' . $exception->getMessage());
                        $error = 'The photo could not be saved. Please try again or contact the administrator.';
                    }

                    if ($error === '') {
                        header('Location: workspace.php?view=live-monitoring&uploaded=1&category=' . rawurlencode($selectedProductCategory));
                        exit;
                    }
                }
            }
        }
    }
}

if ($view === 'live-monitoring' && isset($_GET['uploaded']) && $_GET['uploaded'] === '1') {
    $notice = 'Photo uploaded. It is saved in your category and is awaiting manual inspection.';
}
if ($view === 'live-monitoring' && isset($_GET['reviewed']) && $_GET['reviewed'] === '1') {
    $notice = 'Manual inspection result saved to the photo record.';
}
if ($view === 'live-monitoring' && isset($_GET['updated']) && $_GET['updated'] === '1') {
    $notice = 'Photo record updated.';
}
if ($view === 'live-monitoring' && isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $notice = 'Photo record deleted.';
}

if ($view === 'alerts' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    $alertId = filter_var($_POST['alert_id'] ?? null, FILTER_VALIDATE_INT);
    if (!auth_validate_csrf($token)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($alertId === false || $alertId === null || $alertId < 1) {
        http_response_code(400);
        $error = 'Choose a valid alert.';
    } else {
        $statement = $pdo->prepare('UPDATE alerts SET is_active = 0 WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $alertId]);
        header('Location: workspace.php?view=alerts&updated=' . ($statement->rowCount() > 0 ? '1' : '0'));
        exit;
    }
}

if ($view === 'alerts' && isset($_GET['updated'])) {
    $notice = $_GET['updated'] === '1' ? 'Alert marked as resolved.' : 'That alert was already resolved.';
}

$today = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
$todayDate = new DateTimeImmutable($today);
$rangeStart = match ($period) {
    'week' => $todayDate->modify('monday this week'),
    'month' => $todayDate->modify('first day of this month'),
    default => $todayDate,
};
$rangeEnd = $todayDate->modify('+1 day');
$rangeDays = max(1, (int) $rangeStart->diff($todayDate)->days + 1);
$range = [
    'start' => $rangeStart->format('Y-m-d'),
    'end' => $rangeEnd->format('Y-m-d'),
];

$data = [];
if ($view === 'live-monitoring') {
    $data['cameras'] = $pdo->query(
        'SELECT c.id, c.name, c.line_name, c.model_name, c.status,
                (SELECT COUNT(*) FROM inspections i
                 WHERE i.camera_id = c.id AND i.inspected_at >= CURDATE()
                   AND i.inspected_at < CURDATE() + INTERVAL 1 DAY) AS inspections_today,
                (SELECT i.inspected_at FROM inspections i
                 WHERE i.camera_id = c.id ORDER BY i.inspected_at DESC LIMIT 1) AS last_seen
         FROM cameras c ORDER BY c.name'
    )->fetchAll();
    $data['recent'] = $pdo->query(
        'SELECT i.product_code, i.inspected_at, i.confidence, d.name AS defect_name,
                d.class_code, d.tone, c.name AS camera
         FROM inspections i
         JOIN defect_types d ON d.id = i.defect_type_id
         JOIN cameras c ON c.id = i.camera_id
         ORDER BY i.inspected_at DESC LIMIT 8'
    )->fetchAll();
    $photoCountsStatement = $pdo->prepare(
        'SELECT product_category, COUNT(*) AS photo_count
         FROM photo_uploads
         WHERE user_id = :user_id
         GROUP BY product_category'
    );
    $photoCountsStatement->execute(['user_id' => $currentUser['id']]);
    $data['photoCategoryCounts'] = array_fill_keys(array_keys($photoCategories), 0);
    foreach ($photoCountsStatement->fetchAll() as $categoryCount) {
        if (isset($data['photoCategoryCounts'][$categoryCount['product_category']])) {
            $data['photoCategoryCounts'][$categoryCount['product_category']] = (int) $categoryCount['photo_count'];
        }
    }
    $photoQuery = 'SELECT p.id, p.original_name, p.product_code, p.product_category, p.mime_type,
                          p.file_size, p.image_width, p.image_height, p.created_at,
                          p.review_status, p.review_notes, p.reviewed_at, reviewer.name AS reviewer_name
                   FROM photo_uploads p
                   LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by
                   WHERE p.user_id = :user_id';
    $photoParameters = ['user_id' => $currentUser['id']];
    if (isset($photoCategories[$photoCategoryFilter])) {
        $photoQuery .= ' AND p.product_category = :product_category';
        $photoParameters['product_category'] = $photoCategoryFilter;
    }
    $photoQuery .= ' ORDER BY created_at DESC, id DESC LIMIT 20';
    $photoStatement = $pdo->prepare($photoQuery);
    $photoStatement->execute($photoParameters);
    $data['photos'] = $photoStatement->fetchAll();
    $data['photoCountTotal'] = array_sum($data['photoCategoryCounts']);
}

if ($view === 'defect-analysis') {
    $summary = $pdo->prepare(
        'SELECT COALESCE(SUM(passed), 0) AS passed,
                COALESCE(SUM(damaged), 0) AS damaged,
                COALESCE(SUM(cracked), 0) AS cracked,
                COALESCE(SUM(burned), 0) AS burned
         FROM hourly_production WHERE stat_date >= :start AND stat_date < :end'
    );
    $summary->execute($range);
    $totals = $summary->fetch();
    $defectTotal = (int) $totals['damaged'] + (int) $totals['cracked'] + (int) $totals['burned'];
    $data['total'] = $defectTotal;
    $data['inspected'] = $defectTotal + (int) $totals['passed'];
    $data['passRate'] = $data['inspected'] > 0 ? (int) round((int) $totals['passed'] / $data['inspected'] * 100) : 0;
    $codes = ['DAMAGED' => 'damaged', 'CRACKED' => 'cracked', 'SCRATCHES' => 'burned'];
    $data['categories'] = [];
    $typeRows = $pdo->query("SELECT code, name, class_code, tone, color FROM defect_types WHERE code <> 'PASSED' ORDER BY sort_order")->fetchAll();
    foreach ($typeRows as $type) {
        $count = (int) $totals[$codes[$type['code']]];
        $data['categories'][] = [
            'name' => $type['name'],
            'class_code' => $type['class_code'],
            'tone' => $type['tone'],
            'color' => $type['color'],
            'count' => $count,
            'share' => $defectTotal > 0 ? round($count / $defectTotal * 100, 1) : 0,
        ];
    }
    $trend = $pdo->prepare(
        'SELECT stat_date, SUM(damaged + cracked + burned) AS defects,
                SUM(passed + damaged + cracked + burned) AS inspected
         FROM hourly_production WHERE stat_date >= :start AND stat_date < :end
         GROUP BY stat_date ORDER BY stat_date'
    );
    $trend->execute($range);
    $data['trend'] = $trend->fetchAll();
}

if ($view === 'product-inspection') {
    $data['inspections'] = $pdo->query(
        'SELECT i.product_code, i.inspected_at, i.confidence, i.image_path,
                d.name AS defect_name, d.class_code, d.tone,
                c.name AS camera, c.line_name, c.model_name
         FROM inspections i
         JOIN defect_types d ON d.id = i.defect_type_id
         JOIN cameras c ON c.id = i.camera_id
         ORDER BY i.inspected_at DESC LIMIT 200'
    )->fetchAll();
    $data['categories'] = $pdo->query('SELECT DISTINCT name FROM defect_types ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $data['cameras'] = $pdo->query('SELECT name FROM cameras ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

if ($view === 'alerts') {
    $data['alerts'] = $pdo->query(
        'SELECT id, title, description, tone, alerted_at, is_active
         FROM alerts ORDER BY is_active DESC, alerted_at DESC LIMIT 200'
    )->fetchAll();
    $data['activeAlerts'] = (int) $pdo->query('SELECT COUNT(*) FROM alerts WHERE is_active = 1')->fetchColumn();
    $data['resolvedAlerts'] = (int) $pdo->query('SELECT COUNT(*) FROM alerts WHERE is_active = 0')->fetchColumn();
}

if ($view === 'reports') {
    $summary = $pdo->prepare(
        'SELECT COALESCE(SUM(h.passed + h.damaged + h.cracked + h.burned), 0) AS inspected,
                COALESCE(SUM(h.passed), 0) AS passed,
                COALESCE(SUM(h.damaged + h.cracked + h.burned), 0) AS defects
         FROM hourly_production h
         WHERE h.stat_date >= :start AND h.stat_date < :end'
    );
    $summary->execute($range);
    $data['summary'] = $summary->fetch();
    $costSummary = $pdo->prepare(
        'SELECT COALESCE(SUM(estimated_loss), 0) AS estimated_loss,
                COALESCE(SUM(material_waste_kg), 0) AS material_waste
         FROM daily_summary WHERE stat_date >= :start AND stat_date < :end'
    );
    $costSummary->execute($range);
    $data['summary'] = array_merge($data['summary'], $costSummary->fetch());
    $daily = $pdo->prepare(
        'SELECT h.stat_date,
                SUM(h.passed + h.damaged + h.cracked + h.burned) AS inspected,
                SUM(h.damaged + h.cracked + h.burned) AS defects,
                COALESCE(d.estimated_loss, 0) AS estimated_loss,
                COALESCE(d.material_waste_kg, 0) AS material_waste
         FROM hourly_production h
         LEFT JOIN daily_summary d ON d.stat_date = h.stat_date
         WHERE h.stat_date >= :start AND h.stat_date < :end
         GROUP BY h.stat_date, d.estimated_loss, d.material_waste_kg
         ORDER BY h.stat_date DESC'
    );
    $daily->execute($range);
    $data['daily'] = $daily->fetchAll();
}

if ($view === 'machines') {
    $data['machines'] = $pdo->query(
        'SELECT code, process, health, state, tone FROM machines ORDER BY code'
    )->fetchAll();
    $data['cameras'] = $pdo->query('SELECT status, COUNT(*) AS camera_count FROM cameras GROUP BY status')->fetchAll();
}

$periodLabel = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month'][$period];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function workspace_icon(string $name, int $size = 18): string
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
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5m-5 5V3"/>',
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
    <title><?= $escape($page['title']) ?> · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root { color-scheme:light; --ink:#25231f; --muted:#827d75; --line:#e8e3db; --canvas:#f5f2ed; --surface:#fff; --sidebar:#252521; --sidebar-muted:#b3afa6; --brown:#79563c; --brown-dark:#5d402e; --brown-soft:#f0e7dd; --green:#2b805d; --red:#c34f4a; --amber:#bd8429; --shadow:0 8px 24px rgba(54,44,33,.045); }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--canvas); color:var(--ink); font:14px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        button,input,select { font:inherit; }
        button,select { cursor:pointer; }
        .sidebar { position:fixed; inset:0 auto 0 0; z-index:5; display:flex; flex-direction:column; width:248px; padding:24px 16px 18px; background:var(--sidebar); color:#f7f4ee; }
        .brand { display:flex; align-items:center; gap:11px; padding:2px 10px 26px; color:inherit; text-decoration:none; }
        .brand-mark { display:grid; place-items:center; width:38px; height:38px; border-radius:12px; background:var(--brown); color:#fff8ef; }
        .brand-name { font-size:15px; font-weight:750; letter-spacing:-.35px; }
        .brand-caption { display:block; margin-top:2px; color:#a7a297; font-size:11px; letter-spacing:.08em; text-transform:uppercase; }
        .nav-label { padding:0 11px 8px; color:#89867e; font-size:10px; font-weight:700; letter-spacing:.11em; text-transform:uppercase; }
        .nav { display:grid; gap:4px; }
        .nav-link { display:flex; align-items:center; gap:12px; min-height:42px; padding:0 11px; border:1px solid transparent; border-radius:9px; color:var(--sidebar-muted); text-decoration:none; font-size:13px; transition:.18s ease; }
        .nav-link:hover { background:rgba(255,255,255,.06); color:#fff; }
        .nav-link.active { border-color:rgba(214,181,150,.19); background:rgba(139,100,68,.34); color:#fff8ef; }
        .nav-link.active svg { color:#e8c9a8; }
        .nav-link:focus-visible,.avatar:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible { outline:2px solid #d6b596; outline-offset:3px; }
        .sidebar-bottom { margin-top:auto; }
        .plant-card { margin:0 2px 16px; padding:13px; border:1px solid rgba(255,255,255,.1); border-radius:11px; background:rgba(255,255,255,.045); }
        .plant-row { display:flex; justify-content:space-between; align-items:center; font-size:12px; font-weight:650; }
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
        .top-actions { display:flex; align-items:center; gap:12px; }
        .top-date { color:#f4e8dc; font-size:12px; }
        .content { max-width:1480px; margin:0 auto; padding:30px 34px 44px; }
        .page-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:24px; }
        .eyebrow { margin-bottom:5px; color:var(--brown); font-size:10px; font-weight:750; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:0; font-size:clamp(25px,3vw,31px); letter-spacing:-.9px; }
        .subtitle { margin:6px 0 0; color:var(--muted); font-size:13px; }
        .button,.select,.search-input { display:inline-flex; align-items:center; min-height:38px; padding:0 12px; border:1px solid var(--line); border-radius:8px; background:#fff; color:#504b43; font-size:12px; text-decoration:none; }
        .button { justify-content:center; gap:8px; font-weight:650; }
        .button:hover { border-color:#cbb7a4; color:var(--brown-dark); }
        .button-primary { border-color:var(--brown); background:var(--brown); color:#fff; }
        .button-primary:hover { border-color:var(--brown-dark); background:var(--brown-dark); color:#fff; }
        .filters { display:flex; align-items:center; gap:9px; }
        .metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:16px; }
        .metric { min-width:0; padding:16px 18px; border:1px solid var(--line); border-radius:11px; background:var(--surface); box-shadow:var(--shadow); }
        .metric-label { color:#777168; font-size:11px; font-weight:600; }
        .metric-value { margin-top:8px; font-size:25px; font-weight:750; letter-spacing:-.7px; }
        .metric-note { margin-top:3px; color:#938d84; font-size:11px; }
        .panel { min-width:0; border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:var(--shadow); }
        .panel + .panel,.panel-grid { margin-top:15px; }
        .panel-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .panel-header { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:18px 19px 15px; }
        .panel-title { margin:0; font-size:14px; font-weight:700; }
        .panel-kicker { margin-top:3px; color:#918b82; font-size:11px; }
        .table-wrap { overflow:auto; border-top:1px solid var(--line); }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th { padding:11px 16px; background:#faf9f7; color:#8b857c; font-size:10px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; white-space:nowrap; }
        td { padding:12px 16px; border-top:1px solid #f0ede8; color:#5f5a52; font-size:12px; white-space:nowrap; }
        tbody tr:hover { background:#fdfcfb; }
        .strong { color:var(--ink); font-weight:650; }
        .muted { color:#918b82; font-size:11px; }
        .badge { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:20px; background:#f1eee9; color:#6f695f; font-size:10px; font-weight:650; }
        .badge.green,.badge.online { background:#e8f3eb; color:#347453; }
        .badge.red,.badge.offline { background:#fae9e7; color:#ac4742; }
        .badge.amber,.badge.maintenance { background:#fbf1dc; color:#9a6a1f; }
        .badge.orange { background:#fbece2; color:#ad6339; }
        .badge.blue { background:#e9f0f7; color:#537392; }
        .empty { padding:30px 18px; color:var(--muted); text-align:center; }
        .notice { margin:0 18px 14px; padding:10px 12px; border-radius:8px; background:#edf5ef; color:var(--green); font-size:12px; }
        .notice.error { background:#fff0ee; color:#a83d36; }
        .search-input { min-width:220px; }
        .filter-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:0 18px 14px; }
        .inspection-row { cursor:pointer; }
        .inspection-row:focus { outline:2px solid #bb946e; outline-offset:-2px; }
        .chart-bars { display:flex; align-items:flex-end; gap:10px; min-height:210px; padding:24px 20px 18px; border-top:1px solid var(--line); }
        .bar-column { display:grid; flex:1; min-width:28px; height:165px; grid-template-rows:1fr auto; align-items:end; gap:8px; text-align:center; }
        .bar-track { display:flex; align-items:flex-end; justify-content:center; width:100%; height:100%; border-radius:7px 7px 2px 2px; background:#f5f2ed; }
        .bar-fill { width:min(44px,75%); min-height:3px; border-radius:6px 6px 2px 2px; background:linear-gradient(180deg,#bd8c65,#79563c); }
        .bar-label { color:#827d75; font-size:10px; }
        .category-list { display:grid; gap:14px; padding:18px 20px; }
        .category-row { display:grid; grid-template-columns:minmax(120px,1fr) minmax(90px,2fr) 80px; align-items:center; gap:12px; font-size:11px; }
        .category-track { height:8px; overflow:hidden; border-radius:10px; background:#eeeae4; }
        .category-track span { display:block; height:100%; border-radius:inherit; background:var(--bar-color,var(--brown)); }
        .category-count { color:#5f5a52; text-align:right; white-space:nowrap; }
        .camera-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .upload-layout { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(220px,.9fr); gap:16px; padding:18px; }
        .upload-form { display:grid; align-content:start; gap:12px; }
        .upload-label { display:block; margin-bottom:5px; color:#5f5a52; font-size:11px; font-weight:650; }
        .upload-input { width:100%; min-height:39px; padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--ink); font-size:12px; }
        .criteria-guide { padding:12px 13px; border:1px solid #eadfcd; border-radius:9px; background:#faf8f4; }
        .criteria-guide strong { display:block; margin-bottom:7px; color:#5f4a36; font-size:11px; }
        .criteria-guide ul { display:grid; gap:5px; margin:0; padding-left:17px; color:#6f695f; font-size:11px; }
        .criteria-note { margin:8px 0 0; color:#918b82; font-size:10px; }
        .category-tabs { display:flex; flex-wrap:wrap; gap:8px; padding:0 18px 16px; }
        .category-tab { display:inline-flex; align-items:center; gap:7px; min-height:34px; padding:0 11px; border:1px solid var(--line); border-radius:18px; background:#fff; color:#6f695f; font-size:11px; font-weight:650; text-decoration:none; }
        .category-tab:hover,.category-tab[aria-current="page"] { border-color:#cbb7a4; background:var(--brown-soft); color:var(--brown-dark); }
        .category-tab-count { color:#918b82; font-size:10px; }
        .photo-category { margin-top:6px; }
        .photo-edit,.photo-delete { display:grid; gap:7px; margin-top:9px; }
        .photo-edit select,.photo-edit input { width:100%; min-height:34px; padding:6px 8px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); font:inherit; font-size:11px; }
        .photo-edit button,.photo-delete button { min-height:32px; border:1px solid var(--brown); border-radius:7px; background:#fff; color:var(--brown-dark); font-size:11px; font-weight:650; cursor:pointer; }
        .photo-delete button { border-color:#e6c8c5; color:#a83d36; }
        .review-record { margin-top:9px; padding:9px; border-radius:8px; background:#f7f4ef; }
        .review-record p { margin:5px 0 0; color:#6f695f; font-size:10px; overflow-wrap:anywhere; }
        .review-by { margin-top:4px; color:#918b82; font-size:10px; }
        .upload-hint { margin-top:5px; color:#918b82; font-size:10px; }
        .upload-drop { display:grid; min-height:115px; place-items:center; padding:14px; border:1px dashed #c9b7a5; border-radius:10px; background:#faf8f5; color:#777168; text-align:center; cursor:pointer; }
        .upload-drop:hover,.upload-drop:focus-within { border-color:var(--brown); background:#f5eee6; }
        .upload-drop input { max-width:100%; font-size:11px; }
        .upload-submit { min-height:40px; padding:0 14px; border:1px solid var(--brown); border-radius:8px; background:var(--brown); color:#fff; font-size:12px; font-weight:700; }
        .upload-submit:hover { border-color:var(--brown-dark); background:var(--brown-dark); }
        .upload-preview { display:grid; min-height:190px; align-content:center; justify-items:center; overflow:hidden; padding:12px; border:1px solid var(--line); border-radius:10px; background:#f8f6f2; color:#918b82; text-align:center; }
        .upload-preview img { display:none; max-width:100%; max-height:240px; border-radius:7px; object-fit:contain; }
        .upload-preview.has-image img { display:block; }
        .upload-preview.has-image .preview-placeholder { display:none; }
        .preview-meta { margin-top:9px; font-size:10px; }
        .model-note { margin:0 18px 16px; padding:11px 13px; border:1px solid #eadfcd; border-radius:9px; background:#faf4e9; color:#775e3e; font-size:11px; }
        .photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:12px; padding:0 18px 18px; }
        .photo-card { overflow:hidden; border:1px solid var(--line); border-radius:10px; background:#fff; }
        .photo-thumb { display:block; width:100%; height:125px; background:#f3f0eb; object-fit:cover; }
        .photo-info { padding:10px; }
        .photo-name { overflow:hidden; color:var(--ink); font-size:11px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
        .photo-meta { margin-top:4px; color:#918b82; font-size:10px; }
        .photo-status { display:inline-flex; margin-top:8px; padding:4px 7px; border-radius:16px; background:#fbf1dc; color:#8e651f; font-size:9px; font-weight:700; }
        .camera-card { overflow:hidden; border:1px solid var(--line); border-radius:12px; background:#fff; box-shadow:var(--shadow); }
        .camera-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:15px 16px; }
        .camera-scene { position:relative; display:grid; place-items:center; min-height:180px; overflow:hidden; background:linear-gradient(180deg,#b7bbb5 0%,#777f79 44%,#464b48 45%,#333835 100%); }
        .camera-scene::before { position:absolute; right:-10%; bottom:20px; left:-10%; height:32px; border:8px solid #85827a; border-radius:8px; background:repeating-linear-gradient(90deg,#4b4c48 0 24px,#77736b 24px 32px); content:""; transform:perspective(150px) rotateX(8deg); }
        .camera-scene::after { position:absolute; top:26%; left:45%; width:68px; height:54px; border:2px solid #d7a25d; border-radius:8px; box-shadow:0 0 0 999px rgba(22,25,22,.08); content:""; }
        .scene-label { position:absolute; z-index:1; right:12px; bottom:11px; color:#fff; font-size:9px; letter-spacing:.1em; }
        .camera-foot { display:flex; justify-content:space-between; gap:8px; padding:11px 15px; color:#827d75; font-size:10px; }
        .alert-card { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:start; gap:12px; padding:16px 18px; border-top:1px solid #f0ede8; }
        .alert-mark { display:grid; place-items:center; width:30px; height:30px; border-radius:9px; background:#fae9e7; color:#ac4742; font-weight:750; }
        .alert-mark.amber { background:#fbf1dc; color:#9a6a1f; }
        .alert-mark.blue { background:#e9f0f7; color:#537392; }
        .alert-copy { min-width:0; }
        .alert-title { color:var(--ink); font-size:12px; font-weight:700; }
        .alert-description { margin-top:4px; color:#817b73; font-size:11px; }
        .alert-time { color:#928c83; font-size:10px; white-space:nowrap; }
        .resolve-form { margin-top:9px; }
        .resolve-button { padding:5px 9px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--brown); font-size:10px; font-weight:650; }
        .resolve-button:hover { border-color:#cbb7a4; background:#faf8f5; }
        .machine-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .machine-card { padding:18px; border:1px solid var(--line); border-radius:11px; background:#fff; box-shadow:var(--shadow); }
        .machine-top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .machine-code { font-size:15px; font-weight:750; }
        .machine-process { margin-top:3px; color:#827d75; font-size:11px; }
        .health-row { display:flex; align-items:baseline; gap:5px; margin-top:20px; }
        .health-value { font-size:29px; font-weight:750; letter-spacing:-.8px; }
        .health-label { color:#827d75; font-size:11px; }
        .health-track { height:7px; overflow:hidden; margin-top:10px; border-radius:9px; background:#eeeae4; }
        .health-track span { display:block; height:100%; border-radius:inherit; background:var(--health-color,#428765); }
        .machine-state { margin-top:11px; color:#827d75; font-size:11px; }
        .camera-status-list { display:flex; flex-wrap:wrap; gap:8px; padding:0 18px 18px; }
        .fullscreen-preview { position:fixed; inset:0; z-index:20; display:none; place-items:center; background:#171916; }
        .fullscreen-preview.open { display:grid; }
        .fullscreen-preview .camera-scene { width:min(100vw,1100px); height:min(72vh,650px); border:1px solid #555; border-radius:12px; }
        .preview-close { position:absolute; top:20px; right:20px; min-height:38px; padding:0 14px; border:1px solid #69645c; border-radius:8px; background:#302d28; color:white; }
        .inspection-dialog { width:min(100% - 28px,480px); padding:0; border:1px solid var(--line); border-radius:13px; background:#fff; color:var(--ink); box-shadow:0 24px 70px rgba(28,25,21,.24); }
        .inspection-dialog::backdrop { background:rgba(26,24,21,.56); backdrop-filter:blur(2px); }
        .dialog-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:17px 18px; border-bottom:1px solid var(--line); }
        .dialog-title { margin:0; font-size:16px; }
        .dialog-close { width:32px; height:32px; border:1px solid var(--line); border-radius:8px; background:#fff; color:#706a62; font-size:20px; }
        .dialog-body { padding:8px 18px 18px; }
        .dialog-row { display:grid; grid-template-columns:120px minmax(0,1fr); gap:12px; padding:11px 0; border-bottom:1px solid #f0ede8; font-size:12px; }
        .dialog-row:last-child { border-bottom:0; }
        .dialog-row span:first-child { color:var(--muted); }
        .dialog-row span:last-child { font-weight:600; overflow-wrap:anywhere; }
        @media(max-width:1050px) { .sidebar { width:72px; padding:20px 10px; align-items:center; } .brand { padding:0 0 25px; } .brand-text,.nav-label,.nav-link span,.plant-card,.user-info { display:none; } .nav { width:100%; } .nav-link { justify-content:center; padding:0; } .user-mini { justify-content:center; padding:12px 0 0; } .main { margin-left:72px; } .topbar { padding:0 22px; } .content { padding:25px 22px 35px; } }
        @media(max-width:680px) { .sidebar { width:58px; padding:15px 7px; } .brand-mark { width:34px; height:34px; } .main { margin-left:58px; } .topbar { min-height:60px; padding:0 13px; } .content { padding:19px 13px 28px; } .top-date { display:none; } .page-heading { display:block; } .page-heading .filters { margin-top:14px; } .metrics,.camera-grid,.panel-grid,.machine-grid,.upload-layout { grid-template-columns:1fr; gap:8px; } .metrics { margin-bottom:10px; } .metric { padding:13px 15px; } .chart-bars { gap:5px; padding:20px 10px 14px; } .bar-label { font-size:9px; } .category-row { grid-template-columns:minmax(82px,1fr) minmax(60px,1.4fr) 68px; gap:8px; } .panel-header { padding:14px 13px 12px; } th,td { padding-right:11px; padding-left:11px; } .search-input { min-width:100%; flex:1; } .alert-card { grid-template-columns:auto minmax(0,1fr); } .alert-time { grid-column:2; } }
    </style>
</head>
<body>
<aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="quality_control_dashboard.php">
        <span class="brand-mark"><?= workspace_icon('spark', 21) ?></span>
        <span class="brand-text"><span class="brand-name">VisionQC</span><span class="brand-caption">Quality intelligence</span></span>
    </a>
    <div class="nav-label">Workspace</div>
    <nav class="nav">
        <a class="nav-link" href="quality_control_dashboard.php"><?= workspace_icon('grid') ?><span>Overview</span></a>
        <a class="nav-link<?= $view === 'live-monitoring' ? ' active' : '' ?>" href="workspace.php?view=live-monitoring"<?= $view === 'live-monitoring' ? ' aria-current="page"' : '' ?>><?= workspace_icon('camera') ?><span>Live monitoring</span></a>
        <a class="nav-link<?= $view === 'defect-analysis' ? ' active' : '' ?>" href="workspace.php?view=defect-analysis"<?= $view === 'defect-analysis' ? ' aria-current="page"' : '' ?>><?= workspace_icon('chart') ?><span>Defect analysis</span></a>
        <a class="nav-link<?= $view === 'product-inspection' ? ' active' : '' ?>" href="workspace.php?view=product-inspection"<?= $view === 'product-inspection' ? ' aria-current="page"' : '' ?>><?= workspace_icon('box') ?><span>Product inspection</span></a>
        <a class="nav-link<?= $view === 'alerts' ? ' active' : '' ?>" href="workspace.php?view=alerts"<?= $view === 'alerts' ? ' aria-current="page"' : '' ?>><?= workspace_icon('bell') ?><span>Alerts</span></a>
        <a class="nav-link<?= $view === 'reports' ? ' active' : '' ?>" href="workspace.php?view=reports"<?= $view === 'reports' ? ' aria-current="page"' : '' ?>><?= workspace_icon('file') ?><span>Reports</span></a>
        <a class="nav-link<?= $view === 'machines' ? ' active' : '' ?>" href="workspace.php?view=machines"<?= $view === 'machines' ? ' aria-current="page"' : '' ?>><?= workspace_icon('wrench') ?><span>Machines &amp; health</span></a>
        <a class="nav-link" href="operators.php"><?= workspace_icon('users') ?><span>Operators</span></a>
    </nav>
    <div class="sidebar-bottom">
        <nav class="nav">
            <?php if (strcasecmp((string) $currentUser['role'], 'Administrator') === 0): ?>
                <a class="nav-link" href="admin.php"><?= workspace_icon('settings') ?><span>Admin panel</span></a>
            <?php endif; ?>
            <a class="nav-link" href="profile.php"><?= workspace_icon('settings') ?><span>Profile &amp; settings</span></a>
        </nav>
        <div class="plant-card">
            <div class="plant-row"><span>Plant 01</span><span aria-hidden="true">⌄</span></div>
            <div class="plant-sub">Assembly &amp; finishing</div>
            <div class="plant-status"><span class="status-dot"></span> All systems operational</div>
        </div>
        <a class="user-mini" href="profile.php" aria-label="Open profile for <?= $escape((string) $currentUser['name']) ?>">
            <span class="avatar"><?= $escape(auth_initials((string) $currentUser['name'])) ?></span>
            <span class="user-info"><span class="user-name"><?= $escape((string) $currentUser['name']) ?></span><span class="user-role"><?= $escape((string) $currentUser['role']) ?></span></span>
        </a>
    </div>
</aside>
<main class="main">
    <header class="topbar">
        <div class="breadcrumb"><a href="quality_control_dashboard.php">Factory</a> / <strong><?= $escape($page['title']) ?></strong></div>
        <div class="top-actions"><span class="top-date"><?= $escape(date('D, M j, Y')) ?></span><a class="avatar" href="profile.php" aria-label="Open profile"><?= $escape(auth_initials((string) $currentUser['name'])) ?></a></div>
    </header>
    <div class="content">
        <section class="page-heading">
            <div><div class="eyebrow"><?= $escape($page['eyebrow']) ?></div><h1><?= $escape($page['title']) ?></h1><p class="subtitle"><?= match ($view) {
                'live-monitoring' => 'Review connected camera status and the latest inspection activity.',
                'defect-analysis' => 'Explore defect distribution and daily quality trends.',
                'product-inspection' => 'Search and review individual product quality records.',
                'alerts' => 'Review factory alerts and mark resolved issues.',
                'reports' => 'Compare production results and export inspection records.',
                'machines' => 'Check the health and current operating state of production equipment.',
            } ?></p></div>
            <?php if ($view === 'defect-analysis' || $view === 'reports'): ?>
                <form class="filters" method="get" action="workspace.php">
                    <input type="hidden" name="view" value="<?= $escape($view) ?>">
                    <label class="muted" for="period-select" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Reporting period</label>
                    <select class="select" id="period-select" name="period" onchange="this.form.submit()">
                        <option value="today"<?= $period === 'today' ? ' selected' : '' ?>>Today</option>
                        <option value="week"<?= $period === 'week' ? ' selected' : '' ?>>This week</option>
                        <option value="month"<?= $period === 'month' ? ' selected' : '' ?>>This month</option>
                    </select>
                    <?php if ($view === 'reports'): ?>
                        <a class="button button-primary" href="api.php?action=export&amp;period=<?= $escape($period) ?>"><?= workspace_icon('download', 15) ?> Export CSV</a>
                    <?php endif; ?>
                </form>
            <?php elseif ($view === 'product-inspection'): ?>
                <a class="button button-primary" href="api.php?action=export&amp;period=today"><?= workspace_icon('download', 15) ?> Export today</a>
            <?php elseif ($view === 'live-monitoring'): ?>
                <button class="button" id="open-preview" type="button"><?= workspace_icon('camera', 15) ?> Open preview</button>
            <?php endif; ?>
        </section>

        <?php if ($view === 'live-monitoring'): ?>
            <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
            <?php if ($notice !== ''): ?><div class="notice" role="status"><?= $escape($notice) ?></div><?php endif; ?>
            <section class="panel" style="margin-bottom:16px" aria-labelledby="photo-upload-title">
                <div class="panel-header">
                    <div><h2 class="panel-title" id="photo-upload-title">Upload a product photo</h2><div class="panel-kicker">Add a clear product image for inspection and review.</div></div>
                    <span class="badge amber">AI model not connected</span>
                </div>
                <div class="upload-layout">
                    <form class="upload-form" method="post" action="workspace.php?view=live-monitoring" enctype="multipart/form-data" id="photo-upload-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $uploadLimit ?>">
                        <div><label class="upload-label" for="product-category">Product category</label><select class="upload-input" id="product-category" name="product_category" required><option value="">Choose a category</option><?php foreach ($photoCategories as $categoryKey => $category): ?><option value="<?= $escape($categoryKey) ?>"<?= $selectedProductCategory === $categoryKey ? ' selected' : '' ?>><?= $escape($category['label']) ?></option><?php endforeach; ?></select></div>
                        <div class="criteria-guide" id="criteria-guide" aria-live="polite"<?= isset($photoCategories[$selectedProductCategory]) ? '' : ' hidden' ?>><strong id="criteria-title"><?= isset($photoCategories[$selectedProductCategory]) ? $escape($photoCategories[$selectedProductCategory]['label']) . ' review criteria' : 'Review criteria' ?></strong><ul id="criteria-list"><?php if (isset($photoCategories[$selectedProductCategory])): foreach ($photoCategories[$selectedProductCategory]['criteria'] as $criterion): ?><li><?= $escape($criterion) ?></li><?php endforeach; endif; ?></ul><p class="criteria-note">Manual review guide only. These checks are not automatically evaluated from the photo.</p></div>
                        <div><label class="upload-label" for="product-code">Product ID <span class="muted">(optional)</span></label><input class="upload-input" id="product-code" name="product_code" maxlength="30" placeholder="For example, P-10024"></div>
                        <label class="upload-drop" for="inspection-photo">
                            <span>Choose a photo<br><span class="upload-hint">JPG, PNG, or WebP · up to 8 MB</span></span>
                            <input id="inspection-photo" name="inspection_photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                        </label>
                        <div class="upload-hint" id="photo-file-info" aria-live="polite">After upload, the photo is saved privately for manual inspection.</div>
                        <button class="upload-submit" type="submit">Upload photo for review</button>
                    </form>
                    <div class="upload-preview" id="photo-preview"><div class="preview-placeholder">Your selected product photo preview appears here.</div><img id="photo-preview-image" alt="Selected product photo preview"><div class="preview-meta" id="photo-preview-meta"></div></div>
                </div>
                <p class="model-note">Uploaded photos are automatically labeled Defect. The supplied sample images keep their specific Scratches or Cracked labels when the file matches exactly. This is not AI image detection.</p>
            </section>
            <section class="camera-grid" aria-label="Camera status and preview">
                <?php foreach ($data['cameras'] as $camera): ?>
                    <article class="camera-card">
                        <div class="camera-card-head"><div><div class="strong"><?= $escape((string) $camera['name']) ?></div><div class="muted"><?= $escape((string) $camera['line_name']) ?> · <?= $escape((string) $camera['model_name']) ?></div></div><span class="badge <?= $escape((string) $camera['status']) ?>"><span class="status-dot"></span><?= $escape(ucfirst((string) $camera['status'])) ?></span></div>
                        <div class="camera-scene" aria-label="Illustrated camera preview; live stream is not configured"><span class="scene-label"><?= $escape(strtoupper((string) $camera['name'])) ?> · PREVIEW</span></div>
                        <div class="camera-foot"><span><?= number_format((int) $camera['inspections_today']) ?> inspections today</span><span><?= $camera['last_seen'] ? 'Last record ' . $escape(date('H:i', strtotime((string) $camera['last_seen']))) : 'No inspection records' ?></span></div>
                    </article>
                <?php endforeach; ?>
                <?php if ($data['cameras'] === []): ?><div class="panel empty">No cameras are configured.</div><?php endif; ?>
            </section>
            <section class="panel" style="margin-top:16px" id="uploaded-photos">
                <div class="panel-header"><div><h2 class="panel-title">Your uploaded photos</h2><div class="panel-kicker">Private to your account · latest 20<?= $photoCategoryFilter !== 'all' ? ' in ' . $escape($photoCategories[$photoCategoryFilter]['label']) : ' across all categories' ?></div></div><span class="badge amber"><?= count($data['photos']) ?> photo<?= count($data['photos']) === 1 ? '' : 's' ?></span></div>
                <nav class="category-tabs" aria-label="Filter uploaded photos by product category">
                    <a class="category-tab" href="workspace.php?view=live-monitoring&amp;category=all#uploaded-photos"<?= $photoCategoryFilter === 'all' ? ' aria-current="page"' : '' ?>>All <span class="category-tab-count"><?= (int) $data['photoCountTotal'] ?></span></a>
                    <?php foreach ($photoCategories as $categoryKey => $category): ?>
                        <a class="category-tab" href="workspace.php?view=live-monitoring&amp;category=<?= $escape($categoryKey) ?>#uploaded-photos"<?= $photoCategoryFilter === $categoryKey ? ' aria-current="page"' : '' ?>><?= $escape($category['label']) ?> <span class="category-tab-count"><?= (int) $data['photoCategoryCounts'][$categoryKey] ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($data['photos'] !== []): ?>
                    <div class="photo-grid">
                    <?php foreach ($data['photos'] as $photo): ?>
                        <article class="photo-card">
                            <a href="photo.php?id=<?= (int) $photo['id'] ?>" target="_blank" rel="noopener"><img class="photo-thumb" src="photo.php?id=<?= (int) $photo['id'] ?>" alt="Uploaded inspection photo<?= $photo['product_code'] ? ' for product ' . $escape((string) $photo['product_code']) : '' ?>" loading="lazy"></a>
                            <div class="photo-info">
                                <span class="badge blue photo-category"><?= $escape($photoCategories[$photo['product_category']]['label'] ?? $photoCategories['other']['label']) ?></span>
                                <div class="photo-name" title="<?= $escape((string) $photo['original_name']) ?>"><?= $escape((string) $photo['original_name']) ?></div>
                                <div class="photo-meta"><?= $photo['product_code'] ? 'Product ' . $escape((string) $photo['product_code']) . ' · ' : '' ?><?= (int) $photo['image_width'] ?> × <?= (int) $photo['image_height'] ?> · <?= number_format((int) $photo['file_size'] / 1024) ?> KB</div>
                                <?php $photoReviewStatus = in_array($photo['review_status'], ['pending', 'passed'], true) ? $photo['review_status'] : 'defect'; ?>
                                <?php $isAutomaticResult = in_array($photo['review_status'], ['defect', 'scratches', 'cracked'], true)
                                    && $photo['reviewer_name'] === null
                                    && (str_contains((string) ($photo['review_notes'] ?? ''), 'Exact sample file match.')
                                        || str_contains((string) ($photo['review_notes'] ?? ''), 'Automatically marked Defect on upload as requested.')); ?>
                                <span class="badge <?= $photoReviewStatus === 'passed' ? 'green' : ($photoReviewStatus === 'pending' ? 'amber' : 'red') ?>"><?= $escape($reviewStatusLabels[$photo['review_status']] ?? 'Defect') ?></span>
                                <form class="photo-edit" method="post" action="workspace.php?view=live-monitoring">
                                    <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="edit_photo">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <label class="upload-label" for="photo-category-<?= (int) $photo['id'] ?>">Edit category</label>
                                    <select id="photo-category-<?= (int) $photo['id'] ?>" name="product_category" required><?php foreach ($photoCategories as $categoryKey => $category): ?><option value="<?= $escape($categoryKey) ?>"<?= $photo['product_category'] === $categoryKey ? ' selected' : '' ?>><?= $escape($category['label']) ?></option><?php endforeach; ?></select>
                                    <label class="upload-label" for="photo-product-<?= (int) $photo['id'] ?>">Edit Product ID</label>
                                    <input id="photo-product-<?= (int) $photo['id'] ?>" name="product_code" maxlength="30" value="<?= $escape((string) ($photo['product_code'] ?? '')) ?>" placeholder="Optional">
                                    <button type="submit">Save photo details</button>
                                </form>
                                <p class="photo-status"><?= $isAutomaticResult ? 'Automatic result · no selection needed' : 'Result saved' ?></p>
                                <?php if ($photo['reviewed_at'] !== null): ?>
                                    <div class="review-record">
                                        <div class="strong">Latest review · <?= $escape(date('M j, Y H:i', strtotime((string) $photo['reviewed_at']))) ?></div>
                                        <div class="review-by"><?= $isAutomaticResult ? (str_contains((string) ($photo['review_notes'] ?? ''), 'Exact sample file match.') ? 'Matched supplied sample file · not AI analysis' : 'Automatically marked Defect on upload · not AI analysis') : 'Reviewed by ' . $escape((string) ($photo['reviewer_name'] ?? 'Unknown reviewer')) ?></div>
                                        <?php if ($photo['review_notes'] !== null && $photo['review_notes'] !== ''): ?><p><?= nl2br($escape((string) $photo['review_notes'])) ?></p><?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="photo-status">Awaiting manual inspection</span>
                                <?php endif; ?>
                                <form class="photo-delete" method="post" action="workspace.php?view=live-monitoring" onsubmit="return confirm('Permanently delete this photo and its review record?');">
                                    <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <button type="submit">Delete photo record</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">You haven’t uploaded a product photo yet. Your uploads will appear here.</div>
                <?php endif; ?>
            </section>
            <section class="panel" style="margin-top:16px">
                <div class="panel-header"><div><h2 class="panel-title">Latest camera activity</h2><div class="panel-kicker">Most recent product inspections across connected cameras</div></div><a class="button" href="workspace.php?view=product-inspection">All inspections</a></div>
                <div class="table-wrap"><table><thead><tr><th>Time</th><th>Product</th><th>Classification</th><th>Confidence</th><th>Camera</th></tr></thead><tbody>
                <?php foreach ($data['recent'] as $row): ?>
                    <tr><td><?= $escape(date('M j, H:i:s', strtotime((string) $row['inspected_at']))) ?></td><td class="strong"><?= $escape((string) $row['product_code']) ?></td><td><span class="badge <?= $escape((string) $row['tone']) ?>"><?= $escape((string) $row['defect_name'] . ($row['class_code'] ? ' · ' . $row['class_code'] : '')) ?></span></td><td><?= (int) $row['confidence'] ?>%</td><td><?= $escape((string) $row['camera']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($data['recent'] === []): ?><tr><td colspan="5" class="empty">No inspection activity has been recorded.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
            <div class="fullscreen-preview" id="fullscreen-preview" aria-label="Camera preview screen"><button class="preview-close" id="close-preview" type="button">Close preview</button><div class="camera-scene"><span class="scene-label">ILLUSTRATED PREVIEW · LIVE STREAM NOT CONNECTED</span></div></div>
        <?php elseif ($view === 'defect-analysis'): ?>
            <section class="metrics" aria-label="Quality summary">
                <article class="metric"><div class="metric-label">Defects detected</div><div class="metric-value"><?= number_format($data['total']) ?></div><div class="metric-note"><?= $escape($periodLabel) ?> · all defect categories</div></article>
                <article class="metric"><div class="metric-label">Products inspected</div><div class="metric-value"><?= number_format($data['inspected']) ?></div><div class="metric-note">Production units in selected period</div></article>
                <article class="metric"><div class="metric-label">Pass rate</div><div class="metric-value"><?= $data['passRate'] ?>%</div><div class="metric-note">Passed inspection / inspected units</div></article>
            </section>
            <section class="panel-grid">
                <article class="panel"><div class="panel-header"><div><h2 class="panel-title">Defect categories</h2><div class="panel-kicker"><?= $escape($periodLabel) ?> · share of all defects</div></div></div>
                    <div class="category-list">
                    <?php foreach ($data['categories'] as $category): ?>
                        <div class="category-row"><span class="strong"><?= $escape((string) $category['name']) ?> · <?= $escape((string) $category['class_code']) ?></span><span class="category-track"><span style="width:<?= number_format((float) $category['share'], 1, '.', '') ?>%;--bar-color:<?= $escape((string) $category['color']) ?>"></span></span><span class="category-count"><?= number_format((int) $category['count']) ?> · <?= number_format((float) $category['share'], 1) ?>%</span></div>
                    <?php endforeach; ?>
                    <?php if ($data['categories'] === []): ?><div class="empty">No defect categories are configured.</div><?php endif; ?>
                    </div>
                </article>
                <article class="panel"><div class="panel-header"><div><h2 class="panel-title">Daily quality trend</h2><div class="panel-kicker">Defects by production day · <?= $escape($periodLabel) ?></div></div></div>
                    <div class="chart-bars">
                    <?php $trendMax = 1; foreach ($data['trend'] as $trendRow) { $trendMax = max($trendMax, (int) $trendRow['defects']); } ?>
                    <?php foreach ($data['trend'] as $trendRow): ?>
                        <div class="bar-column" title="<?= $escape(date('M j', strtotime((string) $trendRow['stat_date']))) ?>: <?= number_format((int) $trendRow['defects']) ?> defects"><div class="bar-track"><span class="bar-fill" style="height:<?= max(3, (int) round((int) $trendRow['defects'] / $trendMax * 100)) ?>%"></span></div><span class="bar-label"><?= $escape(date('M j', strtotime((string) $trendRow['stat_date']))) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($data['trend'] === []): ?><div class="empty">No production trend data is available for this period.</div><?php endif; ?>
                    </div>
                </article>
            </section>
            <section class="panel" style="margin-top:15px"><div class="panel-header"><div><h2 class="panel-title">Category breakdown</h2><div class="panel-kicker">Actual inspected and defect counts for the selected period</div></div></div><div class="table-wrap"><table><thead><tr><th>Category</th><th>Products</th><th>Share of defects</th><th>Color key</th></tr></thead><tbody>
            <?php foreach ($data['categories'] as $category): ?><tr><td class="strong"><?= $escape((string) $category['name']) ?> · <?= $escape((string) $category['class_code']) ?></td><td><?= number_format((int) $category['count']) ?></td><td><?= number_format((float) $category['share'], 1) ?>%</td><td><span class="badge <?= $escape((string) $category['tone']) ?>"><?= $escape(ucfirst((string) $category['tone'])) ?></span></td></tr><?php endforeach; ?>
            <?php if ($data['categories'] === []): ?><tr><td colspan="4" class="empty">No defects for this period.</td></tr><?php endif; ?>
            </tbody></table></div></section>
        <?php elseif ($view === 'product-inspection'): ?>
            <section class="panel">
                <div class="panel-header"><div><h2 class="panel-title">Inspection records</h2><div class="panel-kicker">Showing the latest <?= count($data['inspections']) ?> records · up to 200</div></div><span class="badge"><?= count($data['inspections']) ?> records</span></div>
                <div class="filter-row">
                    <label class="muted" for="inspection-search" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Search inspection records</label><input class="search-input" id="inspection-search" type="search" placeholder="Search product ID...">
                    <label class="muted" for="defect-filter" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Filter by classification</label><select class="select" id="defect-filter"><option value="">All classifications</option><?php foreach ($data['categories'] as $category): ?><option value="<?= $escape(strtolower((string) $category)) ?>"><?= $escape((string) $category) ?></option><?php endforeach; ?></select>
                    <label class="muted" for="camera-filter" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Filter by camera</label><select class="select" id="camera-filter"><option value="">All cameras</option><?php foreach ($data['cameras'] as $camera): ?><option value="<?= $escape(strtolower((string) $camera)) ?>"><?= $escape((string) $camera) ?></option><?php endforeach; ?></select>
                </div>
                <div class="table-wrap"><table id="inspection-table"><thead><tr><th>Time</th><th>Product ID</th><th>Classification</th><th>Confidence</th><th>Camera</th><th>Production line</th></tr></thead><tbody>
                <?php foreach ($data['inspections'] as $inspection): $classification = (string) $inspection['defect_name'] . ($inspection['class_code'] ? ' · ' . $inspection['class_code'] : ''); ?>
                    <tr class="inspection-row" tabindex="0" data-product="<?= $escape(strtolower((string) $inspection['product_code'])) ?>" data-defect="<?= $escape(strtolower((string) $inspection['defect_name'])) ?>" data-camera="<?= $escape(strtolower((string) $inspection['camera'])) ?>" data-confidence="<?= (int) $inspection['confidence'] ?>" data-classification="<?= $escape($classification) ?>" data-time="<?= $escape(date('M j, Y H:i:s', strtotime((string) $inspection['inspected_at']))) ?>" data-line="<?= $escape((string) $inspection['line_name']) ?>" data-model="<?= $escape((string) $inspection['model_name']) ?>" data-image="<?= $escape((string) ($inspection['image_path'] ?? '')) ?>">
                        <td><?= $escape(date('M j, H:i:s', strtotime((string) $inspection['inspected_at']))) ?></td><td class="strong"><?= $escape((string) $inspection['product_code']) ?></td><td><span class="badge <?= $escape((string) $inspection['tone']) ?>"><?= $escape($classification) ?></span></td><td><?= (int) $inspection['confidence'] ?>%</td><td><?= $escape((string) $inspection['camera']) ?></td><td><?= $escape((string) $inspection['line_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($data['inspections'] === []): ?><tr><td colspan="6" class="empty">No inspection records have been saved yet.</td></tr><?php endif; ?>
                </tbody></table></div>
                <div class="panel-header"><span class="muted" id="inspection-result-count"><?= count($data['inspections']) ?> records</span><span class="muted">Select a row to view its record</span></div>
            </section>
        <?php elseif ($view === 'alerts'): ?>
            <section class="metrics" aria-label="Alert summary"><article class="metric"><div class="metric-label">Active alerts</div><div class="metric-value"><?= number_format($data['activeAlerts']) ?></div><div class="metric-note">Requires attention</div></article><article class="metric"><div class="metric-label">Total in alert history</div><div class="metric-value"><?= number_format(count($data['alerts'])) ?></div><div class="metric-note">Most recent 200 alerts</div></article><article class="metric"><div class="metric-label">Resolved</div><div class="metric-value"><?= number_format($data['resolvedAlerts']) ?></div><div class="metric-note">Marked complete by your team</div></article></section>
            <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Alert history</h2><div class="panel-kicker">Review, resolve and track factory events</div></div><select class="select" id="alert-filter" aria-label="Filter alerts"><option value="all">All alerts</option><option value="active">Active only</option><option value="resolved">Resolved only</option></select></div>
                <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <?php if ($notice !== ''): ?><div class="notice" role="status"><?= $escape($notice) ?></div><?php endif; ?>
                <div id="alert-list">
                <?php foreach ($data['alerts'] as $alert): ?>
                    <article class="alert-card" data-status="<?= (int) $alert['is_active'] === 1 ? 'active' : 'resolved' ?>">
                        <span class="alert-mark <?= $escape((string) $alert['tone']) ?>"><?= $alert['tone'] === 'blue' ? 'i' : '!' ?></span>
                        <div class="alert-copy"><div class="alert-title"><?= $escape((string) $alert['title']) ?></div><div class="alert-description"><?= $escape((string) $alert['description']) ?></div>
                            <?php if ((int) $alert['is_active'] === 1): ?><form class="resolve-form" method="post" action="workspace.php?view=alerts"><input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>"><input type="hidden" name="alert_id" value="<?= $escape((string) $alert['id']) ?>"><button class="resolve-button" type="submit">Mark resolved</button></form><?php endif; ?>
                        </div>
                        <div><div class="alert-time"><?= $escape(date('M j, Y · h:i A', strtotime((string) $alert['alerted_at']))) ?></div><span class="badge <?= (int) $alert['is_active'] === 1 ? $escape((string) $alert['tone']) : 'green' ?>"><?= (int) $alert['is_active'] === 1 ? 'Active' : 'Resolved' ?></span></div>
                    </article>
                <?php endforeach; ?>
                </div>
                <?php if ($data['alerts'] === []): ?><div class="empty">No alerts have been recorded.</div><?php endif; ?>
            </section>
        <?php elseif ($view === 'reports'): ?>
            <?php $report = $data['summary']; $reportPassRate = (int) $report['inspected'] > 0 ? (float) $report['passed'] / (int) $report['inspected'] * 100 : 0; ?>
            <section class="metrics" aria-label="Report summary"><article class="metric"><div class="metric-label">Products inspected</div><div class="metric-value"><?= number_format((int) $report['inspected']) ?></div><div class="metric-note"><?= $escape($periodLabel) ?> · <?= $rangeDays ?> day<?= $rangeDays === 1 ? '' : 's' ?></div></article><article class="metric"><div class="metric-label">Pass rate</div><div class="metric-value"><?= number_format($reportPassRate, 1) ?>%</div><div class="metric-note"><?= number_format((int) $report['passed']) ?> passed inspection</div></article><article class="metric"><div class="metric-label">Estimated quality loss</div><div class="metric-value">₹<?= number_format((float) $report['estimated_loss']) ?></div><div class="metric-note"><?= number_format((float) $report['material_waste'], 1) ?> kg material waste</div></article></section>
            <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Daily production report</h2><div class="panel-kicker">Daily quality and cost summary · <?= $escape($periodLabel) ?></div></div><a class="button button-primary" href="api.php?action=export&amp;period=<?= $escape($period) ?>"><?= workspace_icon('download', 15) ?> Export inspection CSV</a></div>
                <div class="table-wrap"><table><thead><tr><th>Date</th><th>Inspected</th><th>Defects</th><th>Pass rate</th><th>Estimated loss</th><th>Material waste</th></tr></thead><tbody>
                <?php foreach ($data['daily'] as $day): $pass = (int) $day['inspected'] > 0 ? ((int) $day['inspected'] - (int) $day['defects']) / (int) $day['inspected'] * 100 : 0; ?>
                    <tr><td class="strong"><?= $escape(date('D, M j, Y', strtotime((string) $day['stat_date']))) ?></td><td><?= number_format((int) $day['inspected']) ?></td><td><?= number_format((int) $day['defects']) ?></td><td><?= number_format($pass, 1) ?>%</td><td>₹<?= number_format((float) $day['estimated_loss']) ?></td><td><?= number_format((float) $day['material_waste'], 1) ?> kg</td></tr>
                <?php endforeach; ?>
                <?php if ($data['daily'] === []): ?><tr><td colspan="6" class="empty">No production report data is available for this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        <?php elseif ($view === 'machines'): ?>
            <?php $healthyCount = count(array_filter($data['machines'], static fn(array $machine): bool => (string) $machine['tone'] === 'green')); $averageHealth = $data['machines'] !== [] ? array_sum(array_map(static fn(array $machine): int => (int) $machine['health'], $data['machines'])) / count($data['machines']) : 0; ?>
            <section class="metrics" aria-label="Machine summary"><article class="metric"><div class="metric-label">Machines monitored</div><div class="metric-value"><?= number_format(count($data['machines'])) ?></div><div class="metric-note">Registered production equipment</div></article><article class="metric"><div class="metric-label">Running normally</div><div class="metric-value"><?= number_format($healthyCount) ?></div><div class="metric-note">Machines reporting normal state</div></article><article class="metric"><div class="metric-label">Average machine health</div><div class="metric-value"><?= number_format($averageHealth, 1) ?>%</div><div class="metric-note">Across registered equipment</div></article></section>
            <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Production equipment</h2><div class="panel-kicker">Health and current maintenance state</div></div></div><div class="machine-grid" style="padding:0 14px 14px">
                <?php foreach ($data['machines'] as $machine): $toneColor = ['green' => '#428765', 'amber' => '#bd8429', 'red' => '#c34f4a'][$machine['tone']] ?? '#827d75'; ?>
                    <article class="machine-card"><div class="machine-top"><div><div class="machine-code"><?= $escape((string) $machine['code']) ?></div><div class="machine-process"><?= $escape((string) $machine['process']) ?></div></div><span class="badge <?= $escape((string) $machine['tone']) ?>"><?= $escape((string) $machine['state']) ?></span></div><div class="health-row"><span class="health-value" style="color:<?= $escape($toneColor) ?>"><?= (int) $machine['health'] ?>%</span><span class="health-label">health score</span></div><div class="health-track"><span style="width:<?= max(0, min(100, (int) $machine['health'])) ?>%;--health-color:<?= $escape($toneColor) ?>"></span></div><div class="machine-state">Process status · <?= $escape((string) $machine['state']) ?></div></article>
                <?php endforeach; ?>
                <?php if ($data['machines'] === []): ?><div class="empty">No machines have been configured.</div><?php endif; ?>
            </div></section>
            <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Camera connectivity</h2><div class="panel-kicker">Status of camera devices registered to this plant</div></div><a class="button" href="workspace.php?view=live-monitoring">Open monitoring</a></div><div class="camera-status-list">
                <?php foreach ($data['cameras'] as $cameraStatus): ?><span class="badge <?= $escape((string) $cameraStatus['status']) ?>"><?= number_format((int) $cameraStatus['camera_count']) ?> <?= $escape(ucfirst((string) $cameraStatus['status'])) ?></span><?php endforeach; ?>
                <?php if ($data['cameras'] === []): ?><span class="muted">No camera devices configured.</span><?php endif; ?>
            </div></section>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/site_footer.php'; ?>
</main>
<dialog class="inspection-dialog" id="inspection-dialog" aria-labelledby="inspection-dialog-title">
    <div class="dialog-head"><h2 class="dialog-title" id="inspection-dialog-title">Inspection details</h2><button class="dialog-close" id="inspection-dialog-close" type="button" aria-label="Close details">&times;</button></div>
    <div class="dialog-body" id="inspection-dialog-body"></div>
</dialog>
<script>
(() => {
    const photoInput = document.getElementById('inspection-photo');
    if (photoInput) {
        const categorySelect = document.getElementById('product-category');
        const criteriaGuide = document.getElementById('criteria-guide');
        const criteriaTitle = document.getElementById('criteria-title');
        const criteriaList = document.getElementById('criteria-list');
        const categoryCriteria = <?= json_encode(array_map(
            static fn(array $category): array => ['label' => $category['label'], 'criteria' => $category['criteria']],
            $photoCategories
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        categorySelect.addEventListener('change', () => {
            const category = categoryCriteria[categorySelect.value];
            criteriaGuide.hidden = !category;
            criteriaList.replaceChildren();
            if (!category) return;
            criteriaTitle.textContent = `${category.label} review criteria`;
            category.criteria.forEach((criterion) => {
                const item = document.createElement('li');
                item.textContent = criterion;
                criteriaList.append(item);
            });
        });

        const preview = document.getElementById('photo-preview');
        const previewImage = document.getElementById('photo-preview-image');
        const previewMeta = document.getElementById('photo-preview-meta');
        const fileInfo = document.getElementById('photo-file-info');
        let previewUrl = '';
        photoInput.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = '';
            preview.classList.remove('has-image');
            previewImage.removeAttribute('src');
            previewMeta.textContent = '';
            const file = photoInput.files[0];
            if (!file) {
                fileInfo.textContent = 'After upload, the photo is saved privately for manual inspection.';
                return;
            }
            const supportedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!supportedTypes.includes(file.type) || file.size > <?= $uploadLimit ?>) {
                fileInfo.textContent = 'Choose a JPG, PNG, or WebP photo no larger than 8 MB.';
                photoInput.value = '';
                return;
            }
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            previewImage.onload = () => {
                preview.classList.add('has-image');
                previewMeta.textContent = `${previewImage.naturalWidth} × ${previewImage.naturalHeight} pixels`;
            };
            fileInfo.textContent = `${file.name} · ${(file.size / 1024).toFixed(0)} KB`;
        });
        document.getElementById('photo-upload-form').addEventListener('submit', () => {
            const submitButton = document.querySelector('.upload-submit');
            submitButton.disabled = true;
            submitButton.textContent = 'Uploading photo…';
        });
    }

    const inspectionTable = document.getElementById('inspection-table');
    if (inspectionTable) {
        const rows = Array.from(inspectionTable.querySelectorAll('tbody tr[data-product]'));
        const search = document.getElementById('inspection-search');
        const defectFilter = document.getElementById('defect-filter');
        const cameraFilter = document.getElementById('camera-filter');
        const resultCount = document.getElementById('inspection-result-count');
        const filterInspections = () => {
            let visible = 0;
            rows.forEach((row) => {
                const matches = row.dataset.product.includes(search.value.trim().toLowerCase())
                    && (!defectFilter.value || row.dataset.defect === defectFilter.value)
                    && (!cameraFilter.value || row.dataset.camera === cameraFilter.value);
                row.hidden = !matches;
                if (matches) visible++;
            });
            resultCount.textContent = `${visible} record${visible === 1 ? '' : 's'}`;
        };
        search.addEventListener('input', filterInspections);
        defectFilter.addEventListener('change', filterInspections);
        cameraFilter.addEventListener('change', filterInspections);
        const inspectionDialog = document.getElementById('inspection-dialog');
        document.getElementById('inspection-dialog-close').addEventListener('click', () => inspectionDialog.close());
        inspectionDialog.addEventListener('click', (event) => {
            if (event.target === inspectionDialog) inspectionDialog.close();
        });

        const showInspection = (row) => {
            const details = [
                ['Product ID', row.dataset.product],
                ['Classification', row.dataset.classification],
                ['Confidence', `${row.dataset.confidence}%`],
                ['Inspected at', row.dataset.time],
                ['Camera', row.dataset.camera],
                ['Production line', row.dataset.line],
                ['Camera model', row.dataset.model],
            ];
            if (row.dataset.image) details.push(['Image path', row.dataset.image]);
            document.getElementById('inspection-dialog-title').textContent = `Inspection · ${row.dataset.product}`;
            const body = document.getElementById('inspection-dialog-body');
            body.replaceChildren();
            details.forEach(([label, value]) => {
                const detailRow = document.createElement('div');
                detailRow.className = 'dialog-row';
                const labelElement = document.createElement('span');
                labelElement.textContent = label;
                const valueElement = document.createElement('span');
                valueElement.textContent = value;
                detailRow.append(labelElement, valueElement);
                body.append(detailRow);
            });
            document.getElementById('inspection-dialog').showModal();
        };
        inspectionTable.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-product]');
            if (row) showInspection(row);
        });
        inspectionTable.addEventListener('keydown', (event) => {
            const row = event.target.closest('tr[data-product]');
            if (row && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                showInspection(row);
            }
        });
    }

    const alertFilter = document.getElementById('alert-filter');
    if (alertFilter) {
        alertFilter.addEventListener('change', () => {
            document.querySelectorAll('#alert-list [data-status]').forEach((alert) => {
                alert.hidden = alertFilter.value !== 'all' && alert.dataset.status !== alertFilter.value;
            });
        });
    }

    const preview = document.getElementById('fullscreen-preview');
    const previewButton = document.getElementById('open-preview');
    const closePreview = document.getElementById('close-preview');
    if (preview && previewButton && closePreview) {
        const close = () => {
            preview.classList.remove('open');
            previewButton.focus();
        };
        previewButton.addEventListener('click', () => {
            preview.classList.add('open');
            closePreview.focus();
        });
        closePreview.addEventListener('click', close);
        preview.addEventListener('click', (event) => {
            if (event.target === preview) close();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && preview.classList.contains('open')) close();
        });
    }
})();
</script>
</body>
</html>
