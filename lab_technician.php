<?php
require_once 'auth.php';
require_once 'db.php';
// Allow access to admin, nurse, pharmacist and receptionist as staff who can manage lab reports
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['nurse_id']) && !isset($_SESSION['pharmacist_id']) && !isset($_SESSION['receptionist_id'])) {
    header('Location: login.php');
    exit();
}

ensure_lab_tests_table($conn);

// Handle mark completed action
if (isset($_POST['mark_completed']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('SELECT patient_id FROM lab_tests WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $r = $res ? $res->fetch_assoc() : null; $stmt->close(); }
    $u = $conn->prepare("UPDATE lab_tests SET status = 'Completed', updated_at = NOW() WHERE id = ? LIMIT 1");
    if ($u) { $u->bind_param('i', $id); $u->execute(); $u->close(); }
    if (!empty($r['patient_id'])) {
        $nid = (int)$r['patient_id'];
        $title = 'Lab Report Completed';
        $message = 'Your lab test report #' . $id . ' has been completed.';
        $ins = $conn->prepare('INSERT INTO notifications (patient_id, title, message) VALUES (?, ?, ?)');
        if ($ins) { $ins->bind_param('iss', $nid, $title, $message); $ins->execute(); $ins->close(); }
    }
    header('Location: lab_technician.php');
    exit();
}

// Fetch pending tests
$pending = $conn->query("SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.status = 'Pending' ORDER BY l.report_date ASC, l.id ASC");

?>
<!DOCTYPE html>
<html>
<head>
<title>Lab Technician - Pending Tests</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
.container{max-width:1100px;margin:20px auto}
.card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 8px 30px rgba(2,6,23,.06)}
.table-responsive{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block}
</style>
</head>
<body>
<div class="container">
    <h2>Pending Lab Tests</h2>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Test Type</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending && $pending->num_rows > 0): ?>
                        <?php while ($row = $pending->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['test_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                                <td>
                                    <a class="btn" href="lab_report_view.php?id=<?php echo (int)$row['id']; ?>">View</a>
                                    <a class="btn" href="lab_report_edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                                    <form method="POST" style="display:inline-block;margin:0;padding:0;" onsubmit="return confirm('Mark this test as completed?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" name="mark_completed" class="btn">Mark Completed</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No pending lab tests.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>