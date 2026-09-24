<?php
declare(strict_types=1);

// Copy these values to environment variables on deployment. XAMPP defaults work locally.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'davao_local_directory';
const DB_USER = 'root';
const DB_PASS = '';
const APP_TIMEZONE = 'Asia/Manila';

date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Load shared presentation behavior without altering any existing page templates.
if (PHP_SAPI !== 'cli') {
    ob_start(function (string $html): string {
        if (stripos($html, '</head>') !== false) $html = str_ireplace('</head>', '<link rel="stylesheet" href="ui.css"><link rel="stylesheet" href="location.css"><link rel="stylesheet" href="profile.css"><link rel="stylesheet" href="chat.css"><link rel="stylesheet" href="customer.css"><script defer src="ui.js"></script><script defer src="chat.js"></script><script defer src="messages.js"></script></head>', $html);
        return $html;
    });
}

function db(bool $withoutDatabase = false): PDO {
    static $connections = [];
    $key = $withoutDatabase ? 'server' : 'app';
    if (isset($connections[$key])) return $connections[$key];
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4' . ($withoutDatabase ? '' : ';dbname=' . DB_NAME);
    return $connections[$key] = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

function logged_in(): bool { return isset($_SESSION['user']); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_role(string $role): bool { return logged_in() && user()['role'] === $role; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid form request. Please go back and try again.'); } }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function notify_user(PDO $pdo, int $userId, string $message, ?string $link = null, string $type = 'general'): void {
    $pdo->prepare('INSERT INTO notifications(user_id,message,link_url,type) VALUES(?,?,?,?)')->execute([$userId, mb_strimwidth($message, 0, 255, ''), $link, $type]);
}
function business_open_now(PDO $pdo, int $businessId): bool {
    $q=$pdo->prepare('SELECT opens,closes,is_closed FROM business_hours WHERE business_id=? AND day_of_week=?');
    $q->execute([$businessId,(int)date('w')]); $h=$q->fetch(); $now=date('H:i:s');
    return (bool)($h && !$h['is_closed'] && $h['opens'] && $h['closes'] && $h['opens'] <= $now && $now < $h['closes']);
}
function upload_business_image(array $file, int $businessId): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Please choose an image to upload.');
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Images must be 5 MB or smaller.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime]) || !@getimagesize($file['tmp_name'])) throw new RuntimeException('Use a JPG, PNG, or WebP image.');
    $dir=__DIR__.'/uploads/businesses'; if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Image storage is unavailable.');
    $name='business-'.$businessId.'-'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Could not save the image.');
    return 'uploads/businesses/'.$name;
}

// Preserve the existing dashboard route while directing administrators to the upgraded console.
if (($_GET['page'] ?? '') === 'admin' && ($_SESSION['user']['role'] ?? '') === 'admin' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php') {
    header('Location: admin.php');
    exit;
}
if (($_GET['page'] ?? '') === 'dashboard' && ($_SESSION['user']['role'] ?? '') === 'owner' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php') {
    header('Location: owner.php');
    exit;
}
if (($_GET['page'] ?? '') === 'dashboard' && ($_SESSION['user']['role'] ?? '') === 'customer' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php') {
    header('Location: customer.php');
    exit;
}
