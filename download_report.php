<?php
require_once 'auth.php';
require_once 'db.php';
ensure_lab_tests_table($conn);

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'download'; // 'view' or 'download'

if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid request';
    exit();
}

$stmt = $conn->prepare('SELECT l.file, l.patient_id FROM lab_tests l WHERE l.id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo 'Server error';
    exit();
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row || empty($row['file'])) {
    http_response_code(404);
    echo 'Report file not found';
    exit();
}

$fileName = basename($row['file']);
$fullPath = __DIR__ . '/uploads/' . $fileName;

// Access control: allow if staff/admin/technician OR patient owns the report
$allowed = false;
if (isset($_SESSION['admin_id']) || isset($_SESSION['nurse_id']) || isset($_SESSION['pharmacist_id']) || isset($_SESSION['receptionist_id']) || isset($_SESSION['lab_technician_id'])) {
    $allowed = true;
} elseif (isset($_SESSION['patient_id'])) {
    $patient_id = current_patient_id();
    if ((int)$patient_id === (int)$row['patient_id']) $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    echo 'Access denied';
    exit();
}

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    echo 'The requested report file is missing.';
    exit();
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'pdf') $mime = 'application/pdf';
elseif (in_array($ext, ['jpg','jpeg'], true)) $mime = 'image/jpeg';
elseif ($ext === 'png') $mime = 'image/png';
elseif ($ext === 'doc') $mime = 'application/msword';
elseif ($ext === 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// For view action, prefer inline for PDFs and images
if ($action === 'view' && in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    readfile($fullPath);
    exit();
}

// Default: force download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit();

?>
