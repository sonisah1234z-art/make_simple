<?php
require_once 'auth.php';
require_once 'db.php';
// restrict to staff and lab technicians
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['nurse_id']) && !isset($_SESSION['pharmacist_id']) && !isset($_SESSION['receptionist_id']) && !isset($_SESSION['lab_technician_id'])) {
    header('Location: login.php');
    exit();
}
ensure_lab_tests_table($conn);

$errors = [];

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;

// load record if editing
$record = null;
if ($editing) {
    $stmt = $conn->prepare('SELECT * FROM lab_tests WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $result = $stmt->get_result(); $record = $result ? $result->fetch_assoc() : null; $stmt->close(); }
    if (!$record) { echo 'Not found'; exit(); }
    // technicians can only edit tests assigned to them
    if (isset($_SESSION['lab_technician_id']) && (!isset($record['technician_id']) || (int)$record['technician_id'] !== (int)$_SESSION['lab_technician_id'])) {
        echo 'Access denied. This test is not assigned to your account.';
        exit();
    }
}

// handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $test_type = $conn->real_escape_string($_POST['test_type'] ?? '');
    $result_text = $conn->real_escape_string($_POST['result'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Pending','Completed']) ? $_POST['status'] : 'Pending';

    // prepare errors and upload handling
    $errors = [];
    $uploadName = '';
    if (!empty($_FILES['attachment']['name'])) {
        $f = $_FILES['attachment'];

        if (!isset($f['error']) || is_array($f['error'])) {
            $errors[] = 'Invalid file upload parameters.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error code: ' . $f['error'];
        } else {
            // size check (<= 5 MB)
            $maxBytes = 5 * 1024 * 1024;
            if ($f['size'] > $maxBytes) {
                $errors[] = 'File exceeds maximum allowed size of 5 MB.';
            }

            // extension and mime validation
            $allowedExt = ['pdf','jpg','jpeg','png'];
            $origName = $f['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Invalid file extension. Allowed: PDF, JPG, JPEG, PNG.';
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['application/pdf','image/jpeg','image/png'];
            if (!in_array($mime, $allowedMimes, true)) {
                $errors[] = 'Invalid file type detected.';
            }

            // final safety: ensure uploaded file and move
            if (empty($errors)) {
                if (!is_uploaded_file($f['tmp_name'])) {
                    $errors[] = 'Uploaded file not found.';
                } else {
                    $uploadName = bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $uploadName;
                    if (!move_uploaded_file($f['tmp_name'], $dest)) {
                        $errors[] = 'Failed to move uploaded file.';
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        if ($editing) {
        // allow admin to assign technician
        $technicianId = null;
        if (isset($_POST['technician_id']) && is_numeric($_POST['technician_id'])) {
            $technicianId = (int)$_POST['technician_id'];
        } elseif (isset($_SESSION['lab_technician_id'])) {
            $technicianId = (int)$_SESSION['lab_technician_id'];
        }

        $sql = 'UPDATE lab_tests SET patient_id=?, test_type=?, result=?, status=?, updated_at=NOW()' . ($uploadName ? ', file=?' : '') . ', technician_id=? WHERE id=? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($uploadName) {
                $stmt->bind_param('issssii', $patient_id, $test_type, $result_text, $status, $uploadName, $technicianId, $id);
            } else {
                $stmt->bind_param('isssii', $patient_id, $test_type, $result_text, $status, $technicianId, $id);
            }
            $stmt->execute();
            $stmt->close();
        }
        } else {
        $technicianId = null;
        if (isset($_POST['technician_id']) && is_numeric($_POST['technician_id'])) {
            $technicianId = (int)$_POST['technician_id'];
        }
        $sql = 'INSERT INTO lab_tests (patient_id, test_type, result, file, status, technician_id) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $fileParam = $uploadName ?: null;
        if ($stmt) { $stmt->bind_param('issssi', $patient_id, $test_type, $result_text, $fileParam, $status, $technicianId); $stmt->execute(); $stmt->close(); }
        }

        // set success flash and redirect
        $_SESSION['flash_success'] = 'Lab report saved successfully.';
        header('Location: lab_technician.php');
        exit();
    }
}

// load patients for selection
$patients = $conn->query('SELECT id, name FROM patients ORDER BY name ASC');
// load technicians for assignment
$techResult = $conn->query('SELECT id, name FROM lab_technicians ORDER BY name ASC');

?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $editing ? 'Edit' : 'Add'; ?> Lab Report</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>.container{max-width:800px;margin:20px auto}.card{padding:18px;background:#fff;border-radius:10px}</style>
</head>
<body>
<div class="container">
    <h2><?php echo $editing ? 'Edit' : 'Add'; ?> Lab Report</h2>
    <div class="card">
        <?php if (!empty($errors)): ?>
            <div style="background:#ffe6e6;padding:10px;border-radius:6px;margin-bottom:12px;color:#a00">
                <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background:#e6ffed;padding:10px;border-radius:6px;margin-bottom:12px;color:#02733a">
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-2">
                <label>Patient</label>
                <select name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo $editing && $record['patient_id']==$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-2">
                <label>Test Type</label>
                <input type="text" name="test_type" required value="<?php echo $editing ? htmlspecialchars($record['test_type']) : ''; ?>">
            </div>
            <div class="mb-2">
                <label>Result</label>
                <textarea name="result" rows="6"><?php echo $editing ? htmlspecialchars($record['result']) : ''; ?></textarea>
            </div>
            <div class="mb-2">
                <label>Attachment (PDF/Image)</label>
                <input type="file" name="attachment" accept=".pdf,image/*">
                <?php if ($editing && !empty($record['file'])): ?>
                    <div>Current: <a href="uploads/<?php echo htmlspecialchars($record['file']); ?>" target="_blank">Download</a></div>
                <?php endif; ?>
            </div>
            <div class="mb-2">
                <label>Status</label>
                <select name="status">
                    <option value="Pending" <?php echo $editing && $record['status']=='Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Completed" <?php echo $editing && $record['status']=='Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <?php if (isset($_SESSION['admin_id'])): ?>
                <div class="mb-2">
                    <label>Assign Technician</label>
                    <select name="technician_id">
                        <option value="">-- Unassigned --</option>
                        <?php while ($t = $techResult->fetch_assoc()): ?>
                            <option value="<?php echo (int)$t['id']; ?>" <?php echo $editing && !empty($record['technician_id']) && $record['technician_id']==$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php else: ?>
                <?php if (isset($_SESSION['lab_technician_id'])): ?>
                    <input type="hidden" name="technician_id" value="<?php echo (int)$_SESSION['lab_technician_id']; ?>">
                <?php endif; ?>
            <?php endif; ?>
            <div>
                <button type="submit" class="btn">Save Report</button>
                <a class="btn" href="lab_technician.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>