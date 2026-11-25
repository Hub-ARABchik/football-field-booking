<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class BookingRepository
{
    public function __construct(private readonly ?PDO $db = null) {}
    private function pdo(): PDO { return $this->db ?? Database::connection(); }

    public function create(int $customerId, int $fieldId, string $date, int $startHour, int $durationHours, string $paymentMethod = 'cash'): array
    {
        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) throw new RuntimeException('invalid_payment_method');
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            // Lock the field row so simultaneous requests for the same field are serialized.
            $fieldStmt = $pdo->prepare("SELECT * FROM fields WHERE id = :id AND status = 'approved' AND is_active = 1 FOR UPDATE");
            $fieldStmt->execute(['id' => $fieldId]);
            $field = $fieldStmt->fetch();
            if (!$field) throw new RuntimeException('field_not_available');

            $zone = new DateTimeZone('Asia/Dushanbe');
            $bookingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
            if (!$bookingDate || $bookingDate->format('Y-m-d') !== $date) throw new RuntimeException('invalid_date');
            $today = new DateTimeImmutable('today', $zone);
            $maxDate = $today->modify('+' . (int) ($GLOBALS['ffbs_config']['booking']['max_advance_days'] ?? 70) . ' days');
            if ($bookingDate < $today || $bookingDate > $maxDate) throw new RuntimeException('date_out_of_range');
            if ($startHour < 0 || $durationHours < 1 || $durationHours > 6 || $startHour + $durationHours > 24) throw new RuntimeException('invalid_time_range');

            $requestedStart = $bookingDate->setTime($startHour, 0);
            if ($requestedStart <= new DateTimeImmutable('now', $zone)) throw new RuntimeException('time_in_past');

            $dayNumber = (int) $bookingDate->format('N');
            $scheduleStmt = $pdo->prepare('SELECT open_time, close_time, is_closed FROM field_schedules WHERE field_id = :field_id AND day_of_week = :day LIMIT 1');
            $scheduleStmt->execute(['field_id' => $fieldId, 'day' => $dayNumber]);
            $schedule = $scheduleStmt->fetch();
            $open = $schedule ? (string) $schedule['open_time'] : (string) $field['open_time'];
            $close = $schedule ? (string) $schedule['close_time'] : (string) $field['close_time'];
            if (($schedule && (bool) $schedule['is_closed']) || $startHour < (int) substr($open, 0, 2) || $startHour + $durationHours > (int) substr($close, 0, 2)) {
                throw new RuntimeException('outside_working_hours');
            }

            $endHour = $startHour + $durationHours;
            $conflict = $pdo->prepare("SELECT id FROM bookings WHERE field_id = :field_id AND booking_date = :booking_date AND status IN ('pending','confirmed') AND start_hour < :end_hour AND start_hour + duration_hours > :start_hour LIMIT 1 FOR UPDATE");
            $conflict->execute(['field_id' => $fieldId, 'booking_date' => $date, 'end_hour' => $endHour, 'start_hour' => $startHour]);
            if ($conflict->fetch()) throw new RuntimeException('slot_conflict');

            $dayType = $dayNumber >= 6 ? 'weekend' : 'weekday';
            $priceStmt = $pdo->prepare('SELECT price FROM prices WHERE field_id = :field_id AND day_type = :day_type AND time_of_day = :time_of_day LIMIT 1');
            $total = 0.0;
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $timeType = $hour >= (int) substr((string) $field['evening_from'], 0, 2) ? 'evening' : 'day';
                $priceStmt->execute(['field_id' => $fieldId, 'day_type' => $dayType, 'time_of_day' => $timeType]);
                $hourPrice = $priceStmt->fetchColumn();
                if ($hourPrice === false) throw new RuntimeException('price_not_configured');
                $total += (float) $hourPrice;
            }

            $code = 'FFBS-' . $bookingDate->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $insert = $pdo->prepare("INSERT INTO bookings (booking_code, user_id, field_id, booking_date, start_hour, duration_hours, price, status, created_at, updated_at) VALUES (:code, :user_id, :field_id, :booking_date, :start_hour, :duration_hours, :price, 'pending', NOW(), NOW())");
            $insert->execute(['code' => $code, 'user_id' => $customerId, 'field_id' => $fieldId, 'booking_date' => $date, 'start_hour' => $startHour, 'duration_hours' => $durationHours, 'price' => number_format($total, 2, '.', '')]);
            $bookingId = (int) $pdo->lastInsertId();
            $payment = $pdo->prepare("INSERT INTO payments (booking_id, amount, method, status, created_at, updated_at) VALUES (:booking_id, :amount, :method, 'pending', NOW(), NOW())");
            $payment->execute(['booking_id' => $bookingId, 'amount' => number_format($total, 2, '.', ''), 'method' => $paymentMethod]);

            $pdo->commit();
            return $this->findForCustomer($bookingId, $customerId) ?? ['id' => $bookingId, 'booking_code' => $code, 'price' => $total, 'status' => 'pending'];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function findForCustomer(int $id, int $customerId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT b.*, f.name AS field_name, f.city FROM bookings b JOIN fields f ON f.id = b.field_id WHERE b.id = :id AND b.user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $customerId]);
        return $stmt->fetch() ?: null;
    }

    public function customerBookings(int $customerId): array
    {
        $stmt = $this->pdo()->prepare('SELECT b.*, f.name AS field_name, f.city, p.status AS payment_status FROM bookings b JOIN fields f ON f.id = b.field_id LEFT JOIN payments p ON p.booking_id = b.id WHERE b.user_id = :user_id ORDER BY b.booking_date DESC, b.start_hour DESC');
        $stmt->execute(['user_id' => $customerId]);
        return $stmt->fetchAll();
    }

    public function ownerBookings(int $ownerId): array
    {
        $stmt = $this->pdo()->prepare('SELECT b.*, f.name AS field_name, u.full_name AS customer_name, u.phone AS customer_phone, p.status AS payment_status FROM bookings b JOIN fields f ON f.id = b.field_id JOIN users u ON u.id = b.user_id LEFT JOIN payments p ON p.booking_id = b.id WHERE f.owner_id = :owner_id ORDER BY b.booking_date DESC, b.start_hour DESC');
        $stmt->execute(['owner_id' => $ownerId]);
        return $stmt->fetchAll();
    }

    public function cancelForCustomer(int $bookingId, int $customerId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id AND user_id = :user_id FOR UPDATE');
            $stmt->execute(['id' => $bookingId, 'user_id' => $customerId]);
            $booking = $stmt->fetch();
            if (!$booking) throw new RuntimeException('booking_not_found');
            if (!in_array($booking['status'], ['pending', 'confirmed'], true)) throw new RuntimeException('booking_not_cancellable');
            $start = new DateTimeImmutable($booking['booking_date'] . sprintf(' %02d:00:00', (int) $booking['start_hour']), new DateTimeZone('Asia/Dushanbe'));
            $hoursLeft = ($start->getTimestamp() - time()) / 3600;
            $cancelHours = (int) ($GLOBALS['ffbs_config']['booking']['cancel_hours'] ?? 3);
            if ($hoursLeft < $cancelHours) throw new RuntimeException('cancellation_window_passed');

            $update = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = 'customer_request', updated_at = NOW() WHERE id = :id");
            $update->execute(['id' => $bookingId]);
            $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, created_at) VALUES (:actor, 'booking.cancelled', 'booking', :entity_id, :details, NOW())");
            $audit->execute(['actor' => $customerId, 'entity_id' => $bookingId, 'details' => json_encode(['reason' => 'customer_request'], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirmByOwner(int $bookingId, int $ownerId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE bookings b JOIN fields f ON f.id = b.field_id SET b.status = 'confirmed', b.confirmed_by = :confirmed_by, b.updated_at = NOW() WHERE b.id = :booking_id AND f.owner_id = :field_owner AND b.status = 'pending'");
            $stmt->execute(['confirmed_by' => $ownerId, 'booking_id' => $bookingId, 'field_owner' => $ownerId]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('booking_not_confirmable');
            $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, created_at) VALUES (:actor, 'booking.confirmed', 'booking', :entity_id, :details, NOW())");
            $audit->execute(['actor' => $ownerId, 'entity_id' => $bookingId, 'details' => json_encode(['status' => 'confirmed'], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}
