<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['receptionist_id'])) {
    header('Location: login.php');
    exit();
}

$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patientId <= 0) {
    header('Location: ' . (isset($_SESSION['admin_id']) ? 'admin-patients.php' : 'view_patients.php'));
    exit();
}

$error = '';
$message = '';
$patient = null;

$stmt = $conn->prepare('SELECT id, patient_code, name, email, phone, date_of_birth, patient_type, department, has_login, created_at FROM patients WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$patient) {
    header('Location: ' . (isset($_SESSION['admin_id']) ? 'admin-patients.php' : 'view_patients.php'));
    exit();
}

$normalizedType = strtolower(trim((string)$patient['patient_type']));
$eligibleConversionTypes = ['walk-in', 'walkin', 'emergency'];
$isLegacyPatient = in_array($normalizedType, $eligibleConversionTypes, true);
$canConvert = $isLegacyPatient && ((int)$patient['has_login'] === 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLegacyPatient) {
        $error = 'Conversion is only available for walk-in and emergency patients.';
    } elseif (!$canConvert) {
        $error = 'This patient cannot be converted at this time.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password === '' || $confirmPassword === '') {
            $error = 'Both password fields are required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $checkStmt = $conn->prepare('SELECT id FROM patients WHERE LOWER(email) = LOWER(?) AND id <> ? LIMIT 1');
            if ($checkStmt) {
                $checkStmt->bind_param('si', $email, $patientId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                if ($checkResult && $checkResult->num_rows > 0) {
                    $error = 'This email is already registered to another patient.';
                }
                $checkStmt->close();
            } else {
                $error = 'Database error while validating email address.';
            }

            if ($error === '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare('UPDATE patients SET email = ?, password = ?, patient_type = ?, has_login = 1 WHERE id = ?');
                if ($updateStmt) {
                    $patientType = 'Online';
                    $updateStmt->bind_param('sssi', $email, $passwordHash, $patientType, $patientId);
                    if ($updateStmt->execute()) {
                        $message = 'Patient has been converted to an online account successfully.';
                        $patient['email'] = $email;
                        $patient['patient_type'] = $patientType;
                        $patient['has_login'] = 1;
                        $canConvert = false;
                    } else {
                        $error = 'Unable to convert patient: ' . $updateStmt->error;
                    }
                    $updateStmt->close();
                } else {
                    $error = 'Database error while saving the conversion.';
                }
            }
        }
    }
}

$backLink = isset($_SESSION['admin_id']) ? 'admin-patients.php' : 'view_patients.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Profile</title>
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 24px; color: #0f172a; }
    .page { max-width: 920px; margin: auto; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    h1 { margin: 0; font-size: 30px; }
    .subtitle { margin-top: 6px; color: #475569; }
    .card { background: white; border-radius: 22px; padding: 28px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); }
    .details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 28px; }
    .field { background: #f8fafc; border-radius: 16px; padding: 18px; }
    .field strong { display: block; margin-bottom: 8px; color: #334155; }
    .badge { display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px; font-size: 13px; font-weight: 700; }
    .badge-online { background: #e0f2fe; color: #0369a1; }
    .badge-walkin { background: #dcfce7; color: #166534; }
    .badge-emergency { background: #fee2e2; color: #991b1b; }
    .form-group { margin-bottom: 16px; }
    label { display: block; font-weight: 700; margin-bottom: 8px; color: #1e293b; }
    input { width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid #cbd5e1; font-size: 15px; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 14px 18px; border: none; border-radius: 16px; background: #0ea5e9; color: white; font-weight: 700; cursor: pointer; }
    .button:hover { background: #0284c7; }
    .button-secondary { background: #e2e8f0; color: #0f172a; }
    .notice { border-radius: 16px; padding: 16px 18px; margin-bottom: 18px; }
    .notice.error { background: #fee2e2; color: #991b1b; }
    .notice.success { background: #dcfce7; color: #14532d; }
    .form-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 24px; }
    .form-label { margin-bottom: 10px; display: block; }
    .link-row { margin-top: 18px; display: flex; gap: 12px; flex-wrap: wrap; }
    .link-row a { text-decoration: none; display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 14px; background: #e2e8f0; color: #0f172a; }
    @media (max-width: 720px) {
        .details { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <h1>Patient Profile</h1>
            <p class="subtitle"><?php echo htmlspecialchars($patient['name']); ?> (<?php echo htmlspecialchars($patient['patient_code']); ?>)</p>
        </div>
        <div class="link-row">
            <a href="<?php echo $backLink; ?>">Back to Patients</a>
            <a class="button button-secondary" href="logout.php">Logout</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="notice success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="details">
            <div class="field">
                <strong>Patient Code</strong>
                <?php echo htmlspecialchars($patient['patient_code'] ?? ''); ?>
            </div>
            <div class="field">
                <strong>Registered</strong>
                <?php echo htmlspecialchars($patient['created_at'] ?? ''); ?>
            </div>
            <div class="field">
                <strong>Email</strong>
                <?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?>
            </div>
            <div class="field">
                <strong>Phone</strong>
                <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
            </div>
            <div class="field">
                <strong>Date of Birth</strong>
                <?php echo htmlspecialchars($patient['date_of_birth'] ?? 'N/A'); ?>
            </div>
            <div class="field">
                <strong>Department</strong>
                <?php echo htmlspecialchars($patient['department'] ?? 'N/A'); ?>
            </div>
            <div class="field" style="grid-column: span 2;">
                <strong>Patient Status</strong>
                <?php
                    $type = $patient['patient_type'] ?? 'Online';
                    $normalizedType = strtolower(trim((string)$type));
                    $badgeClass = $normalizedType === 'emergency' ? 'badge-emergency' : ($normalizedType === 'walk-in' ? 'badge-walkin' : 'badge-online');
                    $badgeLabel = $normalizedType === 'emergency' ? 'Emergency' : ($normalizedType === 'walk-in' ? 'Walk-in' : 'Online');
                ?>
                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($badgeLabel); ?></span>
            </div>
        </div>

        <?php if ($canConvert): ?>
            <div class="form-card">
                <h2>Convert to Online Account</h2>
                <p style="margin-bottom: 18px; color: #475569;">Assign an email and password so this patient can login online.</p>
                <form method="POST" action="patient_profile.php?id=<?php echo $patientId; ?>">
                    <div class="form-group">
                        <label class="form-label" for="email">Email address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="button">Convert Patient</button>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card">
                <h2>Online Access Status</h2>
                <p style="margin-bottom: 16px; color: #475569;">This patient already has online login access assigned.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
