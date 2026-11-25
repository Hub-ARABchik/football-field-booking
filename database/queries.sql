-- FFBS: control queries for MySQL 5.7+/MariaDB.
-- Run after selecting the FFBS database. These are read-only examples.

-- 1. Public catalogue with the lowest hourly price.
SELECT f.id, f.name, f.city, f.size, f.surface, MIN(p.price) AS price_from
FROM fields f
LEFT JOIN prices p ON p.field_id = f.id
WHERE f.status = 'approved' AND f.is_active = 1
GROUP BY f.id
ORDER BY f.city, f.name;

-- 2. Customer booking history (bind :customer_id in application code).
SELECT b.booking_code, b.booking_date, b.start_hour, b.duration_hours,
       b.price, b.status, f.name AS field_name
FROM bookings b
JOIN fields f ON f.id = b.field_id
WHERE b.user_id = :customer_id
ORDER BY b.booking_date DESC, b.start_hour DESC;

-- 3. Owner dashboard totals (bind :owner_id).
SELECT COUNT(DISTINCT f.id) AS fields_total,
       COUNT(b.id) AS bookings_total,
       SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_bookings
FROM fields f
LEFT JOIN bookings b ON b.field_id = f.id
WHERE f.owner_id = :owner_id;

-- 4. Check interval conflicts before creating a booking.
-- The application performs this query inside a transaction after locking the field row.
SELECT id, start_hour, duration_hours, status
FROM bookings
WHERE field_id = :field_id
  AND booking_date = :booking_date
  AND status IN ('pending', 'confirmed')
  AND start_hour < :requested_end_hour
  AND start_hour + duration_hours > :requested_start_hour
FOR UPDATE;

-- 5. Monthly booking and gross-value report.
SELECT DATE_FORMAT(booking_date, '%Y-%m') AS month,
       COUNT(*) AS all_bookings,
       SUM(status = 'confirmed') AS confirmed,
       SUM(status = 'cancelled') AS cancelled,
       SUM(CASE WHEN status IN ('confirmed', 'completed') THEN price ELSE 0 END) AS gross_amount
FROM bookings
GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
ORDER BY month DESC;

-- 6. Payment reconciliation.
SELECT p.status, p.method, COUNT(*) AS payment_count, SUM(p.amount) AS amount_total
FROM payments p
GROUP BY p.status, p.method
ORDER BY p.status, p.method;

-- 7. Records waiting for moderation / action.
SELECT 'field' AS entity, id, name AS label, status, created_at
FROM fields WHERE status = 'pending'
UNION ALL
SELECT 'booking' AS entity, id, booking_code AS label, status, created_at
FROM bookings WHERE status = 'pending';

-- 8. Cyrillic UTF-8 storage check.
SELECT name, CHAR_LENGTH(name) AS characters_count, LENGTH(name) AS bytes_count
FROM fields WHERE city = 'Хуҷанд' LIMIT 10;
