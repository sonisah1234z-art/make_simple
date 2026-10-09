<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'announcements.php';

header('Content-Type: application/json; charset=utf-8');

// Allow patient or other logged in users to mark announcements as seen for their role
$context = get_current_user_context();
$role = $context['role'];
$userId = $context['id'];

mark_announcements_seen($conn, $role, $userId);

echo json_encode(['success' => true]);
exit();

?>
