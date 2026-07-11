<?php
require_once 'auth.php';
require_nurse();
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM nurse_records WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header("Location:view_nurses.php");
exit();
?>
