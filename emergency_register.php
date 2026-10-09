<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['receptionist_id'])) {
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
$emergencyDepartment = '';
foreach ($departments as $dept) {
    if (stripos($dept, 'emergency') !== false) {
        $emergencyDepartment = $dept;
        break;
    }
}
if ($emergencyDepartment === '' && count($departments) > 0) {
    $emergencyDepartment = $departments[0];
}

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
    $ailment = trim($_POST['ailment'] ?? '');

    if ($name === '' || $phone === '' || $ailment === '') {
        $error = 'Name, phone, and ailment description are required for emergency patient registration.';
    } else {
        $patientType = 'Emergency';
        $department = 'Emergency Department';

        $duplicateCheck = $conn->prepare('SELECT id FROM patients WHERE LOWER(name) = LOWER(?) AND phone = ? AND patient_type = ? LIMIT 1');
        if ($duplicateCheck) {
            $duplicateCheck->bind_param('sss', $name, $phone, $patientType);
            $duplicateCheck->execute();
            $duplicateResult = $duplicateCheck->get_result();
            if ($duplicateResult && $duplicateResult->num_rows > 0) {
                $error = 'An emergency patient with the same name and phone already exists.';
                $duplicateCheck->close();
            } else {
                $duplicateCheck->close();
                $stmt = $conn->prepare('INSERT INTO patients (name, phone, patient_type, department) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssss', $name, $phone, $patientType, $department);
                    if ($stmt->execute()) {
                        $newPatientId = $conn->insert_id;
                        $patientCode = 'P-' . str_pad((string)$newPatientId, 4, '0', STR_PAD_LEFT);
                        $codeStmt = $conn->prepare('UPDATE patients SET patient_code = ? WHERE id = ?');
                        if ($codeStmt) {
                            $codeStmt->bind_param('si', $patientCode, $newPatientId);
                            $codeStmt->execute();
                            $codeStmt->close();
                        }
                        $message = 'Emergency patient registered successfully. Patient ID: ' . htmlspecialchars($patientCode);
                    } else {
                        $error = 'Unable to register emergency patient: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error preparing emergency patient registration.';
                }
            }
        } else {
            $error = 'Database error checking for duplicate patient records.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Emergency Patient</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 24px; }
    .container { max-width: 520px; margin: auto; background: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); }
    h1 { margin-bottom: 8px; }
    .subtitle { margin-bottom: 20px; color: #64748b; }
    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; font-weight: 600; }
    input, textarea { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #cbd5e1; }
    textarea { min-height: 120px; resize: vertical; }
    .button { padding: 14px 18px; border: none; border-radius: 14px; background: #dc2626; color: white; cursor: pointer; width: 100%; font-weight: 700; }
    .button:hover { background: #b91c1c; }
    .notice { padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; }
    .error { background: #fee2e2; color: #991b1b; }
    .success { background: #dcfce7; color: #166534; }
    .actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
    .actions a { display: inline-block; padding: 10px 16px; border-radius: 12px; background: #e2e8f0; color: #0f172a; text-decoration: none; }
</style>
</head>
<body>
<div class="container">
    <h1>Register Emergency Patient</h1>
    <p class="subtitle">This form registers a patient for emergency care and assigns them to Emergency Department.</p>
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
            <label for="ailment">Ailment / Emergency Reason</label>
            <textarea id="ailment" name="ailment" required></textarea>
        </div>
        <div class="form-group">
            <label for="department">Department</label>
            <input id="department" type="text" name="department" value="<?php echo htmlspecialchars($emergencyDepartment); ?>" readonly>
            <datalist id="departments">
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($emergencyDepartment); ?>">
            <p style="margin-top: 8px; color: #475569; font-size: 14px;">Emergency patients are automatically assigned to the emergency department.</p>
        </div>
        <button class="button" type="submit">Register Emergency Patient</button>
    </form>
    <div class="actions">
        <a href="view_patients.php">Back to Patients</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
</body>
</html>
