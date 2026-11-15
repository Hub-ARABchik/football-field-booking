<?php
// ===== Танзимот =====
define('BASE', '/futbol');          // номи папка дар htdocs
define('DB_HOST', 'localhost'); define('DB_NAME', 'futbol_booking');
define('DB_USER', 'root');      define('DB_PASS', '');
define('CANCEL_HOURS', 3);          // то чанд соат пеш аз бозӣ бекор кардан мумкин аст
define('CURRENCY', 'сом.');

date_default_timezone_set('Asia/Dushanbe');
session_start();
try {
  $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) { die('Пайвастшавӣ ба пойгоҳи додаҳо имконнопазир аст: '.htmlspecialchars($e->getMessage())); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function go($path){ header('Location: '.BASE.$path); exit; }
function flash($type, $msg){ $_SESSION['flash'][] = [$type, $msg]; }
function user(){ static $u = false; if ($u === false) { global $pdo;
  $u = null; if (!empty($_SESSION['uid'])) { $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; } } return $u; }
function require_login(){ if (!user()) { flash('err','Аввал ба низом ворид шавед.'); go('/login.php'); } }
function require_admin(){ $u = user(); if (!$u || $u['role'] !== 'admin') { flash('err','Ин саҳифа танҳо барои маъмур аст.'); go('/login.php'); } }
function csrf(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.csrf().'">'; }
function csrf_check(){ if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); die('Токени амният нодуруст аст.'); } }
function money($n){ return number_format((float)$n, 0, '.', ' ').' '.CURRENCY; }

// Ҷадвали нархҳо барои як майдонча: [day_type][time_of_day] => price
function load_prices($pdo, $fid){
  $s = $pdo->prepare('SELECT day_type,time_of_day,price FROM prices WHERE field_id=?'); $s->execute([$fid]); $p = [];
  foreach ($s as $r) $p[$r['day_type']][$r['time_of_day']] = (float)$r['price']; return $p;
}
// Нархи як соат: рӯзи корӣ/истироҳат (Шанбе, Якшанбе) ва рӯз/шом
function price_calc($prices, $date, $hour, $eveningFrom){
  $dt  = ((int)date('N', strtotime($date)) >= 6) ? 'weekend' : 'weekday';
  $tod = ($hour >= (int)substr($eveningFrom, 0, 2)) ? 'evening' : 'day';
  return $prices[$dt][$tod] ?? 0;
}
$DAYS = ['Душанбе','Сешанбе','Чоршанбе','Панҷшанбе','Ҷумъа','Шанбе','Якшанбе'];
$MONTHS = ['','Январ','Феврал','Март','Апрел','Май','Июн','Июл','Август','Сентябр','Октябр','Ноябр','Декабр'];
$STATUS = ['pending'=>'Интизори пардохт','confirmed'=>'Тасдиқшуда','cancelled'=>'Бекоршуда','completed'=>'Анҷомёфта'];
