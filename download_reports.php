<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
$message = '';
ensure_lab_module_tables($conn);

$sql = "SELECT lr.id, lr.request_id AS test_id, lr.patient_id, p.name AS patient_name,
               lr.test_name, lr.status,
               DATE(COALESCE(lr.updated_at, lr.created_at)) AS report_date
        FROM lab_reports lr
        LEFT JOIN patients p ON p.id = lr.patient_id
        WHERE lr.status IN ('Completed', 'Doctor Verified', 'Approved')
          AND EXISTS (
              SELECT 1
              FROM lab_test_results ltr
              WHERE ltr.request_id = lr.request_id
          )
        ORDER BY report_date DESC, lr.id DESC";
$result = $conn->query($sql);
$reports = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
<title>Download Reports</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1280px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block;background:#0d6efd;color:#fff;border:0}
.alert{padding:10px;border-radius:8px;margin-bottom:12px}
.alert-danger{background:#ffe6e6;color:#a00}
</style>
</head>
<body>
<div class="container">
    <h2>Download Reports</h2>
    <div class="card">
        <?php if ($message !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient</th>
                        <th>Test ID</th>
                        <th>Test Name</th>
                        <th>Status</th>
                        <th>Completed Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reports)): foreach ($reports as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['patient_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name'] ?? ''); ?></td>
                            <td><?php echo (int)$row['test_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['test_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                            <td>
                                <a class="btn" href="lab_report_view.php?request_id=<?php echo (int)$row['test_id']; ?>" target="_blank">View Report</a>
                                <a class="btn" href="download_lab_report.php?report_id=<?php echo (int)$row['id']; ?>" target="_blank">Download PDF</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7">No completed laboratory reports found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
