<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
ensure_lab_module_tables($conn);

$message = '';
$messageType = '';

$requests = [];
$requestStmt = $conn->query('SELECT ltr.*, p.name AS patient_name, lt.name AS technician_name FROM lab_test_requests ltr LEFT JOIN patients p ON p.id = ltr.patient_id LEFT JOIN lab_technicians lt ON lt.id = ltr.assigned_to ORDER BY FIELD(ltr.status, "Assigned", "In Progress", "Completed", "Pending", "Cancelled"), ltr.assigned_date DESC, ltr.request_date DESC');
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
            <span class="hero-badge"><i class="bi bi-clipboard-check"></i> Lab Requests</span>
            <h2 class="mt-2 mb-1">Laboratory requests and assignments</h2>
            <p class="text-muted">Admin view only. New assignments are handled by doctors from the doctor dashboard.</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="lab-card">
        <h4 class="mb-3">Recent Lab Requests</h4>
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Assigned By</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo (int)$request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['patient_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($request['test_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?></td>
                            <td><?php echo htmlspecialchars($request['priority'] ?? 'Normal'); ?></td>
                            <td><?php echo htmlspecialchars($request['technician_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo htmlspecialchars($request['assigned_by'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($request['due_date'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>