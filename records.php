<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

$pdo = database_connection();
$currentUser = auth_require_admin($pdo);
$recordTypes = [
    'inspections' => [
        'label' => 'Product inspections',
        'table' => 'inspections',
        'primary' => 'id',
        'query' => 'SELECT i.id, i.product_code, i.inspected_at, d.name AS defect, i.confidence, c.name AS camera
                    FROM inspections i
                    JOIN defect_types d ON d.id = i.defect_type_id
                    JOIN cameras c ON c.id = i.camera_id
                    ORDER BY i.inspected_at DESC, i.id DESC',
        'columns' => ['id' => 'ID', 'product_code' => 'Product ID', 'inspected_at' => 'Inspected at', 'defect' => 'Result', 'confidence' => 'Confidence %', 'camera' => 'Camera'],
        'fields' => [
            'product_code' => ['label' => 'Product ID', 'type' => 'text', 'required' => true, 'max' => 30],
            'inspected_at' => ['label' => 'Inspected at', 'type' => 'datetime', 'required' => true],
            'defect_type_id' => ['label' => 'Classification', 'type' => 'options', 'required' => true, 'options_query' => 'SELECT id, name FROM defect_types ORDER BY sort_order, name'],
            'confidence' => ['label' => 'Confidence (%)', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 100],
            'camera_id' => ['label' => 'Camera', 'type' => 'options', 'required' => true, 'options_query' => 'SELECT id, name FROM cameras ORDER BY name'],
            'image_path' => ['label' => 'Image path', 'type' => 'text', 'required' => false, 'max' => 255],
        ],
    ],
    'alerts' => [
        'label' => 'Alerts',
        'table' => 'alerts',
        'primary' => 'id',
        'query' => 'SELECT id, title, description, tone, alerted_at, is_active FROM alerts ORDER BY alerted_at DESC, id DESC',
        'columns' => ['id' => 'ID', 'title' => 'Title', 'description' => 'Description', 'tone' => 'Severity', 'alerted_at' => 'Time', 'is_active' => 'Active'],
        'fields' => [
            'title' => ['label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 120],
            'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => true, 'max' => 255],
            'tone' => ['label' => 'Severity', 'type' => 'select', 'required' => true, 'options' => ['red' => 'High', 'amber' => 'Medium', 'blue' => 'Info']],
            'alerted_at' => ['label' => 'Alert time', 'type' => 'datetime', 'required' => true],
            'is_active' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['1' => 'Active', '0' => 'Resolved']],
        ],
    ],
    'cameras' => [
        'label' => 'Cameras',
        'table' => 'cameras',
        'primary' => 'id',
        'query' => 'SELECT id, name, line_name, model_name, status FROM cameras ORDER BY id',
        'columns' => ['id' => 'ID', 'name' => 'Camera', 'line_name' => 'Production line', 'model_name' => 'Model', 'status' => 'Status'],
        'fields' => [
            'name' => ['label' => 'Camera name', 'type' => 'text', 'required' => true, 'max' => 30],
            'line_name' => ['label' => 'Production line', 'type' => 'text', 'required' => true, 'max' => 50],
            'model_name' => ['label' => 'Model', 'type' => 'text', 'required' => true, 'max' => 50],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['online' => 'Online', 'offline' => 'Offline', 'maintenance' => 'Maintenance']],
        ],
    ],
    'machines' => [
        'label' => 'Machines',
        'table' => 'machines',
        'primary' => 'id',
        'query' => 'SELECT id, code, process, health, state, tone FROM machines ORDER BY code',
        'columns' => ['id' => 'ID', 'code' => 'Machine', 'process' => 'Process', 'health' => 'Health %', 'state' => 'State', 'tone' => 'Status'],
        'fields' => [
            'code' => ['label' => 'Machine code', 'type' => 'text', 'required' => true, 'max' => 10],
            'process' => ['label' => 'Process', 'type' => 'text', 'required' => true, 'max' => 40],
            'health' => ['label' => 'Health (%)', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 100],
            'state' => ['label' => 'State', 'type' => 'text', 'required' => true, 'max' => 30],
            'tone' => ['label' => 'Status color', 'type' => 'select', 'required' => true, 'options' => ['green' => 'Good', 'amber' => 'Needs attention', 'red' => 'Critical']],
        ],
    ],
    'operators' => [
        'label' => 'Operators',
        'table' => 'operators',
        'primary' => 'id',
        'query' => 'SELECT id, name, shift FROM operators ORDER BY name',
        'columns' => ['id' => 'ID', 'name' => 'Name', 'shift' => 'Shift'],
        'fields' => [
            'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 60],
            'shift' => ['label' => 'Shift', 'type' => 'select', 'required' => true, 'options' => ['Morning' => 'Morning', 'Afternoon' => 'Afternoon', 'Night' => 'Night']],
        ],
    ],
    'performance' => [
        'label' => 'Operator performance',
        'table' => 'operator_performance',
        'primary' => 'id',
        'query' => 'SELECT p.id, o.name AS operator_name, p.perf_date, p.accuracy
                    FROM operator_performance p
                    JOIN operators o ON o.id = p.operator_id
                    ORDER BY p.perf_date DESC, p.id DESC',
        'columns' => ['id' => 'ID', 'operator_name' => 'Operator', 'perf_date' => 'Date', 'accuracy' => 'Accuracy %'],
        'fields' => [
            'operator_id' => ['label' => 'Operator', 'type' => 'options', 'required' => true, 'options_query' => 'SELECT id, name FROM operators ORDER BY name'],
            'perf_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
            'accuracy' => ['label' => 'Accuracy (%)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 100],
        ],
    ],
    'hourly-production' => [
        'label' => 'Hourly production',
        'table' => 'hourly_production',
        'primary' => 'id',
        'query' => 'SELECT id, stat_date, hour_of_day, passed, damaged, cracked, burned FROM hourly_production ORDER BY stat_date DESC, hour_of_day DESC',
        'columns' => ['id' => 'ID', 'stat_date' => 'Date', 'hour_of_day' => 'Hour', 'passed' => 'Passed', 'damaged' => 'Damaged', 'cracked' => 'Cracked', 'burned' => 'Scratches'],
        'fields' => [
            'stat_date' => ['label' => 'Production date', 'type' => 'date', 'required' => true],
            'hour_of_day' => ['label' => 'Hour (0–23)', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 23],
            'passed' => ['label' => 'Passed units', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 4294967295],
            'damaged' => ['label' => 'Damaged units', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 4294967295],
            'cracked' => ['label' => 'Cracked units', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 4294967295],
            'burned' => ['label' => 'Scratches units', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 4294967295],
        ],
    ],
    'daily-summary' => [
        'label' => 'Daily summaries',
        'table' => 'daily_summary',
        'primary' => 'stat_date',
        'query' => 'SELECT stat_date, total_inspected, estimated_loss, material_waste_kg FROM daily_summary ORDER BY stat_date DESC',
        'columns' => ['stat_date' => 'Date', 'total_inspected' => 'Inspected', 'estimated_loss' => 'Estimated loss', 'material_waste_kg' => 'Waste (kg)'],
        'fields' => [
            'stat_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
            'total_inspected' => ['label' => 'Total inspected', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 4294967295],
            'estimated_loss' => ['label' => 'Estimated loss (INR)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 9999999999],
            'material_waste_kg' => ['label' => 'Material waste (kg)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 999999],
        ],
    ],
    'defects' => [
        'label' => 'Defect classifications',
        'table' => 'defect_types',
        'primary' => 'id',
        'query' => 'SELECT id, code, name, class_code, tone, color, sort_order FROM defect_types ORDER BY sort_order, id',
        'columns' => ['id' => 'ID', 'code' => 'Code', 'name' => 'Classification', 'class_code' => 'Class code', 'tone' => 'Tone', 'color' => 'Color', 'sort_order' => 'Order'],
        'fields' => [
            'code' => ['label' => 'Code', 'type' => 'text', 'required' => true, 'max' => 20],
            'name' => ['label' => 'Classification name', 'type' => 'text', 'required' => true, 'max' => 50],
            'class_code' => ['label' => 'Class code', 'type' => 'text', 'required' => false, 'max' => 10],
            'tone' => ['label' => 'Tone', 'type' => 'select', 'required' => true, 'options' => ['green' => 'Green', 'red' => 'Red', 'amber' => 'Amber', 'orange' => 'Orange']],
            'color' => ['label' => 'Chart color', 'type' => 'color', 'required' => true],
            'sort_order' => ['label' => 'Display order', 'type' => 'integer', 'required' => true, 'min' => 0, 'max' => 255],
        ],
    ],
    'photos' => [
        'label' => 'Uploaded photo records',
        'table' => 'photo_uploads',
        'primary' => 'id',
        'query' => "SELECT p.id, p.user_id, p.original_name, p.product_code, p.product_category,
                           CASE p.review_status
                               WHEN 'pending' THEN 'Needs review'
                               WHEN 'passed' THEN 'Passed'
                               WHEN 'scratches' THEN 'Scratches'
                               WHEN 'cracked' THEN 'Cracked'
                               ELSE 'Defect'
                           END AS review_status,
                           p.created_at,
                           COALESCE(u.name, 'Deleted user') AS owner
                    FROM photo_uploads p LEFT JOIN users u ON u.id = p.user_id
                    ORDER BY p.created_at DESC, p.id DESC",
        'columns' => ['id' => 'ID', 'owner' => 'Owner', 'original_name' => 'Filename', 'product_code' => 'Product ID', 'product_category' => 'Category', 'review_status' => 'Review', 'created_at' => 'Uploaded'],
        'fields' => [
            'original_name' => ['label' => 'Filename', 'type' => 'text', 'required' => true, 'max' => 255],
            'product_code' => ['label' => 'Product ID', 'type' => 'text', 'required' => false, 'max' => 30],
            'product_category' => ['label' => 'Category', 'type' => 'select', 'required' => true, 'options' => ['food' => 'Food', 'drinks' => 'Drinks', 'cosmetics' => 'Cosmetics', 'other' => 'Other']],
            'review_status' => ['label' => 'Review result', 'type' => 'select', 'required' => true, 'options' => ['pending' => 'Awaiting review', 'passed' => 'Passed', 'defect' => 'Defect']],
            'review_notes' => ['label' => 'Review notes', 'type' => 'textarea', 'required' => false, 'max' => 1000],
        ],
    ],
    'accounts' => [
        'label' => 'User accounts',
        'table' => 'users',
        'primary' => 'id',
        'query' => 'SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC',
        'columns' => ['id' => 'ID', 'name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'created_at' => 'Joined'],
        'fields' => [
            'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 60],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 120],
            'role' => ['label' => 'Role', 'type' => 'select', 'required' => true, 'options' => ['Factory manager' => 'Factory manager', 'Administrator' => 'Administrator']],
        ],
    ],
];

$type = (string) ($_GET['type'] ?? 'inspections');
if (!isset($recordTypes[$type])) {
    $type = 'inspections';
}
$pageNumber = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageNumber = $pageNumber === false || $pageNumber < 1 ? 1 : $pageNumber;
$config = $recordTypes[$type];
$deleteConfirmation = match ($type) {
    'accounts' => 'Delete this account and all of its uploaded photo records? This cannot be undone.',
    'operators' => 'Delete this operator and the related operator-performance records? This cannot be undone.',
    'photos' => 'Delete this photo record and its stored image? This cannot be undone.',
    default => 'Delete this record? This cannot be undone.',
};
$error = '';
$notice = '';
$editRecord = null;
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function records_photo_path(array $photo): ?string
{
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$photo['mime_type'] ?? ''])) {
        return null;
    }
    $storedName = (string) ($photo['stored_name'] ?? '');
    if (preg_match('/\A[a-f0-9-]{36}\z/D', $storedName) !== 1) {
        return null;
    }
    return __DIR__ . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR
        . $storedName . '.' . $extensions[$photo['mime_type']];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postType = (string) ($_POST['type'] ?? '');
    if (!isset($recordTypes[$postType])) {
        http_response_code(400);
        $error = 'Choose a valid record type.';
    } elseif (!auth_validate_csrf(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } else {
        $type = $postType;
        $config = $recordTypes[$type];
        $rawRecordId = (string) ($_POST['record_id'] ?? '');
        $recordId = $config['primary'] === 'stat_date'
            ? $rawRecordId
            : filter_var($rawRecordId, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        $validRecordId = $config['primary'] === 'stat_date'
            ? (DateTimeImmutable::createFromFormat('!Y-m-d', $rawRecordId) !== false
                && DateTimeImmutable::createFromFormat('!Y-m-d', $rawRecordId)->format('Y-m-d') === $rawRecordId)
            : ($recordId !== false && $recordId !== null && $recordId > 0);
        if (!$validRecordId || !in_array($action, ['save', 'delete'], true)) {
            http_response_code(400);
            $error = 'Choose a valid record action.';
        } else {
            $findRecord = $pdo->prepare(
                'SELECT * FROM `' . $config['table'] . '` WHERE `' . $config['primary'] . '` = :record_id LIMIT 1'
            );
            $findRecord->execute(['record_id' => $recordId]);
            $existingRecord = $findRecord->fetch();
            if (!$existingRecord) {
                http_response_code(404);
                $error = 'That record no longer exists.';
            } elseif ($action === 'save') {
                if ($type === 'photos' && !in_array($existingRecord['review_status'], ['pending', 'passed'], true)) {
                    $existingRecord['review_status'] = 'defect';
                }
                $editRecord = $existingRecord;
                foreach ($config['fields'] as $field => $_definition) {
                    if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                        $editRecord[$field] = (string) $_POST[$field];
                    }
                }
                $values = [];
                foreach ($config['fields'] as $field => $definition) {
                    $rawValue = trim((string) ($_POST[$field] ?? ''));
                    if ($rawValue === '' && empty($definition['required'])) {
                        $values[$field] = null;
                        continue;
                    }
                    if ($rawValue === '' && !empty($definition['required'])) {
                        $error = $definition['label'] . ' is required.';
                        break;
                    }
                    if (isset($definition['max']) && mb_strlen($rawValue, 'UTF-8') > $definition['max']) {
                        $error = $definition['label'] . ' is too long.';
                        break;
                    }
                    if ($definition['type'] === 'integer' || $definition['type'] === 'decimal') {
                        $number = filter_var($rawValue, $definition['type'] === 'integer' ? FILTER_VALIDATE_INT : FILTER_VALIDATE_FLOAT);
                        if ($number === false || $number < $definition['min'] || $number > $definition['max']) {
                            $error = $definition['label'] . ' must be between ' . $definition['min'] . ' and ' . $definition['max'] . '.';
                            break;
                        }
                        $values[$field] = $number;
                    } elseif ($definition['type'] === 'select') {
                        if (!array_key_exists($rawValue, $definition['options'])) {
                            $error = 'Choose a valid value for ' . $definition['label'] . '.';
                            break;
                        }
                        $values[$field] = $rawValue;
                    } elseif ($definition['type'] === 'options') {
                        $choices = $pdo->query($definition['options_query'])->fetchAll(PDO::FETCH_KEY_PAIR);
                        if (!array_key_exists($rawValue, $choices)) {
                            $error = 'Choose a valid value for ' . $definition['label'] . '.';
                            break;
                        }
                        $values[$field] = (int) $rawValue;
                    } elseif ($definition['type'] === 'email') {
                        if (filter_var($rawValue, FILTER_VALIDATE_EMAIL) === false) {
                            $error = 'Enter a valid email address.';
                            break;
                        }
                        $values[$field] = $rawValue;
                    } elseif ($definition['type'] === 'color') {
                        if (preg_match('/\A#[a-fA-F0-9]{6}\z/D', $rawValue) !== 1) {
                            $error = 'Choose a valid six-digit color.';
                            break;
                        }
                        $values[$field] = strtolower($rawValue);
                    } elseif ($definition['type'] === 'date') {
                        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawValue);
                        if (!$date || $date->format('Y-m-d') !== $rawValue) {
                            $error = 'Enter a valid date for ' . $definition['label'] . '.';
                            break;
                        }
                        $values[$field] = $rawValue;
                    } elseif ($definition['type'] === 'datetime') {
                        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $rawValue);
                        if (!$date || $date->format('Y-m-d\TH:i') !== $rawValue) {
                            $error = 'Enter a valid date and time for ' . $definition['label'] . '.';
                            break;
                        }
                        $values[$field] = $date->format('Y-m-d H:i:s');
                    } else {
                        $values[$field] = $rawValue;
                    }
                }

                if ($error === '' && $type === 'accounts' && (int) $recordId === (int) $currentUser['id']
                    && $values['role'] !== 'Administrator') {
                    $error = 'You cannot remove administrator access from your own account.';
                }
                if ($error === '' && $type === 'accounts'
                    && strcasecmp((string) $existingRecord['role'], 'Administrator') === 0
                    && $values['role'] !== 'Administrator') {
                    $pdo->beginTransaction();
                    $admins = $pdo->query("SELECT id FROM users WHERE role = 'Administrator' FOR UPDATE")->fetchAll();
                    if (count($admins) <= 1) {
                        $pdo->rollBack();
                        $error = 'You cannot remove access from the last administrator.';
                    }
                }

                if ($error === '') {
                    $assignments = [];
                    foreach (array_keys($values) as $field) {
                        $assignments[] = '`' . $field . '` = :' . $field;
                    }
                    try {
                        $update = $pdo->prepare(
                            'UPDATE `' . $config['table'] . '` SET ' . implode(', ', $assignments)
                            . ' WHERE `' . $config['primary'] . '` = :record_id'
                        );
                        $values['record_id'] = $recordId;
                        $update->execute($values);
                        if ($pdo->inTransaction()) {
                            $pdo->commit();
                        }
                        if ($type === 'accounts' && (int) $recordId === (int) $currentUser['id']) {
                            $_SESSION['user']['name'] = (string) $values['name'];
                            $_SESSION['user']['email'] = (string) $values['email'];
                        }
                        header('Location: records.php?type=' . rawurlencode($type) . '&page=' . $pageNumber . '&notice=updated');
                        exit;
                    } catch (PDOException $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Record update failed for ' . $type . ' #' . $recordId . ': ' . $exception->getMessage());
                        $error = 'The record could not be saved. Check that its values are unique and valid.';
                    }
                }
            } elseif ($action === 'delete') {
                if ($type === 'accounts' && (int) $recordId === (int) $currentUser['id']) {
                    $error = 'You cannot delete your own administrator account.';
                } else {
                    $ownedPhotoRows = [];
                    if ($type === 'accounts') {
                        $ownedPhotos = $pdo->prepare('SELECT stored_name, mime_type FROM photo_uploads WHERE user_id = :user_id');
                        $ownedPhotos->execute(['user_id' => $recordId]);
                        $ownedPhotoRows = $ownedPhotos->fetchAll();
                    }
                    try {
                        $pdo->beginTransaction();
                        if ($type === 'accounts' && strcasecmp((string) $existingRecord['role'], 'Administrator') === 0) {
                            $admins = $pdo->query("SELECT id FROM users WHERE role = 'Administrator' FOR UPDATE")->fetchAll();
                            if (count($admins) <= 1) {
                                $pdo->rollBack();
                                $error = 'You cannot delete the last administrator account.';
                            }
                        }
                        if ($error === '') {
                            $delete = $pdo->prepare(
                                'DELETE FROM `' . $config['table'] . '` WHERE `' . $config['primary'] . '` = :record_id'
                            );
                            $delete->execute(['record_id' => $recordId]);
                            $pdo->commit();
                        }

                        if ($error === '' && $type === 'photos') {
                            $photoPath = records_photo_path($existingRecord);
                            if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) {
                                error_log('Failed to remove deleted inspection photo file: ' . $photoPath);
                                $error = 'The photo record was deleted, but its image file could not be removed. Contact the administrator.';
                            }
                        }
                        if ($error === '' && $type === 'accounts') {
                            foreach ($ownedPhotoRows as $ownedPhoto) {
                                $photoPath = records_photo_path($ownedPhoto);
                                if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) {
                                    error_log('Failed to remove photo for deleted account: ' . $photoPath);
                                    $error = 'The account was deleted, but one or more owned photo files could not be removed. Contact the administrator.';
                                }
                            }
                        }
                        if ($error === '') {
                            header('Location: records.php?type=' . rawurlencode($type) . '&page=' . $pageNumber . '&notice=' . rawurlencode($type === 'accounts' ? 'account-deleted' : 'deleted'));
                            exit;
                        }
                    } catch (PDOException $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Record deletion failed for ' . $type . ' #' . $recordId . ': ' . $exception->getMessage());
                        $error = 'This record could not be deleted because it is still used by other records or violates a database rule.';
                    }
                }
            }
        }
    }
}

if (isset($_GET['notice'])) {
    $notice = match ((string) $_GET['notice']) {
        'updated' => 'Record updated.',
        'deleted' => 'Record deleted.',
        'account-deleted' => 'Account and its owned photo records deleted.',
        default => (string) $_GET['notice'],
    };
}

$editRawId = (string) ($_GET['edit'] ?? '');
$editId = $config['primary'] === 'stat_date'
    ? $editRawId
    : filter_var($editRawId, FILTER_VALIDATE_INT);
$validEditId = $config['primary'] === 'stat_date'
    ? (DateTimeImmutable::createFromFormat('!Y-m-d', $editRawId) !== false
        && DateTimeImmutable::createFromFormat('!Y-m-d', $editRawId)->format('Y-m-d') === $editRawId)
    : ($editId !== false && $editId !== null && $editId > 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $validEditId) {
    $editStatement = $pdo->prepare(
        'SELECT * FROM `' . $config['table'] . '` WHERE `' . $config['primary'] . '` = :record_id LIMIT 1'
    );
    $editStatement->execute(['record_id' => $editId]);
    $editRecord = $editStatement->fetch() ?: null;
}
$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM `' . $config['table'] . '`')->fetchColumn();
$pageSize = 100;
$pageCount = max(1, (int) ceil($totalRecords / $pageSize));
$pageNumber = min($pageNumber, $pageCount);
$recordQuery = $pdo->prepare($config['query'] . ' LIMIT :page_size OFFSET :page_offset');
$recordQuery->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
$recordQuery->bindValue(':page_offset', ($pageNumber - 1) * $pageSize, PDO::PARAM_INT);
$recordQuery->execute();
$records = $recordQuery->fetchAll();
$optionValues = [];
foreach ($config['fields'] as $field => $definition) {
    if ($definition['type'] === 'options') {
        $optionValues[$field] = $pdo->query($definition['options_query'])->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record management · VisionQC</title>
    <link rel="stylesheet" href="site-layout.css">
    <style>
        :root { color-scheme:light; --ink:#25231f; --muted:#827d75; --line:#e8e3db; --canvas:#f5f2ed; --surface:#fff; --sidebar:#252521; --brown:#79563c; --brown-dark:#5d402e; --red:#a83d36; --green:#2b805d; }
        * { box-sizing:border-box; } body { margin:0; background:var(--canvas); color:var(--ink); font:14px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .sidebar { position:fixed; inset:0 auto 0 0; width:228px; padding:23px 16px; background:var(--sidebar); color:#f7f4ee; }
        .brand { display:block; margin:0 0 22px; color:#fff; font-weight:750; text-decoration:none; }
        .nav a { display:block; margin:4px 0; padding:10px 12px; border-radius:8px; color:#c9c5bc; font-size:13px; text-decoration:none; }
        .nav a:hover,.nav a.active { background:#574333; color:#fff; }
        .main { min-height:100vh; margin-left:228px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; min-height:65px; padding:0 30px; border-bottom:1px solid var(--brown-dark); background:var(--brown); color:#fff8ef; }
        .topbar a { color:inherit; text-decoration:none; } .content { max-width:1500px; margin:auto; padding:28px 30px 42px; }
        .heading { display:flex; justify-content:space-between; align-items:end; gap:15px; margin-bottom:20px; }
        h1 { margin:3px 0; font-size:28px; } h2 { margin:0; font-size:16px; } .muted,.kicker { color:var(--muted); font-size:12px; }
        .card { margin-bottom:16px; border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:0 8px 24px rgba(54,44,33,.04); }
        .card-head { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:17px 18px; }
        .toolbar { display:flex; flex-wrap:wrap; gap:8px; padding:0 18px 16px; }
        select,input,textarea,button { font:inherit; } select,.field input,.field textarea { width:100%; min-height:38px; padding:8px 10px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); }
        .toolbar select { width:auto; min-width:210px; } .field textarea { min-height:85px; resize:vertical; }
        .button { display:inline-flex; min-height:35px; align-items:center; justify-content:center; padding:0 11px; border:1px solid var(--line); border-radius:7px; background:#fff; color:#514b42; font-size:12px; font-weight:650; text-decoration:none; cursor:pointer; }
        .button.primary { border-color:var(--brown); background:var(--brown); color:#fff; } .button.danger { border-color:#e6c8c5; color:var(--red); }
        .table-wrap { overflow:auto; border-top:1px solid var(--line); } table { width:100%; border-collapse:collapse; text-align:left; }
        th { padding:10px 12px; background:#faf9f7; color:var(--muted); font-size:10px; text-transform:uppercase; white-space:nowrap; }
        td { max-width:320px; padding:11px 12px; border-top:1px solid #f0ede8; font-size:12px; overflow-wrap:anywhere; }
        .row-actions { display:flex; gap:6px; align-items:center; }.row-actions form { margin:0; }
        .notice { margin:0 18px 14px; padding:10px 12px; border-radius:7px; background:#eaf4ed; color:var(--green); font-size:12px; }
        .notice.error { background:#fff0ee; color:var(--red); }
        .edit-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:13px; padding:0 18px 18px; }
        .field { display:grid; gap:5px; font-size:11px; font-weight:650; }.field.full { grid-column:1/-1; }
        .edit-actions { display:flex; gap:8px; align-items:center; grid-column:1/-1; }
        .empty { padding:28px; color:var(--muted); text-align:center; }
        @media(max-width:740px) { .sidebar { width:58px; padding:14px 5px; }.brand { overflow:hidden; white-space:nowrap; }.nav a { overflow:hidden; padding:10px 7px; font-size:0; }.nav a:before { content:"•"; font-size:18px; }.main { margin-left:58px; }.content { padding:18px 12px; }.topbar { padding:0 13px; }.heading { display:block; }.heading select { margin-top:12px; }.edit-form { grid-template-columns:1fr; }.field.full,.edit-actions { grid-column:1; } }
    </style>
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="admin.php">VisionQC · Admin</a>
    <nav class="nav" aria-label="Administration">
        <a href="admin.php">Admin panel</a>
        <a href="quality_control_dashboard.php">Quality dashboard</a>
        <a class="active" href="records.php">Record management</a>
        <a href="workspace.php?view=live-monitoring">Live monitoring</a>
        <a href="workspace.php?view=product-inspection">Product inspection</a>
        <a href="operators.php">Operators</a>
        <a href="profile.php">Profile</a>
    </nav>
</aside>
<main class="main">
    <header class="topbar"><a href="admin.php">Factory / <strong>Record management</strong></a><span><?= $escape((string) $currentUser['name']) ?> · Administrator</span></header>
    <div class="content">
        <section class="heading"><div><div class="muted">Administrator tools</div><h1>Record management</h1><div class="muted">Edit or delete system records. Accounts and uploaded photos have extra safeguards.</div></div>
            <form method="get" action="records.php"><label class="muted" for="record-type">Record type</label><select id="record-type" name="type" onchange="this.form.submit()"><?php foreach ($recordTypes as $key => $recordType): ?><option value="<?= $escape($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= $escape($recordType['label']) ?></option><?php endforeach; ?></select></form>
        </section>
        <section class="card">
            <header class="card-head"><div><h2><?= $escape($config['label']) ?></h2><div class="kicker"><?= number_format($totalRecords) ?> total · page <?= $pageNumber ?> of <?= $pageCount ?></div></div></header>
            <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
            <?php if ($notice !== ''): ?><div class="notice" role="status"><?= $escape($notice) ?></div><?php endif; ?>
            <?php if ($editRecord !== null): ?>
                <form class="edit-form" method="post" action="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>">
                    <input type="hidden" name="type" value="<?= $escape($type) ?>">
                    <input type="hidden" name="record_id" value="<?= $escape((string) $editRecord[$config['primary']]) ?>">
                    <input type="hidden" name="action" value="save">
                    <?php foreach ($config['fields'] as $field => $definition): ?>
                        <?php
                        $fieldValue = (string) ($editRecord[$field] ?? '');
                        if ($definition['type'] === 'datetime' && $fieldValue !== '') {
                            $fieldValue = date('Y-m-d\TH:i', strtotime($fieldValue));
                        }
                        $fieldType = match ($definition['type']) {
                            'date' => 'date',
                            'datetime' => 'datetime-local',
                            'integer', 'decimal' => 'number',
                            'email' => 'email',
                            'color' => 'color',
                            default => 'text',
                        };
                        $fieldClass = in_array($definition['type'], ['textarea'], true) ? 'field full' : 'field';
                        ?>
                        <label class="<?= $fieldClass ?>"><?= $escape($definition['label']) ?>
                            <?php if ($definition['type'] === 'select' || $definition['type'] === 'options'): ?>
                                <?php $choices = $definition['type'] === 'options' ? $optionValues[$field] : $definition['options']; ?>
                                <select name="<?= $escape($field) ?>"<?= !empty($definition['required']) ? ' required' : '' ?>>
                                    <?php foreach ($choices as $optionValue => $optionLabel): ?><option value="<?= $escape((string) $optionValue) ?>"<?= (string) $editRecord[$field] === (string) $optionValue ? ' selected' : '' ?>><?= $escape((string) $optionLabel) ?></option><?php endforeach; ?>
                                </select>
                            <?php elseif ($definition['type'] === 'textarea'): ?>
                                <textarea name="<?= $escape($field) ?>" maxlength="<?= (int) ($definition['max'] ?? 10000) ?>"<?= !empty($definition['required']) ? ' required' : '' ?>><?= $escape($fieldValue) ?></textarea>
                            <?php else: ?>
                                <input type="<?= $fieldType ?>" name="<?= $escape($field) ?>" value="<?= $escape($fieldValue) ?>"<?= isset($definition['min']) ? ' min="' . $escape((string) $definition['min']) . '"' : '' ?><?= isset($definition['max']) && in_array($definition['type'], ['text', 'email'], true) ? ' maxlength="' . (int) $definition['max'] . '"' : '' ?><?= isset($definition['max']) && in_array($definition['type'], ['integer', 'decimal'], true) ? ' max="' . $escape((string) $definition['max']) . '"' : '' ?><?= $definition['type'] === 'decimal' ? ' step="0.01"' : '' ?><?= !empty($definition['required']) ? ' required' : '' ?>>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <div class="edit-actions"><button class="button primary" type="submit">Save changes</button><a class="button" href="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber ?>">Cancel</a></div>
                </form>
            <?php elseif ($editRawId !== '' && !$validEditId): ?>
                <div class="notice error">That record could not be found.</div>
            <?php endif; ?>
            <?php if ($records !== []): ?>
                <div class="table-wrap"><table>
                    <thead><tr><?php foreach ($config['columns'] as $columnLabel): ?><th><?= $escape($columnLabel) ?></th><?php endforeach; ?><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <?php foreach ($config['columns'] as $column => $_columnLabel): ?><td><?= $escape((string) ($record[$column] ?? '—')) ?></td><?php endforeach; ?>
                            <td><div class="row-actions">
                                <a class="button" href="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber ?>&amp;edit=<?= rawurlencode((string) $record[$config['primary']]) ?>">Edit</a>
                                <form method="post" action="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber ?>" onsubmit="return confirm(<?= htmlspecialchars(json_encode($deleteConfirmation, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>);">
                                    <input type="hidden" name="csrf_token" value="<?= $escape(auth_csrf_token()) ?>"><input type="hidden" name="type" value="<?= $escape($type) ?>">
                                    <input type="hidden" name="record_id" value="<?= $escape((string) $record[$config['primary']]) ?>"><input type="hidden" name="action" value="delete">
                                    <button class="button danger" type="submit">Delete</button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ($pageCount > 1): ?><div class="toolbar"><span class="muted">Showing <?= number_format(($pageNumber - 1) * $pageSize + 1) ?>–<?= number_format(min($pageNumber * $pageSize, $totalRecords)) ?> of <?= number_format($totalRecords) ?></span><?php if ($pageNumber > 1): ?><a class="button" href="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber - 1 ?>">Previous</a><?php endif; ?><?php if ($pageNumber < $pageCount): ?><a class="button" href="records.php?type=<?= $escape($type) ?>&amp;page=<?= $pageNumber + 1 ?>">Next</a><?php endif; ?></div><?php endif; ?>
            <?php else: ?>
                <div class="empty">No <?= $escape(strtolower($config['label'])) ?> records were found.</div>
            <?php endif; ?>
        </section>
        <?php require __DIR__ . '/site_footer.php'; ?>
    </div>
</main>
</body>
</html>
