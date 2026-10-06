<?php
require __DIR__ . '/config.php';
require __DIR__ . '/database_upgrade.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    apply_database_upgrade($pdo);
    $visitorToken = $_SESSION['visitor_token'] ??= bin2hex(random_bytes(16));
    $userId = isset(user()['id']) ? (int) user()['id'] : null;
    $ownerField = $userId !== null ? 'user_id' : 'visitor_token';
    $ownerValue = $userId ?? $visitorToken;

    $list = $pdo->prepare("SELECT id,title,updated_at FROM support_conversations WHERE {$ownerField}=? ORDER BY updated_at DESC,id DESC");
    $list->execute([$ownerValue]);
    $conversations = $list->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $requestedId = (int) ($_GET['conversation'] ?? 0);
        $selected = null;
        foreach ($conversations as $conversation) {
            if ((int) $conversation['id'] === $requestedId) { $selected = $conversation; break; }
        }
        if ($requestedId && !$selected) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'reply' => 'That support conversation could not be found.']);
            exit;
        }
        $selected ??= $conversations[0] ?? null;
        $messages = [];
        if ($selected) {
            $query = $pdo->prepare('SELECT sender,message,created_at FROM support_messages WHERE conversation_id=? ORDER BY id');
            $query->execute([$selected['id']]);
            $messages = $query->fetchAll();
        }
        echo json_encode(['ok' => true, 'conversations' => $conversations, 'conversation' => $selected, 'messages' => $messages]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'reply' => 'This support action is not available.']);
        exit;
    }

    if (($_POST['action'] ?? '') === 'new') {
        $pdo->prepare("INSERT INTO support_conversations({$ownerField},title) VALUES(?, 'New chat')")->execute([$ownerValue]);
        $id = (int) $pdo->lastInsertId();
        echo json_encode(['ok' => true, 'conversation' => ['id' => $id, 'title' => 'New chat', 'updated_at' => date('Y-m-d H:i:s')], 'messages' => []]);
        exit;
    }

    $text = trim($_POST['message'] ?? '');
    if ($text === '' || mb_strlen($text) > 500) throw new RuntimeException('Enter a message of up to 500 characters.');
    $conversationId = (int) ($_POST['conversation'] ?? 0);
    $conversation = null;
    if ($conversationId) {
        $find = $pdo->prepare("SELECT id,title FROM support_conversations WHERE id=? AND {$ownerField}=?");
        $find->execute([$conversationId, $ownerValue]);
        $conversation = $find->fetch();
        if (!$conversation) throw new RuntimeException('That support conversation is no longer available.');
    } else {
        $title = mb_strimwidth($text, 0, 48, '…');
        $pdo->prepare("INSERT INTO support_conversations({$ownerField},title) VALUES(?,?)")->execute([$ownerValue, $title]);
        $conversation = ['id' => (int) $pdo->lastInsertId(), 'title' => $title];
    }

    $conversationId = (int) $conversation['id'];
    $title = mb_strimwidth($text, 0, 48, '…');
    if ($conversation['title'] === 'New chat') {
        $pdo->prepare("UPDATE support_conversations SET title=? WHERE id=? AND {$ownerField}=?")->execute([$title, $conversationId, $ownerValue]);
    }
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"user",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $text]);

    // The existing assistant is rule-based and has no external AI provider configured.
    $question = mb_strtolower($text);
    if (str_contains($question, 'inquir')) {
        $reply = 'You can send an inquiry from an approved business profile. Owner replies appear in Messages, and inquiry updates appear in Notifications.';
    } elseif (str_contains($question, 'account') || str_contains($question, 'login') || str_contains($question, 'log in') || str_contains($question, 'password')) {
        $reply = 'Use Log in in the site header to access your account. New here? Choose Join us to create a customer or business owner account.';
    } elseif (str_contains($question, 'save') || str_contains($question, 'favorite')) {
        $reply = 'Sign in as a customer, open a business profile, and choose Save. You can revisit saved listings from Saved on your dashboard.';
    } elseif (str_contains($question, 'product') || str_contains($question, 'service')) {
        $reply = 'Open a business profile to view its listed products and services, contact details, hours, reviews, and location.';
    } elseif (str_contains($question, 'search') || str_contains($question, 'find') || str_contains($question, 'directory')) {
        $reply = 'Open Directory to search by business name, service, category, or Davao area. You can also filter by rating and available details.';
    } else {
        $reply = 'I can help with the Davao Local directory, customer accounts, saved businesses, and inquiries. What would you like to know?';
    }
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"bot",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $reply]);
    $pdo->prepare('UPDATE support_conversations SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$conversationId]);
    echo json_encode(['ok' => true, 'conversation' => ['id' => $conversationId, 'title' => $title], 'reply' => $reply, 'time' => date('M j, g:i A')]);
} catch (Throwable $error) {
    error_log('Davao Local support chat failed: ' . $error->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'reply' => $error instanceof RuntimeException ? $error->getMessage() : 'We could not complete that support request. Please try again.']);
}
