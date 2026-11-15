<?php require_once 'config.php'; $title='Сабти ном'; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $n=trim($_POST['full_name']); $ph=preg_replace('/\D/','',$_POST['phone']); $pw=$_POST['password'];
  if (mb_strlen($n)<3) $err='Номро пурра нависед.'; elseif (strlen($ph)<9) $err='Рақами телефон нодуруст аст.'; elseif (strlen($pw)<6) $err='Парол ҳадди ақал 6 аломат бошад.';
  else { $c=$pdo->prepare('SELECT 1 FROM users WHERE phone=?'); $c->execute([$ph]);
    if ($c->fetch()) $err='Ин рақам аллакай сабт шудааст.';
    else { $pdo->prepare('INSERT INTO users (full_name,phone,email,password_hash) VALUES (?,?,?,?)')->execute([$n,$ph,trim($_POST['email'])?:null,password_hash($pw,PASSWORD_DEFAULT)]);
      session_regenerate_id(true); $_SESSION['uid']=$pdo->lastInsertId(); flash('ok','Хуш омадед!'); go('/index.php'); } } }
require 'includes/header.php'; ?>
<form class="form card" method="post"><h1>Сабти ном</h1><?php if($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?><?= csrf_field() ?>
<label class="f">Ному насаб<input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></label>
<label class="f">Рақами телефон<input type="text" name="phone" inputmode="tel" required value="<?= e($_POST['phone'] ?? '') ?>"></label>
<label class="f">Email (ихтиёрӣ)<input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label class="f">Парол<input type="password" name="password" required minlength="6"></label>
<button class="btn" style="width:100%">Сабт шудан</button></form>
<?php require 'includes/footer.php'; ?>
