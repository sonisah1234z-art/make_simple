<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';
require_once 'announcements.php';

header('Content-Type: application/json; charset=utf-8');

$context = get_current_user_context();
$announcement = get_latest_unseen_announcement($conn, $context['role'], $context['id']);
if (!$announcement) {
    echo json_encode(['found' => false]);
    exit();
}

$attachment = null;
if (!empty($announcement['attachment'])) {
    $safe = basename($announcement['attachment']);
    $fullPath = __DIR__ . '/uploads/' . $safe;
    if (file_exists($fullPath) && is_file($fullPath)) {
        $attachment = [
            'file' => $safe,
            'url' => 'uploads/' . $safe,
            'ext' => strtolower(pathinfo($safe, PATHINFO_EXTENSION))
        ];
    }
}

// Return safe text-only fields (escape on server)
$payload = [
    'found' => true,
    'id' => (int)$announcement['id'],
    'title' => htmlspecialchars($announcement['title']),
    'message' => htmlspecialchars($announcement['message']),
    'publish_date' => $announcement['publish_date'] ?? '',
    'attachment' => $attachment
];

echo json_encode($payload);
exit();

?>
