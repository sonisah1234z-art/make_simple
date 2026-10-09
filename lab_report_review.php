<?php
require_once 'auth.php';
require_doctor();
require_once 'db.php';
ensure_lab_module_tables($conn);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $diagnosis = trim((string)($_POST['diagnosis'] ?? ''));
    $treatmentNotes = trim((string)($_POST['treatment_notes'] ?? ''));

    if ($reportId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'Doctor Verified' : 'Processing';
        $stmt = $conn->prepare('UPDATE lab_reports SET status = ?, diagnosis = ?, treatment_notes = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('sssi', $status, $diagnosis, $treatmentNotes, $reportId);
            $stmt->execute();
            $stmt->close();
        }

        if ($action === 'approve') {
            $patientStmt = $conn->prepare('SELECT patient_id FROM lab_reports WHERE id = ? LIMIT 1');
            if ($patientStmt) {
                $patientStmt->bind_param('i', $reportId);
                $patientStmt->execute();
                $patientRow = $patientStmt->get_result()->fetch_assoc();
                $patientStmt->close();
                if ($patientRow) {
                    $notifStmt = $conn->prepare('INSERT INTO notifications (patient_id, title, message) VALUES (?, ?, ?)');
                    if ($notifStmt) {
                        $title = 'Lab Report Approved';
                        $messageText = 'Your lab report is now approved and available in your patient portal.';
                        $notifStmt->bind_param('iss', $patientRow['patient_id'], $title, $messageText);
                        $notifStmt->execute();
                        $notifStmt->close();
                    }
                }
            }
        }

        $message = $action === 'approve' ? 'Report verified successfully.' : 'Report returned to processing.';
        $messageType = 'success';
    } else {
        $message = 'Unable to update the report.';
        $messageType = 'danger';
    }
}

$reports = [];
$reportStmt = $conn->prepare('SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE lr.doctor_id = ? ORDER BY FIELD(lr.status, "Result Entered", "Completed", "Doctor Verified", "Pending Review", "Approved", "Processing", "Rejected"), lr.created_at DESC');
if ($reportStmt) {
    $doctorId = (int)($_SESSION['doctor_id'] ?? 0);
    $reportStmt->bind_param('i', $doctorId);
    $reportStmt->execute();
    $reportResult = $reportStmt->get_result();
    while ($row = $reportResult->fetch_assoc()) {
        $reports[] = $row;
    }
    $reportStmt->close();
}

require_once 'header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="hero-badge"><i class="bi bi-eye"></i> Review Lab Reports</span>
            <h2 class="mt-2 mb-1">Approve or reject reports from the lab</h2>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="lab-card">
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['report_id']); ?></td>
                            <td><?php echo htmlspecialchars($report['patient_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($report['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['technician_name'] ?? '—'); ?></td>
                            <td><span class="status-pill status-pending-review"><?php echo htmlspecialchars($report['status']); ?></span></td>
                            <td>
                                <?php if (in_array($report['status'], ['Result Entered', 'Completed', 'Pending Review'], true)): ?>
                                    <form class="d-inline" method="post">
                                        <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success" type="submit">Verify Report</button>
                                    </form>
                                    <form class="d-inline" method="post">
                                        <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Return to Processing</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
