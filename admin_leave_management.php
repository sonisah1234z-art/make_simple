<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'leave_management.php';

ensure_leave_management_schema($conn);

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';
$roleFilter = $_GET['role'] ?? 'All';
$staffFilter = trim($_GET['staff'] ?? '');
$leaveTypeFilter = $_GET['leave_type'] ?? 'All';
$dateFilter = trim($_GET['date'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['leave_action'] ?? '';

    if ($requestId > 0 && in_array($action, ['approve', 'reject', 'edit', 'delete'], true)) {
        $recordStmt = $conn->prepare('SELECT * FROM leave_requests WHERE id = ? LIMIT 1');
        $record = null;
        if ($recordStmt) {
            $recordStmt->bind_param('i', $requestId);
            $recordStmt->execute();
            $record = $recordStmt->get_result()->fetch_assoc();
            $recordStmt->close();
        }

        if (!$record) {
            $message = 'Leave record not found.';
        } elseif ($action === 'delete') {
            $deleteStmt = $conn->prepare('DELETE FROM leave_requests WHERE id = ? LIMIT 1');
            $deleted = false;
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $requestId);
                $deleted = $deleteStmt->execute() && $deleteStmt->affected_rows === 1;
                $deleteStmt->close();
            }
            if ($deleted) {
                if (!empty($record['document_path'])) {
                    $documentPath = __DIR__ . '/' . ltrim($record['document_path'], '/');
                    if (is_file($documentPath)) { unlink($documentPath); }
                }
                $message = 'Leave record deleted successfully.';
            } else {
                $message = 'Unable to delete the leave record.';
            }
        } elseif ($action === 'edit') {
            $leaveType = trim($_POST['leave_type'] ?? '');
            $startDate = trim($_POST['start_date'] ?? '');
            $endDate = trim($_POST['end_date'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $status = $_POST['status'] ?? 'Pending';
            if ($leaveType === '' || $startDate === '' || $endDate === '' || $reason === '' || !in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
                $message = 'Please complete all leave record fields.';
            } elseif (strtotime($endDate) < strtotime($startDate)) {
                $message = 'End date cannot be earlier than start date.';
            } else {
                $updateStmt = $conn->prepare('UPDATE leave_requests SET leave_type = ?, start_date = ?, end_date = ?, reason = ?, status = ?, reviewed_at = CASE WHEN ? = \'Pending\' THEN NULL ELSE COALESCE(reviewed_at, NOW()) END, reviewed_by = CASE WHEN ? = \'Pending\' THEN NULL ELSE COALESCE(reviewed_by, ?) END WHERE id = ? LIMIT 1');
                $reviewedBy = $_SESSION['admin_name'] ?? 'Administrator';
                if ($updateStmt) {
                    $updateStmt->bind_param('ssssssssi', $leaveType, $startDate, $endDate, $reason, $status, $status, $status, $reviewedBy, $requestId);
                    $message = $updateStmt->execute() ? 'Leave record updated successfully.' : 'Unable to update the leave record.';
                    $updateStmt->close();
                }
                $editId = 0;
            }
        } else {
            $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
            $remarks = trim($_POST['remarks'] ?? '');
            $reviewedBy = $_SESSION['admin_name'] ?? 'Administrator';
            $updateStmt = $conn->prepare('UPDATE leave_requests SET status = ?, remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ? LIMIT 1');
            if ($updateStmt) {
                $updateStmt->bind_param('sssi', $newStatus, $remarks, $reviewedBy, $requestId);
                if ($updateStmt->execute()) {
                    insert_staff_notification($conn, $record['staff_role'], (int)$record['staff_id'], 'Leave Request ' . $newStatus, 'Your leave request has been ' . strtolower($newStatus) . '.');
                    $message = 'Leave request ' . strtolower($newStatus) . ' successfully.';
                } else {
                    $message = 'Unable to update the leave request.';
                }
                $updateStmt->close();
            }
        }
    }
}

$sql = 'SELECT * FROM leave_requests WHERE 1=1';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' AND (staff_name LIKE ? OR staff_role LIKE ? OR leave_type LIKE ? OR reason LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

if ($statusFilter !== 'All') {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($roleFilter !== 'All') {
    $sql .= ' AND staff_role = ?';
    $params[] = $roleFilter;
    $types .= 's';
}

if ($staffFilter !== '') {
    $sql .= ' AND staff_name LIKE ?';
    $params[] = '%' . $staffFilter . '%';
    $types .= 's';
}

if ($leaveTypeFilter !== 'All') {
    $sql .= ' AND leave_type = ?';
    $params[] = $leaveTypeFilter;
    $types .= 's';
}

if ($dateFilter !== '') {
    $sql .= ' AND (start_date <= ? AND end_date >= ?)';
    $params[] = $dateFilter;
    $params[] = $dateFilter;
    $types .= 'ss';
}

$sql .= ' ORDER BY submitted_at DESC';

$requests = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}

$staffNames = [];
$staffResult = $conn->query('SELECT DISTINCT staff_name FROM leave_requests ORDER BY staff_name');
if ($staffResult) { while ($staffRow = $staffResult->fetch_assoc()) { $staffNames[] = $staffRow['staff_name']; } }
$leaveTypes = [];
$leaveTypeResult = $conn->query('SELECT DISTINCT leave_type FROM leave_requests ORDER BY leave_type');
if ($leaveTypeResult) { while ($leaveTypeRow = $leaveTypeResult->fetch_assoc()) { $leaveTypes[] = $leaveTypeRow['leave_type']; } }
$editRecord = null;
if ($editId > 0) {
    foreach ($requests as $request) {
        if ((int)$request['id'] === $editId) { $editRecord = $request; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Leave Management</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.container{max-width:1500px;margin:24px auto;padding:0 20px}
.card{background:white;border-radius:12px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.08);margin-bottom:20px}
form.inline{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
form.inline>div{min-width:150px;flex:1}
input,select,textarea,button{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
button{background:#2563eb;color:white;border:none;cursor:pointer}
button.reject{background:#dc2626}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:bold}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-approved{background:#dcfce7;color:#166534}
.badge-rejected{background:#fee2e2;color:#991b1b}
.actions{display:flex;gap:6px;flex-wrap:wrap;min-width:220px}
.actions form{display:inline-flex;gap:6px;align-items:center}
.btn-edit{background:#475569}
.btn-delete{background:#b91c1c}
.edit-panel{border:1px solid #bfdbfe;background:#eff6ff}
@media(max-width:800px){table{display:block;overflow-x:auto;white-space:nowrap}.actions{min-width:190px}.container{padding:0 12px}}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Staff Leave Management</h2>
        <p>Pending Leave Requests and Leave History. Review and manage staff leave requests from a single page.</p>
        <?php if (!empty($message)): ?>
            <div style="padding:10px 12px;border-radius:8px;margin:12px 0;background:#eff6ff;color:#1d4ed8;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form class="inline" method="get">
            <div>
                <label>Search</label><br>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Staff, role, type, reason">
            </div>
            <div>
                <label>Staff</label><br>
                <input list="staff-options" type="text" name="staff" value="<?php echo htmlspecialchars($staffFilter); ?>" placeholder="Staff name">
                <datalist id="staff-options"><?php foreach ($staffNames as $staffName): ?><option value="<?php echo htmlspecialchars($staffName); ?>"><?php endforeach; ?></datalist>
            </div>
            <div>
                <label>Status</label><br>
                <select name="status">
                    <option value="All" <?php echo $statusFilter === 'All' ? 'selected' : ''; ?>>All</option>
                    <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div>
                <label>Role</label><br>
                <select name="role">
                    <option value="All" <?php echo $roleFilter === 'All' ? 'selected' : ''; ?>>All</option>
                    <option value="Doctor" <?php echo $roleFilter === 'Doctor' ? 'selected' : ''; ?>>Doctor</option>
                    <option value="Nurse" <?php echo $roleFilter === 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                    <option value="Pharmacist" <?php echo $roleFilter === 'Pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                    <option value="Receptionist" <?php echo $roleFilter === 'Receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                    <option value="Lab Technician" <?php echo $roleFilter === 'Lab Technician' ? 'selected' : ''; ?>>Lab Technician</option>
                </select>
            </div>
            <div>
                <label>Leave Type</label><br>
                <select name="leave_type">
                    <option value="All">All</option>
                    <?php foreach ($leaveTypes as $leaveType): ?><option value="<?php echo htmlspecialchars($leaveType); ?>" <?php echo $leaveTypeFilter === $leaveType ? 'selected' : ''; ?>><?php echo htmlspecialchars($leaveType); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Date</label><br>
                <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            <button type="submit">Filter</button>
            <a href="admin_leave_management.php" style="padding:10px 12px;">Reset</a>
        </form>
    </div>

    <?php if ($editRecord): ?>
        <div class="card edit-panel">
            <h3>Edit Leave Record #<?php echo (int)$editRecord['id']; ?></h3>
            <form class="inline" method="post">
                <input type="hidden" name="request_id" value="<?php echo (int)$editRecord['id']; ?>">
                <input type="hidden" name="leave_action" value="edit">
                <div><label>Staff</label><br><strong><?php echo htmlspecialchars($editRecord['staff_name']); ?></strong></div>
                <div><label>Leave Type</label><br><input name="leave_type" value="<?php echo htmlspecialchars($editRecord['leave_type']); ?>" required></div>
                <div><label>Start Date</label><br><input type="date" name="start_date" value="<?php echo htmlspecialchars($editRecord['start_date']); ?>" required></div>
                <div><label>End Date</label><br><input type="date" name="end_date" value="<?php echo htmlspecialchars($editRecord['end_date']); ?>" required></div>
                <div><label>Status</label><br><select name="status"><option <?php echo $editRecord['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option><option <?php echo $editRecord['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option><option <?php echo $editRecord['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option></select></div>
                <div style="flex-basis:100%"><label>Reason</label><br><textarea name="reason" rows="3" style="width:100%" required><?php echo htmlspecialchars($editRecord['reason']); ?></textarea></div>
                <button type="submit">Save Changes</button><a href="admin_leave_management.php">Cancel</a>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff</th>
                    <th>Role</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Request Date</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="11">No leave requests found.</td></tr>
                <?php else: foreach ($requests as $request): ?>
                    <tr>
                        <td>#<?php echo (int)$request['id']; ?></td>
                        <td><?php echo htmlspecialchars($request['staff_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['staff_role']); ?></td>
                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                        <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                        <td><?php echo htmlspecialchars($request['submitted_at']); ?></td>
                        <td><?php echo htmlspecialchars($request['remarks'] ?? ''); ?></td>
                        <td class="actions">
                            <?php if ($request['status'] === 'Pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <button type="submit" name="leave_action" value="approve">Approve</button>
                                    <button class="reject" type="submit" name="leave_action" value="reject">Reject</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn-edit" style="color:white;padding:10px 12px;border-radius:8px;text-decoration:none;" href="admin_leave_management.php?edit=<?php echo (int)$request['id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this leave record? This action cannot be undone.');">
                                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                <button class="btn-delete" type="submit" name="leave_action" value="delete">Delete</button>
                            </form>
                            <?php if (!empty($request['reviewed_by'])): ?><small style="width:100%;color:#64748b;">Reviewed by <?php echo htmlspecialchars($request['reviewed_by']); ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
