<?php
/** Safe, repeatable upgrades for installations created before the admin upgrade. */
function apply_database_upgrade(PDO $pdo): void {
    $columns = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $has = function(string $table, string $column) use ($columns): bool { $columns->execute([$table,$column]); return (bool)$columns->fetchColumn(); };
    if (!$has('users','status')) $pdo->exec("ALTER TABLE users ADD status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER password_hash");
    if (!$has('users','last_login')) $pdo->exec('ALTER TABLE users ADD last_login DATETIME NULL AFTER status');
    if (!$has('businesses','rejection_reason')) $pdo->exec('ALTER TABLE businesses ADD rejection_reason VARCHAR(255) NULL AFTER status');
    if (!$has('businesses','region')) $pdo->exec("ALTER TABLE businesses ADD region VARCHAR(80) NOT NULL DEFAULT 'Davao Region' AFTER city");
    if (!$has('businesses','province')) $pdo->exec("ALTER TABLE businesses ADD province VARCHAR(80) NOT NULL DEFAULT 'Davao del Sur' AFTER region");
    if (!$has('businesses','facebook_url')) $pdo->exec('ALTER TABLE businesses ADD facebook_url VARCHAR(255) NULL AFTER website');
    if (!$has('businesses','business_status')) $pdo->exec("ALTER TABLE businesses ADD business_status ENUM('open','closed','temporarily_closed') NOT NULL DEFAULT 'open' AFTER status");
    if (!$has('businesses','is_verified')) $pdo->exec('ALTER TABLE businesses ADD is_verified BOOLEAN NOT NULL DEFAULT FALSE AFTER verified_at');
    if (!$has('businesses','logo_path')) $pdo->exec('ALTER TABLE businesses ADD logo_path VARCHAR(255) NULL AFTER is_verified');
    if (!$has('businesses','is_demo')) $pdo->exec('ALTER TABLE businesses ADD is_demo BOOLEAN NOT NULL DEFAULT FALSE AFTER is_verified');
    // Mark only the known seeded fixtures; do not infer demo status from ordinary owner data.
    $pdo->exec("UPDATE businesses b JOIN users u ON u.id=b.owner_id SET b.is_demo=1 WHERE b.is_demo=0 AND (b.contact_email LIKE '%@demo.local' OR b.contact_email LIKE '%@%.test' OR b.address LIKE 'Demo Address,%' OR (b.name='Maria\'s Printing Services' AND b.contact_email='maria@example.test'))");
    if (!$has('reviews','updated_at')) $pdo->exec('ALTER TABLE reviews ADD updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    if (!$has('notifications','link_url')) $pdo->exec('ALTER TABLE notifications ADD link_url VARCHAR(255) NULL AFTER message');
    if (!$has('notifications','type')) $pdo->exec("ALTER TABLE notifications ADD type VARCHAR(40) NOT NULL DEFAULT 'general' AFTER link_url");
    $table = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?');
    $table->execute(['products']);
    if (!$table->fetchColumn()) {
        $pdo->exec("CREATE TABLE products (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, description TEXT NULL, price DECIMAL(10,2) NULL, stock INT NULL, availability ENUM('available','unavailable') NOT NULL DEFAULT 'available', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE, INDEX(business_id))");
    }
    $columns = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $productHas = function(string $column) use ($columns): bool { $columns->execute(['products',$column]); return (bool)$columns->fetchColumn(); };
    if (!$productHas('image_path')) $pdo->exec('ALTER TABLE products ADD image_path VARCHAR(255) NULL AFTER description');
    if (!$productHas('is_featured')) $pdo->exec('ALTER TABLE products ADD is_featured BOOLEAN NOT NULL DEFAULT FALSE AFTER availability');
    $table->execute(['support_conversations']);
    $supportConversationsCreated = !$table->fetchColumn();
    if ($supportConversationsCreated) $pdo->exec('CREATE TABLE support_conversations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NULL,visitor_token VARCHAR(64) NULL,title VARCHAR(120) NOT NULL DEFAULT "New chat",created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(user_id),INDEX(visitor_token),INDEX(updated_at),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)');
    $conversationUniqueIndexes = $pdo->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_used FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='support_conversations' AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY' GROUP BY INDEX_NAME")->fetchAll();
    foreach ($conversationUniqueIndexes as $uniqueIndex) {
        $indexedColumns = explode(',', $uniqueIndex['columns_used']);
        if (count($indexedColumns) !== 1 || !in_array($indexedColumns[0], ['user_id','visitor_token'], true)) continue;
        $lookupName = 'support_conversations_' . $indexedColumns[0] . '_lookup';
        $lookup = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $lookup->execute(['support_conversations',$lookupName]);
        if (!$lookup->fetchColumn()) $pdo->exec('ALTER TABLE support_conversations ADD INDEX `' . $lookupName . '` (`' . $indexedColumns[0] . '`)');
        $pdo->exec('ALTER TABLE support_conversations DROP INDEX `' . str_replace('`', '``', $uniqueIndex['INDEX_NAME']) . '`');
    }
    $table->execute(['support_messages']);
    if (!$table->fetchColumn()) $pdo->exec('CREATE TABLE support_messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id INT UNSIGNED NULL,user_id INT UNSIGNED NULL,visitor_token VARCHAR(64) NULL,sender ENUM("user","bot") NOT NULL,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,INDEX(conversation_id),INDEX(user_id),INDEX(visitor_token))');
    $supportMessagesConversationAdded = !$has('support_messages','conversation_id');
    if ($supportMessagesConversationAdded) $pdo->exec('ALTER TABLE support_messages ADD conversation_id INT UNSIGNED NULL AFTER id, ADD INDEX (conversation_id)');
    if ($supportConversationsCreated || $supportMessagesConversationAdded) {
        $pdo->exec("INSERT INTO support_conversations(user_id,visitor_token,title) SELECT DISTINCT user_id,NULL,'Previous support chat' FROM support_messages WHERE user_id IS NOT NULL");
        $pdo->exec("INSERT INTO support_conversations(user_id,visitor_token,title) SELECT DISTINCT NULL,visitor_token,'Previous support chat' FROM support_messages WHERE visitor_token IS NOT NULL");
        $pdo->exec('UPDATE support_messages m JOIN support_conversations c ON c.user_id=m.user_id SET m.conversation_id=c.id WHERE m.conversation_id IS NULL AND m.user_id IS NOT NULL');
        $pdo->exec('UPDATE support_messages m JOIN support_conversations c ON c.visitor_token=m.visitor_token SET m.conversation_id=c.id WHERE m.conversation_id IS NULL AND m.visitor_token IS NOT NULL');
    }
    $table->execute(['conversations']);
    if (!$table->fetchColumn()) $pdo->exec('CREATE TABLE conversations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,customer_id INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE(business_id,customer_id),FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE)');
    $table->execute(['messages']);
    if (!$table->fetchColumn()) $pdo->exec('CREATE TABLE messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id INT UNSIGNED NOT NULL,sender_id INT UNSIGNED NOT NULL,receiver_id INT UNSIGNED NOT NULL,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,read_at DATETIME NULL,FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE,INDEX(conversation_id),INDEX(receiver_id))');
    $table->execute(['business_images']);
    if (!$table->fetchColumn()) $pdo->exec('CREATE TABLE business_images (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,image_path VARCHAR(255) NOT NULL,caption VARCHAR(160) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,INDEX(business_id))');
    $table->execute(['business_reports']);
    if (!$table->fetchColumn()) $pdo->exec("CREATE TABLE business_reports (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,reason ENUM('fake_business','wrong_information','inappropriate_content','duplicate_listing','closed_business','other') NOT NULL,details TEXT NULL,status ENUM('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',admin_notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,INDEX(status),INDEX(business_id))");
    $table->execute(['review_reports']);
    if (!$table->fetchColumn()) $pdo->exec("CREATE TABLE review_reports (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,review_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,reason VARCHAR(255) NOT NULL,status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,UNIQUE(review_id,user_id),INDEX(status))");
    $table->execute(['business_views']);
    if (!$table->fetchColumn()) $pdo->exec('CREATE TABLE business_views (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,INDEX(business_id),INDEX(viewed_at))');
    $table->execute(['appointments']);
    if (!$table->fetchColumn()) $pdo->exec("CREATE TABLE appointments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,service_id INT UNSIGNED NULL,customer_id INT UNSIGNED NOT NULL,appointment_at DATETIME NOT NULL,notes TEXT NULL,status ENUM('pending','confirmed','rejected','rescheduled','completed','cancelled') NOT NULL DEFAULT 'pending',owner_notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL,FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE CASCADE,INDEX(business_id,appointment_at),INDEX(customer_id,status))");
}
function audit(PDO $pdo, ?int $userId, string $action, string $details): void {
    $pdo->prepare('INSERT INTO activity_logs(user_id,action,details) VALUES(?,?,?)')->execute([$userId,$action,mb_strimwidth($details,0,255,'')]);
}
