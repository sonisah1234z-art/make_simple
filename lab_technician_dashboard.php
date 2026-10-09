<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_module_tables($conn);

$techId = (int)($_SESSION['lab_technician_id'] ?? 0);

// Delete only reports uploaded by the signed-in technician.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $reportStmt = $conn->prepare('SELECT file FROM lab_reports WHERE id = ? AND technician_id = ? LIMIT 1');
    $report = null;
    if ($reportStmt) {
        $reportStmt->bind_param('ii', $reportId, $techId);
        $reportStmt->execute();
        $report = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
    }

    if (empty($report)) {
        $_SESSION['flash_error'] = 'You can only delete reports uploaded by your account.';
    } else {
        $deleteStmt = $conn->prepare('DELETE FROM lab_reports WHERE id = ? AND technician_id = ? LIMIT 1');
        $deleted = false;
        if ($deleteStmt) {
            $deleteStmt->bind_param('ii', $reportId, $techId);
            $deleted = $deleteStmt->execute() && $deleteStmt->affected_rows === 1;
            $deleteStmt->close();
        }

        if ($deleted) {
            $reportFile = $report['file'] ?? '';
            if ($reportFile !== '') {
                $filePath = __DIR__ . '/uploads/' . basename($reportFile);
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
            $_SESSION['flash_success'] = 'Report deleted successfully.';
        } else {
            $_SESSION['flash_error'] = 'Unable to delete the report.';
        }
    }

    header('Location: lab_technician_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_report'])) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $completeStmt = $conn->prepare('SELECT request_id, doctor_id, patient_id, test_name FROM lab_reports WHERE id = ? AND technician_id = ? AND status = ? LIMIT 1');
    $resultEntered = 'Result Entered';
    $report = null;
    if ($completeStmt) {
        $completeStmt->bind_param('iis', $reportId, $techId, $resultEntered);
        $completeStmt->execute();
        $report = $completeStmt->get_result()->fetch_assoc();
        $completeStmt->close();
    }

    if (!$report) {
        $_SESSION['flash_error'] = 'Only your Result Entered reports can be completed.';
    } else {
        $completed = 'Completed';
        $updateReport = $conn->prepare('UPDATE lab_reports SET status = ?, updated_at = NOW() WHERE id = ? AND technician_id = ? AND status = ? LIMIT 1');
        $updated = false;
        if ($updateReport) {
            $updateReport->bind_param('siis', $completed, $reportId, $techId, $resultEntered);
            $updated = $updateReport->execute() && $updateReport->affected_rows === 1;
            $updateReport->close();
        }
        if ($updated) {
            if (!empty($report['request_id'])) {
                $requestUpdate = $conn->prepare('UPDATE lab_test_requests SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ? LIMIT 1');
                if ($requestUpdate) { $requestUpdate->bind_param('sii', $completed, $report['request_id'], $techId); $requestUpdate->execute(); $requestUpdate->close(); }
            }
            if (!empty($report['doctor_id'])) {
                $title = 'Lab Report Ready for Verification';
                $notification = 'Completed lab report for ' . ($report['test_name'] ?? 'laboratory test') . ' is ready for verification.';
                insert_staff_notification($conn, 'Doctor', (int)$report['doctor_id'], $title, $notification);
            }
            $_SESSION['flash_success'] = 'Report completed and sent to the assigned doctor for verification.';
        } else {
            $_SESSION['flash_error'] = 'Unable to complete the report.';
        }
    }
    header('Location: lab_technician_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_legacy_report'])) {
    $legacyId = (int)($_POST['id'] ?? 0);
    $legacyStmt = $conn->prepare('SELECT technician_id, file FROM lab_tests WHERE id = ? LIMIT 1');
    $legacyRecord = null;
    if ($legacyStmt) {
        $legacyStmt->bind_param('i', $legacyId);
        $legacyStmt->execute();
        $legacyRecord = $legacyStmt->get_result()->fetch_assoc();
        $legacyStmt->close();
    }

    if (!$legacyRecord) {
        $_SESSION['flash_error'] = 'Report not found.';
    } elseif ($techId > 0 && !empty($legacyRecord['technician_id']) && (int)$legacyRecord['technician_id'] !== $techId) {
        $_SESSION['flash_error'] = 'You can only delete reports uploaded by your account.';
    } else {
        $legacyFile = (string)($legacyRecord['file'] ?? '');
        $deleteLegacy = $conn->prepare('DELETE FROM lab_tests WHERE id = ? LIMIT 1');
        $deletedLegacy = false;
        if ($deleteLegacy) {
            $deleteLegacy->bind_param('i', $legacyId);
            $deletedLegacy = $deleteLegacy->execute() && $deleteLegacy->affected_rows === 1;
            $deleteLegacy->close();
        }

        if ($deletedLegacy) {
            if ($legacyFile !== '') {
                $legacyPath = __DIR__ . '/uploads/' . basename($legacyFile);
                if (is_file($legacyPath)) {
                    unlink($legacyPath);
                }
            }
            $_SESSION['flash_success'] = 'Report deleted successfully.';
        } else {
            $_SESSION['flash_error'] = 'Unable to delete the report.';
        }
    }

    header('Location: lab_technician_dashboard.php');
    exit();
}

// handle assigned request status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_status_action']) && isset($_POST['request_id'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['request_status_action'];
    $statusMap = [
        'start' => 'In Progress',
        'complete' => 'Completed',
        'cancel' => 'Cancelled',
    ];
    if (isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        $stmt = $conn->prepare('SELECT assigned_to FROM lab_test_requests WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
            $res = $stmt->get_result();
            $task = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
        if ($task && (int)$task['assigned_to'] === $techId) {
            $update = $conn->prepare('UPDATE lab_test_requests SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($update) { $update->bind_param('si', $newStatus, $requestId); $update->execute(); $update->close(); }
        }
    }
    header('Location: lab_technician_dashboard.php');
    exit();
}

// handle mark completed
if (isset($_POST['mark_completed']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    // ensure the test belongs to this technician
    $stmt = $conn->prepare('SELECT technician_id, patient_id FROM lab_tests WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $r = $res ? $res->fetch_assoc() : null; $stmt->close(); }
    if ($r && (empty($r['technician_id']) || (int)$r['technician_id'] === $techId)) {
        $errors = [];
        if (!empty($_FILES['replacement']['name'])) {
            $f = $_FILES['replacement'];
            if (!isset($f['error']) || is_array($f['error'])) {
                $errors[] = 'Invalid file upload parameters.';
            } elseif ($f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error code: ' . $f['error'];
            } else {
                $maxBytes = 5 * 1024 * 1024;
                if ($f['size'] > $maxBytes) { $errors[] = 'File exceeds maximum allowed size of 5 MB.'; }
                $allowedExt = ['pdf','jpg','jpeg','png'];
                $origName = $f['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) { $errors[] = 'Invalid file extension. Allowed: PDF, JPG, JPEG, PNG.'; }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['application/pdf','image/jpeg','image/png'];
                if (!in_array($mime, $allowedMimes, true)) { $errors[] = 'Invalid file type detected.'; }
                if (empty($errors)) {
                    if (!is_uploaded_file($f['tmp_name'])) { $errors[] = 'Uploaded file not found.'; }
                    else {
                        $uploadName = bin2hex(random_bytes(8)) . '.' . $ext;
                        $dest = __DIR__ . '/uploads/' . $uploadName;
                        if (!move_uploaded_file($f['tmp_name'], $dest)) { $errors[] = 'Failed to move uploaded file.'; }
                    }
                }
            }
        } else {
            $errors[] = 'No file selected for replacement.';
        }

        if (empty($errors)) {
            // update record: file, set technician_id if unassigned, updated_at
            $u = $conn->prepare('UPDATE lab_tests SET file = ?, technician_id = COALESCE(technician_id, ?), updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($u) { $u->bind_param('sii', $uploadName, $techId, $id); $u->execute(); $u->close(); }
            // notify patient if present
            if (!empty($r['patient_id'])) {
                $nid = (int)$r['patient_id'];
                $title = 'New Lab Report Uploaded';
                $message = 'A new report has been uploaded for lab test #' . $id . '.';
                $ins = $conn->prepare('INSERT INTO notifications (patient_id, title, message) VALUES (?, ?, ?)');
                if ($ins) { $ins->bind_param('iss', $nid, $title, $message); $ins->execute(); $ins->close(); }
            }
            $_SESSION['flash_success'] = 'Replacement file uploaded successfully.';
        } else {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }
    }
    // AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        } else {
            echo json_encode(['success' => true, 'message' => 'File uploaded', 'file' => $uploadName, 'id' => $id]);
        }
        exit();
    }
    header('Location: lab_technician_dashboard.php'); exit();
}

// fetch assigned lab work requests for this technician
$assignedTasks = [];
$taskStmt = $conn->prepare('SELECT ltr.*, p.name AS patient_name, d.name AS assigned_by_name FROM lab_test_requests ltr LEFT JOIN patients p ON p.id = ltr.patient_id LEFT JOIN doctors d ON d.id = ltr.assigned_by WHERE ltr.assigned_to = ? ORDER BY FIELD(ltr.status, "Assigned", "In Progress", "Pending", "Completed", "Cancelled"), ltr.due_date ASC, ltr.assigned_date DESC');
if ($taskStmt) { $taskStmt->bind_param('i', $techId); $taskStmt->execute(); $taskResult = $taskStmt->get_result(); while ($taskRow = $taskResult->fetch_assoc()) { $assignedTasks[] = $taskRow; } $taskStmt->close(); }

// existing tests list remains available below
$assignedResult = null;
if ($techId > 0) {
    $sql = 'SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE (l.technician_id = ? OR (l.technician_id IS NULL AND l.status = "Pending")) ORDER BY FIELD(l.status, "Pending", "Completed"), l.report_date ASC, l.id ASC';
    $stmt = $conn->prepare($sql);
    if ($stmt) { $stmt->bind_param('i', $techId); $stmt->execute(); $assignedResult = $stmt->get_result(); $stmt->close(); }
    if ($assignedResult && $assignedResult->num_rows === 0) {
        $stmt2 = $conn->prepare("SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.status = 'Pending' ORDER BY l.report_date ASC, l.id ASC");
        if ($stmt2) { $stmt2->execute(); $assignedResult = $stmt2->get_result(); $stmt2->close(); }
    }
} else {
    $stmt = $conn->prepare("SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.status = 'Pending' ORDER BY l.report_date ASC, l.id ASC");
    if ($stmt) { $stmt->execute(); $assignedResult = $stmt->get_result(); $stmt->close(); }
}

$uploadedReports = [];
$reportListStmt = $conn->prepare('SELECT lr.id, lr.report_id, lr.request_id, lr.test_name, lr.status, lr.created_at, p.name AS patient_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id WHERE lr.technician_id = ? ORDER BY lr.created_at DESC, lr.id DESC');
if ($reportListStmt) {
    $reportListStmt->bind_param('i', $techId);
    $reportListStmt->execute();
    $reportListResult = $reportListStmt->get_result();
    while ($reportRow = $reportListResult->fetch_assoc()) {
        $uploadedReports[] = $reportRow;
    }
    $reportListStmt->close();
}

?>
<!DOCTYPE html>
<html>
<head>
<title>My Lab Tests</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Inter,Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1400px;margin:20px auto;padding:0 16px}
.dashboard-shell{display:flex;gap:20px;align-items:flex-start}
.sidebar{width:280px;flex-shrink:0;background:linear-gradient(180deg,#0f4c81 0%,#0a335f 100%);color:#fff;border-radius:20px;padding:20px;box-shadow:0 14px 40px rgba(10,51,95,.18);position:sticky;top:20px}
.sidebar h3{font-size:20px;margin:0 0 6px}
.sidebar p{font-size:13px;opacity:.85;margin:0 0 18px}
.nav-group{margin-bottom:18px}
.nav-group-title{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;opacity:.7;margin-bottom:8px}
.nav-list{display:flex;flex-direction:column;gap:6px}
.nav-link{display:block;padding:10px 12px;border-radius:10px;color:#f7fbff;text-decoration:none;font-size:14px;transition:.2s}
.nav-link:hover,.nav-link.active{background:rgba(255,255,255,.16)}
.nav-link.disabled{opacity:.6;pointer-events:none}
.main-panel{flex:1;min-width:0}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.stats{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat{flex:1;min-width:180px;padding:12px;border-radius:12px;background:linear-gradient(180deg,#f8fbff,#eef6ff);box-shadow:0 6px 18px rgba(2,6,23,.06);display:flex;align-items:center;justify-content:space-between}
.stat .value{font-size:22px;font-weight:700}
.badge-status{padding:6px 10px;border-radius:12px;color:#fff;font-weight:600}
.badge-pending{background:#ffb020}
.badge-completed{background:#27ae60}
.thumb{width:54px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #eee}
.actions a,.actions button{margin-right:6px}
.modal-backdrop{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center}
.modal{background:#fff;padding:18px;border-radius:10px;max-width:540px;width:100%}
@media (max-width: 980px){.dashboard-shell{flex-direction:column}.sidebar{width:100%;position:static}}
</style>
<script>
function openReplaceModal(id){
    document.getElementById('replace_test_id').value = id;
    document.getElementById('modalBackdrop').style.display = 'flex';
}
function closeReplaceModal(){
    document.getElementById('modalBackdrop').style.display = 'none';
}
</script>
</head>
<body>
<div class="container">
    <div class="dashboard-shell">
        <aside class="sidebar">
            <h3>🏠 Laboratory Dashboard</h3>
            <p>Manage patients, tests, reports and lab operations in one place.</p>

            <div class="nav-group">
                <div class="nav-group-title">Upload Report</div>
                <div class="nav-list">
                    <a class="nav-link active" href="lab_test_result.php">Upload Report</a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Main</div>
                <div class="nav-list">
                    <a class="nav-link active" href="lab_technician_dashboard.php">Dashboard</a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Patients</div>
                <div class="nav-list">
                    <a class="nav-link" href="admin-patients.php">Patient List</a>
                    <a class="nav-link" href="patient_history.php">Patient History</a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Laboratory Tests</div>
                <div class="nav-list">
                    <a class="nav-link" href="lab_technician.php">New Requests</a>
                    <a class="nav-link" href="lab_technician.php">Pending Tests</a>
                    <a class="nav-link" href="lab_technician_dashboard.php#in-progress">In Progress</a>
                    <a class="nav-link" href="lab_technician_dashboard.php">Completed Tests</a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Operations</div>
                <div class="nav-list">
                    <a class="nav-link" href="sample_collection.php">Sample Collection</a>
                    <a class="nav-link" href="laboratory_inventory.php">Laboratory Inventory</a>
                    <a class="nav-link" href="leave_request.php">Leave Requests</a>
                    <a class="nav-link" href="leave_history.php">Leave History</a>
                    <a class="nav-link" href="leave_calendar.php">Leave Calendar</a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Account</div>
                <div class="nav-list">
                    <a class="nav-link" href="my_profile.php">My Profile</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </aside>

        <div class="main-panel">
            <div class="card" id="upload-report" style="margin-bottom:20px;">
                <h2>Upload Report</h2>
                <p style="color:#475569;">Select an assigned test, choose the completed PDF, JPG, or PNG report, and upload it. The original assigning doctor is linked automatically.</p>
                <?php $uploadTasks = array_filter($assignedTasks, static function ($task) { return in_array($task['status'], ['Assigned', 'In Progress', 'Sample Collected'], true); }); ?>
                <?php if (!empty($uploadTasks)): ?>
                    <div class="table-responsive">
                        <table width="100%" class="table-modern">
                            <thead><tr><th>Patient</th><th>Patient ID</th><th>Test</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($uploadTasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['patient_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo (int)$task['patient_id']; ?></td>
                                        <td><?php echo htmlspecialchars($task['test_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($task['status'] ?? ''); ?></td>
                                        <td><a class="btn btn-primary" href="lab_test_result.php?request_id=<?php echo (int)$task['id']; ?>">Upload Report</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty">No assigned lab tests are ready for report upload.</p>
                <?php endif; ?>
            </div>
            <h2>Assigned Lab Tests</h2>
            <div class="card" id="in-progress">
        <?php
        // stats
        $totalAssigned = 0; $totalPending = 0; $totalCompleted = 0;
        $c1 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE technician_id = " . intval($techId));
        $totalAssigned = $c1 ? (int)$c1->fetch_assoc()['c'] : 0;
        $c2 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE status = 'Pending'");
        $totalPending = $c2 ? (int)$c2->fetch_assoc()['c'] : 0;
        $c3 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE status = 'Completed'");
        $totalCompleted = $c3 ? (int)$c3->fetch_assoc()['c'] : 0;
        ?>
        <div class="stats" id="stats">
            <div class="stat"><div><div style="color:#334e68">Assigned</div><div class="value"><?php echo $totalAssigned; ?></div></div><div><svg width="28" height="28" fill="none" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4z" fill="#2c7be5"></path><path d="M6 20v-1c0-2.21 3.58-4 6-4s6 1.79 6 4v1H6z" fill="#2c7be5"></path></svg></div></div>
            <div class="stat"><div><div style="color:#334e68">Pending</div><div class="value"><?php echo $totalPending; ?></div></div><div><svg width="28" height="28" viewBox="0 0 24 24"><path d="M12 3v10l3 3" stroke="#ffb020" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"></path><circle cx="12" cy="12" r="9" stroke="#ffb020" stroke-width="1.5" fill="none"></circle></svg></div></div>
            <div class="stat"><div><div style="color:#334e68">Completed</div><div class="value"><?php echo $totalCompleted; ?></div></div><div><svg width="28" height="28" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" stroke="#27ae60" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"></path></svg></div></div>
        </div>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background:#e6ffed;padding:10px;border-radius:6px;margin-bottom:12px;color:#02733a">
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background:#ffe6e6;padding:10px;border-radius:6px;margin-bottom:12px;color:#a00">
                <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($assignedTasks)): ?>
            <div class="card" style="margin-bottom:20px;">
                <h3>Assigned Lab Work</h3>
                <div class="table-responsive">
                    <table width="100%" class="table-modern">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Assigned By</th>
                                <th>Assigned Date</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedTasks as $task): ?>
                                <tr>
                                    <td><?php echo (int)$task['id']; ?></td>
                                    <td><?php echo htmlspecialchars($task['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['test_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['assigned_by_name'] ?? 'Doctor'); ?></td>
                                    <td><?php echo htmlspecialchars($task['assigned_date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['due_date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['priority'] ?? 'Normal'); ?></td>
                                    <td><?php echo htmlspecialchars($task['status']); ?></td>
                                    <td>
                                        <?php if ($task['status'] === 'Assigned'): ?>
                                            <form method="post" style="display:inline-block; margin:0;">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$task['id']; ?>">
                                                <input type="hidden" name="request_status_action" value="start">
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Start</button>
                                            </form>
                                            <form method="post" style="display:inline-block; margin:0;">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$task['id']; ?>">
                                                <input type="hidden" name="request_status_action" value="cancel">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                            </form>
                                        <?php elseif ($task['status'] === 'In Progress'): ?>
                                            <form method="post" style="display:inline-block; margin:0;">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$task['id']; ?>">
                                                <input type="hidden" name="request_status_action" value="complete">
                                                <button class="btn btn-sm btn-success" type="submit">Complete</button>
                                            </form>
                                            <form method="post" style="display:inline-block; margin:0;">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$task['id']; ?>">
                                                <input type="hidden" name="request_status_action" value="cancel">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge-status <?php echo $task['status'] === 'Completed' ? 'badge-completed' : 'badge-pending'; ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($uploadedReports)): ?>
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <h3 style="margin:0;">Uploaded Reports</h3>
                </div>
                <div class="table-responsive">
                    <table width="100%" class="table-modern">
                        <thead><tr><th>Report ID</th><th>Patient</th><th>Test</th><th>Status</th><th>Uploaded</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($uploadedReports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['report_id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($report['patient_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($report['test_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($report['status'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($report['created_at'] ?? ''); ?></td>
                                <td>
                                    <?php if ($report['status'] === 'Result Entered'): ?>
                                        <form method="post" style="display:inline-block;margin:0;" onsubmit="return confirm('Mark this report as completed and send it to the assigned doctor?');">
                                            <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                            <button class="btn btn-success" type="submit" name="complete_report" value="1">Mark Completed</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline-block;margin:0;" onsubmit="return confirm('Are you sure you want to delete this report? This action cannot be undone.');">
                                        <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                        <button class="btn btn-outline-danger" type="submit" name="delete_report" value="1">Delete Report</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <table width="100%" class="table-modern">
            <thead><tr><th>ID</th><th>Patient</th><th>Test</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($assignedResult && $assignedResult->num_rows>0): while ($row = $assignedResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['test_type']); ?></td>
                    <td><?php echo $row['status'] === 'Completed' ? '<span class="badge-status badge-completed">Completed</span>' : '<span class="badge-status badge-pending">Pending</span>'; ?></td>
                    <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                    <td>
                        <a class="btn btn-edit" href="lab_report_edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to delete this report? This action cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" name="delete_legacy_report" value="1" class="btn btn-danger">Delete</button>
                        </form>
                        <?php if (!empty($row['file'])): 
                            $ext = strtolower(pathinfo($row['file'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($row['file']); ?>" class="thumb" alt="thumb">
                            <?php else: ?>
                                <a href="uploads/<?php echo htmlspecialchars($row['file']); ?>" target="_blank">Download</a>
                            <?php endif; 
                        endif; ?>
                        <div class="actions">
                            <button type="button" class="btn btn-replace" onclick="openReplaceModal(<?php echo (int)$row['id']; ?>)">Replace</button>
                        </div>
                        <?php if ($row['status'] !== 'Completed'): ?>
                            <button class="btn btn-complete" onclick="markCompleted(<?php echo (int)$row['id']; ?>)">Mark Completed</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">No assigned tests.</td></tr>
            <?php endif; ?>
        </table>
            </div>
        </div>
    </div>
</div>
<!-- Replace Modal -->
<div id="modalBackdrop" class="modal-backdrop">
    <div class="modal" role="dialog">
        <h3>Replace Lab Report</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" id="replace_test_id" name="id" value="">
            <div style="margin:12px 0">
                <label>Choose file (PDF, JPG, PNG) — max 5MB</label>
                <input type="file" name="replacement" accept=".pdf,image/*" required>
            </div>
            <div style="text-align:right">
                <button type="button" onclick="closeReplaceModal()">Cancel</button>
                <button type="submit" name="replace_file">Upload</button>
            </div>
        </form>
    </div>
</div>
<!-- Toast -->
<div id="toast" style="position:fixed;right:20px;bottom:20px;display:none;background:#333;color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 6px 18px rgba(2,6,23,.18)"></div>
<div id="spinner" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.95);padding:16px;border-radius:10px;box-shadow:0 8px 30px rgba(2,6,23,.2)"><div style="width:36px;height:36px;border-radius:50%;border:4px solid #eee;border-top-color:#2c7be5;animation:spin 1s linear infinite;margin:0 auto"></div><div style="text-align:center;margin-top:8px;color:#334e68">Processing...</div></div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
// AJAX upload with progress
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('modalBackdrop');
    var modalForm = modal.querySelector('form');
    modalForm.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(modalForm);
        fd.append('replace_file', '1');
        var file = modalForm.querySelector('input[type=file]').files[0];
        if (!file) { showToast('Please choose a file'); return; }
        var allowed = ['pdf','jpg','jpeg','png'];
        var ext = file.name.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) === -1) { showToast('Invalid file type'); return; }
        if (file.size > 5 * 1024 * 1024) { showToast('File too large (max 5MB)'); return; }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'lab_technician_dashboard.php');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', function(ev){
            if (ev.lengthComputable) {
                var pct = Math.round(ev.loaded / ev.total * 100);
                showToast('Uploading ' + pct + '%');
            }
        });
        xhr.onreadystatechange = function(){
            if (xhr.readyState === 4) {
                hideSpinner();
                try {
                    var res = JSON.parse(xhr.responseText);
                } catch(e) { showToast('Server error'); closeReplaceModal(); return; }
                if (res.success) {
                    showToast(res.message || 'Upload complete');
                    // update UI: reload page to reflect changes
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    showToast(res.message || 'Upload failed');
                }
                closeReplaceModal();
            }
        };
        showSpinner();
        xhr.send(fd);
    });
});

function markCompleted(id){
    if (!confirm('Mark this test as completed?')) return;
    var fd = new FormData(); fd.append('id', id); fd.append('mark_completed', '1');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'lab_technician_dashboard.php');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function(){
        if (xhr.readyState === 4) {
            try { var res = JSON.parse(xhr.responseText); } catch(e){ showToast('Server error'); return; }
            if (res.success) { showToast(res.message || 'Marked completed'); setTimeout(function(){ location.reload(); }, 800); }
            else showToast(res.message || 'Failed');
        }
    };
    xhr.send(fd);
    showSpinner();
}

function showToast(msg){ var t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(window._toastTimer); window._toastTimer = setTimeout(function(){ t.style.display='none'; }, 3500); }
function showSpinner(){ document.getElementById('spinner').style.display = 'block'; }
function hideSpinner(){ document.getElementById('spinner').style.display = 'none'; }
</script>
</body>
</html>