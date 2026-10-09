<?php
require_once 'auth.php';
require_once 'db.php';
ensure_lab_tests_table($conn);

// Build query depending on role
if (isset($_SESSION['admin_id']) || isset($_SESSION['lab_technician_id'])) {
    if (isset($_SESSION['lab_technician_id'])) {
        ensure_lab_module_tables($conn);
        $technicianId = (int)$_SESSION['lab_technician_id'];
        $sql = "SELECT l.id, NULL AS request_id, l.patient_id, p.name AS patient_name, l.test_type, l.file, l.image, l.status, l.report_date, lt.name AS technician_name, 'lab_tests' AS report_source
                FROM lab_tests l
                LEFT JOIN patients p ON p.id = l.patient_id
                LEFT JOIN lab_technicians lt ON lt.id = l.technician_id
                WHERE l.technician_id = ?
                UNION ALL
                SELECT lr.id, lr.request_id, lr.patient_id, p.name AS patient_name, lr.test_name AS test_type, lr.file, NULL AS image, lr.status, DATE(COALESCE(lr.updated_at, lr.created_at)) AS report_date, lt.name AS technician_name, 'lab_reports' AS report_source
                FROM lab_reports lr
                LEFT JOIN patients p ON p.id = lr.patient_id
                LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id
                WHERE lr.technician_id = ?
                ORDER BY report_date DESC, id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $technicianId, $technicianId);
    } else {
        $sql = "SELECT l.*, p.name AS patient_name, lt.name AS technician_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id LEFT JOIN lab_technicians lt ON lt.id = l.technician_id ORDER BY l.report_date DESC, l.id DESC";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} elseif (isset($_SESSION['patient_id'])) {
    $patient_id = current_patient_id();
    // Only completed reports visible to patients
    $sql = "SELECT l.*, p.name AS patient_name, lt.name AS technician_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id LEFT JOIN lab_technicians lt ON lt.id = l.technician_id WHERE l.patient_id = ? AND l.status = 'Completed' ORDER BY l.report_date DESC, l.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    header('Location: login.php');
    exit();
}
?>
<?php require 'header.php'; ?>

<div class="container">
    <h2 class="text-center my-3"><img src="assets/icons/hospital.svg" class="icon"> My Lab Reports</h2>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Report Date</th>
                        <th>Status</th>
                        <th>Lab Technician</th>
                        <th>Report Type</th>
                        <th>Attachment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()):
                    $file = trim((string)($row['file'] ?? ($row['image'] ?? '')));
                    $fileExists = false;
                    $fileExt = '';
                    if ($file !== '') {
                        $safe = basename($file);
                        $fullPath = __DIR__ . '/uploads/' . $safe;
                        $fileExists = file_exists($fullPath) && is_file($fullPath);
                        $fileExt = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
                    }
                    // Derive report type
                    $reportType = 'N/A';
                    if ($fileExt === 'pdf') $reportType = 'PDF Document';
                    elseif (in_array($fileExt, ['doc','docx'], true)) $reportType = 'Word Document';
                    elseif (in_array($fileExt, ['jpg','jpeg','png'], true)) $reportType = 'Image';
                    else if ($file !== '') $reportType = strtoupper($fileExt);
                ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['test_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo htmlspecialchars($row['technician_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reportType); ?></td>
                        <td>
                            <?php if ($file === ''): ?>
                                <span class="text-muted">No attachment</span>
                            <?php elseif (!$fileExists): ?>
                                <span class="text-danger">File unavailable</span>
                            <?php else: ?>
                                <?php if (in_array($fileExt, ['jpg','jpeg','png'], true)): ?>
                                    <img src="uploads/<?php echo htmlspecialchars(basename($file)); ?>" alt="thumb" style="max-width:80px;height:auto;border-radius:6px">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars(basename($file)); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($row['report_source'] ?? 'lab_tests') === 'lab_reports'): ?>
                            <?php elseif ($file !== '' && $fileExists): ?>
                                <a class="btn btn-sm btn-outline-primary" href="download_report.php?id=<?php echo (int)$row['id']; ?>&action=view" target="_blank">View</a>
                                <a class="btn btn-sm btn-primary ms-2" href="download_report.php?id=<?php echo (int)$row['id']; ?>&action=download">Download</a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center">No lab reports available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

