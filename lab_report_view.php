<?php
require_once 'auth.php';
require_once 'db.php';
// allow staff, lab technicians, and patient to view
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['nurse_id']) && !isset($_SESSION['pharmacist_id']) && !isset($_SESSION['receptionist_id']) && !isset($_SESSION['lab_technician_id']) && !isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}
ensure_lab_tests_table($conn);

$id = (int)($_GET['id'] ?? 0);
// include technician name
$stmt = $conn->prepare('SELECT l.*, p.name as patient_name, lt.name as technician_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id LEFT JOIN lab_technicians lt ON lt.id = l.technician_id WHERE l.id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}
if (!$row) {
    echo 'Report not found';
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>View Lab Report</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>.container{max-width:900px;margin:20px auto}.card{padding:18px;background:#fff;border-radius:10px}</style>
</head>
<body>
<div class="container">
    <h2>Lab Report #<?php echo (int)$row['id']; ?></h2>
    <div class="card">
        <p><strong>Patient:</strong> <?php echo htmlspecialchars($row['patient_name']); ?></p>
        <p><strong>Test:</strong> <?php echo htmlspecialchars($row['test_type']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
        <p><strong>Result:</strong></p>
        <div style="white-space:pre-wrap;background:#f8fafc;padding:12px;border-radius:8px"><?php echo htmlspecialchars($row['result']); ?></div>
        <?php if (!empty($row['file'])): 
            $ext = strtolower(pathinfo($row['file'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])): ?>
                <p><strong>Image:</strong></p>
                <img src="uploads/<?php echo htmlspecialchars($row['file']); ?>" alt="report image" style="max-width:100%;height:auto;border:1px solid #eee;padding:6px;border-radius:8px">
            <?php else: ?>
                <p><strong>Attachment:</strong> <a href="uploads/<?php echo htmlspecialchars($row['file']); ?>" target="_blank">Download</a></p>
            <?php endif; 
        endif; ?>
        <?php if (!empty($row['technician_name'])): ?>
            <p><strong>Uploaded By:</strong> <?php echo htmlspecialchars($row['technician_name']); ?></p>
        <?php endif; ?>
        <?php if (!empty($row['updated_at'])): ?>
            <p><strong>Uploaded On:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
        <?php endif; ?>
        <p class="mt-3">
                <?php if (isset($_SESSION['admin_id']) || isset($_SESSION['nurse_id']) || isset($_SESSION['pharmacist_id']) || isset($_SESSION['receptionist_id']) || (isset($_SESSION['lab_technician_id']) && isset($row['technician_id']) && (int)$row['technician_id'] === (int)($_SESSION['lab_technician_id'] ?? 0))): ?>
                    <a class="btn" href="lab_report_edit.php?id=<?php echo (int)$row['id']; ?>">Edit Report</a>
            <?php endif; ?>
            <a class="btn" href="lab_technician.php">Back to Pending</a>
        </p>
    </div>
</div>
</body>
</html>