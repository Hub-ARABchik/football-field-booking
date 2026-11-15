<?php require_once 'config.php'; $title='Ворид шудан'; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $s=$pdo->prepare('SELECT * FROM users WHERE phone=?'); $s->execute([preg_replace('/\D/','',$_POST['phone'])]); $x=$s->fetch();
  if ($x && password_verify($_POST['password'], $x['password_hash'])) { session_regenerate_id(true); $_SESSION['uid']=$x['id']; go($x['role']==='admin' ? '/admin/index.php' : '/index.php'); }
  $err='Рақами телефон ё парол нодуруст аст.'; }
require 'includes/header.php'; ?>
<form class="form card" method="post"><h1>Ворид шудан</h1><?php if($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?><?= csrf_field() ?>
<label class="f">Рақами телефон<input type="text" name="phone" inputmode="tel" required autofocus></label>
<label class="f">Парол<input type="password" name="password" required></label>
<button class="btn" style="width:100%">Ворид шудан</button><p class="muted" style="margin-top:16px">Ҳисоб надоред? <a href="register.php" style="color:var(--mint)">Сабти ном</a></p></form>
<?php require 'includes/footer.php'; ?>
