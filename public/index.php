<?php $title = 'Майдончаҳои футбол'; require 'includes/header.php';
$fields = $pdo->query("SELECT f.*, (SELECT MIN(price) FROM prices WHERE field_id=f.id) AS minp FROM fields f WHERE is_active=1 ORDER BY id")->fetchAll();
$nF = count($fields);
$nB = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status<>'cancelled'")->fetchColumn();
$nU = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?>
<section class="hero">
  <div>
    <h1>Майдончаро банд кунед, то бозӣ оғоз шавад.</h1>
    <p>Вақтҳои холиро дар ҷадвал бубинед, соати лозимаро интихоб кунед ва дар якчанд сония банд кунед.</p>
    <div class="cta"><a class="btn" href="#fields">Майдончаҳоро дидан</a><?php if(!$u): ?><a class="btn ghost" href="register.php">Сабти ном</a><?php endif; ?></div>
    <div class="stats"><div><b data-count="<?= $nF ?>">0</b><span>майдонча</span></div><div><b data-count="<?= $nB ?>">0</b><span>банд</span></div><div><b data-count="<?= $nU ?>">0</b><span>мизоҷ</span></div></div>
  </div>
  <svg class="pitch" viewBox="0 0 400 270" aria-hidden="true">
    <rect x="10" y="10" width="380" height="250" rx="35"/><path d="M200 10V260"/><circle cx="200" cy="135" r="38"/>
    <path d="M10 80h58v110H10M390 80h-58v110h58"/><path d="M10 112h24v46H10M390 112h-24v46h24"/><circle class="dot" cx="200" cy="135" r="4"/>
  </svg>
</section>
<section id="fields" class="sec">
  <h2>Майдончаҳо</h2>
  <div class="grid">
  <?php foreach ($fields as $f): ?>
    <a class="card fcard rv" href="field.php?id=<?= $f['id'] ?>">
      <div class="ph"><?= $f['photo'] ? '<img src="'.BASE.'/uploads/'.e($f['photo']).'" alt="'.e($f['name']).'">' : '⚽' ?></div>
      <div class="body">
        <h3><?= e($f['name']) ?></h3>
        <div class="tags"><span class="tag"><?= e($f['size']) ?></span><span class="tag"><?= e($f['surface']) ?></span><span class="tag"><?= substr($f['open_time'],0,5) ?>–<?= substr($f['close_time'],0,5) ?></span></div>
        <p class="muted"><?= e($f['description']) ?></p>
        <div class="row" style="margin-top:18px"><div class="price"><small>аз </small><?= money($f['minp']) ?><small> / соат</small></div><span class="btn sm">Банд кардан</span></div>
      </div>
    </a>
  <?php endforeach; if (!$fields): ?><p class="muted">Ҳоло майдонча илова нашудааст.</p><?php endif; ?>
  </div>
</section>
<section class="steps">
  <div class="card rv"><h3>Майдончаро интихоб кунед</h3><p class="muted">Андоза, рӯйпӯш ва нарх дар як ҷо.</p></div>
  <div class="card rv"><h3>Соатро пахш кунед</h3><p class="muted">Ҷадвали ҳафта нишон медиҳад, кадом соатҳо холӣ ҳастанд.</p></div>
  <div class="card rv"><h3>Пардохтро тасдиқ кунед</h3><p class="muted">Маъмур пардохтро тасдиқ мекунад ва банди шумо қатъӣ мешавад.</p></div>
</section>
<?php require 'includes/footer.php'; ?>
