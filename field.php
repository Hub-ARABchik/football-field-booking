<?php require_once 'config.php';
$s = $pdo->prepare('SELECT * FROM fields WHERE id=? AND is_active=1'); $s->execute([(int)($_GET['id'] ?? 0)]); $f = $s->fetch();
if (!$f) { flash('err','Майдонча ёфт нашуд.'); go('/index.php'); }
$title = $f['name']; $u = user();
$w = max(0, min(8, (int)($_GET['w'] ?? 0)));
$start = strtotime('monday this week') + $w * 7 * 86400;
$days = []; for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime("+$i days", $start));
$q = $pdo->prepare("SELECT booking_date,start_hour,user_id FROM bookings WHERE field_id=? AND booking_date BETWEEN ? AND ? AND status<>'cancelled'");
$q->execute([$f['id'], $days[0], $days[6]]); $busy = [];
foreach ($q as $r) $busy[$r['booking_date'].'|'.$r['start_hour']] = (int)$r['user_id'];
$prices = load_prices($pdo, $f['id']);
$h0 = (int)substr($f['open_time'],0,2); $h1 = (int)substr($f['close_time'],0,2); $ev = (int)substr($f['evening_from'],0,2);
require 'includes/header.php'; ?>
<div class="row" style="flex-wrap:wrap;margin-bottom:22px">
  <div><h1 style="font-size:clamp(1.6rem,3.5vw,2.5rem)"><?= e($f['name']) ?></h1>
  <div class="tags"><span class="tag"><?= e($f['size']) ?></span><span class="tag"><?= e($f['surface']) ?></span><span class="tag"><?= substr($f['open_time'],0,5) ?>–<?= substr($f['close_time'],0,5) ?></span></div></div>
  <div class="weeknav">
    <?php if ($w > 0): ?><a class="btn ghost sm" href="?id=<?= $f['id'] ?>&w=<?= $w-1 ?>">← Ҳафтаи пеш</a><?php endif; ?>
    <?php if ($w < 8): ?><a class="btn ghost sm" href="?id=<?= $f['id'] ?>&w=<?= $w+1 ?>">Ҳафтаи оянда →</a><?php endif; ?>
  </div>
</div>
<p class="muted" style="max-width:70ch"><?= e($f['description']) ?></p>
<div class="card" style="margin:18px 0;padding:18px"><div class="cols" style="gap:0 24px">
  <div><span class="muted">Рӯзи корӣ</span><br>рӯз <b><?= money($prices['weekday']['day'] ?? 0) ?></b> · шом (аз <?= $ev ?>:00) <b><?= money($prices['weekday']['evening'] ?? 0) ?></b></div>
  <div><span class="muted">Шанбе ва Якшанбе</span><br>рӯз <b><?= money($prices['weekend']['day'] ?? 0) ?></b> · шом (аз <?= $ev ?>:00) <b><?= money($prices['weekend']['evening'] ?? 0) ?></b></div>
</div></div>
<div class="legend"><span><i style="background:rgba(52,211,153,.4)"></i>Холӣ (рӯз)</span><span><i style="background:rgba(255,181,71,.5)"></i>Холӣ (шом)</span><span><i style="background:#4f6559"></i>Банд</span><span><i style="background:#5b6cf0"></i>Банди шумо</span></div>
<div class="card sched-wrap">
<table class="sched"><thead><tr><th></th>
<?php foreach ($days as $i => $d): ?><th class="<?= $i>=5?'we':'' ?>"><?= $DAYS[$i] ?><b><?= date('d.m', strtotime($d)) ?></b></th><?php endforeach; ?></tr></thead><tbody>
<?php for ($h = $h0; $h < $h1; $h++): ?><tr><td class="h"><?= sprintf('%02d:00', $h) ?></td>
  <?php foreach ($days as $i => $d): $key = "$d|$h"; $p = price_calc($prices, $d, $h, $f['evening_from']); $past = strtotime("$d $h:00") <= time();
    if (isset($busy[$key])) { $mine = $u && $busy[$key] === (int)$u['id']; echo '<td><button class="cell '.($mine?'mine':'busy').'" disabled>'.($mine?'Шумо':'Банд').'</button></td>'; }
    elseif ($past) echo '<td><button class="cell past" disabled>—</button></td>';
    else echo '<td><button class="cell free '.($h>=$ev?'eve':'').'" data-date="'.$d.'" data-hour="'.$h.'" data-price="'.money($p).'" data-label="'.$DAYS[$i].', '.date('d.m', strtotime($d)).' · '.sprintf('%02d:00–%02d:00',$h,$h+1).'">'.round($p).'</button></td>';
  endforeach; ?></tr>
<?php endfor; ?></tbody></table></div>
<div id="bookModal" class="modal"><div class="card">
  <h3>Тасдиқи банд</h3><p class="muted"><?= e($f['name']) ?></p><p id="mWhen" style="margin-top:8px"></p><div class="sum" id="mPrice"></div>
  <?php if ($u && $u['role']==='client'): ?>
  <form method="post" action="book.php"><?= csrf_field() ?><input type="hidden" name="field_id" value="<?= $f['id'] ?>"><input type="hidden" name="date"><input type="hidden" name="hour">
    <div class="muted" style="font-size:.85rem">Тарзи пардохт</div>
    <div class="radios"><label><input type="radio" name="method" value="cash" checked>Нақд дар ҷой</label><label><input type="radio" name="method" value="transfer">Интиқол</label></div>
    <div class="row"><button type="button" class="btn ghost" data-close>Бекор</button><button class="btn">Банд кардан</button></div>
  </form>
  <?php else: ?><p class="muted" style="margin-bottom:16px"><?= $u ? 'Маъмур банд карда наметавонад.' : 'Барои банд кардан аввал ворид шавед.' ?></p>
    <div class="row"><button type="button" class="btn ghost" data-close>Пӯшидан</button><?php if(!$u): ?><a class="btn" href="login.php">Ворид шудан</a><?php endif; ?></div><?php endif; ?>
</div></div>
<?php require 'includes/footer.php'; ?>
