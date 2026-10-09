<?php
require_once 'auth.php';
require_nurse();
require_once 'db.php';

ensure_nurse_care_tables($conn);

$currentNurseId = (int)($_SESSION['nurse_id'] ?? 0);
$currentNurse = null;
if ($currentNurseId > 0) {
    $currentNurseResult = $conn->query("SELECT id, nurse_name, email, username, phone, department, address, profile_photo FROM nurses WHERE id = $currentNurseId LIMIT 1");
    if ($currentNurseResult && $currentNurseResult->num_rows > 0) {
        $currentNurse = $currentNurseResult->fetch_assoc();
    }
}

$feature = isset($feature) ? $feature : ($_GET['feature'] ?? 'assigned_patients');
$patientsResult = $conn->query("SELECT id, name FROM patients ORDER BY name ASC");
$patients = $patientsResult ? $patientsResult->fetch_all(MYSQLI_ASSOC) : [];

$profileSuccessMessage = '';
$profileErrorMessage = '';
$profileMode = isset($_GET['mode']) && $_GET['mode'] === 'edit' ? 'edit' : 'view';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'profile') {
    $profileMode = 'edit';
    $requestedNurseId = isset($_POST['nurse_id']) ? (int) $_POST['nurse_id'] : 0;
    if ($requestedNurseId !== $currentNurseId) {
        $profileErrorMessage = 'You can only edit your own nurse profile.';
    } else {
        $name = trim((string)($_POST['nurse_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '' || $email === '') {
            $profileErrorMessage = 'Name and email are required.';
        } else {
            $duplicateStmt = $conn->prepare('SELECT id FROM nurses WHERE email = ? AND id != ? LIMIT 1');
            if ($duplicateStmt) {
                $duplicateStmt->bind_param('si', $email, $currentNurseId);
                $duplicateStmt->execute();
                $duplicateResult = $duplicateStmt->get_result();
                if ($duplicateResult && $duplicateResult->num_rows > 0) {
                    $profileErrorMessage = 'This email address is already used by another nurse.';
                }
                $duplicateStmt->close();
            }

            if ($profileErrorMessage === '') {
                $photoPath = $currentNurse['profile_photo'] ?? '';
                if (isset($_FILES['profile_photo']) && is_array($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                        $profileErrorMessage = 'The profile photo upload failed. Please try a different file.';
                    } else {
                        $fileName = $_FILES['profile_photo']['name'];
                        $fileTmp = $_FILES['profile_photo']['tmp_name'];
                        $fileSize = (int)$_FILES['profile_photo']['size'];
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                        if (!in_array($fileExt, $allowedExtensions, true)) {
                            $profileErrorMessage = 'Profile photo must be JPG, JPEG, PNG, or WEBP.';
                        } elseif ($fileSize > 2 * 1024 * 1024) {
                            $profileErrorMessage = 'Profile photo must be 2MB or smaller.';
                        } else {
                            $uploadDir = __DIR__ . '/uploads/nurses';
                            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                                $profileErrorMessage = 'Profile photo directory could not be created.';
                            } else {
                                $safeName = 'nurse_' . $currentNurseId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                                $targetPath = $uploadDir . '/' . $safeName;
                                if (!move_uploaded_file($fileTmp, $targetPath)) {
                                    $profileErrorMessage = 'Unable to save the new profile photo.';
                                } else {
                                    $photoPath = 'uploads/nurses/' . $safeName;
                                }
                            }
                        }
                    }
                }
            }

            if ($profileErrorMessage === '') {
                $stmt = $conn->prepare('UPDATE nurses SET nurse_name = ?, email = ?, phone = ?, address = ?, profile_photo = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssssi', $name, $email, $phone, $address, $photoPath, $currentNurseId);
                    $success = $stmt->execute();
                    $stmt->close();

                    if ($success) {
                        $_SESSION['nurse_name'] = $name;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['username'] = $name;
                        $currentNurse['nurse_name'] = $name;
                        $currentNurse['email'] = $email;
                        $currentNurse['phone'] = $phone;
                        $currentNurse['address'] = $address;
                        $currentNurse['profile_photo'] = $photoPath;
                        $profileSuccessMessage = 'Profile saved successfully.';
                    } else {
                        $profileErrorMessage = 'Profile update failed. Please try again.';
                    }
                } else {
                    $profileErrorMessage = 'The profile update could not be prepared.';
                }
            }
        }
    }
}

function nurse_render_stat_card(string $label, string $value, string $color = '#2c3e50'): void
{
    echo '<div style="background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 10px rgba(0,0,0,0.06);min-width:180px;flex:1;">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.12em;color:#64748b;">' . htmlspecialchars($label) . '</div>
            <div style="margin-top:10px;font-size:28px;font-weight:bold;color:' . htmlspecialchars($color) . ';">' . htmlspecialchars($value) . '</div>
          </div>';
}

function nurse_render_feature_table(array $rows, array $columns): void
{
    if (empty($rows)) {
        echo '<div style="padding:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#475569;">No records found for this feature yet.</div>';
        return;
    }

    echo '<div style="overflow-x:auto; background:#fff; border-radius:12px; box-shadow:0 3px 10px rgba(15,23,42,0.06);"><table style="width:100%; border-collapse:collapse; min-width:760px;">';
    echo '<thead><tr>';
    foreach ($columns as $column) {
        echo '<th style="padding:12px 10px; background:#1f2937; color:#fff; text-align:left; font-size:13px;">' . htmlspecialchars($column) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr style="border-bottom:1px solid #e5e7eb;">';
        foreach ($columns as $key => $label) {
            $value = $row[$key] ?? '';
            echo '<td style="padding:12px 10px; vertical-align:top; color:#0f172a;">' . htmlspecialchars((string) $value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'vital_signs') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $bp = trim((string)($_POST['blood_pressure'] ?? ''));
    $pulse = trim((string)($_POST['pulse'] ?? ''));
    $temp = trim((string)($_POST['temperature'] ?? ''));
    $resp = trim((string)($_POST['respiration'] ?? ''));
    $spo2 = trim((string)($_POST['oxygen_saturation'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($patientId > 0 && $bp !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_vital_signs (nurse_id, patient_id, blood_pressure, pulse, temperature, respiration, oxygen_saturation, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iissssss', $currentNurseId, $patientId, $bp, $pulse, $temp, $resp, $spo2, $notes);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'medication_admin') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $name = trim((string)($_POST['medication_name'] ?? ''));
    $dosage = trim((string)($_POST['dosage'] ?? ''));
    $route = trim((string)($_POST['route'] ?? ''));
    $schedule = trim((string)($_POST['schedule_time'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($patientId > 0 && $name !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_medication_admin (nurse_id, patient_id, medication_name, dosage, route, schedule_time, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iisssss', $currentNurseId, $patientId, $name, $dosage, $route, $schedule, $notes);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'nursing_notes') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $type = trim((string)($_POST['note_type'] ?? 'General'));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($patientId > 0 && $notes !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_nursing_notes (nurse_id, patient_id, note_type, notes) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iiss', $currentNurseId, $patientId, $type, $notes);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'tasks') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $title = trim((string)($_POST['task_title'] ?? ''));
    $details = trim((string)($_POST['task_details'] ?? ''));
    $due = trim((string)($_POST['due_datetime'] ?? ''));

    if ($patientId > 0 && $title !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_tasks (nurse_id, patient_id, task_title, task_details, due_datetime) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iisss', $currentNurseId, $patientId, $title, $details, $due);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'medication_reminders') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $medication = trim((string)($_POST['medication_name'] ?? ''));
    $time = trim((string)($_POST['reminder_time'] ?? ''));
    $frequency = trim((string)($_POST['frequency'] ?? 'Daily'));

    if ($patientId > 0 && $medication !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_medication_reminders (nurse_id, patient_id, medication_name, reminder_time, frequency) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iisss', $currentNurseId, $patientId, $medication, $time, $frequency);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'notifications') {
    $message = trim((string)($_POST['message'] ?? ''));
    $priority = trim((string)($_POST['priority'] ?? 'Normal'));

    if ($message !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_notifications (nurse_id, message, priority) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iss', $currentNurseId, $message, $priority);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feature === 'daily_reports') {
    $summary = trim((string)($_POST['summary'] ?? ''));
    $tasks = trim((string)($_POST['tasks_completed'] ?? ''));
    $concerns = trim((string)($_POST['concerns'] ?? ''));

    if ($summary !== '') {
        $stmt = $conn->prepare('INSERT INTO nurse_daily_reports (nurse_id, report_date, summary, tasks_completed, concerns) VALUES (?, CURDATE(), ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('isss', $currentNurseId, $summary, $tasks, $concerns);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$featureDetails = [
    'assigned_patients' => ['title' => 'My Assigned Patients', 'description' => 'Current patient assignments for this nurse.'],
    'patient_details' => ['title' => 'Patient Details', 'description' => 'Patient information and care context.'],
    'vital_signs' => ['title' => 'Vital Signs Recording', 'description' => 'Latest recorded vital signs for assigned patients.'],
    'medication_admin' => ['title' => 'Medication Administration', 'description' => 'Drug administration records for patient care.'],
    'nursing_notes' => ['title' => 'Nursing Notes', 'description' => 'Observations and clinical documentation.'],
    'doctor_instructions' => ['title' => 'Doctor Instructions', 'description' => 'Orders and care directions from physicians.'],
    'iv_monitoring' => ['title' => 'IV Fluid Monitoring', 'description' => 'IV site, fluids, and infusion tracking.'],
    'treatment_records' => ['title' => 'Injection/Treatment Records', 'description' => 'Treatment administration and procedure records.'],
    'lab_test_status' => ['title' => 'Lab Test Status', 'description' => 'Laboratory test visibility and results status.'],
    'emergency_alerts' => ['title' => 'Emergency Alerts', 'description' => 'High-priority patient alerts and clinical warnings.'],
    'ward_management' => ['title' => 'Ward/Room/Bed Management', 'description' => 'Patient placement and bed allocation.'],
    'admission_transfer' => ['title' => 'Admission/Discharge/Transfer Information', 'description' => 'Patient status movement throughout the ward.'],
    'tasks' => ['title' => 'Nursing Tasks', 'description' => 'Daily tasks and completion tracking.'],
    'medication_reminders' => ['title' => 'Medication Reminders', 'description' => 'Planned medication timing reminders.'],
    'patient_monitoring' => ['title' => 'Patient Monitoring', 'description' => 'Continuous patient observation and alerts.'],
    'notifications' => ['title' => 'Notifications', 'description' => 'Nursing updates and operational notices.'],
    'profile' => ['title' => 'Nurse Profile', 'description' => 'Professional profile and contact information.'],
    'daily_reports' => ['title' => 'Daily Nursing Reports', 'description' => 'Daily summary of care activities and outcomes.'],
];

$featureInfo = $featureDetails[$feature] ?? $featureDetails['assigned_patients'];

?><!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($featureInfo['title']); ?></title>
    <link rel="stylesheet" href="assets/css/compact-ui.css">
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; color:#111827; }
        .wrap { max-width:1200px; margin:32px auto; padding:0 16px 40px; }
        .topbar { background:#1f2937; color:#fff; padding:20px 26px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .topbar a { color:#fff; text-decoration:none; background:#38bdf8; color:#082f49; padding:10px 16px; border-radius:8px; font-weight:bold; }
        .card { background:#fff; border-radius:12px; box-shadow:0 3px 12px rgba(15,23,42,0.06); padding:22px; margin-top:20px; }
        .title { font-size:32px; margin:0 0 8px; }
        .subtitle { color:#475569; margin-bottom:18px; }
        .stats { display:flex; gap:16px; flex-wrap:wrap; margin:20px 0; }
        .btn { display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:10px 15px; border-radius:8px; font-weight:bold; }
        input, select, textarea { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; margin-top:6px; margin-bottom:14px; font-size:14px; }
        label { display:block; font-weight:bold; color:#1f2937; margin-top:6px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; }
        button { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:11px 16px; cursor:pointer; font-weight:bold; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div><strong>🏥 Nurse Care Module</strong> · <?php echo htmlspecialchars($featureInfo['title']); ?></div>
            <a href="nurse_dashboard.php">Back to Dashboard</a>
        </div>

        <div class="card">
            <h1 class="title"><?php echo htmlspecialchars($featureInfo['title']); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($featureInfo['description']); ?></div>

            <?php
            $featureQuery = '';
            $featureColumns = [];
            $featureRows = [];

            switch ($feature) {
                case 'assigned_patients':
                    $featureQuery = "SELECT a.id, p.name AS patient_name, p.id AS patient_id, p.department, a.care_level, a.status
                                     FROM nurse_patient_assignments a
                                     JOIN patients p ON p.id = a.patient_id
                                     WHERE a.nurse_id = ?
                                     ORDER BY a.assigned_date DESC, a.id DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'patient_id' => 'Patient ID', 'department' => 'Department', 'care_level' => 'Care Level', 'status' => 'Status'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'patient_details':
                    $featureQuery = "SELECT p.id, p.name, p.email, p.phone, p.department, p.patient_code, COALESCE(a.care_level, 'Routine') AS care_level
                                     FROM patients p
                                     LEFT JOIN nurse_patient_assignments a ON a.patient_id = p.id AND a.nurse_id = ?
                                     WHERE EXISTS (SELECT 1 FROM nurse_patient_assignments x WHERE x.patient_id = p.id AND x.nurse_id = ?)
                                     ORDER BY p.name ASC";
                    $featureColumns = ['name' => 'Patient', 'patient_code' => 'Code', 'email' => 'Email', 'phone' => 'Phone', 'department' => 'Department', 'care_level' => 'Care Level'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('ii', $currentNurseId, $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'vital_signs':
                    $featureQuery = "SELECT v.id, p.name AS patient_name, v.blood_pressure, v.pulse, v.temperature, v.respiration, v.oxygen_saturation, v.recorded_at
                                     FROM nurse_vital_signs v
                                     JOIN patients p ON p.id = v.patient_id
                                     WHERE v.nurse_id = ?
                                     ORDER BY v.recorded_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'blood_pressure' => 'BP', 'pulse' => 'Pulse', 'temperature' => 'Temp', 'respiration' => 'Resp', 'oxygen_saturation' => 'SpO₂', 'recorded_at' => 'Time'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Record New Vital Signs</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div><label>Patient</label><select name="patient_id">';
                    foreach ($patients as $patient) {
                        echo '<option value="' . (int)$patient['id'] . '">' . htmlspecialchars($patient['name']) . '</option>';
                    }
                    echo '</select></div>
                                    <div><label>Blood Pressure</label><input type="text" name="blood_pressure" placeholder="120/80"></div>
                                    <div><label>Pulse</label><input type="text" name="pulse" placeholder="72 bpm"></div>
                                    <div><label>Temperature</label><input type="text" name="temperature" placeholder="36.8C"></div>
                                    <div><label>Respiration</label><input type="text" name="respiration" placeholder="18/min"></div>
                                    <div><label>Oxygen Saturation</label><input type="text" name="oxygen_saturation" placeholder="98%"></div>
                                    <div style="grid-column:1 / -1;"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>
                                </div>
                                <button type="submit">Save Vital Signs</button>
                            </form>
                          </div>';
                    break;

                case 'medication_admin':
                    $featureQuery = "SELECT m.id, p.name AS patient_name, m.medication_name, m.dosage, m.route, m.schedule_time, m.status, m.administered_at
                                     FROM nurse_medication_admin m
                                     JOIN patients p ON p.id = m.patient_id
                                     WHERE m.nurse_id = ?
                                     ORDER BY m.administered_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'medication_name' => 'Medication', 'dosage' => 'Dosage', 'route' => 'Route', 'schedule_time' => 'Schedule', 'status' => 'Status', 'administered_at' => 'Time'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Administer Medication</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div><label>Patient</label><select name="patient_id">';
                    foreach ($patients as $patient) {
                        echo '<option value="' . (int)$patient['id'] . '">' . htmlspecialchars($patient['name']) . '</option>';
                    }
                    echo '</select></div>
                                    <div><label>Medication Name</label><input type="text" name="medication_name"></div>
                                    <div><label>Dosage</label><input type="text" name="dosage" placeholder="500 mg"></div>
                                    <div><label>Route</label><input type="text" name="route" placeholder="Oral / IV"></div>
                                    <div><label>Schedule Time</label><input type="text" name="schedule_time" placeholder="08:00"></div>
                                    <div style="grid-column:1 / -1;"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>
                                </div>
                                <button type="submit">Save Administration</button>
                            </form>
                          </div>';
                    break;

                case 'nursing_notes':
                    $featureQuery = "SELECT n.id, p.name AS patient_name, n.note_type, n.notes, n.created_at
                                     FROM nurse_nursing_notes n
                                     JOIN patients p ON p.id = n.patient_id
                                     WHERE n.nurse_id = ?
                                     ORDER BY n.created_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'note_type' => 'Type', 'notes' => 'Notes', 'created_at' => 'Created'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Add Nursing Note</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div><label>Patient</label><select name="patient_id">';
                    foreach ($patients as $patient) {
                        echo '<option value="' . (int)$patient['id'] . '">' . htmlspecialchars($patient['name']) . '</option>';
                    }
                    echo '</select></div>
                                    <div><label>Note Type</label><input type="text" name="note_type" placeholder="General / Observation" value="General"></div>
                                    <div style="grid-column:1 / -1;"><label>Notes</label><textarea name="notes" rows="4" required></textarea></div>
                                </div>
                                <button type="submit">Save Note</button>
                            </form>
                          </div>';
                    break;

                case 'doctor_instructions':
                    $featureQuery = "SELECT d.id, p.name AS patient_name, d.instruction_title, d.instruction_details, d.priority, d.created_at
                                     FROM nurse_doctor_instructions d
                                     JOIN patients p ON p.id = d.patient_id
                                     WHERE d.nurse_id = ?
                                     ORDER BY d.created_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'instruction_title' => 'Title', 'instruction_details' => 'Details', 'priority' => 'Priority', 'created_at' => 'Date'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'iv_monitoring':
                    $featureQuery = "SELECT i.id, p.name AS patient_name, i.fluid_name, i.rate_ml_hr, i.volume_remaining, i.site_condition, i.status, i.monitored_at
                                     FROM nurse_iv_monitoring i
                                     JOIN patients p ON p.id = i.patient_id
                                     WHERE i.nurse_id = ?
                                     ORDER BY i.monitored_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'fluid_name' => 'Fluid', 'rate_ml_hr' => 'Rate', 'volume_remaining' => 'Remaining', 'site_condition' => 'Site', 'status' => 'Status', 'monitored_at' => 'Time'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'treatment_records':
                    $featureQuery = "SELECT t.id, p.name AS patient_name, t.treatment_name, t.treatment_type, t.notes, t.performed_at
                                     FROM nurse_treatment_records t
                                     JOIN patients p ON p.id = t.patient_id
                                     WHERE t.nurse_id = ?
                                     ORDER BY t.performed_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'treatment_name' => 'Treatment', 'treatment_type' => 'Type', 'notes' => 'Notes', 'performed_at' => 'Date'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'lab_test_status':
                    $featureQuery = "SELECT l.id, p.name AS patient_name, l.test_name, l.status, l.result_summary, l.updated_at
                                     FROM nurse_lab_status l
                                     JOIN patients p ON p.id = l.patient_id
                                     WHERE l.nurse_id = ?
                                     ORDER BY l.updated_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'test_name' => 'Test', 'status' => 'Status', 'result_summary' => 'Result', 'updated_at' => 'Updated'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'emergency_alerts':
                    $featureQuery = "SELECT e.id, p.name AS patient_name, e.alert_type, e.message, e.severity, e.is_active, e.created_at
                                     FROM nurse_emergency_alerts e
                                     JOIN patients p ON p.id = e.patient_id
                                     WHERE e.nurse_id = ?
                                     ORDER BY e.created_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'alert_type' => 'Type', 'message' => 'Message', 'severity' => 'Severity', 'is_active' => 'Active', 'created_at' => 'Time'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'ward_management':
                    $featureQuery = "SELECT w.id, p.name AS patient_name, w.ward_name, w.room_number, w.bed_number, w.status, w.updated_at
                                     FROM nurse_ward_management w
                                     LEFT JOIN patients p ON p.id = w.patient_id
                                     WHERE w.nurse_id = ?
                                     ORDER BY w.updated_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'ward_name' => 'Ward', 'room_number' => 'Room', 'bed_number' => 'Bed', 'status' => 'Status', 'updated_at' => 'Updated'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'admission_transfer':
                    $featureQuery = "SELECT a.id, p.name AS patient_name, a.admission_date, a.ward_name, a.room_number, a.bed_number, a.discharge_date, a.status, a.notes
                                     FROM nurse_admissions a
                                     JOIN patients p ON p.id = a.patient_id
                                     WHERE a.nurse_id = ?
                                     ORDER BY a.admission_date DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'admission_date' => 'Admission', 'ward_name' => 'Ward', 'room_number' => 'Room', 'bed_number' => 'Bed', 'discharge_date' => 'Discharge', 'status' => 'Status'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'tasks':
                    $featureQuery = "SELECT t.id, p.name AS patient_name, t.task_title, t.task_details, t.due_datetime, t.status
                                     FROM nurse_tasks t
                                     JOIN patients p ON p.id = t.patient_id
                                     WHERE t.nurse_id = ?
                                     ORDER BY t.due_datetime DESC, t.id DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'task_title' => 'Task', 'task_details' => 'Details', 'due_datetime' => 'Due', 'status' => 'Status'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Create Nursing Task</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div><label>Patient</label><select name="patient_id">';
                    foreach ($patients as $patient) {
                        echo '<option value="' . (int)$patient['id'] . '">' . htmlspecialchars($patient['name']) . '</option>';
                    }
                    echo '</select></div>
                                    <div><label>Task Title</label><input type="text" name="task_title" required></div>
                                    <div style="grid-column:1 / -1;"><label>Task Details</label><textarea name="task_details" rows="3"></textarea></div>
                                    <div style="grid-column:1 / -1;"><label>Due Date & Time</label><input type="datetime-local" name="due_datetime"></div>
                                </div>
                                <button type="submit">Add Task</button>
                            </form>
                          </div>';
                    break;

                case 'medication_reminders':
                    $featureQuery = "SELECT r.id, p.name AS patient_name, r.medication_name, r.reminder_time, r.frequency, r.status
                                     FROM nurse_medication_reminders r
                                     JOIN patients p ON p.id = r.patient_id
                                     WHERE r.nurse_id = ?
                                     ORDER BY r.created_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'medication_name' => 'Medication', 'reminder_time' => 'Time', 'frequency' => 'Frequency', 'status' => 'Status'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Add Reminder</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div><label>Patient</label><select name="patient_id">';
                    foreach ($patients as $patient) {
                        echo '<option value="' . (int)$patient['id'] . '">' . htmlspecialchars($patient['name']) . '</option>';
                    }
                    echo '</select></div>
                                    <div><label>Medication Name</label><input type="text" name="medication_name" required></div>
                                    <div><label>Reminder Time</label><input type="text" name="reminder_time" placeholder="09:00 AM"></div>
                                    <div><label>Frequency</label><input type="text" name="frequency" value="Daily"></div>
                                </div>
                                <button type="submit">Create Reminder</button>
                            </form>
                          </div>';
                    break;

                case 'patient_monitoring':
                    $featureQuery = "SELECT m.id, p.name AS patient_name, m.heart_rate, m.blood_pressure, m.temperature, m.oxygen_saturation, m.alert_level, m.monitored_at
                                     FROM nurse_patient_monitoring m
                                     JOIN patients p ON p.id = m.patient_id
                                     WHERE m.nurse_id = ?
                                     ORDER BY m.monitored_at DESC";
                    $featureColumns = ['patient_name' => 'Patient', 'heart_rate' => 'Heart Rate', 'blood_pressure' => 'BP', 'temperature' => 'Temp', 'oxygen_saturation' => 'SpO₂', 'alert_level' => 'Alert', 'monitored_at' => 'Time'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    break;

                case 'notifications':
                    $featureQuery = "SELECT id, message, priority, is_read, created_at FROM nurse_notifications WHERE nurse_id = ? ORDER BY created_at DESC";
                    $featureColumns = ['message' => 'Message', 'priority' => 'Priority', 'is_read' => 'Read', 'created_at' => 'Created'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Add Notification</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div style="grid-column:1 / -1;"><label>Message</label><textarea name="message" rows="4" required></textarea></div>
                                    <div><label>Priority</label><select name="priority"><option>Normal</option><option>High</option><option>Critical</option></select></div>
                                </div>
                                <button type="submit">Send Notification</button>
                            </form>
                          </div>';
                    break;

                case 'profile':
                    if ($currentNurse) {
                        $photoSrc = !empty($currentNurse['profile_photo']) ? htmlspecialchars($currentNurse['profile_photo']) : 'assets/images/default-avatar.jpg';
                        echo '<div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px;">';
                        echo '<div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">';
                        echo '<img src="' . $photoSrc . '" alt="Profile photo" style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid #dbeafe; background:#f8fafc;">';
                        echo '<div><h3 style="margin:0 0 6px;">' . htmlspecialchars($currentNurse['nurse_name']) . '</h3><div style="color:#475569;">Nurse Profile</div></div>';
                        echo '</div>';
                        if ($profileMode === 'view') {
                            echo '<a href="nurse_profile.php?mode=edit" class="btn">Edit Profile</a>';
                        } else {
                            echo '<a href="nurse_profile.php" class="btn" style="background:#64748b;">View Profile</a>';
                        }
                        echo '</div>';

                        if ($profileSuccessMessage !== '') {
                            echo '<div style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; border-radius:8px; padding:12px 14px; margin-bottom:16px;">' . htmlspecialchars($profileSuccessMessage) . '</div>';
                        }

                        if ($profileErrorMessage !== '') {
                            echo '<div style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:8px; padding:12px 14px; margin-bottom:16px;">' . htmlspecialchars($profileErrorMessage) . '</div>';
                        }

                        if ($profileMode === 'edit') {
                            echo '<div class="card" style="margin-top:0;">';
                            echo '<h3 style="margin-top:0;">Edit Profile</h3>';
                            echo '<form method="POST" enctype="multipart/form-data">';
                            echo '<input type="hidden" name="nurse_id" value="' . (int)$currentNurse['id'] . '">';
                            echo '<div class="grid">';
                            echo '<div><label>Profile Photo</label><input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp"></div>';
                            echo '<div><label>Current Photo</label><img src="' . $photoSrc . '" alt="Current profile photo" style="width:120px; height:120px; border-radius:12px; object-fit:cover; border:1px solid #d1d5db; background:#f8fafc; margin-top:8px;"></div>';
                            echo '<div><label>Full Name</label><input type="text" name="nurse_name" value="' . htmlspecialchars($currentNurse['nurse_name']) . '" required></div>';
                            echo '<div><label>Email</label><input type="email" name="email" value="' . htmlspecialchars($currentNurse['email'] ?? '') . '" required></div>';
                            echo '<div><label>Phone</label><input type="text" name="phone" value="' . htmlspecialchars($currentNurse['phone'] ?? '') . '"></div>';
                            echo '<div><label>Department</label><input type="text" name="department" value="' . htmlspecialchars($currentNurse['department'] ?? '') . '" readonly></div>';
                            echo '<div style="grid-column:1 / -1;"><label>Address</label><textarea name="address" rows="3">' . htmlspecialchars($currentNurse['address'] ?? '') . '</textarea></div>';
                            echo '<div><label>Username</label><input type="text" value="' . htmlspecialchars($currentNurse['username'] ?? 'N/A') . '" readonly></div>';
                            echo '<div><label>Role</label><input type="text" value="Nurse" readonly></div>';
                            echo '</div>';
                            echo '<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">';
                            echo '<button type="submit">Save Changes</button>';
                            echo '<a href="nurse_profile.php" class="btn" style="background:#64748b;">Cancel</a>';
                            echo '</div>';
                            echo '</form></div>';
                        } else {
                            echo '<div class="stats">';
                            nurse_render_stat_card('Name', $currentNurse['nurse_name']);
                            nurse_render_stat_card('Department', $currentNurse['department'] ?? 'General');
                            nurse_render_stat_card('Phone', $currentNurse['phone'] ?? 'N/A');
                            echo '</div>';
                            echo '<div class="card" style="margin-top:0;"><div class="grid">';
                            echo '<div><label>Email</label><div style="padding:10px 0;">' . htmlspecialchars($currentNurse['email'] ?? 'N/A') . '</div></div>';
                            echo '<div><label>Username</label><div style="padding:10px 0;">' . htmlspecialchars($currentNurse['username'] ?? 'N/A') . '</div></div>';
                            echo '<div><label>Role</label><div style="padding:10px 0;">Nurse</div></div>';
                            echo '<div><label>Address</label><div style="padding:10px 0;">' . htmlspecialchars($currentNurse['address'] ?? 'N/A') . '</div></div>';
                            echo '<div><label>Assigned Nurse ID</label><div style="padding:10px 0;">' . (int)$currentNurse['id'] . '</div></div>';
                            echo '</div></div>';
                        }
                    }
                    break;

                case 'daily_reports':
                    $featureQuery = "SELECT id, report_date, summary, tasks_completed, concerns FROM nurse_daily_reports WHERE nurse_id = ? ORDER BY report_date DESC";
                    $featureColumns = ['report_date' => 'Date', 'summary' => 'Summary', 'tasks_completed' => 'Tasks', 'concerns' => 'Concerns'];
                    $stmt = $conn->prepare($featureQuery);
                    if ($stmt) {
                        $stmt->bind_param('i', $currentNurseId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $featureRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                        $stmt->close();
                    }
                    echo '<div class="card" style="margin-top:20px;">
                            <h3>Submit Daily Report</h3>
                            <form method="POST">
                                <div class="grid">
                                    <div style="grid-column:1 / -1;"><label>Summary</label><textarea name="summary" rows="4" required></textarea></div>
                                    <div style="grid-column:1 / -1;"><label>Tasks Completed</label><input type="text" name="tasks_completed"></div>
                                    <div style="grid-column:1 / -1;"><label>Concerns</label><textarea name="concerns" rows="3"></textarea></div>
                                </div>
                                <button type="submit">Save Daily Report</button>
                            </form>
                          </div>';
                    break;

                default:
                    $featureRows = [];
                    $featureColumns = ['patient_name' => 'Patient'];
                    break;
            }

            if ($feature !== 'profile' && !in_array($feature, ['vital_signs', 'medication_admin', 'nursing_notes', 'tasks', 'medication_reminders', 'notifications', 'daily_reports'], true)) {
                $count = count($featureRows);
                echo '<div class="stats">';
                nurse_render_stat_card('Records', (string)$count, '#0ea5e9');
                if ($feature === 'assigned_patients' || $feature === 'patient_details') {
                    $active = 0;
                    foreach ($featureRows as $row) {
                        if (($row['status'] ?? '') === 'Active' || ($row['care_level'] ?? '') !== '') {
                            $active++;
                        }
                    }
                    nurse_render_stat_card('Active', (string)$active, '#16a34a');
                }
                echo '</div>';
            }

            if ($feature !== 'profile') {
                nurse_render_feature_table($featureRows, $featureColumns);
            }
            ?>
        </div>
    </div>
</body>
</html>
