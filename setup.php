<?php
require __DIR__ . '/config.php';
try {
    db(true)->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) db()->exec($statement);
    $admin = db()->prepare('INSERT IGNORE INTO users(role_id,name,email,password_hash) VALUES(3,?,?,?)');
    $admin->execute(['Directory Administrator','admin@davaolocal.test',password_hash('Admin123!', PASSWORD_DEFAULT)]);
    $owner = db()->prepare('INSERT IGNORE INTO users(role_id,name,email,password_hash) VALUES(2,?,?,?)');
    $owner->execute(['Maria Santos','owner@davaolocal.test',password_hash('Owner123!', PASSWORD_DEFAULT)]);
    $count = (int)db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn();
    if (!$count) {
      $ownerId=(int)db()->query("SELECT id FROM users WHERE email='owner@davaolocal.test'")->fetchColumn();
      $cat=(int)db()->query("SELECT id FROM categories WHERE name='Printing Services'")->fetchColumn();
      $q=db()->prepare("INSERT INTO businesses(owner_id,category_id,name,description,contact_phone,contact_email,address,barangay,district,latitude,longitude,status,verified_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
      $q->execute([$ownerId,$cat,"Maria's Printing Services",'Fast and friendly printing for students, events, and small businesses.','0917 555 0101','maria@example.test','Mahayag Street, Buhangin','Buhangin','Talomo',7.1137,125.6287,'approved']);
      $id=(int)db()->lastInsertId(); foreach(['Photocopying','Tarpaulin printing','ID printing'] as $s){db()->prepare('INSERT IGNORE INTO services(name) VALUES(?)')->execute([$s]);$sid=(int)db()->prepare('SELECT id FROM services WHERE name=?')->execute([$s]); $st=db()->prepare('SELECT id FROM services WHERE name=?');$st->execute([$s]);db()->prepare('INSERT INTO business_services(business_id,service_id) VALUES(?,?)')->execute([$id,$st->fetchColumn()]);}
      for($d=0;$d<7;$d++) db()->prepare('INSERT INTO business_hours(business_id,day_of_week,opens,closes,is_closed) VALUES(?,?,?,?,?)')->execute([$id,$d,'08:00','18:00',$d===0]);
    }
    require_once __DIR__ . '/demo_seed.php';
    require_once __DIR__ . '/database_upgrade.php';
    apply_database_upgrade(db());
    $added = seed_demo_businesses(db());
    echo '<h1>Setup complete</h1><p>Database and sample data are ready. '.$added.' additional demo businesses were added.</p><p>Admin: <b>admin@davaolocal.test</b> / <b>Admin123!</b><br>Owner: <b>owner@davaolocal.test</b> / <b>Owner123!</b></p><p><a href="index.php">Open application</a>. Delete or protect setup.php after deployment.</p>';
} catch(Throwable $e) { http_response_code(500); echo '<h1>Setup failed</h1><p>' . e($e->getMessage()) . '</p><p>Start MySQL in XAMPP and check config.php credentials.</p>'; }
