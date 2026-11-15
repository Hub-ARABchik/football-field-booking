<?php require_once 'config.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') go('/index.php'); csrf_check();
$u = user(); if ($u['role'] !== 'client') { flash('err','Танҳо мизоҷ банд карда метавонад.'); go('/index.php'); }
$fid = (int)$_POST['field_id']; $date = $_POST['date'] ?? ''; $hour = (int)($_POST['hour'] ?? -1);
$method = in_array($_POST['method'] ?? '', ['cash','transfer']) ? $_POST['method'] : 'cash';
$back = "/field.php?id=$fid";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4))) { flash('err','Санаи нодуруст.'); go($back); }
if (strtotime("$date $hour:00") <= time()) { flash('err','Вақти гузаштаро банд кардан мумкин нест.'); go($back); }
if (strtotime($date) > strtotime('+70 days')) { flash('err','Банд кардан танҳо то 70 рӯз пеш мумкин аст.'); go($back); }
try {
  $pdo->beginTransaction();
  // Сатри майдончаро қулф мекунем: дархостҳои баробарвақт навбат мегиранд
  $s = $pdo->prepare('SELECT * FROM fields WHERE id=? AND is_active=1 FOR UPDATE'); $s->execute([$fid]); $f = $s->fetch();
  if (!$f || $hour < (int)substr($f['open_time'],0,2) || $hour >= (int)substr($f['close_time'],0,2)) throw new RuntimeException('Ин соат дар ҷадвали корӣ нест.');
  $c = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE field_id=? AND booking_date=? AND start_hour=? AND status<>'cancelled' FOR UPDATE");
  $c->execute([$fid, $date, $hour]);
  if ($c->fetchColumn() > 0) throw new RuntimeException('Ин соат аллакай банд шудааст. Соати дигарро интихоб кунед.');
  $price = price_calc(load_prices($pdo, $fid), $date, $hour, $f['evening_from']);
  $pdo->prepare('INSERT INTO bookings (user_id,field_id,booking_date,start_hour,price) VALUES (?,?,?,?,?)')->execute([$u['id'], $fid, $date, $hour, $price]);
  $bid = $pdo->lastInsertId();
  $pdo->prepare('INSERT INTO payments (booking_id,amount,method) VALUES (?,?,?)')->execute([$bid, $price, $method]);
  $pdo->commit();
  flash('ok','Банд сабт шуд. Маъмур пардохтро тасдиқ мекунад.'); go('/my.php');
} catch (RuntimeException $e) { $pdo->rollBack(); flash('err', $e->getMessage()); go($back);
} catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack();
  flash('err', $e->getCode() == 23000 ? 'Ин соат дар ҳамин лаҳза аз ҷониби каси дигар банд шуд.' : 'Хатои пойгоҳи додаҳо.'); go($back); }
