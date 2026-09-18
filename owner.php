<?php
require __DIR__ . '/config.php';
require __DIR__ . '/database_upgrade.php';
require __DIR__ . '/owner_layout.php';

try {
    $pdo = db();
    apply_database_upgrade($pdo);
} catch (Throwable $e) {
    http_response_code(503);
    exit('The database is unavailable. Please try again later.');
}
if (!is_role('owner')) {
    flash('Business owner access is required.', 'error');
    redirect('index.php?page=login');
}
$u = user();

$q = $pdo->prepare('SELECT b.*, c.name category, COUNT(DISTINCT pr.id) products, COUNT(DISTINCT bs.service_id) services, COUNT(DISTINCT r.id) reviews, COALESCE(AVG(r.rating),0) rating FROM businesses b JOIN categories c ON c.id=b.category_id LEFT JOIN products pr ON pr.business_id=b.id LEFT JOIN business_services bs ON bs.business_id=b.id LEFT JOIN reviews r ON r.business_id=b.id AND r.status="visible" WHERE b.owner_id=? GROUP BY b.id ORDER BY b.updated_at DESC');
$q->execute([$u['id']]);
$businesses = $q->fetchAll();
$firstBusiness = (int)($businesses[0]['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$name || !$email) {
            flash('Enter your name and a valid email.', 'error');
        } else {
            $pdo->prepare('UPDATE users SET name=?,email=? WHERE id=?')->execute([$name, $email, $u['id']]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            flash('Profile updated.');
        }
        redirect('owner.php#profile');
    }
    $businessId = (int)($_POST['id'] ?? 0);
    $owned = $pdo->prepare('SELECT id FROM businesses WHERE id=? AND owner_id=?');
    $owned->execute([$businessId, $u['id']]);
    if (!$owned->fetchColumn()) {
        http_response_code(403);
        exit('Not permitted.');
    }
    if ($action === 'business') {
        foreach (['name','contact_phone','address','barangay'] as $field) {
            if (!trim($_POST[$field] ?? '')) {
                flash('Please complete all required fields.', 'error');
                redirect('owner.php?edit='.$businessId);
            }
        }
        $pdo->prepare('UPDATE businesses SET name=?,description=?,contact_phone=?,contact_email=?,facebook_url=?,website=?,address=?,barangay=?,district=?,city=?,province=?,region=?,latitude=?,longitude=?,business_status=? WHERE id=? AND owner_id=?')->execute([
            trim($_POST['name']), trim($_POST['description'] ?? ''), trim($_POST['contact_phone']),
            trim($_POST['contact_email'] ?? ''), trim($_POST['facebook_url'] ?? ''), trim($_POST['website'] ?? ''),
            trim($_POST['address']), trim($_POST['barangay']), trim($_POST['district'] ?? ''),
            trim($_POST['city'] ?? '') ?: 'Davao City', trim($_POST['province'] ?? '') ?: 'Davao del Sur',
            trim($_POST['region'] ?? '') ?: 'Davao Region', $_POST['latitude'] !== '' ? $_POST['latitude'] : null,
            $_POST['longitude'] !== '' ? $_POST['longitude'] : null, $_POST['business_status'] ?? 'open', $businessId, $u['id']
        ]);
        foreach (range(0, 6) as $day) {
            $closed=isset($_POST['closed'][$day]); $opens=$_POST['opens'][$day] ?? '08:00'; $closes=$_POST['closes'][$day] ?? '17:00';
            if (!$closed && (!preg_match('/^\d{2}:\d{2}$/',$opens) || !preg_match('/^\d{2}:\d{2}$/',$closes) || $opens >= $closes)) { flash('Each open day needs a valid closing time after its opening time.','error'); redirect('owner.php?edit='.$businessId); }
            $pdo->prepare('INSERT INTO business_hours(business_id,day_of_week,opens,closes,is_closed) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE opens=VALUES(opens),closes=VALUES(closes),is_closed=VALUES(is_closed)')->execute([$businessId, $day, $opens, $closes, $closed ? 1 : 0]);
        }
        flash('Business profile saved.');
        redirect('owner.php?edit='.$businessId);
    }
    if ($action === 'service') {
        $name = trim($_POST['service_name'] ?? '');
        if ($name) {
            $pdo->prepare('INSERT IGNORE INTO services(name) VALUES(?)')->execute([$name]);
            $service = $pdo->prepare('SELECT id FROM services WHERE name=?');
            $service->execute([$name]);
            $pdo->prepare('INSERT INTO business_services(business_id,service_id,price,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price),description=VALUES(description)')->execute([$businessId, $service->fetchColumn(), $_POST['service_price'] !== '' ? $_POST['service_price'] : null, trim($_POST['service_description'] ?? '')]);
            flash('Service saved.');
        }
        redirect('owner.php?edit='.$businessId.'#services');
    }
    if ($action === 'service_delete') {
        $pdo->prepare('DELETE FROM business_services WHERE business_id=? AND service_id=?')->execute([$businessId, (int)$_POST['service_id']]);
        flash('Service removed.');
        redirect('owner.php?edit='.$businessId.'#services');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
foreach ($businesses as $business) if ((int)$business['id'] === $editId) $edit = $business;
if ($edit) {
    $q = $pdo->prepare('SELECT bs.*,s.name FROM business_services bs JOIN services s ON s.id=bs.service_id WHERE bs.business_id=?');
    $q->execute([$edit['id']]);
    $services = $q->fetchAll();
    $q = $pdo->prepare('SELECT * FROM business_hours WHERE business_id=?');
    $q->execute([$edit['id']]);
    $hours = [];
    foreach ($q->fetchAll() as $hour) $hours[(int)$hour['day_of_week']] = $hour;
    ob_start();
?>
<section class="panel">
  <div class="panel-title"><div><h2>Manage <?= e($edit['name']) ?></h2><p>Update the information customers see for your business.</p></div><div class="quick-actions"><a class="button light" href="business_gallery.php?business=<?= $edit['id'] ?>">Manage gallery</a><a class="button light" href="products.php?business=<?= $edit['id'] ?>">Manage products (<?= $edit['products'] ?>)</a></div></div>
  <form method="post" class="registration-form wide"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="business"><input type="hidden" name="id" value="<?= $edit['id'] ?>">
    <div class="form-grid">
      <label>Business name *<input required name="name" value="<?= e($edit['name']) ?>"></label><label>Contact number *<input required name="contact_phone" value="<?= e($edit['contact_phone']) ?>"></label>
      <label>Email<input type="email" name="contact_email" value="<?= e($edit['contact_email']) ?>"></label><label>Facebook / Messenger URL<input type="url" name="facebook_url" value="<?= e($edit['facebook_url']) ?>"></label>
      <label>Website<input type="url" name="website" value="<?= e($edit['website']) ?>"></label><label>Business status<select name="business_status"><option value="open">Open</option><option value="closed" <?= $edit['business_status']==='closed'?'selected':'' ?>>Closed today</option><option value="temporarily_closed" <?= $edit['business_status']==='temporarily_closed'?'selected':'' ?>>Temporarily closed</option></select></label>
    </div>
    <label>Description<textarea name="description"><?= e($edit['description']) ?></textarea></label>
    <div class="form-grid"><label>Region<input name="region" value="<?= e($edit['region']) ?>"></label><label>Province<input name="province" value="<?= e($edit['province']) ?>"></label><label>City<input name="city" value="<?= e($edit['city']) ?>"></label><label>Barangay *<input required name="barangay" value="<?= e($edit['barangay']) ?>"></label><label>Street / landmark *<input required name="address" value="<?= e($edit['address']) ?>"></label><label>District<input name="district" value="<?= e($edit['district']) ?>"></label><label>Latitude<input type="number" step="any" name="latitude" value="<?= e($edit['latitude']) ?>"></label><label>Longitude<input type="number" step="any" name="longitude" value="<?= e($edit['longitude']) ?>"></label></div>
    <h3>Operating hours</h3><div class="hours-grid" data-hours-editor><div class="hours-heading"><span>Day</span><span>Open</span><span>Close</span><span>Status</span><span>Schedule</span></div><?php $days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; foreach ($days as $dayNumber => $dayName): $hour=$hours[$dayNumber] ?? ['opens'=>'08:00:00','closes'=>'17:00:00','is_closed'=>0]; $closed=(bool)$hour['is_closed']; ?><div class="hours-row"><b><?= e($dayName) ?></b><input aria-label="<?=e($dayName)?> opening time" type="time" name="opens[<?= $dayNumber ?>]" value="<?= substr($hour['opens'],0,5) ?>" <?= $closed?'disabled':'' ?>><span class="hours-to" aria-hidden="true">to</span><input aria-label="<?=e($dayName)?> closing time" type="time" name="closes[<?= $dayNumber ?>]" value="<?= substr($hour['closes'],0,5) ?>" <?= $closed?'disabled':'' ?>><label class="hours-status"><input type="checkbox" name="closed[<?= $dayNumber ?>]" <?= $closed?'checked':'' ?>><span><?= $closed?'Closed':'Open' ?></span></label><span class="hours-action"><?= $closed?'Closed':'Available' ?></span></div><?php endforeach ?></div>
    <button class="button coral">Save business profile</button>
  </form>
</section>
<section class="panel" id="services"><h2>Services offered</h2>
  <form method="post" class="admin-filter"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="service"><input type="hidden" name="id" value="<?= $edit['id'] ?>"><input required name="service_name" placeholder="Service name"><input type="number" min="0" step=".01" name="service_price" placeholder="Price (PHP)"><input name="service_description" placeholder="Short description"><button class="button coral">Add service</button></form>
  <div class="table-wrap"><table><tr><th>Service</th><th>Price</th><th>Description</th><th></th></tr><?php foreach ($services as $service): ?><tr><td><?= e($service['name']) ?></td><td><?= $service['price']===null?'Ask':'₱'.number_format((float)$service['price'],2) ?></td><td><?= e($service['description']) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="service_delete"><input type="hidden" name="id" value="<?= $edit['id'] ?>"><input type="hidden" name="service_id" value="<?= $service['service_id'] ?>"><button class="danger">Remove</button></form></td></tr><?php endforeach ?></table></div>
</section>
<?php
    owner_page('My business', ob_get_clean(), 'business', $firstBusiness);
}

$unread = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND read_at IS NULL');
$unread->execute([$u['id']]);
$messages = (int)$unread->fetchColumn();
$productCount = array_sum(array_column($businesses, 'products'));
$serviceCount = array_sum(array_column($businesses, 'services'));
$reviewCount = array_sum(array_column($businesses, 'reviews'));
$rating = $reviewCount ? array_sum(array_map(fn($b) => (float)$b['rating'] * $b['reviews'], $businesses)) / $reviewCount : 0;
$completion = $businesses ? 100 : 0;
$ids=array_column($businesses,'id'); $metrics=['views'=>0,'favorites'=>0,'inquiries'=>0,'approved'=>0,'rejected'=>0]; $mostViewed=null; $recent=[];
if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT COUNT(*) FROM business_views WHERE business_id IN ($marks)");$q->execute($ids);$metrics['views']=(int)$q->fetchColumn();$q=$pdo->prepare("SELECT COUNT(*) FROM favorites WHERE business_id IN ($marks)");$q->execute($ids);$metrics['favorites']=(int)$q->fetchColumn();$q=$pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE business_id IN ($marks)");$q->execute($ids);$metrics['inquiries']=(int)$q->fetchColumn();foreach($businesses as $b){$metrics[$b['status']] = ($metrics[$b['status']]??0)+1;}$q=$pdo->prepare("SELECT b.name,COUNT(v.id) views FROM businesses b LEFT JOIN business_views v ON v.business_id=b.id WHERE b.id IN ($marks) GROUP BY b.id ORDER BY views DESC,b.name LIMIT 1");$q->execute($ids);$mostViewed=$q->fetch();$q=$pdo->prepare('SELECT action,details,created_at FROM activity_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 6');$q->execute([$u['id']]);$recent=$q->fetchAll();}
ob_start();
?>
<section class="admin-grid stats-grid owner-stats"><article><b><?= $metrics['views'] ?></b><span>Profile views</span></article><article><b><?= $metrics['favorites'] ?></b><span>Favorites</span></article><article><b><?= $metrics['inquiries'] ?></b><span>Inquiries</span></article><article><b><?= $reviewCount ?></b><span>Total reviews</span></article><article><b><?= number_format($rating,1) ?></b><span>Average rating</span></article><article><b><?= $metrics['approved'] ?>/<?= $metrics['rejected'] ?></b><span>Approved / rejected</span></article></section>
<section class="panel"><h2>Analytics</h2><p><?= $mostViewed ? 'Most viewed business: '.e($mostViewed['name']).' ('.$mostViewed['views'].' views)' : 'Analytics will appear when customers visit your profiles.' ?></p><div class="activity-list"><?php foreach($recent as $x):?><p><b><?=e($x['action'])?></b> — <?=e($x['details'])?><small><?=e($x['created_at'])?></small></p><?php endforeach;if(!$recent):?><p>No recent owner activity yet.</p><?php endif?></div></section>
<section class="panel"><div class="panel-title"><div><h2>Profile completion</h2><p><?= $completion ?>% complete<?= $completion===100 ? ' — your customer-facing profile is complete.' : ' — add your first business listing.' ?></p></div><b class="completion"><?= $completion ?>%</b></div><div class="progress"><span style="width:<?= $completion ?>%"></span></div></section>
<section class="panel"><div class="panel-title"><h2>My listings</h2><a class="button coral" href="index.php?page=business-form">Add another business</a></div><div class="table-wrap"><table><tr><th>Business</th><th>Category</th><th>Status</th><th>Products</th><th>Services</th><th>Action</th></tr><?php foreach ($businesses as $business): ?><tr><td><b><?= e($business['name']) ?></b><small><?= e($business['barangay']) ?>, Davao City</small></td><td><?= e($business['category']) ?></td><td><span class="status <?= e($business['status']) ?>"><?= e($business['status']) ?></span></td><td><?= $business['products'] ?></td><td><?= $business['services'] ?></td><td><a class="link-button" href="owner.php?edit=<?= $business['id'] ?>">Manage</a></td></tr><?php endforeach ?></table></div></section>
<section class="panel" id="profile"><h2>Account settings</h2><form method="post" class="registration-form wide"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="profile"><div class="form-grid"><label>Full name<input required name="name" value="<?= e($u['name']) ?>"></label><label>Email<input required type="email" name="email" value="<?= e($u['email']) ?>"></label></div><button class="button dark">Save settings</button></form></section>
<?php
owner_page('Welcome back, '.explode(' ', $u['name'])[0].'.', ob_get_clean(), 'dashboard', $firstBusiness);
