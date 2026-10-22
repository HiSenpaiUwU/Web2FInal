<?php
/* Shared customer dashboard frame. */
function customer_nav(int $unreadMessages, string $active = ''): void {
?>
<header class="site-header"><div class="nav-container">
  <a class="brand" href="index.php" aria-label="Davao Local home"><span class="brand-mark">D</span><span>Davao <b>Local</b></span></a>
  <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="customer-navigation"><span></span><span></span><span></span></button>
  <nav id="customer-navigation" aria-label="Customer navigation"><a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="customer.php">Dashboard</a><a class="<?= $active === 'directory' ? 'active' : '' ?>" href="index.php?page=directory">Directory</a><a class="<?= $active === 'map' ? 'active' : '' ?>" href="index.php?page=map">Map</a><a class="<?= $active === 'saved' ? 'active' : '' ?>" href="customer.php#saved">Saved</a><a class="message-nav <?= $active === 'messages' ? 'active' : '' ?>" href="messages.php">Messages<?php if($unreadMessages):?><span><?=$unreadMessages?></span><?php endif?></a><a class="<?= $active === 'notifications' ? 'active' : '' ?>" href="notifications.php">Notifications</a><a class="nav-logout" href="index.php?action=logout">Log out</a></nav>
</div></header>
<?php
}

function customer_page(string $title, string $content, int $unreadMessages, string $headerAction = '', string $active = ''): never {
    ob_start();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Davao Local</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="customer.css?v=<?= (int)filemtime(__DIR__ . '/customer.css') ?>"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"></head><body><?php customer_nav($unreadMessages, $active) ?><main class="admin-main standalone customer-dashboard"><header class="customer-hero customer-subpage-header"><p class="eyebrow">CUSTOMER DASHBOARD</p><h1><?= e($title) ?></h1><?= $headerAction ?></header><?= $content ?></main><script src="ui.js"></script><script>(function(){const nav=document.getElementById('customer-navigation'),links=nav?nav.querySelectorAll('a'):[];function savedState(){const isSaved=location.hash==='#saved';links.forEach(function(link){if(link.getAttribute('href')==='customer.php#saved')link.classList.toggle('active',isSaved);if(isSaved&&link.getAttribute('href')==='customer.php')link.classList.remove('active');});}savedState();window.addEventListener('hashchange',savedState);})();</script></body></html>
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
