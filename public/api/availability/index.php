<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function ffbs_availability_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    ffbs_availability_json(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Only GET is supported.'], 405);
}

$fieldId = filter_var($_GET['field_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$weekOffset = filter_var($_GET['week'] ?? 0, FILTER_VALIDATE_INT);

if ($fieldId === false || $fieldId === null || $weekOffset === false || $weekOffset < 0 || $weekOffset > 8) {
    ffbs_availability_json(['ok' => false, 'error' => 'invalid_parameters', 'message' => 'Provide a valid field_id and a week offset between 0 and 8.'], 422);
}

try {
    $fieldStmt = $pdo->prepare('SELECT id, name, size, surface, photo, open_time, close_time, evening_from FROM fields WHERE id = ? AND is_active = 1 LIMIT 1');
    $fieldStmt->execute([(int) $fieldId]);
    $field = $fieldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$field) {
        ffbs_availability_json(['ok' => false, 'error' => 'field_not_found', 'message' => 'The requested football field is not available.'], 404);
    }

    $weekStart = (new DateTimeImmutable('today'))->modify('monday this week')->modify('+' . (int) $weekOffset . ' weeks');
    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $weekStart->modify('+' . $i . ' days');
    }
    $dateFrom = $dates[0]->format('Y-m-d');
    $dateTo = $dates[6]->format('Y-m-d');

    $busyStmt = $pdo->prepare("SELECT booking_date, start_hour FROM bookings WHERE field_id = ? AND booking_date BETWEEN ? AND ? AND status <> 'cancelled'");
    $busyStmt->execute([(int) $fieldId, $dateFrom, $dateTo]);
    $busy = [];
    foreach ($busyStmt->fetchAll(PDO::FETCH_ASSOC) as $booking) {
        $busy[(string) $booking['booking_date'] . '|' . (int) $booking['start_hour']] = true;
    }

    $prices = load_prices($pdo, (int) $fieldId);
    $openHour = (int) substr((string) $field['open_time'], 0, 2);
    $closeHour = (int) substr((string) $field['close_time'], 0, 2);
    $days = [];

    foreach ($dates as $dateObject) {
        $date = $dateObject->format('Y-m-d');
        $dayIndex = (int) $dateObject->format('N') - 1;
        $slots = [];

        for ($hour = $openHour; $hour < $closeHour; $hour++) {
            $key = $date . '|' . $hour;
            $startTimestamp = strtotime($date . ' ' . sprintf('%02d:00:00', $hour));
            $status = isset($busy[$key]) ? 'occupied' : (($startTimestamp !== false && $startTimestamp <= time()) ? 'past' : 'available');
            $price = price_calc($prices, $date, $hour, (string) $field['evening_from']);

            $slots[] = [
                'hour' => $hour,
                'start_time' => sprintf('%02d:00', $hour),
                'end_time' => sprintf('%02d:00', $hour + 1),
                'status' => $status,
                'price' => (float) $price,
                'price_label' => money($price),
            ];
        }

        $days[] = [
            'date' => $date,
            'day_name' => $DAYS[$dayIndex] ?? '',
            'day_number' => (int) $dateObject->format('d'),
            'is_weekend' => $dayIndex >= 5,
            'slots' => $slots,
        ];
    }

    $photo = $field['photo'] ? basename((string) $field['photo']) : null;
    ffbs_availability_json([
        'ok' => true,
        'field' => [
            'id' => (int) $field['id'],
            'name' => (string) $field['name'],
            'size' => (string) $field['size'],
            'surface' => (string) $field['surface'],
            'open_time' => substr((string) $field['open_time'], 0, 5),
            'close_time' => substr((string) $field['close_time'], 0, 5),
            'photo_url' => $photo ? BASE . '/uploads/' . rawurlencode($photo) : null,
        ],
        'week' => [
            'offset' => (int) $weekOffset,
            'from' => $dateFrom,
            'to' => $dateTo,
        ],
        'days' => $days,
        'legend' => [
            'available' => 'Холӣ',
            'occupied' => 'Банд',
            'past' => 'Гузашта',
        ],
    ]);
} catch (Throwable $e) {
    error_log('FFBS availability API error: ' . $e->getMessage());
    ffbs_availability_json(['ok' => false, 'error' => 'server_error', 'message' => 'Unable to load availability right now.'], 500);
}
