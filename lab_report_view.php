<?php
require_once 'auth.php';
require_once 'db.php';
// allow staff, doctors, lab technicians, and patient to view
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['nurse_id']) && !isset($_SESSION['pharmacist_id']) && !isset($_SESSION['receptionist_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['lab_technician_id']) && !isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}
ensure_lab_tests_table($conn);

$id = (int)($_GET['id'] ?? 0);
$reportKey = trim((string)($_GET['report_id'] ?? ''));
$modernReportId = ctype_digit($reportKey) ? (int)$reportKey : 0;
$requestId = (int)($_GET['request_id'] ?? 0);
$row = null;
$parameterRows = [];
if ($modernReportId > 0 || $reportKey !== '' || $requestId > 0) {
    ensure_lab_module_tables($conn);
    if ($modernReportId > 0) {
        $stmt = $conn->prepare('SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE lr.id = ? LIMIT 1');
    } elseif ($requestId > 0) {
        $stmt = $conn->prepare('SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE lr.request_id = ? ORDER BY lr.id DESC LIMIT 1');
    } else {
        $stmt = $conn->prepare('SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE lr.report_id = ? LIMIT 1');
    }
    if ($stmt) {
        if ($modernReportId > 0) {
            $stmt->bind_param('i', $modernReportId);
        } elseif ($requestId > 0) {
            $stmt->bind_param('i', $requestId);
        } else {
            $stmt->bind_param('s', $reportKey);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($row && isset($_SESSION['doctor_id']) && (int)$row['doctor_id'] !== (int)$_SESSION['doctor_id']) { exit('Access denied.'); }
    if ($row && isset($_SESSION['lab_technician_id']) && (int)$row['technician_id'] !== (int)$_SESSION['lab_technician_id']) { exit('Access denied.'); }
    if ($row && isset($_SESSION['patient_id']) && ((int)$row['patient_id'] !== (int)$_SESSION['patient_id'] || !in_array($row['status'], ['Doctor Verified', 'Approved'], true))) { exit('This report is not available until doctor verification.'); }
    if ($row) {
        $row['test_type'] = $row['test_name'];
        $resultData = json_decode((string)$row['parameters_json'], true);
        $row['result'] = is_array($resultData) && isset($resultData['summary']) ? $resultData['summary'] : (string)$row['parameters_json'];
        $parameterRows = is_array($resultData) && isset($resultData['parameters']) && is_array($resultData['parameters']) ? $resultData['parameters'] : [];
    }
} else {
    // Legacy lab_tests report view.
    $stmt = $conn->prepare('SELECT l.*, p.name as patient_name, lt.name as technician_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id LEFT JOIN lab_technicians lt ON lt.id = l.technician_id WHERE l.id = ? LIMIT 1');
}
if ($stmt) {
    if ($modernReportId === 0 && $reportKey === '' && $requestId === 0) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}
if (!$row && $id > 0 && $modernReportId === 0 && $reportKey === '' && $requestId === 0) {
    ensure_lab_module_tables($conn);
    $fallbackStmt = $conn->prepare('SELECT lr.*, p.name AS patient_name, d.name AS doctor_name, lt.name AS technician_name FROM lab_reports lr LEFT JOIN patients p ON p.id = lr.patient_id LEFT JOIN doctors d ON d.id = lr.doctor_id LEFT JOIN lab_technicians lt ON lt.id = lr.technician_id WHERE lr.id = ? LIMIT 1');
    if ($fallbackStmt) {
        $fallbackStmt->bind_param('i', $id);
        $fallbackStmt->execute();
        $row = $fallbackStmt->get_result()->fetch_assoc();
        $fallbackStmt->close();
    }
    if ($row && isset($_SESSION['lab_technician_id']) && (int)$row['technician_id'] !== (int)$_SESSION['lab_technician_id']) {
        exit('Access denied.');
    }
    if ($row) {
        $row['test_type'] = $row['test_name'];
        $resultData = json_decode((string)$row['parameters_json'], true);
        $row['result'] = is_array($resultData) && isset($resultData['summary']) ? $resultData['summary'] : (string)$row['parameters_json'];
        $parameterRows = is_array($resultData) && isset($resultData['parameters']) && is_array($resultData['parameters']) ? $resultData['parameters'] : [];
    }
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
    <div style="background:#0f4c81;color:#fff;padding:24px;border-radius:10px 10px 0 0">
        <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.8">Hospital Laboratory</div>
        <h2 style="margin:6px 0 0">Laboratory Report</h2>
        <div style="margin-top:8px">Report #<?php echo htmlspecialchars($row['report_id'] ?? (string)$row['id']); ?></div>
    </div>
    <div class="card">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;background:#f8fafc;padding:14px;border-radius:8px">
            <div><strong>Patient:</strong> <?php echo htmlspecialchars($row['patient_name'] ?? ''); ?></div>
            <div><strong>Patient ID:</strong> <?php echo (int)($row['patient_id'] ?? 0); ?></div>
            <div><strong>Doctor:</strong> <?php echo htmlspecialchars($row['doctor_name'] ?? 'Not assigned'); ?></div>
            <div><strong>Department:</strong> <?php echo htmlspecialchars($row['department'] ?? ''); ?></div>
            <div><strong>Test:</strong> <?php echo htmlspecialchars($row['test_type']); ?></div>
            <div><strong>Sample Type:</strong> <?php echo htmlspecialchars($row['sample_type'] ?? 'Not recorded'); ?></div>
            <div><strong>Collection Date:</strong> <?php echo htmlspecialchars($row['collection_date'] ?? 'Not recorded'); ?></div>
            <div><strong>Technician:</strong> <?php echo htmlspecialchars($row['technician_name'] ?? ''); ?></div>
            <div><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></div>
            <div><strong>Reported:</strong> <?php echo htmlspecialchars($row['updated_at'] ?: $row['created_at']); ?></div>
        </div>
        <?php if (!empty($parameterRows)): ?>
            <h3 style="margin-top:22px">Results</h3>
            <div style="overflow-x:auto"><table width="100%" border="1" cellpadding="8" style="border-collapse:collapse">
                <thead><tr><th>Test Parameter</th><th>Result</th><th>Unit</th><th>Reference Range</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($parameterRows as $parameter): ?><tr>
                    <td><?php echo htmlspecialchars($parameter['parameter'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($parameter['result'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($parameter['unit'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($parameter['reference_range'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($parameter['status'] ?? 'Normal'); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php else: ?>
            <p><strong>Result:</strong></p>
            <div style="white-space:pre-wrap;background:#f8fafc;padding:12px;border-radius:8px"><?php echo htmlspecialchars($row['result']); ?></div>
        <?php endif; ?>
        <?php if (!empty($row['remarks'])): ?><p style="margin-top:18px"><strong>Technician Remarks:</strong> <?php echo htmlspecialchars($row['remarks']); ?></p><?php endif; ?>
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