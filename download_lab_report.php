<?php
require_once 'auth.php';
require_once 'db.php';
ensure_lab_module_tables($conn);

$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    exit('Invalid report.');
}

$reportStmt = $conn->prepare('SELECT * FROM lab_reports WHERE id = ? LIMIT 1');
if ($reportStmt) {
    $reportStmt->bind_param('i', $reportId);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
}

if (!$report) {
    exit('Report not found.');
}

if (isset($_SESSION['patient_id']) && (int)$report['patient_id'] !== (int)$_SESSION['patient_id'] && !isset($_SESSION['doctor_id']) && !isset($_SESSION['lab_technician_id']) && !isset($_SESSION['admin_id'])) {
    exit('Access denied.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($report['report_id'] ?? 'lab-report') . '.pdf"');
echo "PDF generation placeholder for " . htmlspecialchars($report['report_id'] ?? 'report');
