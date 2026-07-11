<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
ensure_lab_tests_table($conn);

$statusFilter = $_GET['status'] ?? '';
$params = [];
$sql = "SELECT l.*, p.name AS patient_name, t.name AS technician_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id LEFT JOIN lab_technicians t ON t.id = l.technician_id";
if ($statusFilter === 'Pending' || $statusFilter === 'Completed') {
    $sql .= " WHERE l.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY l.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $params[0]);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}

$total = $conn->query('SELECT COUNT(*) AS total FROM lab_tests');
$totalCount = $total ? (int)$total->fetch_assoc()['total'] : 0;
$pending = $conn->query("SELECT COUNT(*) AS total FROM lab_tests WHERE status='Pending'");
$pendingCount = $pending ? (int)$pending->fetch_assoc()['total'] : 0;
$completed = $conn->query("SELECT COUNT(*) AS total FROM lab_tests WHERE status='Completed'");
$completedCount = $completed ? (int)$completed->fetch_assoc()['total'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
<title>Lab Reports Management</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>.container{max-width:1200px;margin:20px auto}.card{padding:18px;background:#fff;border-radius:8px}.filters{display:flex;gap:10px;align-items:center;margin-bottom:12px}</style>
</head>
<body>
<div class="container">
    <h2>Lab Reports Management</h2>
    <div class="card">
        <div style="display:flex;gap:20px;align-items:center;justify-content:space-between">
            <div>
                <strong>Total:</strong> <?php echo $totalCount; ?> &nbsp; <strong>Pending:</strong> <?php echo $pendingCount; ?> &nbsp; <strong>Completed:</strong> <?php echo $completedCount; ?>
            </div>
            <div class="filters">
                <form method="GET">
                    <label>Status:</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="Pending" <?php echo $statusFilter==='Pending'?'selected':''; ?>>Pending</option>
                        <option value="Completed" <?php echo $statusFilter==='Completed'?'selected':''; ?>>Completed</option>
                    </select>
                </form>
                <a class="btn" href="admin_lab_reports.php">Reset</a>
            </div>
        </div>

        <table width="100%" border="1" style="border-collapse:collapse;margin-top:12px">
            <tr><th>ID</th><th>Patient</th><th>Test</th><th>Technician</th><th>Status</th><th>Date</th><th>Attachment</th><th>Actions</th></tr>
            <?php if ($result && $result->num_rows>0): while ($r = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['test_type']); ?></td>
                    <td>
                        <?php if (!empty($r['technician_name'])): ?>
                            <a href="admin_lab_technicians.php?action=edit&id=<?php echo (int)$r['technician_id']; ?>"><?php echo htmlspecialchars($r['technician_name']); ?></a>
                        <?php else: ?>
                            Unassigned
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($r['status']); ?></td>
                    <td><?php echo htmlspecialchars($r['report_date']); ?></td>
                    <td><?php if (!empty($r['file'])): ?><a href="uploads/<?php echo htmlspecialchars($r['file']); ?>" target="_blank">Download</a><?php else: ?>-<?php endif; ?></td>
                    <td>
                        <a href="lab_report_view.php?id=<?php echo (int)$r['id']; ?>">View</a>
                        <a href="lab_report_edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8">No reports found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>