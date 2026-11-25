<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FieldRepository
{
    public function __construct(private readonly ?PDO $db = null) {}
    private function pdo(): PDO { return $this->db ?? Database::connection(); }

    public function paginatePublic(array $filters, int $page = 1, int $perPage = 12): array
    {
        $where = ["f.status = 'approved'", 'f.is_active = 1'];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(f.name LIKE :q_name OR f.city LIKE :q_city OR f.address LIKE :q_address OR f.description LIKE :q_description)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params['q_name'] = $like;
            $params['q_city'] = $like;
            $params['q_address'] = $like;
            $params['q_description'] = $like;
        }
        foreach (['city', 'size', 'surface'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = "f.{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        $priceMin = ($filters['price_min'] ?? '') !== '' ? (float) $filters['price_min'] : null;
        $priceMax = ($filters['price_max'] ?? '') !== '' ? (float) $filters['price_max'] : null;
        $having = [];
        if ($priceMin !== null) $having[] = 'MIN(p.price) >= ' . $this->pdo()->quote((string) $priceMin);
        if ($priceMax !== null) $having[] = 'MIN(p.price) <= ' . $this->pdo()->quote((string) $priceMax);
        $whereSql = implode(' AND ', $where);
        $havingSql = $having ? ' HAVING ' . implode(' AND ', $having) : '';

        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM (
                SELECT f.id FROM fields f LEFT JOIN prices p ON p.field_id = f.id
                WHERE {$whereSql} GROUP BY f.id{$havingSql}
            ) AS filtered_fields"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $perPage = max(1, min(48, $perPage));
        $pages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $page = max(1, min(max(1, $pages), $page));
        $offset = ($page - 1) * $perPage;
        $order = match ((string) ($filters['sort'] ?? 'recommended')) {
            'price_asc' => 'min_price ASC, f.name ASC',
            'price_desc' => 'min_price DESC, f.name ASC',
            'name_asc' => 'f.name ASC',
            default => 'f.id DESC',
        };
        $sql = "SELECT f.id, f.name, f.city, f.address, f.size, f.surface, f.photo, f.description,
                       f.open_time, f.close_time, MIN(p.price) AS min_price, MAX(p.price) AS max_price
                FROM fields f LEFT JOIN prices p ON p.field_id = f.id
                WHERE {$whereSql} GROUP BY f.id{$havingSql}
                ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function findPublic(int $id): ?array
    {
        $stmt = $this->pdo()->prepare("SELECT f.*, u.full_name AS owner_name FROM fields f LEFT JOIN users u ON u.id = f.owner_id WHERE f.id = :id AND f.status = 'approved' AND f.is_active = 1 LIMIT 1");
        $stmt->execute(['id' => $id]);
        $field = $stmt->fetch();
        if (!$field) return null;
        $images = $this->pdo()->prepare('SELECT id, image_path, alt_text, sort_order FROM field_images WHERE field_id = :id ORDER BY sort_order, id');
        $images->execute(['id' => $id]);
        $field['images'] = $images->fetchAll();
        $prices = $this->pdo()->prepare('SELECT day_type, time_of_day, price FROM prices WHERE field_id = :id ORDER BY day_type, time_of_day');
        $prices->execute(['id' => $id]);
        $field['prices'] = $prices->fetchAll();
        $schedules = $this->pdo()->prepare('SELECT day_of_week, open_time, close_time, is_closed FROM field_schedules WHERE field_id = :id ORDER BY day_of_week');
        $schedules->execute(['id' => $id]);
        $field['schedules'] = $schedules->fetchAll();
        return $field;
    }

    public function ownerFields(int $ownerId): array
    {
        $stmt = $this->pdo()->prepare('SELECT f.id, f.name, f.city, f.status, f.is_active, f.created_at, COUNT(b.id) AS booking_count FROM fields f LEFT JOIN bookings b ON b.field_id = f.id WHERE f.owner_id = :owner_id GROUP BY f.id ORDER BY f.created_at DESC');
        $stmt->execute(['owner_id' => $ownerId]);
        return $stmt->fetchAll();
    }

    public function createForOwner(int $ownerId, array $data): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
        $stmt = $pdo->prepare(
            "INSERT INTO fields (owner_id, name, city, address, size, surface, description, open_time, close_time, evening_from, capacity, status, is_active, created_at, updated_at)
             VALUES (:owner_id, :name, :city, :address, :size, :surface, :description, :open_time, :close_time, :evening_from, :capacity, 'pending', 0, NOW(), NOW())"
        );
        $stmt->execute([
            'owner_id' => $ownerId,
            'name' => $data['name'], 'city' => $data['city'], 'address' => $data['address'],
            'size' => $data['size'], 'surface' => $data['surface'], 'description' => $data['description'],
            'open_time' => $data['open_time'], 'close_time' => $data['close_time'],
            'evening_from' => $data['evening_from'], 'capacity' => $data['capacity'],
        ]);
        $id = (int) $pdo->lastInsertId();
        $schedule = $pdo->prepare('INSERT INTO field_schedules (field_id, day_of_week, open_time, close_time, is_closed) VALUES (:field_id, :day, :open, :close, 0)');
        for ($day = 1; $day <= 7; $day++) {
            $schedule->execute(['field_id' => $id, 'day' => $day, 'open' => $data['open_time'], 'close' => $data['close_time']]);
        }
        $priceInsert = $pdo->prepare('INSERT INTO prices (field_id, day_type, time_of_day, price) VALUES (:field_id, :day_type, :time_of_day, :price)');
        foreach (['weekday', 'weekend'] as $dayType) {
            foreach (['day', 'evening'] as $timeOfDay) {
                $priceInsert->execute([
                    'field_id' => $id, 'day_type' => $dayType, 'time_of_day' => $timeOfDay,
                    'price' => number_format((float) $data['prices'][$dayType][$timeOfDay], 2, '.', ''),
                ]);
            }
        }
        $pdo->commit();
        return $id;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function availability(int $fieldId, int $weekOffset): array
    {
        $fieldStmt = $this->pdo()->prepare("SELECT id, name, open_time, close_time, evening_from FROM fields WHERE id = :id AND status = 'approved' AND is_active = 1 LIMIT 1");
        $fieldStmt->execute(['id' => $fieldId]);
        $field = $fieldStmt->fetch();
        if (!$field) return [];

        $schedulesStmt = $this->pdo()->prepare('SELECT day_of_week, open_time, close_time, is_closed FROM field_schedules WHERE field_id = :id');
        $schedulesStmt->execute(['id' => $fieldId]);
        $schedules = [];
        foreach ($schedulesStmt->fetchAll() as $schedule) $schedules[(int) $schedule['day_of_week']] = $schedule;

        $start = (new \DateTimeImmutable('monday this week'))->modify('+' . $weekOffset . ' weeks');
        $end = $start->modify('+6 days');
        $busyStmt = $this->pdo()->prepare("SELECT booking_date, start_hour, duration_hours FROM bookings WHERE field_id = :field_id AND booking_date BETWEEN :from_date AND :to_date AND status IN ('pending','confirmed')");
        $busyStmt->execute(['field_id' => $fieldId, 'from_date' => $start->format('Y-m-d'), 'to_date' => $end->format('Y-m-d')]);
        $busy = [];
        foreach ($busyStmt->fetchAll() as $booking) {
            for ($hour = (int) $booking['start_hour']; $hour < (int) $booking['start_hour'] + (int) $booking['duration_hours']; $hour++) {
                $busy[$booking['booking_date']][$hour] = true;
            }
        }

        $priceStmt = $this->pdo()->prepare('SELECT day_type, time_of_day, price FROM prices WHERE field_id = :id');
        $priceStmt->execute(['id' => $fieldId]);
        $prices = [];
        foreach ($priceStmt->fetchAll() as $price) $prices[$price['day_type']][$price['time_of_day']] = (float) $price['price'];

        $days = [];
        $today = new \DateTimeImmutable('today');
        for ($i = 0; $i < 7; $i++) {
            $date = $start->modify('+' . $i . ' days');
            $dayNumber = (int) $date->format('N');
            $schedule = $schedules[$dayNumber] ?? null;
            $closed = $schedule ? (bool) $schedule['is_closed'] : false;
            $openHour = (int) substr((string) ($schedule['open_time'] ?? $field['open_time']), 0, 2);
            $closeHour = (int) substr((string) ($schedule['close_time'] ?? $field['close_time']), 0, 2);
            $dayType = $dayNumber >= 6 ? 'weekend' : 'weekday';
            $slots = [];
            for ($hour = $openHour; !$closed && $hour < $closeHour; $hour++) {
                $when = $date->setTime($hour, 0);
                $timeType = $hour >= (int) substr((string) $field['evening_from'], 0, 2) ? 'evening' : 'day';
                $slots[] = [
                    'start_hour' => $hour,
                    'start_time' => sprintf('%02d:00', $hour),
                    'status' => ($date < $today || ($date->format('Y-m-d') === $today->format('Y-m-d') && $when <= new \DateTimeImmutable())) ? 'past' : (isset($busy[$date->format('Y-m-d')][$hour]) ? 'busy' : 'available'),
                    'price' => $prices[$dayType][$timeType] ?? null,
                ];
            }
            $days[] = ['date' => $date->format('Y-m-d'), 'day_of_week' => $dayNumber, 'closed' => $closed, 'slots' => $slots];
        }
        return ['field' => ['id' => (int) $field['id'], 'name' => $field['name']], 'week_start' => $start->format('Y-m-d'), 'week_offset' => $weekOffset, 'days' => $days];
    }
}
