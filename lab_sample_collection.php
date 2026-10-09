<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_module_tables($conn);

$message = '';
$messageType = '';
$technicianName = $_SESSION['lab_technician_name'] ?? 'Lab Technician';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $sampleType = trim((string)($_POST['sample_type'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($requestId > 0 && $sampleType !== '') {
        $sampleId = 'SPL-' . date('YmdHis') . '-' . $requestId;
        $stmt = $conn->prepare('INSERT INTO lab_samples (request_id, sample_id, sample_type, collection_datetime, collected_by, notes) VALUES (?, ?, ?, NOW(), ?, ?)');
        if ($stmt) {
            $stmt->bind_param('issss', $requestId, $sampleId, $sampleType, $technicianName, $notes);
            if ($stmt->execute()) {
                $update = $conn->prepare('UPDATE lab_test_requests SET status = ? WHERE id = ?');
                if ($update) {
                    $status = 'Sample Collected';
                    $update->bind_param('si', $status, $requestId);
                    $update->execute();
                    $update->close();
                }
                $message = 'Sample collected successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to save sample collection.';
                $messageType = 'danger';
            }
            $stmt->close();
        }
    } else {
        $message = 'Please select a request and enter the sample type.';
        $messageType = 'danger';
    }
}

$requests = [];
$requestStmt = $conn->query('SELECT ltr.id, ltr.test_name, ltr.department, ltr.status, p.name AS patient_name FROM lab_test_requests ltr JOIN patients p ON p.id = ltr.patient_id WHERE ltr.status IN (\'Pending\', \'Sample Collected\') ORDER BY ltr.request_date DESC, ltr.id DESC');
if ($requestStmt) {
    while ($row = $requestStmt->fetch_assoc()) {
        $requests[] = $row;
    }
}

require_once 'header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="hero-badge"><i class="bi bi-droplet"></i> Sample Collection</span>
            <h2 class="mt-2 mb-1">Record sample collection for pending requests</h2>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="lab-card mb-4">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Lab Request</label>
                <select class="form-select" name="request_id" required>
                    <option value="">Select request</option>
                    <?php foreach ($requests as $request): ?>
                        <option value="<?php echo (int)$request['id']; ?>"><?php echo htmlspecialchars($request['patient_name'] . ' - ' . $request['test_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sample Type</label>
                <input class="form-control" name="sample_type" placeholder="Blood / Urine / Swab" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notes</label>
                <input class="form-control" name="notes" placeholder="Optional notes">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Save Collection</button>
            </div>
        </form>
    </div>

    <div class="lab-card">
        <h4 class="mb-3">Pending Requests</h4>
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead><tr><th>Patient</th><th>Test</th><th>Department</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['department']); ?></td>
                            <td><span class="status-pill status-pending"><?php echo htmlspecialchars($request['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
