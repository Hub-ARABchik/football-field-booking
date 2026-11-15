<?php require_once 'config.php'; require_login(); if ($_SERVER['REQUEST_METHOD'] !== 'POST') go('/my.php'); csrf_check(); $u = user();
$pdo->beginTransaction();
$s = $pdo->prepare("SELECT * FROM bookings WHERE id=? AND user_id=? FOR UPDATE"); $s->execute([(int)$_POST['id'], $u['id']]); $b = $s->fetch();
$ts = $b ? strtotime($b['booking_date'].' '.$b['start_hour'].':00') : 0;
if (!$b || !in_array($b['status'], ['pending','confirmed'])) { $pdo->rollBack(); flash('err','Ин бандро бекор кардан мумкин нест.'); go('/my.php'); }
if ($ts - CANCEL_HOURS*3600 < time()) { $pdo->rollBack(); flash('err','Бекор кардан танҳо то '.CANCEL_HOURS.' соат пеш аз бозӣ мумкин аст.'); go('/my.php'); }
$pdo->prepare("UPDATE bookings SET status='cancelled', cancelled_at=NOW() WHERE id=?")->execute([$b['id']]);
$pdo->prepare("UPDATE payments SET status=IF(status='confirmed','refunded','rejected') WHERE booking_id=?")->execute([$b['id']]);
$pdo->commit(); flash('ok','Банд бекор шуд.'); go('/my.php');
