<?php
function owner_nav(string $active, int $firstBusiness): void {
?>
<aside class="sidebar" id="owner-sidebar">
  <a class="brand" href="index.php"><span class="brand-mark">D</span>Davao <b>Local</b></a>
  <button class="menu-toggle" type="button" aria-controls="owner-links" aria-expanded="false">Menu</button>
  <nav id="owner-links">
    <p class="sidebar-label">OWNER WORKSPACE</p>
    <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="owner.php">Dashboard</a>
    <a class="<?= $active === 'business' ? 'active' : '' ?>" href="owner.php?edit=<?= $firstBusiness ?>">My Business</a>
    <a class="<?= $active === 'products' ? 'active' : '' ?>" href="products.php">Products</a>
    <a class="<?= $active === 'gallery' ? 'active' : '' ?>" href="business_gallery.php?business=<?= $firstBusiness ?>">Gallery</a>
    <a class="<?= $active === 'services' ? 'active' : '' ?>" href="owner.php?edit=<?= $firstBusiness ?>#services">Services</a>
    <a class="<?= $active === 'messages' ? 'active' : '' ?>" href="messages.php">Messages</a><a class="<?= $active === 'reviews' ? 'active' : '' ?>" href="owner_reviews.php">Reviews</a><a class="<?= $active === 'appointments' ? 'active' : '' ?>" href="appointments.php">Appointments</a><a class="<?= $active === 'notifications' ? 'active' : '' ?>" href="notifications.php">Notifications</a>
    <a href="index.php?page=directory">Business Preview</a><a href="index.php?page=directory">Directory</a><a href="index.php?page=business-form">Add Another Business</a><a href="owner.php#profile">Settings</a>
    <a class="logout" href="index.php?action=logout">Log Out</a>
  </nav>
</aside>
<?php
}

function owner_page(string $title, string $content, string $active, int $firstBusiness, string $headerAction = ''): never {
    $u = user(); ob_start();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Davao Local</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="owner.css?v=owner-layout-fixed-5"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"></head><body><div class="admin-shell"><?php owner_nav($active, $firstBusiness) ?><main class="admin-main"><header class="admin-top"><div><span class="eyebrow">BUSINESS OWNER</span><h1><?= e($title) ?></h1></div><div class="admin-user">Signed in as <b><?= e($u['name']) ?></b></div><?= $headerAction ?></header><?php if ($f = take_flash()): ?><div class="flash <?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif ?><?= $content ?></main></div></body></html>
<?php
    echo ob_get_clean(); exit;
}

/* Adapts older owner pages without changing their forms or page-specific markup. */
function owner_legacy_page(string $title, string $active, int $firstBusiness, string $html): never {
    preg_match('~<main class="admin-main(?: standalone)?">(.*)</main>~s', $html, $match);
    $content = $match[1] ?? $html;
    preg_match('~<header class="admin-top">.*?(<form.*?</form>)\s*</header>~s', $content, $action);
    $content = preg_replace('~^\s*<a class="text-link"[^>]*>.*?</a>\s*<header class="admin-top">.*?</header>~s', '', $content, 1);
    owner_page($title, $content, $active, $firstBusiness, $action[1] ?? '');
}
