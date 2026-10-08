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
        $title = mb_strimwidth($text, 0, 48, '…');
        $pdo->prepare("INSERT INTO support_conversations({$ownerField},title) VALUES(?,?)")->execute([$ownerValue, $title]);
        $conversation = ['id' => (int) $pdo->lastInsertId(), 'title' => $title];
    }

    $conversationId = (int) $conversation['id'];
    $title = mb_strimwidth($text, 0, 48, '…');
    $previous = $pdo->prepare('SELECT message FROM support_messages WHERE conversation_id=? AND sender="user" ORDER BY id DESC LIMIT 1');
    $previous->execute([$conversationId]); $previousQuestion = (string) ($previous->fetchColumn() ?: '');
    if ($conversation['title'] === 'New chat') {
        $pdo->prepare("UPDATE support_conversations SET title=? WHERE id=? AND {$ownerField}=?")->execute([$title, $conversationId, $ownerValue]);
    }
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"user",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $text]);

    // Guided help uses explicit intents and the previous user turn for short follow-ups.
    $question = mb_strtolower($text);
    if (preg_match('/\b(where|how|that|it|there|which|what about)\b/u', $question) && mb_strlen($question) < 55 && $previousQuestion !== '') $question .= ' ' . mb_strtolower($previousQuestion);
    $intents = [
      ['~\b(register|registration|sign up|create account|join)\b~', 'Choose Join us in the site navigation, enter your name and email, set a password, and select Customer or Business owner. Submit the form, then log in with that account.'],
      ['~\b(login|log in|sign in|password|forgot)\b~', 'Choose Log in from the site navigation and enter your email and password. If you do not have an account yet, use Join us to register.'],
      ['~\b(owner dashboard|owner account|business owner|my business|business registration|register.*business)\b~', 'Sign in as a Business owner, then open the Owner dashboard. Choose Add Another Business to submit a listing. It stays pending until an administrator reviews it.'],
      ['~\b(verification|verified|pending approval|approve|rejected|suspended)\b~', 'New business listings begin as Pending. An administrator reviews them; approved listings become visible in Directory. Owners can check the current status from My Business.'],
      ['~\b(contact|message|inquiry|inquire|business owner|reach)\b~', 'Open Directory, search for the business, and open its profile. Select Contact Business to start a private conversation. Replies appear in Messages, and new activity appears in Notifications.'],
      ['~\b(saved|save|favorite|bookmark)\b~', 'Sign in as a Customer, open a business profile, and choose Save. Use Saved in the customer navigation to return to it or remove it later.'],
      ['~\b(appointment|booking|schedule|cancel appointment)\b~', 'Open the business profile and submit an appointment request with your preferred date and details. Track it in Appointments; the business owner can confirm or reject a pending request.'],
      ['~\b(review|rating|stars)\b~', 'Open a business profile and use its review form to choose a star rating and write your comments. You can see your submitted reviews on the profile.'],
      ['~\b(notification|alert)\b~', 'Open Notifications from your account navigation to review updates about messages, appointments, and business activity.'],
      ['~\b(map|location|directions|nearby|area)\b~', 'Choose Map or Directory from the navigation. Directory filters listings by area; a business profile provides its location and map or directions link.'],
      ['~\b(product|service|gallery|photo|image)\b~', 'Customers can see a business’s products, services, and gallery on its profile. Owners can manage these from Products, Services, and Gallery in the owner workspace.'],
      ['~\b(settings|profile|change.*details|account details)\b~', 'Open Settings from your account workspace to update your profile details. Owners can also edit business details from My Business.'],
      ['~\b(report|admin|moderation|category)\b~', 'Administrators can manage reports, business verification, users, categories, reviews, and activity logs from the Admin workspace. Customers can report a business from its profile.'],
      ['~\b(search|find|directory|businesses)\b~', 'Open Directory. Search by business name or category, then narrow results by area or other available filters. Open a result to see its contact details, location, services, products, hours, and reviews.'],
    ];
    $reply = null; foreach ($intents as [$pattern, $answer]) { if (preg_match($pattern, $question)) { $reply = $answer; break; } }
    if ($reply === null) $reply = "I'm not sure about that yet. I can help with finding businesses, contacting businesses, Saved, Messages, appointments, reviews, and account help. Try asking, ‘How do I contact a business?’";
    $pdo->prepare('INSERT INTO support_messages(conversation_id,user_id,visitor_token,sender,message) VALUES(?,?,?,"bot",?)')->execute([$conversationId, $userId, $userId === null ? $visitorToken : null, $reply]);
    $pdo->prepare('UPDATE support_conversations SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$conversationId]);
    echo json_encode(['ok' => true, 'conversation' => ['id' => $conversationId, 'title' => $title], 'reply' => $reply, 'time' => date('M j, g:i A')]);
} catch (Throwable $error) {
    error_log('Davao Local support chat failed: ' . $error->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'reply' => $error instanceof RuntimeException ? $error->getMessage() : 'We could not complete that support request. Please try again.']);
}
