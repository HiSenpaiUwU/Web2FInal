<?php
require __DIR__ . '/config.php';
require __DIR__ . '/database_upgrade.php';
require __DIR__ . '/customer_layout.php';

try {
    $p = db();
    apply_database_upgrade($p);
} catch (Throwable $e) {
    exit('Directory unavailable.');
}
if (!is_role('customer')) {
    flash('Customer access is required.', 'error');
    redirect('index.php?page=login');
}

$u = user();
$q = $p->prepare('SELECT b.*, c.name category FROM favorites f JOIN businesses b ON b.id=f.business_id JOIN categories c ON c.id=b.category_id WHERE f.user_id=? AND b.status="approved" ORDER BY f.created_at DESC LIMIT 6');
$q->execute([$u['id']]);
$favorites = $q->fetchAll();
$q = $p->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND read_at IS NULL');
$q->execute([$u['id']]);
$unreadMessages = (int) $q->fetchColumn();
$q = $p->prepare('SELECT cv.id,cv.updated_at,b.name business,(SELECT COUNT(*) FROM messages m WHERE m.conversation_id=cv.id AND m.receiver_id=? AND m.read_at IS NULL) unread FROM conversations cv JOIN businesses b ON b.id=cv.business_id WHERE cv.customer_id=? AND b.status="approved" ORDER BY cv.updated_at DESC LIMIT 4');
$q->execute([$u['id'], $u['id']]);
$conversations = $q->fetchAll();
$q = $p->prepare("SELECT a.id,a.appointment_at,a.status,b.name business,s.name service FROM appointments a JOIN businesses b ON b.id=a.business_id LEFT JOIN services s ON s.id=a.service_id WHERE a.customer_id=? AND a.appointment_at>=NOW() AND a.status IN ('pending','confirmed','rescheduled') ORDER BY a.appointment_at ASC LIMIT 5");
$q->execute([$u['id']]);
$appointments = $q->fetchAll();
$q = $p->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id=? AND appointment_at>=NOW() AND status IN ('pending','confirmed','rescheduled')");
$q->execute([$u['id']]);
$appointmentCount = (int) $q->fetchColumn();
$categories = $p->query('SELECT id, name FROM categories WHERE active=1 ORDER BY name LIMIT 8')->fetchAll();
$customerCssVersion = filemtime(__DIR__ . '/customer.css');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My dashboard | Davao Local</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="admin.css">
  <link rel="stylesheet" href="customer.css?v=<?= $customerCssVersion ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
</head>
<body>
  <main class="admin-main standalone customer-dashboard">
    <?php customer_nav($unreadMessages, 'dashboard'); ?>
    <header class="customer-hero">
      <p class="eyebrow">CUSTOMER DASHBOARD</p>
      <h1>Welcome back, <?= e(explode(' ', $u['name'])[0]) ?>.</h1>
      <p>Your saved places, conversations, and upcoming appointments.</p>
      <form action="index.php" class="customer-search">
        <input type="hidden" name="page" value="directory">
        <label class="search-label"><span aria-hidden="true">⌕</span><input name="q" aria-label="Search businesses, products, or services" placeholder="Search businesses, products, or services"></label>
        <button class="button coral">Search directory</button>
      </form>
      <a class="customer-directory-link" href="index.php?page=directory">Browse all Davao listings →</a>
      <section class="category-shortcuts" aria-labelledby="browse-categories">
        <h2 id="browse-categories">Browse by category</h2>
        <div><?php foreach ($categories as $c): ?><a href="index.php?page=directory&amp;category=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
      </section>
    </header>
    <?php if ($f = take_flash()): ?><div class="flash <?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif ?>
    <section class="panel customer-priority" aria-label="Your activity">
      <div class="customer-priority-links">
        <a href="#saved"><b><?= count($favorites) ?></b><span>Saved businesses</span></a>
        <a href="messages.php"><b><?= count($conversations) ?></b><span>Conversations<?php if ($unreadMessages): ?> · <?= $unreadMessages ?> unread<?php endif ?></span></a>
        <a href="appointments.php"><b><?= $appointmentCount ?></b><span>Upcoming appointments</span></a>
      </div>
    </section>
    <section class="panel" id="saved">
      <div class="section-title"><div><h2>Saved businesses</h2><p>Listings you bookmarked for later.</p></div><a class="text-link" href="index.php?page=directory">Find more →</a></div>
      <div class="cards">
        <?php foreach ($favorites as $b): ?><article class="business-card"><div class="card-content"><span class="pill"><?= e($b['category']) ?></span><?php if ((bool) $b['is_demo']): ?><small class="demo-badge">Fictional demo</small><?php endif ?><?php if ($b['is_verified']): ?><small class="verified">Verified</small><?php endif ?><h3><?= e($b['name']) ?></h3><p>📍 <?= e($b['barangay'] ?: $b['city']) ?>, <?= e($b['city']) ?></p><a class="text-link" href="index.php?page=business&amp;id=<?= (int) $b['id'] ?>">View business →</a></div></article><?php endforeach ?>
        <?php if (!$favorites): ?><p class="empty">No saved businesses yet. Browse the directory and choose “Save” on a listing to keep it here. <a href="index.php?page=directory">Explore businesses →</a></p><?php endif ?>
      </div>
    </section>
    <div class="customer-dashboard-columns">
      <section class="panel">
        <div class="section-title"><div><h2>Conversations</h2><p>Messages with business owners.</p></div><a href="messages.php">All messages →</a></div>
        <?php if ($conversations): ?><ul class="customer-activity-list"><?php foreach ($conversations as $conversation): ?><li><a href="messages.php?conversation=<?= (int) $conversation['id'] ?>"><b><?= e($conversation['business']) ?></b><span><?= $conversation['unread'] ? (int) $conversation['unread'] . ' unread' : 'Open conversation' ?> · <?= e(date('M j, Y', strtotime($conversation['updated_at']))) ?></span></a></li><?php endforeach ?></ul><?php else: ?><p class="empty">No conversations yet. Open a business profile and send an inquiry to start one. <a href="index.php?page=directory">Browse listings →</a></p><?php endif ?>
      </section>
      <section class="panel">
        <div class="section-title"><div><h2>Upcoming appointments</h2><p>Your pending and confirmed bookings.</p></div><a href="appointments.php">Appointments →</a></div>
        <?php if ($appointments): ?><ul class="customer-activity-list"><?php foreach ($appointments as $appointment): ?><li><a href="appointments.php"><b><?= e($appointment['business']) ?><?= $appointment['service'] ? ' · ' . e($appointment['service']) : '' ?></b><span><?= e(date('M j, Y · g:i A', strtotime($appointment['appointment_at']))) ?> · <?= e(ucfirst($appointment['status'])) ?></span></a></li><?php endforeach ?></ul><?php else: ?><p class="empty">No upcoming appointments. Check a business profile for available services and appointment options. <a href="index.php?page=directory">Find a business →</a></p><?php endif ?>
      </section>
    </div>
  </main>
</body>
</html>
