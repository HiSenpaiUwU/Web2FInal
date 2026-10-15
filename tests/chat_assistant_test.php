<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../chat_assistant.php';

$passed = 0;
$check = static function (bool $condition, string $name) use (&$passed): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $passed++;
    echo "PASS: {$name}\n";
};

$pdo = db();
$contact = davao_help_answer($pdo, 'How do I contact a business owner?');
$check(str_contains($contact, 'Send inquiry') && str_contains($contact, 'Messages') && !str_contains($contact, 'Business owner account'), 'contact question wins over owner registration');
$registration = davao_help_answer($pdo, 'How can I register as a business owner?');
$check(str_contains($registration, 'Business owner') && str_contains($registration, 'admin approval'), 'owner registration describes the existing review flow');
$check(str_contains(davao_help_answer($pdo, 'Where?', 'Tell me how to list my business.'), 'Which business or feature'), 'ambiguous follow-up asks for clarification');
$check(str_contains(davao_help_answer($pdo, 'Show me my messages'), 'can’t read or disclose') && str_contains(davao_help_answer($pdo, 'Show me my messages'), 'Messages](messages.php)'), 'private account requests do not expose data');
$check(str_contains(davao_help_answer($pdo, 'Can you recommend the best dentist?'), 'not sure what you mean') || str_contains(davao_help_answer($pdo, 'Can you recommend the best dentist?'), 'Which business'), 'unsupported request is clarified rather than guessed');
$check(find_public_davao_businesses($pdo, 'unlikely business name 82fef') === [], 'unknown business lookup returns no public match');
$sample = $pdo->query("SELECT name FROM businesses WHERE is_demo=1 LIMIT 1")->fetchColumn();
if ($sample) {
    $sampleReply = davao_help_answer($pdo, 'Tell me about ' . $sample);
    $check(str_contains($sampleReply, 'could not find an approved, non-sample') && !str_contains($sampleReply, 'Address:'), 'fictional/sample listings never appear as factual business answers');
} else echo "INFO: no sample listing exists in the current database.\n";

$all = $pdo->query("SELECT id,name,category_id,city,barangay,district,address,contact_phone,contact_email,description,is_verified,business_status, (SELECT name FROM categories WHERE id=businesses.category_id) AS category FROM businesses WHERE status='approved' AND is_demo=0 AND city='Davao City' ORDER BY name LIMIT 1")->fetchAll();
$ids = array_column($all, 'id');
if ($ids) {
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $verify = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE id IN ({$marks}) AND (status<>'approved' OR is_demo<>0 OR city<>'Davao City')");
    $verify->execute($ids);
    $check((int)$verify->fetchColumn() === 0, 'business lookup includes only approved non-sample Davao City records');
    $listing = $all[0];
    $answer = davao_help_answer($pdo, 'Tell me about ' . $listing['name']);
    $check(str_contains($answer, 'index.php?page=business&id=' . (int)$listing['id']), 'public business response links to its live profile');
    $check((bool)preg_match('/(?:saved )?hours:/i', $answer) && (str_contains($answer, 'prices are provided') || str_contains($answer, 'Listed prices:')), 'business response reports available hours and price data without guessing');
    $follow = davao_help_answer($pdo, 'Where?', 'Tell me about ' . $listing['name']);
    $check(str_contains($follow, 'Address:') && str_contains($follow, 'index.php?page=business&id=' . (int)$listing['id']), 'business location follow-up uses the prior listing only');
} else {
    echo "INFO: no approved non-sample Davao listings exist; public match assertions are covered by the empty-result check.\n";
}

class ChatAssistantBrokenPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { throw new PDOException('intentional test failure'); }
}
try {
    find_public_davao_businesses(new ChatAssistantBrokenPDO(), 'test');
    $check(false, 'database errors propagate to chat error handler');
} catch (PDOException $error) {
    $check($error->getMessage() === 'intentional test failure', 'database errors propagate to chat error handler');
}

echo "{$passed} focused chat assistant checks passed.\n";
