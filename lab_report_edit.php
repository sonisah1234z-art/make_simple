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
<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #f4f8ff 0%, #eef4ff 100%);
    color: #1e293b;
}
.page-shell {
    min-height: 100vh;
    padding: 24px 16px;
}
.container {
    max-width: 900px;
    margin: 0 auto;
}
.hero-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
    margin-bottom: 20px;
}
.hero-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 28px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: #fff;
}
.hero-card h2 {
    margin: 0 0 4px;
    font-size: 24px;
}
.hero-card p {
    margin: 0;
    color: #64748b;
}
.card {
    padding: 24px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
    border: 1px solid #e2e8f0;
}
.report-form {
    display: grid;
    gap: 16px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.field.full {
    grid-column: 1 / -1;
}
label {
    font-weight: 600;
    color: #334155;
}
input, select, textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    font-size: 14px;
    background: #fff;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
}
textarea {
    min-height: 120px;
    resize: vertical;
}
input[type="file"] {
    border: none;
    padding: 0;
    background: transparent;
}
.upload-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 16px;
    border: 1px dashed #93c5fd;
    background: linear-gradient(90deg, #f8fbff, #eff6ff);
    border-radius: 16px;
}
.upload-content {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.upload-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    padding: 10px 14px;
    border-radius: 999px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.upload-help {
    color: #64748b;
    font-size: 13px;
}
.current-file {
    color: #334155;
    font-size: 14px;
}
.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 16px;
    border: none;
    border-radius: 999px;
    text-decoration: none;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.btn.secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #dbe4ef;
}
.alert-success, .alert-error {
    padding: 12px 14px;
    border-radius: 12px;
    margin-bottom: 14px;
}
.alert-error {
    background: #ffe7e7;
    color: #b42318;
}
.alert-success {
    background: #e7f8ef;
    color: #027a48;
}
@media (max-width: 720px) {
    .form-grid { grid-template-columns: 1fr; }
    .hero-card { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="page-shell">
    <div class="container">
        <div class="hero-card">
            <div class="hero-icon">🧪</div>
            <div>
                <h2><?php echo $editing ? 'Edit' : 'Upload'; ?> Lab Report</h2>
                <p>Record test results and attach a PDF or image report in a polished, easy-to-use form.</p>
            </div>
        </div>
        <div class="card">
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert-success">
                    <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="report-form">
                <div class="form-grid">
                    <div class="field">
                        <label for="patient_id">Patient</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php while ($p = $patients->fetch_assoc()): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo $editing && $record['patient_id']==$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Pending" <?php echo $editing && $record['status']=='Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Completed" <?php echo $editing && $record['status']=='Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                </div>
                <div class="field full">
                    <label for="test_type">Test Type</label>
                    <input id="test_type" type="text" name="test_type" required value="<?php echo $editing ? htmlspecialchars($record['test_type']) : ''; ?>">
                </div>
                <div class="field full">
                    <label for="result">Result</label>
                    <textarea id="result" name="result" rows="6"><?php echo $editing ? htmlspecialchars($record['result']) : ''; ?></textarea>
                </div>
                <div class="upload-box">
                    <div class="upload-content">
                        <label class="upload-label" for="attachment">Choose File</label>
                        <span class="upload-help">PDF, JPG, JPEG, or PNG up to 5 MB</span>
                        <input id="attachment" type="file" name="attachment" accept=".pdf,image/*">
                    </div>
                    <?php if ($editing && !empty($record['file'])): ?>
                        <div class="current-file">Current attachment: <a href="uploads/<?php echo htmlspecialchars($record['file']); ?>" target="_blank">Download</a></div>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <div class="field full">
                        <label for="technician_id">Assign Technician</label>
                        <select id="technician_id" name="technician_id">
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
                <div class="actions">
                    <button type="submit" class="btn">Save Report</button>
                    <a class="btn secondary" href="lab_technician.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>