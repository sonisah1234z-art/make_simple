<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'leave_management.php';

ensure_leave_management_schema($conn);

$staffContext = get_leave_staff_context();
$role = $staffContext['role'];
$staffId = $staffContext['id'];
if (!is_staff_role($role) || $staffId <= 0) {
    header('Location: login.php');
    exit();
}

$requests = [];
$stmt = $conn->prepare('SELECT * FROM leave_requests WHERE staff_role = ? AND staff_id = ? ORDER BY start_date DESC, id DESC');
if ($stmt) {
    $stmt->bind_param('si', $role, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave History</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.container{max-width:1100px;margin:24px auto;padding:0 20px}
.card{background:white;border-radius:12px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.08);margin-bottom:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:bold}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-approved{background:#dcfce7;color:#166534}
.badge-rejected{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Leave History</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Leave Type</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6">No leave history available.</td></tr>
                <?php else: foreach ($requests as $request): ?>
                    <tr>
                        <td>#<?php echo (int)$request['id']; ?></td>
                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                        <td><?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                        <td><?php echo htmlspecialchars($request['remarks'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
