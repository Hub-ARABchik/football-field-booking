-- Optional sample catalog data for a disposable development database only.
-- No demo user and no reusable password are created by this file.
SET NAMES utf8mb4;

INSERT INTO fields (owner_id, name, city, address, size, surface, description, capacity, open_time, close_time, evening_from, status, is_active)
SELECT NULL, 'Арена Меркурий', 'Хуҷанд', 'Суроғаи намунавӣ 1', '5x5', 'Алафи сунъӣ', 'Майдони намунавӣ барои санҷиши каталог.', 10, '08:00', '23:00', '18:00', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE name = 'Арена Меркурий' AND city = 'Хуҷанд');

INSERT INTO fields (owner_id, name, city, address, size, surface, description, capacity, open_time, close_time, evening_from, status, is_active)
SELECT NULL, 'Арена Олимп', 'Хуҷанд', 'Суроғаи намунавӣ 2', '7x7', 'Алафи сунъӣ', 'Майдони кушодаи намунавӣ.', 14, '08:00', '23:00', '18:00', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE name = 'Арена Олимп' AND city = 'Хуҷанд');

INSERT INTO fields (owner_id, name, city, address, size, surface, description, capacity, open_time, close_time, evening_from, status, is_active)
SELECT NULL, 'Стадион Хуҷанд', 'Хуҷанд', 'Суроғаи намунавӣ 3', '11x11', 'Алафи табиӣ', 'Майдони калон барои бозиҳои даставӣ.', 22, '09:00', '22:00', '18:00', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE name = 'Стадион Хуҷанд' AND city = 'Хуҷанд');

INSERT INTO prices (field_id, day_type, time_of_day, price)
SELECT f.id, p.day_type, p.time_of_day, p.price
FROM fields f
JOIN (
  SELECT 'Арена Меркурий' AS field_name, 'weekday' AS day_type, 'day' AS time_of_day, 80.00 AS price UNION ALL
  SELECT 'Арена Меркурий', 'weekday', 'evening', 120.00 UNION ALL
  SELECT 'Арена Меркурий', 'weekend', 'day', 100.00 UNION ALL
  SELECT 'Арена Меркурий', 'weekend', 'evening', 150.00 UNION ALL
  SELECT 'Арена Олимп', 'weekday', 'day', 110.00 UNION ALL
  SELECT 'Арена Олимп', 'weekday', 'evening', 160.00 UNION ALL
  SELECT 'Арена Олимп', 'weekend', 'day', 140.00 UNION ALL
  SELECT 'Арена Олимп', 'weekend', 'evening', 200.00 UNION ALL
  SELECT 'Стадион Хуҷанд', 'weekday', 'day', 250.00 UNION ALL
  SELECT 'Стадион Хуҷанд', 'weekday', 'evening', 350.00 UNION ALL
  SELECT 'Стадион Хуҷанд', 'weekend', 'day', 300.00 UNION ALL
  SELECT 'Стадион Хуҷанд', 'weekend', 'evening', 420.00
) p ON p.field_name = f.name
WHERE f.city = 'Хуҷанд'
  AND NOT EXISTS (SELECT 1 FROM prices x WHERE x.field_id = f.id AND x.day_type = p.day_type AND x.time_of_day = p.time_of_day);

INSERT INTO field_schedules (field_id, day_of_week, open_time, close_time, is_closed)
SELECT f.id, d.day_of_week, f.open_time, f.close_time, 0
FROM fields f
CROSS JOIN (
  SELECT 1 AS day_of_week UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
) d
WHERE f.city = 'Хуҷанд'
  AND f.name IN ('Арена Меркурий', 'Арена Олимп', 'Стадион Хуҷанд')
  AND NOT EXISTS (SELECT 1 FROM field_schedules s WHERE s.field_id = f.id AND s.day_of_week = d.day_of_week);
