<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';

$message = '';
$error = '';
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $testName = trim($_POST['test_name'] ?? '');
    $sampleType = trim($_POST['sample_type'] ?? '');
    $sampleId = trim($_POST['sample_id'] ?? '');
    $collectionDateTime = trim($_POST['collection_datetime'] ?? '');
    $collectedBy = trim($_POST['collected_by'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $action = $_POST['action'] ?? 'add';

    if ($patientId <= 0 || $testName === '' || $sampleType === '' || $sampleId === '' || $collectionDateTime === '' || $collectedBy === '') {
        $error = 'All required fields are mandatory.';
    } else {
        if ($action === 'edit' && $editId > 0) {
            $stmt = $conn->prepare('UPDATE lab_sample_collections SET patient_id=?, test_name=?, sample_type=?, sample_id=?, collection_datetime=?, collected_by=?, notes=?, updated_at=NOW() WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('issssssi', $patientId, $testName, $sampleType, $sampleId, $collectionDateTime, $collectedBy, $notes, $editId);
                $stmt->execute();
                $stmt->close();
                $message = 'Sample collection updated successfully.';
            } else {
                $error = 'Unable to update sample collection.';
            }
        } else {
            $stmt = $conn->prepare('INSERT INTO lab_sample_collections (patient_id, test_name, sample_type, sample_id, collection_datetime, collected_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('issssss', $patientId, $testName, $sampleType, $sampleId, $collectionDateTime, $collectedBy, $notes);
                $stmt->execute();
                $stmt->close();
                $message = 'Sample collection added successfully.';
            } else {
                $error = 'Unable to add sample collection.';
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM lab_sample_collections WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $delId);
        $stmt->execute();
        $stmt->close();
        $message = 'Sample collection deleted.';
    }
}

$editRecord = null;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM lab_sample_collections WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editRecord = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$patients = [];
$patientResult = $conn->query('SELECT id, name FROM patients ORDER BY name ASC');
if ($patientResult) { while ($row = $patientResult->fetch_assoc()) { $patients[] = $row; } }

$records = [];
$recordResult = $conn->query('SELECT sc.*, p.name AS patient_name FROM lab_sample_collections sc LEFT JOIN patients p ON p.id = sc.patient_id ORDER BY sc.collection_datetime DESC, sc.id DESC');
if ($recordResult) { while ($row = $recordResult->fetch_assoc()) { $records[] = $row; } }
?>
<!DOCTYPE html>
<html>
<head>
<title>Sample Collection</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1280px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;margin-top:8px;font-weight:600}
input,select,textarea,button{width:100%;padding:8px 10px;border-radius:8px;border:1px solid #d8e4f1;margin-top:6px}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block;background:#0d6efd;color:#fff;border:0;cursor:pointer}
.btn-secondary{background:#6c757d}
.alert{padding:10px;border-radius:8px;margin-bottom:12px}
.alert-success{background:#e8f8ef;color:#157347}
.alert-danger{background:#ffe6e6;color:#a00}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
@media (max-width: 900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <h2>Sample Collection</h2>
    <div class="card">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
            <div>
                <label>Patient</label>
                <select name="patient_id" required>
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo (int)$patient['id']; ?>" <?php echo ($editRecord && (int)$editRecord['patient_id'] === (int)$patient['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($patient['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Test Name</label>
                <input type="text" name="test_name" value="<?php echo htmlspecialchars($editRecord['test_name'] ?? ''); ?>" required>
                <label>Sample Type</label>
                <input type="text" name="sample_type" value="<?php echo htmlspecialchars($editRecord['sample_type'] ?? ''); ?>" required>
                <label>Sample ID</label>
                <input type="text" name="sample_id" value="<?php echo htmlspecialchars($editRecord['sample_id'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Collection Date & Time</label>
                <input type="datetime-local" name="collection_datetime" value="<?php echo htmlspecialchars($editRecord['collection_datetime'] ?? ''); ?>" required>
                <label>Collected By</label>
                <input type="text" name="collected_by" value="<?php echo htmlspecialchars($editRecord['collected_by'] ?? ''); ?>" required>
                <label>Notes</label>
                <textarea name="notes" rows="4"><?php echo htmlspecialchars($editRecord['notes'] ?? ''); ?></textarea>
                <div style="margin-top:10px;display:flex;gap:8px">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn btn-secondary" href="sample_collection.php">Cancel</a>
                </div>
            </div>
        </form>
        <div class="table-wrap" style="margin-top:20px">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Sample Type</th>
                        <th>Sample ID</th>
                        <th>Collection Date</th>
                        <th>Collected By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): foreach ($records as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patient_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['sample_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['sample_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['collection_datetime']); ?></td>
                            <td><?php echo htmlspecialchars($row['collected_by']); ?></td>
                            <td>
                                <a class="btn" href="sample_collection.php?edit=<?php echo (int)$row['id']; ?>">Edit</a>
                                <a class="btn btn-secondary" href="sample_collection.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this sample collection?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7">No sample collections found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
