<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_tests_table($conn);

$message = '';
$search = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date_filter'] ?? '');

$sql = "SELECT l.id, l.test_type, l.status, l.report_date, l.file, p.id AS patient_id, p.name AS patient_name
        FROM lab_tests l
        LEFT JOIN patients p ON p.id = l.patient_id
        WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (p.id = ? OR p.name LIKE ?)";
    $params[] = $search;
    $params[] = '%' . $search . '%';
    $types .= 'is';
}

if ($dateFilter !== '') {
    $sql .= " AND l.report_date = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$sql .= " ORDER BY l.report_date DESC, l.id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $records = [];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Patient History</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1280px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;align-items:end}
.filter-row input,.filter-row button{padding:8px 10px;border-radius:8px;border:1px solid #d8e4f1}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block;background:#0d6efd;color:#fff;border:0}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
.badge{padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}
.badge-pending{background:#fff4d6;color:#b7791f}
.badge-completed{background:#e8f8ef;color:#157347}
.badge-in-progress{background:#eaf3ff;color:#2765b0}
</style>
</head>
<body>
<div class="container">
    <h2>Patient History</h2>
    <div class="card">
        <form method="get" class="filter-row">
            <div>
                <label>Search</label><br>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Patient ID or Name">
            </div>
            <div>
                <label>Test Date</label><br>
                <input type="date" name="date_filter" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            <div>
                <button class="btn" type="submit">Filter</button>
                <a class="btn" href="patient_history.php" style="background:#6c757d">Reset</a>
            </div>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Test Name</th>
                        <th>Test Date</th>
                        <th>Status</th>
                        <th>Report</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): foreach ($records as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['patient_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['age'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['test_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                            <td>
                                <?php
                                $status = strtolower((string)($row['status'] ?? 'Pending'));
                                $badgeClass = 'badge-pending';
                                if ($status === 'completed') { $badgeClass = 'badge-completed'; }
                                elseif ($status === 'in progress' || $status === 'in-progress') { $badgeClass = 'badge-in-progress'; }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['file'])): ?>
                                <?php else: ?>
                                    <span style="color:#888">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8">No patient history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
