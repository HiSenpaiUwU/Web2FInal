<?php
/* Shared customer dashboard frame. */
function customer_nav(int $unreadMessages, string $active = ''): void {
?>
<div class="customer-nav">
  <a class="brand" href="index.php"><span class="brand-mark">D</span>Davao <b>Local</b></a>
  <nav aria-label="Customer navigation"><a href="index.php?page=directory">Directory</a><a href="customer.php#saved">Saved</a><a href="messages.php" class="message-nav <?= $active === 'messages' ? 'active' : '' ?>">Messages<?php if($unreadMessages):?><span><?=$unreadMessages?></span><?php endif?></a><a href="index.php?action=logout">Log out</a></nav>
</div>
<?php
}

function customer_page(string $title, string $content, int $unreadMessages, string $headerAction = '', string $active = ''): never {
    ob_start();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Davao Local</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="customer.css"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"></head><body><main class="admin-main standalone customer-dashboard"><?php customer_nav($unreadMessages, $active) ?><header class="customer-hero customer-subpage-header"><p class="eyebrow">CUSTOMER DASHBOARD</p><h1><?= e($title) ?></h1><?= $headerAction ?></header><?= $content ?></main></body></html>
<?php
    echo ob_get_clean(); exit;
}

function customer_legacy_page(string $title, int $unreadMessages, string $html): never {
    preg_match('~<main class="admin-main standalone">(.*)</main>~s', $html, $match);
    $content = $match[1] ?? $html;
    preg_match('~<header class="admin-top">.*?(<form.*?</form>)\s*</header>~s', $content, $action);
    $content = preg_replace('~^\s*<a class="text-link"[^>]*>.*?</a>\s*<header class="admin-top">.*?</header>~s', '', $content, 1);
    customer_page($title, $content, $unreadMessages, $action[1] ?? '');
}
