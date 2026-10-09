<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';
ensure_lab_module_tables($conn);

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'Doctor Verified'));
$patientId = (int)($_SESSION['patient_id'] ?? 0);

$where = ['lr.patient_id = ?'];
$params = [$patientId];
$types = 'i';

if ($search !== '') {
    $where[] = '(lr.report_id LIKE ? OR p.name LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ss';
}

if ($statusFilter !== '') {
    $where[] = 'lr.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = 'SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE ' . implode(' AND ', $where) . ' ORDER BY lr.created_at DESC';
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $reports = $stmt->get_result();
    $stmt->close();
}

require_once 'header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="hero-badge"><i class="bi bi-file-earmark-medical"></i> My Lab Reports</span>
            <h2 class="mt-2 mb-1">View approved lab reports</h2>
            <p class="text-muted mb-0">Access your authorized clinical results and download them as needed.</p>
        </div>
    </div>

    <div class="lab-card mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Patient name or report ID">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="Doctor Verified" <?php echo $statusFilter === 'Doctor Verified' ? 'selected' : ''; ?>>Doctor Verified</option>
                    <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved (legacy)</option>
                    <option value="Pending Review" <?php echo $statusFilter === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>

    <div class="lab-card">
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Test Name</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($reports) && $reports->num_rows > 0): while ($row = $reports->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['report_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['doctor_name'] ?? '—'); ?></td>
                            <td><span class="status-pill status-approved"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary" href="download_lab_report.php?report_id=<?php echo (int)$row['id']; ?>" target="_blank">Download PDF</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted">No reports found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
