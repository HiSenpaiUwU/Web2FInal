<?php
require __DIR__ . '/config.php';
require __DIR__ . '/database_upgrade.php';
try { apply_database_upgrade(db()); echo '<h1>Upgrade complete</h1><p>Existing data was preserved. The admin and owner management upgrade is ready.</p><p><a href="index.php">Open Davao Local</a></p>'; }
catch(Throwable $e) { http_response_code(500); echo '<h1>Upgrade unavailable</h1><p>Start MySQL and run setup.php first.</p>'; error_log($e->getMessage()); }
