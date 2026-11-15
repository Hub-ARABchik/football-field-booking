<?php $title='Бандҳои ман'; require_once 'config.php'; require_login(); require 'includes/header.php';
$s = $pdo->prepare("SELECT b.*, f.name fname, p.status pstatus, p.method FROM bookings b JOIN fields f ON f.id=b.field_id LEFT JOIN payments p ON p.booking_id=b.id WHERE b.user_id=? ORDER BY b.booking_date DESC, b.start_hour DESC");
$s->execute([$u['id']]); $rows = $s->fetchAll(); $PS = ['pending'=>'интизор','confirmed'=>'пардохташуда','rejected'=>'рад шуд','refunded'=>'баргардонида шуд']; ?>
<h2>Таърихи бандҳои ман</h2>
<div class="card scroll"><table class="tbl"><thead><tr><th>Майдонча</th><th>Сана</th><th>Вақт</th><th>Нарх</th><th>Пардохт</th><th>Ҳолат</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $ts = strtotime($r['booking_date'].' '.$r['start_hour'].':00');
  $st = $r['status']; if ($st === 'confirmed' && $ts + 3600 < time()) $st = 'completed';
  $can = in_array($r['status'], ['pending','confirmed']) && $ts - CANCEL_HOURS*3600 >= time(); ?>
<tr><td><?= e($r['fname']) ?></td><td><?= date('d.m.Y', strtotime($r['booking_date'])) ?></td><td><?= sprintf('%02d:00–%02d:00',$r['start_hour'],$r['start_hour']+1) ?></td>
<td><?= money($r['price']) ?></td><td class="muted"><?= $PS[$r['pstatus']] ?? '—' ?></td><td><span class="badge b-<?= $st ?>"><?= $STATUS[$st] ?></span></td>
<td><?php if ($can): ?><form method="post" action="cancel.php" onsubmit="return confirm('Банд бекор карда шавад?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn danger sm">Бекор кардан</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">Шумо ҳоло банд накардаед. <a href="index.php#fields" style="color:var(--mint)">Майдончаро интихоб кунед</a>.</td></tr><?php endif; ?>
</tbody></table></div>
<p class="muted" style="margin-top:14px">Бандро то <?= CANCEL_HOURS ?> соат пеш аз оғози бозӣ бекор кардан мумкин аст.</p>
<?php require 'includes/footer.php'; ?>
