<?php
session_start();
include 'db.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];
$error = '';
$message = '';

$status_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
if ($status_column && $status_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
}

$profile_columns = [
    'profile_photo' => "ALTER TABLE doctors ADD COLUMN profile_photo VARCHAR(255) NULL",
    'qualifications' => "ALTER TABLE doctors ADD COLUMN qualifications VARCHAR(255) NULL",
    'experience_years' => "ALTER TABLE doctors ADD COLUMN experience_years INT NOT NULL DEFAULT 0",
    'consultation_fee' => "ALTER TABLE doctors ADD COLUMN consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    'department' => "ALTER TABLE doctors ADD COLUMN department VARCHAR(255) NULL",
    'contact_info' => "ALTER TABLE doctors ADD COLUMN contact_info VARCHAR(255) NULL",
    'working_days' => "ALTER TABLE doctors ADD COLUMN working_days VARCHAR(255) NOT NULL DEFAULT 'Mon-Fri'",
    'available_slots' => "ALTER TABLE doctors ADD COLUMN available_slots VARCHAR(255) NOT NULL DEFAULT '09:00,11:00,14:00'",
    'about' => "ALTER TABLE doctors ADD COLUMN about TEXT NULL",
    'availability_status' => "ALTER TABLE doctors ADD COLUMN availability_status VARCHAR(20) NOT NULL DEFAULT 'Available'"
];
foreach ($profile_columns as $column => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM doctors LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        $conn->query($sql);
    }
}

$appointment_columns = [
    'appointment_slot' => "ALTER TABLE appointments ADD COLUMN appointment_slot VARCHAR(20) NULL"
];
foreach ($appointment_columns as $column => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM appointments LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        $conn->query($sql);
    }
}

$selectedDoctorId = (int)($_POST['doctor_id'] ?? $_GET['doctor_id'] ?? 0);
$selectedDepartmentName = trim((string)($_GET['dept'] ?? ''));
$selectedDepartment = trim((string)($_POST['department'] ?? $_GET['department'] ?? $selectedDepartmentName ?? ''));
$selectedSpecialty = trim((string)($_POST['specialty'] ?? $_GET['specialty'] ?? ''));
$departmentKeywords = [
    'Critical Care Medicine' => ['critical', 'icu', 'critical care'],
    'Dental' => ['dental', 'dentist', 'oral', 'tooth'],
    'Dermatology' => ['dermatology', 'derma', 'skin'],
    'Emergency Medicine' => ['emergency', 'er', 'urgent'],
    'ENT (Ear, Nose & Throat)' => ['ent', 'ear', 'nose', 'throat'],
    'Orthopedics and Trauma Surgery' => ['orthopedic', 'orthopaedic', 'trauma', 'bone', 'joint'],
    'General Practice' => ['general practice', 'general medicine', 'primary care', 'family medicine', 'physician'],
    'Internal Medicine' => ['internal medicine', 'internal'],
    'Neurology' => ['neurology', 'neuro', 'neurologist', 'brain'],
    'Neurosurgery' => ['neurosurgery', 'neurosurgeon', 'brain surgery'],
    'Nephrology' => ['nephrology', 'kidney'],
    'Obstetrics & Gynecology' => ['obstetrics', 'gynecology', 'obgyn', 'women’s health', 'women'],
    'Paediatrics' => ['paediatrics', 'pediatrics', 'pediatric', 'child'],
    'Psychiatry & Mental Health' => ['psychiatry', 'mental health', 'psychology', 'psych'],
    'Plastic Surgery and Burns' => ['plastic surgery', 'burns', 'reconstructive'],
    'Pharmacy' => ['pharmacy', 'pharmacist', 'medicines'],
    'Radiology' => ['radiology', 'imaging', 'x-ray', 'ct', 'mri', 'ultrasound'],
    'Urology and Kidney Transplant Surgery' => ['urology', 'kidney transplant', 'kidney'],
    'Cardiology' => ['cardiology', 'cardiologist', 'heart'],
    'Pediatrics' => ['pediatrics', 'pediatric', 'child', 'children'],
    'General Physician' => ['general physician', 'general medicine', 'family doctor', 'general practitioner'],
];
$filterMessage = '';
if ($selectedDepartment !== '' || $selectedDepartmentName !== '') {
    $filterMessage = 'Showing doctors from the selected department.';
} elseif ($selectedSpecialty !== '') {
    $filterMessage = 'Showing doctors matching the selected specialty.';
} elseif ($selectedDoctorId > 0) {
    $filterMessage = 'Filtering available doctors and time slots based on your selection.';
}
$selectedDate = '';
if (!empty($_POST['appointment_date'])) {
    $selectedDate = $conn->real_escape_string($_POST['appointment_date']);
} elseif (!empty($_GET['appointment_date'])) {
    $selectedDate = $conn->real_escape_string($_GET['appointment_date']);
}
$selectedTime = '';
if (!empty($_POST['appointment_time'])) {
    $selectedTime = $conn->real_escape_string($_POST['appointment_time']);
}

$conn->query("CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$departments = [];
$departmentResult = $conn->query('SELECT id, name FROM departments WHERE status = "Active" ORDER BY name ASC');
if ($departmentResult) {
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

if (empty($departments)) {
    $deptFallbackResult = $conn->query("SELECT DISTINCT TRIM(department) AS name FROM doctors WHERE department IS NOT NULL AND TRIM(department) <> '' ORDER BY name ASC");
    if ($deptFallbackResult) {
        while ($row = $deptFallbackResult->fetch_assoc()) {
            $departments[] = ['id' => 0, 'name' => $row['name']];
        }
    }
}

$doctors = [];
$filterActive = $selectedDepartmentName !== '' || $selectedDepartment !== '' || $selectedSpecialty !== '';
$doctorQuery = "SELECT id, name, specialty, availability_status, status, available_slots, department, working_days FROM doctors WHERE (status = 'Available' OR availability_status = 'Available') ORDER BY name ASC";
$result = $conn->query($doctorQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($filterActive && !doctor_matches_filters($row, $selectedDepartment, $selectedDepartmentName, $selectedSpecialty, $departmentKeywords)) {
            continue;
        }
        $doctors[] = $row;
    }
}
$fallbackMessage = '';
if ($filterActive && empty($doctors)) {
    $fallbackMessage = 'No available doctors were found for your selected filter. Showing all open doctors instead.';
    $doctors = [];
    $result = $conn->query($doctorQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
    }
}

function doctor_matches_filters(array $doctor, string $selectedDepartment, string $selectedDepartmentName, string $selectedSpecialty, array $departmentKeywords): bool
{
    $specialty = strtolower(trim((string)($doctor['specialty'] ?? '')));
    $department = strtolower(trim((string)($doctor['department'] ?? '')));
    $filterTerms = [];
    if ($selectedDepartmentName !== '') {
        $filterTerms[] = $selectedDepartmentName;
    }
    if ($selectedDepartment !== '') {
        $filterTerms[] = $selectedDepartment;
    }
    $filterTerms = array_filter(array_unique($filterTerms), fn($term) => trim((string)$term) !== '');
    foreach ($filterTerms as $term) {
        $normalizedTerm = strtolower(trim((string)$term));
        $keywords = $departmentKeywords[$term] ?? [$normalizedTerm];
        $keywords = array_unique(array_merge([$normalizedTerm], $keywords, preg_split('/[^a-z0-9]+/', $normalizedTerm)));
        $matches = false;
        foreach ($keywords as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword === '') {
                continue;
            }
            if (strpos($specialty, $keyword) !== false || strpos($department, $keyword) !== false) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return false;
        }
    }
    if ($selectedSpecialty !== '') {
        $selectedSpecialty = strtolower(trim($selectedSpecialty));
        if ($selectedSpecialty !== '' && strpos($specialty, $selectedSpecialty) === false && strpos($department, $selectedSpecialty) === false) {
            return false;
        }
    }
    return true;
}

function parse_slots(?string $slots): array
{
    if (!$slots) {
        return ['09:00', '11:00', '14:00'];
    }

    $parsed = [];
    foreach (explode(',', $slots) as $slot) {
        $slot = trim($slot);
        if ($slot !== '') {
            $parsed[] = $slot;
        }
    }

    return $parsed ?: ['09:00', '11:00', '14:00'];
}

function doctor_is_working_on_date(string $workingDays, string $date): bool
{
    $workingDays = strtolower(trim($workingDays));
    if ($workingDays === '' || $workingDays === 'mon-fri' || $workingDays === 'mon to fri' || $workingDays === 'monday to friday') {
        return true;
    }

    $day = strtolower(date('l', strtotime($date)));
    $dayShort = strtolower(date('D', strtotime($date)));

    if (strpos($workingDays, $day) !== false || strpos($workingDays, $dayShort) !== false) {
        return true;
    }

    if (preg_match('/mon\s*-\s*fri|monday\s*-\s*friday/i', $workingDays) && in_array($dayShort, ['mon', 'tue', 'wed', 'thu', 'fri'], true)) {
        return true;
    }

    return false;
}

function get_slot_statuses(mysqli $conn, int $doctorId, string $date): array
{
    $doctor = null;
    $stmt = $conn->prepare('SELECT available_slots, availability_status, status FROM doctors WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $doctor = $result->fetch_assoc();
    }
    $stmt->close();

    if (!$doctor) {
        return [];
    }

    $status = $doctor['availability_status'] ?: $doctor['status'];
    if ($status !== 'Available') {
        return [];
    }

    $slots = parse_slots($doctor['available_slots'] ?? '');
    $statuses = [];
    foreach ($slots as $slot) {
        $statuses[] = [
            'slot' => $slot,
            'available' => !is_slot_taken($conn, $doctorId, $date, $slot),
        ];
    }

    return $statuses;
}

function is_slot_taken(mysqli $conn, int $doctorId, string $date, string $time): bool
{
    $stmt = $conn->prepare('SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN (\'Cancelled\', \'Completed\') LIMIT 1');
    $stmt->bind_param('iss', $doctorId, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $taken = $result && $result->num_rows > 0;
    $stmt->close();
    return $taken;
}

function has_existing_booking(mysqli $conn, int $patientId, int $doctorId, string $date, string $time): bool
{
    $stmt = $conn->prepare('SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN (\'Cancelled\', \'Completed\') LIMIT 1');
    $stmt->bind_param('iiss', $patientId, $doctorId, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function get_available_slots(mysqli $conn, int $doctorId, string $date): array
{
    $slotStatuses = get_slot_statuses($conn, $doctorId, $date);
    $available = [];
    foreach ($slotStatuses as $slotStatus) {
        if (!empty($slotStatus['available'])) {
            $available[] = $slotStatus['slot'];
        }
    }

    return $available;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $selectedDepartment = trim((string)($_POST['department'] ?? ''));
    $date = $conn->real_escape_string($_POST['appointment_date'] ?? '');
    $time = $conn->real_escape_string($_POST['appointment_time'] ?? '');
    $refreshSlots = !empty($_POST['refresh_slots']);

    $selectedDoctorId = $doctor_id;
    $selectedDate = $date;
    $selectedTime = '';

    if ($refreshSlots) {
        $error = '';
        $message = '';
    } elseif ($selectedDepartment === '') {
        $error = 'Please select a department first.';
    } elseif (!$doctor_id || !$date || !$time) {
        $error = 'Please select a doctor and choose a date/time.';
    } else {
        $statusCheck = $conn->prepare('SELECT availability_status, status FROM doctors WHERE id = ?');
        $statusCheck->bind_param('i', $doctor_id);
        $statusCheck->execute();
        $statusCheck->bind_result($availabilityStatus, $currentStatus);
        $statusCheck->fetch();
        $statusCheck->close();

        $effectiveStatus = $availabilityStatus ?: $currentStatus;
        if ($effectiveStatus !== 'Available') {
            $error = 'Selected doctor is not available. Please choose another available doctor.';
        } else {
            $availableSlotsForBooking = get_available_slots($conn, $doctor_id, $date);
            if (!in_array($time, $availableSlotsForBooking, true)) {
                $error = 'Please choose a genuinely available slot.';
            } elseif (has_existing_booking($conn, $patient_id, $doctor_id, $date, $time)) {
                $error = 'You already have a booking for this doctor at this date and time.';
            } else {
                $stmt = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, appointment_slot) VALUES (?, ?, ?, ?, ?, ?)');
                $status = 'Pending';
                $stmt->bind_param('iissss', $patient_id, $doctor_id, $date, $time, $status, $time);
                if ($stmt->execute()) {
                    $message = 'Appointment request sent successfully. Admin will confirm it shortly.';
                    $selectedTime = $time;
                } else {
                    $error = 'Unable to book appointment at this time.';
                }
                $stmt->close();
            }
        }
    }
}

$slotStatuses = [];
$availableSlots = [];
if ($selectedDoctorId > 0 && $selectedDate !== '') {
    $slotStatuses = get_slot_statuses($conn, $selectedDoctorId, $selectedDate);
    foreach ($slotStatuses as $slotStatus) {
        if (!empty($slotStatus['available'])) {
            $availableSlots[] = $slotStatus['slot'];
        }
    }
} elseif ($selectedDoctorId > 0) {
    $slotStatuses = get_slot_statuses($conn, $selectedDoctorId, date('Y-m-d'));
    foreach ($slotStatuses as $slotStatus) {
        if (!empty($slotStatus['available'])) {
            $availableSlots[] = $slotStatus['slot'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Appointment</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 38%, #0f172a 100%);
        color: #e2e8f0;
        padding: 24px;
    }
    .page {
        max-width: 960px;
        margin: 0 auto;
    }
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 30px;
    }
    h1 {
        font-size: 34px;
        font-weight: 700;
        color: #ffffff;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
        letter-spacing: 0.02em;
        text-rendering: optimizeLegibility;
    }
    .subtitle { color: #cbd5e1; margin-top: 6px; }
    .card {
        background: rgba(15, 23, 42, 0.96);
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 28px;
        padding: 32px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22);
    }
    .grid { display: grid; grid-template-columns: 1fr 360px; gap: 26px; }
    label { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px; }
    select, input { width: 100%; padding: 14px 16px; border-radius: 16px; border: 1px solid rgba(148, 163, 184, 0.35); background: #ffffff; color: #000000; font-size: 15px; margin-bottom: 18px; transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease; }
    .doctor-list { list-style: none; padding: 0; margin: 0 0 16px; display: grid; gap: 8px; }
    .doctor-list li { padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(148,163,184,0.16); }
    select option { color: #000000; background: #ffffff; }
    select option[value=""] { color: #6b7280; }
    input:focus, select:focus {
        outline: none;
        border-color: #7c3aed;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.18);
    }
    select option:checked { background: #93c5fd; color: #000000; }
    .button { border: none; border-radius: 16px; padding: 14px 18px; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; }
    .button:hover { transform: translateY(-2px); }
    .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    .alert.info { background: rgba(59,130,246,0.12); color: #bfdbfe; border: 1px solid rgba(148,163,184,0.32); }
    .doctor-card { background: rgba(255,255,255,0.05); border-radius: 22px; padding: 20px; }
    .doctor-card h3 { margin-bottom: 10px; font-size: 20px; }
    .doctor-card p { color: #cbd5e1; line-height: 1.7; }
    .link { color: #93c5fd; text-decoration: none; }
    .link:hover { text-decoration: underline; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; flex-direction: column; align-items: flex-start; }
        h1 { font-size: 26px; }
        .card { padding: 20px 16px; border-radius: 20px; }
        select, input { padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .button { padding: 12px 16px; font-size: 14px; }
        .doctor-card { padding: 16px; border-radius: 18px; }
        .doctor-card h3 { font-size: 16px; }
        .doctor-card p { font-size: 13px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        .topbar { gap: 8px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .card { padding: 14px 12px; border-radius: 16px; }
        .grid { gap: 12px; }
        select, input { padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }
        label { font-size: 13px; }
        .button { padding: 10px 14px; font-size: 12px; border-radius: 12px; }
        .doctor-card { padding: 12px; border-radius: 14px; }
        .doctor-card h3 { font-size: 14px; }
        .doctor-card p { font-size: 12px; }
        .link { font-size: 12px; }
    }
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Book Appointment</h1>
            <p class="subtitle">Hello, <?php echo htmlspecialchars($patient_name); ?>. Choose a doctor and request your appointment.</p>
        </div>
        <div>
            <a class="link" href="dashboard.php">Back to Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 20px; font-weight: 600;">Home</a>
        </div>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php elseif (!empty($message)): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid">
            <div>
                <h2>Choose your appointment</h2>
                <form id="appointment-form" method="POST" action="book-appointment.php">
                    <input type="hidden" name="refresh_slots" id="refresh_slots" value="0">
                    <label for="department">Select Department</label>
                    <select id="department" name="department" required>
                        <option value="">Choose a department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department['name']); ?>" <?php echo $selectedDepartment === $department['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div id="filter-notification" class="alert info" style="display: <?php echo $filterMessage !== '' ? 'block' : 'none'; ?>; margin-bottom: 18px;">
                        <?php echo htmlspecialchars($filterMessage); ?>
                    </div>

                    <label for="doctor_id">Select Doctor</label>
                    <select id="doctor_id" name="doctor_id" required <?php echo empty($doctors) ? 'disabled' : ''; ?> >
                        <option value="">Pick a doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo (int)$doctor['id']; ?>" <?php echo $selectedDoctorId === (int)$doctor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($doctor['name']) . ' — ' . htmlspecialchars($doctor['specialty']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <ul class="doctor-list">
                        <?php foreach ($doctors as $doctor): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($doctor['name']); ?></strong> — <?php echo htmlspecialchars($doctor['specialty']); ?>
                                <span style="display:block; margin-top:4px;"><a class="link" href="doctor-profile.php?id=<?php echo (int)$doctor['id']; ?>">View doctor profile</a></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (empty($doctors)): ?>
                        <div class="alert error" style="margin-top: 0;">No available doctors are currently open for booking. Please check back later.</div>
                    <?php endif; ?>

                    <label for="appointment_date">Date</label>
                    <input id="appointment_date" type="date" name="appointment_date" required value="<?php echo htmlspecialchars($selectedDate); ?>" <?php echo empty($doctors) ? 'disabled' : ''; ?> >

                    <label for="appointment_time">Time</label>
                    <?php if ($selectedDoctorId > 0 && $selectedDate !== '' && empty($availableSlots)): ?>
                        <div class="alert error" style="margin-top:0;">No available slots for the selected date.</div>
                    <?php endif; ?>
                    <select id="appointment_time" name="appointment_time" required <?php echo empty($doctors) ? 'disabled' : ''; ?>>
                        <option value="">Choose an available slot</option>
                        <?php foreach ($slotStatuses as $slotStatus): ?>
                            <?php $slot = $slotStatus['slot']; $slotAvailable = !empty($slotStatus['available']); ?>
                            <option value="<?php echo htmlspecialchars($slot); ?>" <?php echo $selectedTime !== '' && $selectedTime === $slot ? 'selected' : ''; ?> <?php echo $slotAvailable ? '' : 'disabled'; ?>>
                                <?php echo htmlspecialchars($slot); ?><?php echo $slotAvailable ? '' : ' (Booked)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="button" type="submit" <?php echo empty($doctors) ? 'disabled' : ''; ?>>Request Appointment</button>
                </form>
            </div>
            <div class="doctor-card">
                <h3>Need help?</h3>
                <p>Appointments are reviewed by the hospital admin and updated automatically once confirmed. You can track your booking status from the dashboard.</p>
                <p>Select a doctor and date to view only the available time slots. Duplicate bookings are blocked automatically.</p>
                <?php if ($selectedDoctorId > 0): ?>
                    <p style="margin-top:10px;"><a class="link" href="doctor-profile.php?id=<?php echo (int)$selectedDoctorId; ?>">Open doctor profile</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('appointment-form');
        const departmentSelect = document.getElementById('department');
        const doctorSelect = document.getElementById('doctor_id');
        const dateInput = document.getElementById('appointment_date');
        const refreshInput = document.getElementById('refresh_slots');

        if (!form || !doctorSelect || !dateInput || !refreshInput) {
            return;
        }

        const refreshSlots = () => {
            if (!departmentSelect || !refreshInput || !form || !departmentSelect.value) {
                return;
            }

            form.noValidate = true;
            refreshInput.value = '1';
            form.submit();
        };

        const filterNotification = document.getElementById('filter-notification');

        const updateFilterNotification = () => {
            if (!filterNotification || !departmentSelect || !doctorSelect) {
                return;
            }

            if (departmentSelect.value) {
                filterNotification.textContent = 'Showing doctors from the selected department.';
                filterNotification.style.display = 'block';
            } else if (doctorSelect.value) {
                filterNotification.textContent = 'Filtering available doctors based on your selection.';
                filterNotification.style.display = 'block';
            } else {
                filterNotification.style.display = 'none';
            }
        };

        departmentSelect?.addEventListener('change', () => {
            updateFilterNotification();
            refreshSlots();
        });
        doctorSelect.addEventListener('change', () => {
            updateFilterNotification();
        });
        dateInput.addEventListener('change', () => {
            refreshSlots();
        });

        updateFilterNotification();
    });
</script>
</body>
</html>
