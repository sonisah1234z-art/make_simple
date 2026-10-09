<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['receptionist_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$message = '';
$departments = [];
$deptResult = $conn->query("SELECT name FROM departments ORDER BY name ASC");
if ($deptResult) {
    while ($deptRow = $deptResult->fetch_assoc()) {
        $departments[] = $deptRow['name'];
    }
}

$needsBed = false;
$selectedBedId = 0;

function generate_patient_code(mysqli $conn): string
{
    $result = $conn->query('SELECT MAX(id) AS max_id FROM patients');
    $nextId = 1;
    if ($result) {
        $row = $result->fetch_assoc();
        $nextId = max(1, ((int)$row['max_id']) + 1);
    }
    return 'P-' . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $needsBed = trim($_POST['needs_bed'] ?? 'no') === 'yes';
    $selectedBedId = (int)($_POST['bed_id'] ?? 0);

    if ($name === '' || $phone === '') {
        $error = 'Name and phone are required for walk-in patient registration.';
    } elseif ($date_of_birth !== '' && !DateTime::createFromFormat('Y-m-d', $date_of_birth)) {
        $error = 'Please enter a valid date of birth in YYYY-MM-DD format.';
    } elseif ($needsBed && $selectedBedId <= 0) {
        $error = 'Please select an available bed when the patient needs a bed.';
    } else {
        $patientType = 'Walk-in';
        $departmentValue = $department !== '' ? $department : null;
        $dateValue = $date_of_birth !== '' ? $date_of_birth : null;

        if ($needsBed && $selectedBedId > 0) {
            $bedCheck = $conn->prepare('SELECT id, status, patient_id FROM beds WHERE id = ? LIMIT 1');
            if ($bedCheck) {
                $bedCheck->bind_param('i', $selectedBedId);
                $bedCheck->execute();
                $bedResult = $bedCheck->get_result();
                $bedRow = $bedResult ? $bedResult->fetch_assoc() : null;
                $bedCheck->close();
                if (!$bedRow || $bedRow['patient_id'] !== null || ($bedRow['status'] ?? 'Available') !== 'Available') {
                    $error = 'The selected bed is no longer available. Please choose a different available bed.';
                }
            } else {
                $error = 'Unable to verify the selected bed availability.';
            }
        }

        if (!$error) {
            $duplicateCheck = $conn->prepare('SELECT id FROM patients WHERE LOWER(name) = LOWER(?) AND phone = ? AND patient_type = ? LIMIT 1');
            if ($duplicateCheck) {
                $duplicateCheck->bind_param('sss', $name, $phone, $patientType);
                $duplicateCheck->execute();
                $duplicateResult = $duplicateCheck->get_result();
                if ($duplicateResult && $duplicateResult->num_rows > 0) {
                    $error = 'A walk-in patient with the same name and phone already exists.';
                }
                $duplicateCheck->close();
            } else {
                $error = 'Database error checking for duplicate patient records.';
            }
        }

        if (!$error) {
            $stmt = $conn->prepare('INSERT INTO patients (name, phone, date_of_birth, patient_type, department) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sssss', $name, $phone, $dateValue, $patientType, $departmentValue);
                if ($stmt->execute()) {
                    $newPatientId = $conn->insert_id;
                    $patientCode = 'P-' . str_pad((string)$newPatientId, 4, '0', STR_PAD_LEFT);
                    $codeStmt = $conn->prepare('UPDATE patients SET patient_code = ? WHERE id = ?');
                    if ($codeStmt) {
                        $codeStmt->bind_param('si', $patientCode, $newPatientId);
                        $codeStmt->execute();
                        $codeStmt->close();
                    }

                    if ($needsBed && $selectedBedId > 0) {
                        $assignStmt = $conn->prepare('UPDATE beds SET status = ?, patient_id = ? WHERE id = ? AND patient_id IS NULL AND status = ?');
                        if ($assignStmt) {
                            $occupied = 'Occupied';
                            $available = 'Available';
                            $assignStmt->bind_param('siss', $occupied, $newPatientId, $selectedBedId, $available);
                            if ($assignStmt->execute()) {
                                if ($assignStmt->affected_rows === 0) {
                                    $error = 'Unable to assign the selected bed. It may have been taken by another registration.';
                                }
                            } else {
                                $error = 'Unable to assign bed: ' . $assignStmt->error;
                            }
                            $assignStmt->close();
                        } else {
                            $error = 'Unable to prepare bed assignment query.';
                        }
                    }

                    if (!$error) {
                        $message = 'Walk-in patient registered successfully. Patient ID: ' . htmlspecialchars($patientCode);
                    }
                } else {
                    $error = 'Unable to register walk-in patient: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Database error preparing walk-in patient registration.';
            }
        }
    }
}

$bedList = $conn->query("SELECT b.*, p.name AS current_patient_name FROM beds b LEFT JOIN patients p ON p.id = b.patient_id ORDER BY b.ward ASC, b.bed_number ASC");
$availableBedCount = 0;
if ($bedList) {
    while ($bedRow = $bedList->fetch_assoc()) {
        if (($bedRow['patient_id'] !== null && $bedRow['patient_id'] !== '') || ($bedRow['status'] ?? 'Available') !== 'Available') {
            continue;
        }
        $availableBedCount++;
    }
    $bedList->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Walk-in Patient</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 24px; }
    .container { max-width: 520px; margin: auto; background: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); }
    h1 { margin-bottom: 8px; }
    .subtitle { margin-bottom: 20px; color: #64748b; }
    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; font-weight: 600; }
    input, select { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #cbd5e1; }
    .button { padding: 14px 18px; border: none; border-radius: 14px; background: #2563eb; color: white; cursor: pointer; width: 100%; font-weight: 700; }
    .button:hover { background: #1d4ed8; }
    .notice { padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; }
    .error { background: #fee2e2; color: #991b1b; }
    .success { background: #dcfce7; color: #166534; }
    .actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
    .actions a { display: inline-block; padding: 10px 16px; border-radius: 12px; background: #e2e8f0; color: #0f172a; text-decoration: none; }
    .radio-group { display: flex; gap: 16px; flex-wrap: wrap; }
    .radio-inline { display: flex; align-items: center; gap: 8px; font-weight: 500; }
    .bed-selection-panel { margin-bottom: 20px; }
    .table-responsive { overflow-x: auto; }
    .bed-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .bed-table th, .bed-table td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; vertical-align: middle; }
    .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 0.9rem; font-weight: 600; }
    .status-available { background: #d1fae5; color: #065f46; }
    .status-occupied { background: #fee2e2; color: #991b1b; }
    .status-reserved { background: #fef3c7; color: #92400e; }
    .status-maintenance { background: #e5e7eb; color: #374151; }
    .bed-radio-label input[type="radio"] { width: 18px; height: 18px; }
    .disabled-row { opacity: 0.6; }
</style>
</head>
<body>
<div class="container">
    <h1>Register Walk-in Patient</h1>
    <p class="subtitle">This form registers a patient without email or password for walk-in care.</p>
    <?php if ($error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($message): ?>
        <div class="notice success"><?php echo $message; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input id="name" type="text" name="name" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input id="phone" type="text" name="phone" required>
        </div>
        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input id="date_of_birth" type="date" name="date_of_birth">
        </div>
            <div class="form-group">
            <label for="department">Department</label>
            <input id="department" list="departments" type="text" name="department" placeholder="Select or type department name" value="<?php echo htmlspecialchars($department ?? ''); ?>">
            <datalist id="departments">
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-group">
            <label>Needs Bed?</label>
            <div class="radio-group">
                <label class="radio-inline"><input type="radio" name="needs_bed" value="no" <?php echo !$needsBed ? 'checked' : ''; ?>> No</label>
                <label class="radio-inline"><input type="radio" name="needs_bed" value="yes" <?php echo $needsBed ? 'checked' : ''; ?>> Yes, patient needs a bed</label>
            </div>
        </div>
        <div id="bed-selection-panel" class="bed-selection-panel" style="display: <?php echo $needsBed ? 'block' : 'none'; ?>;">
            <h2 style="margin-bottom:12px; font-size:1rem;">Select Bed for Patient</h2>
            <?php if ($availableBedCount === 0): ?>
                <div class="notice error">No available beds.</div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="bed-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Bed Number</th>
                            <th>Ward</th>
                            <th>Room Number</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Current Patient</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bedList && $bedList->num_rows): ?>
                            <?php while ($bed = $bedList->fetch_assoc()): ?>
                                <?php
                                    $status = $bed['status'] ?? 'Available';
                                    $isSelectable = $status === 'Available' && ($bed['patient_id'] === null || $bed['patient_id'] === '');
                                    $statusClass = 'status-maintenance';
                                    if ($status === 'Available') {
                                        $statusClass = 'status-available';
                                    } elseif ($status === 'Occupied') {
                                        $statusClass = 'status-occupied';
                                    } elseif ($status === 'Reserved') {
                                        $statusClass = 'status-reserved';
                                    }
                                    $roomNumber = $bed['room_number'] ?? $bed['bed_number'];
                                    $departmentLabel = $bed['department'] ?? $bed['ward'] ?? 'N/A';
                                ?>
                                <tr<?php echo $isSelectable ? '' : ' class="disabled-row"'; ?>>
                                    <td>
                                        <label class="bed-radio-label">
                                            <input type="radio" name="bed_id" value="<?php echo (int)$bed['id']; ?>" <?php echo $isSelectable ? '' : 'disabled'; ?> <?php echo $selectedBedId === (int)$bed['id'] ? 'checked' : ''; ?>>
                                        </label>
                                    </td>
                                    <td><?php echo htmlspecialchars($bed['bed_number']); ?></td>
                                    <td><?php echo htmlspecialchars($bed['ward']); ?></td>
                                    <td><?php echo htmlspecialchars($roomNumber); ?></td>
                                    <td><?php echo htmlspecialchars($departmentLabel); ?></td>
                                    <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td><?php echo htmlspecialchars($bed['current_patient_name'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-muted">No beds have been configured yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <button class="button" type="submit">Register Walk-in Patient</button>
    </form>
    <div class="actions">
        <a href="view_patients.php">Back to Patients</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<script>
    document.querySelectorAll('input[name="needs_bed"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('bed-selection-panel').style.display = this.value === 'yes' ? 'block' : 'none';
        });
    });
</script>
</body>
</html>
