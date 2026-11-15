<?php require_once 'config.php'; $_SESSION = []; session_destroy(); session_start(); flash('ok','Шумо аз низом баромадед.'); go('/index.php');
