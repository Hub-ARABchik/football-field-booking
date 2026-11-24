<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function ffbs_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    ffbs_json(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Only GET is supported.'], 405);
}

try {
    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) > 100) {
        ffbs_json(['ok' => false, 'error' => 'query_too_long', 'message' => 'Search text must not exceed 100 characters.'], 422);
    }

    $size = trim((string) ($_GET['size'] ?? ''));
    $surface = trim((string) ($_GET['surface'] ?? ''));
    $sort = (string) ($_GET['sort'] ?? 'recommended');
    $page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
    $perPage = max(1, min(24, (int) ($_GET['per_page'] ?? 12)));
    $priceMin = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? filter_var($_GET['price_min'], FILTER_VALIDATE_FLOAT) : null;
    $priceMax = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? filter_var($_GET['price_max'], FILTER_VALIDATE_FLOAT) : null;

    if (($priceMin !== null && ($priceMin === false || $priceMin < 0)) || ($priceMax !== null && ($priceMax === false || $priceMax < 0))) {
        ffbs_json(['ok' => false, 'error' => 'invalid_price_filter', 'message' => 'Price filters must be non-negative numbers.'], 422);
    }
    if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
        ffbs_json(['ok' => false, 'error' => 'invalid_price_range', 'message' => 'Minimum price cannot exceed maximum price.'], 422);
    }

    $allowedSurfaces = ['Алафи сунъӣ', 'Алафи табиӣ', 'Паркет', 'Резина'];
    if ($surface !== '' && !in_array($surface, $allowedSurfaces, true)) {
        ffbs_json(['ok' => false, 'error' => 'invalid_surface', 'message' => 'Unknown surface type.'], 422);
    }

    $where = ['f.is_active = 1'];
    $params = [];

    if ($query !== '') {
        $where[] = '(f.name LIKE ? OR COALESCE(f.description, \'\') LIKE ? OR f.size LIKE ? OR f.surface LIKE ?)';
        $like = '%' . $query . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($size !== '') {
        $where[] = 'f.size = ?';
        $params[] = $size;
    }
    if ($surface !== '') {
        $where[] = 'f.surface = ?';
        $params[] = $surface;
    }

    $having = [];
    $havingParams = [];
    if ($priceMin !== null) {
        $having[] = 'COALESCE(MIN(p.price), 0) >= ?';
        $havingParams[] = (float) $priceMin;
    }
    if ($priceMax !== null) {
        $having[] = 'COALESCE(MIN(p.price), 0) <= ?';
        $havingParams[] = (float) $priceMax;
    }
    $whereSql = implode(' AND ', $where);
    $havingSql = $having ? ' HAVING ' . implode(' AND ', $having) : '';

    $countSql = "SELECT COUNT(*) FROM (
        SELECT f.id
        FROM fields f
        LEFT JOIN prices p ON p.field_id = f.id
        WHERE {$whereSql}
        GROUP BY f.id{$havingSql}
    ) AS filtered_fields";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(array_merge($params, $havingParams));
    $total = (int) $countStmt->fetchColumn();

    $orderBy = match ($sort) {
        'price_asc' => 'min_price ASC, f.name ASC',
        'price_desc' => 'min_price DESC, f.name ASC',
        'name_asc' => 'f.name ASC',
        default => 'f.id ASC',
    };

    $offset = ($page - 1) * $perPage;
    $sql = "SELECT
                f.id, f.name, f.size, f.surface, f.photo, f.description,
                f.open_time, f.close_time,
                COALESCE(MIN(p.price), 0) AS min_price,
                COALESCE(MAX(p.price), 0) AS max_price
            FROM fields f
            LEFT JOIN prices p ON p.field_id = f.id
            WHERE {$whereSql}
            GROUP BY f.id{$havingSql}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $havingParams));
    $fields = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $photo = $row['photo'] ? basename((string) $row['photo']) : null;
        $fields[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'size' => (string) $row['size'],
            'surface' => (string) $row['surface'],
            'description' => (string) ($row['description'] ?? ''),
            'open_time' => substr((string) $row['open_time'], 0, 5),
            'close_time' => substr((string) $row['close_time'], 0, 5),
            'price_from' => (float) $row['min_price'],
            'price_to' => (float) $row['max_price'],
            'price_label' => money($row['min_price']),
            'photo_url' => $photo ? BASE . '/uploads/' . rawurlencode($photo) : null,
            'url' => BASE . '/field.php?id=' . (int) $row['id'],
        ];
    }

    ffbs_json([
        'ok' => true,
        'data' => $fields,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ],
    ]);
} catch (Throwable $e) {
    error_log('FFBS fields API error: ' . $e->getMessage());
    ffbs_json(['ok' => false, 'error' => 'server_error', 'message' => 'Unable to load football fields right now.'], 500);
}
