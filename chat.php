<?php
require __DIR__ . '/config.php';
require __DIR__ . '/database_upgrade.php';
require __DIR__ . '/chat_assistant.php';
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
        echo json_encode(['ok' => true, 'csrf' => csrf(), 'conversations' => $conversations, 'conversation' => $selected, 'messages' => $messages]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'reply' => 'This support action is not available.']);
        exit;
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); echo json_encode(['ok' => false, 'reply' => 'Your session expired. Reopen support chat and try again.']); exit;
    }

    if (($_POST['action'] ?? '') === 'new') {
        $pdo->prepare("INSERT INTO support_conversations({$ownerField},title) VALUES(?, 'New chat')")->execute([$ownerValue]);
        $id = (int) $pdo->lastInsertId();
        echo json_encode(['ok' => true, 'csrf' => csrf(), 'conversation' => ['id' => $id, 'title' => 'New chat', 'updated_at' => date('Y-m-d H:i:s')], 'messages' => []]);
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
        $title = mb_strimwidth($text, 0, 48, 'â€¦');
        $pdo->prepare("INSERT INTO support_conversations({$ownerField},title) VALUES(?,?)")->execute([$ownerValue, $title]);
        $conversation = ['id' => (int) $pdo->lastInsertId(), 'title' => $title];
    }

    $conversationId = (int) $conversation['id'];
    $title = mb_strimwidth($text, 0, 48, 'â€¦');
    $previous = $pdo->prepare('SELECT message FROM support_messages WHERE conversation_id=? AND sender="user" ORDER BY id DESC LIMIT 1');
    $previous->execute([$conversationId]); $previousQuestion = (string) ($previous->fetchColumn() ?: '');
    if ($conversation['title'] === 'New chat') {
        $pdo->prepare("UPDATE support_conversations SET title=? WHERE id=? AND {$ownerField}=?")->execute([$title, $conversationId, $ownerValue]);
    }
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"user",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $text]);

    // Resolve the reply using current public listings and narrowly scoped help intents.
    $reply = davao_help_answer($pdo, $text, $previousQuestion);
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"bot",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $reply]);
    $pdo->prepare('UPDATE support_conversations SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$conversationId]);
    echo json_encode(['ok' => true, 'conversation' => ['id' => $conversationId, 'title' => $title], 'reply' => $reply, 'time' => date('M j, g:i A')]);
} catch (Throwable $error) {
    error_log('Davao Local support chat failed: ' . $error->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'reply' => $error instanceof RuntimeException ? $error->getMessage() : 'We could not complete that support request. Please try again.']);
}
