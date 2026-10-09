<?php
require_once 'auth.php';
require_doctor();
require_once 'db.php';
ensure_lab_module_tables($conn);

$message = '';
$messageType = '';
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

// handle doctor assignment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $department = trim((string)($_POST['department'] ?? ''));
    $testCategory = trim((string)($_POST['test_category'] ?? ''));
    $testName = trim((string)($_POST['test_name'] ?? ''));
    $priority = trim((string)($_POST['priority'] ?? 'Normal'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);
    $dueDate = trim((string)($_POST['due_date'] ?? ''));
    if ($dueDate === '') { $dueDate = null; }

    if ($patientId > 0 && $department !== '' && $testName !== '' && $assignedTo > 0) {
        $stmt = $conn->prepare('INSERT INTO lab_test_requests (doctor_id, patient_id, department, test_category, test_name, priority, notes, status, request_date, due_date, assigned_to, assigned_by, assigned_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, CURDATE())');
        if ($stmt) {
            $status = 'Assigned';
            $stmt->bind_param('iisssssssii', $doctorId, $patientId, $department, $testCategory, $testName, $priority, $notes, $status, $dueDate, $assignedTo, $doctorId);
            if ($stmt->execute()) {
                $message = 'Lab request assigned successfully.';
                $messageType = 'success';
                // notify assigned technician
                insert_staff_notification($conn, 'Lab Technician', $assignedTo, 'New Lab Assignment', 'A new laboratory assignment has been assigned to you by Dr. ' . ($_SESSION['doctor_name'] ?? 'Doctor') . '.');
            } else {
                $message = 'Unable to create the lab request.';
                $messageType = 'danger';
            }
            $stmt->close();
        }
    } else {
        $message = 'Please complete the patient, department, test details, and select a lab technician.';
        $messageType = 'danger';
    }
}

$patients = [];
$patientStmt = $conn->query('SELECT id, name FROM patients ORDER BY name');
if ($patientStmt) { while ($row = $patientStmt->fetch_assoc()) { $patients[] = $row; } }

$labTechnicians = [];
$techStmt = $conn->query('SELECT id, name FROM lab_technicians ORDER BY name');
if ($techStmt) { while ($row = $techStmt->fetch_assoc()) { $labTechnicians[] = $row; } }

$requests = [];
$requestStmt = $conn->prepare('SELECT ltr.*, p.name AS patient_name, lt.name AS technician_name, d.name AS doctor_name FROM lab_test_requests ltr JOIN patients p ON p.id = ltr.patient_id LEFT JOIN lab_technicians lt ON lt.id = ltr.assigned_to LEFT JOIN doctors d ON d.id = ltr.assigned_by WHERE ltr.doctor_id = ? ORDER BY ltr.request_date DESC, ltr.id DESC');
if ($requestStmt) { $requestStmt->bind_param('i', $doctorId); $requestStmt->execute(); $requestResult = $requestStmt->get_result(); while ($row = $requestResult->fetch_assoc()) { $requests[] = $row; } $requestStmt->close(); }

require_once 'header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="hero-badge"><i class="bi bi-beaker"></i> Lab Requests</span>
            <h2 class="mt-2 mb-1">Create and track lab requests</h2>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="lab-card mb-4">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Patient</label>
                <select class="form-select" name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo (int)$patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Department</label>
                <input class="form-control" name="department" placeholder="e.g. Pathology" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Test Type</label>
                <input class="form-control" name="test_category" placeholder="e.g. Blood Work">
            </div>
            <div class="col-md-4">
                <label class="form-label">Test Name</label>
                <input class="form-control" name="test_name" placeholder="e.g. CBC" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lab Technician</label>
                <select class="form-select" name="assigned_to" required>
                    <option value="">Select technician</option>
                    <?php foreach ($labTechnicians as $tech): ?>
                        <option value="<?php echo (int)$tech['id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority">
                    <option value="Normal">Normal</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Due Date</label>
                <input class="form-control" type="date" name="due_date">
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input class="form-control" name="notes" placeholder="Optional notes">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Submit Request</button>
            </div>
        </form>
    </div>

    <div class="lab-card">
        <h4 class="mb-3">Recent Requests</h4>
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Department</th>
                        <th>Priority</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['patient_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($request['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['department']); ?></td>
                            <td><?php echo htmlspecialchars($request['priority'] ?? 'Normal'); ?></td>
                            <td><?php echo htmlspecialchars($request['technician_name'] ?? 'Unassigned'); ?></td>
                            <td><span class="status-pill status-pending"><?php echo htmlspecialchars($request['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
