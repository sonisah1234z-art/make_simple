<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';
require_once 'otp-management.php';
require_once 'appointment_reminder_helper.php';

if (!function_exists('get_system_setting')) {
    function get_system_setting(mysqli $conn, string $key, string $default = ''): string {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) {
            return $default;
        }

        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row['setting_value'] ?? $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting(mysqli $conn, string $key, string $value): bool {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('is_birthday_today')) {
    function is_birthday_today(?string $dateOfBirth): bool {
        if ($dateOfBirth === null || trim($dateOfBirth) === '') {
            return false;
        }

        try {
            $dob = new DateTimeImmutable($dateOfBirth);
        } catch (Exception $e) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        return $dob->format('m-d') === $today->format('m-d');
    }
}

if (!function_exists('has_birthday_wish_sent')) {
    function has_birthday_wish_sent(mysqli $conn, int $patientId, int $year): bool {
        $conn->query("CREATE TABLE IF NOT EXISTS birthday_wish_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            year INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_year (patient_id, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('SELECT 1 FROM birthday_wish_logs WHERE patient_id = ? AND year = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $patientId, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('send_birthday_email_if_available')) {
    function send_birthday_email_if_available(mysqli $conn, string $email, string $patientName, string $message): void {
        $conn->query("CREATE TABLE IF NOT EXISTS birthday_wish_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            year INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_year (patient_id, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

ensure_appointment_reminder_support($conn);
$patient_reminders = getPatientReminders($conn, $patient_id);

function parse_doctor_slots(?string $slots): array
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

function build_department_image(string $slug, string $title, string $accent1, string $accent2): string
{
    $safeTitle = preg_replace('/[^\w\s-]/u', '', $title) ?: 'Department';
    $themeSvg = '';

    switch ($slug) {
        case 'dental-opd':
            $themeSvg = '<rect x="118" y="136" width="238" height="218" rx="28" fill="#ffffff" fill-opacity="0.2" />
                <rect x="150" y="188" width="180" height="122" rx="24" fill="#ffffff" fill-opacity="0.9" />
                <circle cx="208" cy="240" r="36" fill="#2563eb" fill-opacity="0.2" />
                <path d="M208 204c12 0 22 10 22 22s-10 22-22 22-22-10-22-22 10-22 22-22Z" fill="#ffffff" />
                <rect x="336" y="176" width="226" height="190" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <rect x="380" y="214" width="132" height="84" rx="16" fill="#2563eb" fill-opacity="0.12" />
                <path d="M422 214v84M388 252h136" stroke="#2563eb" stroke-width="10" stroke-linecap="round" />
                <circle cx="566" cy="158" r="44" fill="#ffffff" fill-opacity="0.28" />
                <path d="M566 126v64M534 158h64" stroke="#ffffff" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'general-practice':
            $themeSvg = '<rect x="136" y="146" width="220" height="228" rx="26" fill="#ffffff" fill-opacity="0.22" />
                <circle cx="246" cy="242" r="72" fill="#ffffff" fill-opacity="0.92" />
                <rect x="214" y="214" width="64" height="92" rx="16" fill="#0f766e" fill-opacity="0.15" />
                <path d="M246 190c22 0 40 18 40 40v26h-20v-26c0-11-9-20-20-20s-20 9-20 20v26h-20v-26c0-22 18-40 40-40Z" fill="#0f766e" />
                <rect x="392" y="168" width="240" height="190" rx="24" fill="#ffffff" fill-opacity="0.9" />
                <path d="M450 236c18-24 42-36 74-36 18 0 36 6 50 16" stroke="#0f766e" stroke-width="10" stroke-linecap="round" />
                <path d="M458 288h134" stroke="#0f766e" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'pain-management-opd':
            $themeSvg = '<rect x="130" y="140" width="236" height="216" rx="26" fill="#ffffff" fill-opacity="0.2" />
                <path d="M180 244c24-58 88-82 122-42 14 16 22 36 22 58" stroke="#7c3aed" stroke-width="14" stroke-linecap="round" fill="none" />
                <path d="M220 226c12-28 34-46 64-52" stroke="#ffffff" stroke-width="10" stroke-linecap="round" />
                <rect x="396" y="172" width="220" height="172" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M452 212h98" stroke="#7c3aed" stroke-width="10" stroke-linecap="round" />
                <path d="M454 248h78" stroke="#7c3aed" stroke-width="10" stroke-linecap="round" />
                <path d="M468 284h48" stroke="#7c3aed" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'hematology-medicine':
            $themeSvg = '<circle cx="240" cy="250" r="88" fill="#ffffff" fill-opacity="0.24" />
                <path d="M240 180c28 0 52 18 62 44h-124c10-26 34-44 62-44Z" fill="#ffffff" fill-opacity="0.92" />
                <path d="M240 300c-18 0-34-8-44-20l-56-80h100l-20 44h42l-22 56Z" fill="#b45309" fill-opacity="0.2" />
                <rect x="392" y="160" width="244" height="190" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M470 220h88" stroke="#b45309" stroke-width="12" stroke-linecap="round" />
                <circle cx="514" cy="220" r="42" fill="#b45309" fill-opacity="0.14" />
                <path d="M514 188v64M482 220h64" stroke="#b45309" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'special-counselling-opd':
            $themeSvg = '<rect x="132" y="150" width="220" height="214" rx="24" fill="#ffffff" fill-opacity="0.2" />
                <circle cx="242" cy="234" r="70" fill="#ffffff" fill-opacity="0.92" />
                <path d="M224 204c0-18 14-32 32-32s32 14 32 32v20h-64v-20Z" fill="#be185d" fill-opacity="0.12" />
                <path d="M214 278c18-18 42-28 70-28" stroke="#be185d" stroke-width="10" stroke-linecap="round" />
                <rect x="392" y="172" width="228" height="174" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M448 232c18-24 42-36 76-36 18 0 38 6 54 18" stroke="#be185d" stroke-width="10" stroke-linecap="round" />
                <path d="M470 282h112" stroke="#be185d" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'paediatric':
            $themeSvg = '<rect x="128" y="154" width="210" height="204" rx="28" fill="#ffffff" fill-opacity="0.24" />
                <circle cx="232" cy="236" r="66" fill="#ffffff" fill-opacity="0.92" />
                <rect x="208" y="214" width="44" height="70" rx="14" fill="#0369a1" fill-opacity="0.14" />
                <path d="M230 184c18 0 32 14 32 32v8h-64v-8c0-18 14-32 32-32Zm-10 78v18m20-18v18" stroke="#0369a1" stroke-width="10" stroke-linecap="round" />
                <rect x="388" y="176" width="250" height="182" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M450 220h92" stroke="#0369a1" stroke-width="10" stroke-linecap="round" />
                <path d="M462 260h68" stroke="#0369a1" stroke-width="10" stroke-linecap="round" />
                <path d="M520 220v70" stroke="#0369a1" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'family-planning-cac':
            $themeSvg = '<rect x="130" y="154" width="220" height="212" rx="26" fill="#ffffff" fill-opacity="0.2" />
                <circle cx="242" cy="236" r="72" fill="#ffffff" fill-opacity="0.92" />
                <path d="M242 200v72M206 236h72" stroke="#0f766e" stroke-width="12" stroke-linecap="round" />
                <rect x="392" y="170" width="240" height="184" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M444 230h126" stroke="#0f766e" stroke-width="10" stroke-linecap="round" />
                <path d="M450 272h112" stroke="#0f766e" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'neurology':
            $themeSvg = '<rect x="128" y="150" width="218" height="208" rx="26" fill="#ffffff" fill-opacity="0.22" />
                <circle cx="236" cy="238" r="72" fill="#ffffff" fill-opacity="0.92" />
                <path d="M214 218c14-24 42-34 68-26 16 4 28 14 34 28" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" fill="none" />
                <path d="M228 282c12 20 36 32 62 32" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" />
                <rect x="388" y="176" width="246" height="182" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M452 234c16-16 36-24 62-24 18 0 34 4 48 12" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" />
                <path d="M472 278h96" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'transplant-surgery':
            $themeSvg = '<rect x="126" y="152" width="226" height="206" rx="28" fill="#ffffff" fill-opacity="0.2" />
                <path d="M176 286c12-50 46-78 94-78 32 0 58 10 78 32" stroke="#4d7c0f" stroke-width="12" stroke-linecap="round" fill="none" />
                <circle cx="234" cy="228" r="40" fill="#ffffff" fill-opacity="0.9" />
                <path d="M234 204v48M210 228h48" stroke="#4d7c0f" stroke-width="10" stroke-linecap="round" />
                <rect x="392" y="176" width="242" height="180" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M458 230h100" stroke="#4d7c0f" stroke-width="10" stroke-linecap="round" />
                <path d="M468 270h80" stroke="#4d7c0f" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'gastro-medicine':
            $themeSvg = '<rect x="134" y="154" width="214" height="208" rx="24" fill="#ffffff" fill-opacity="0.2" />
                <path d="M212 202c22 0 40 18 40 40v26h-40v-66Zm44 0c22 0 40 18 40 40v26h-40v-66Z" fill="#ffffff" fill-opacity="0.9" />
                <path d="M250 222c0 18 16 32 36 32" stroke="#b91c1c" stroke-width="10" stroke-linecap="round" />
                <rect x="392" y="176" width="240" height="184" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M448 236h128" stroke="#b91c1c" stroke-width="10" stroke-linecap="round" />
                <path d="M470 276h84" stroke="#b91c1c" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'respiratory-medicine':
            $themeSvg = '<rect x="132" y="150" width="224" height="214" rx="24" fill="#ffffff" fill-opacity="0.23" />
                <path d="M212 206c0-22 18-40 40-40s40 18 40 40v44c0 22-18 40-40 40s-40-18-40-40v-44Z" fill="#ffffff" fill-opacity="0.92" />
                <path d="M232 196v64M272 196v64" stroke="#166534" stroke-width="10" stroke-linecap="round" />
                <rect x="394" y="172" width="238" height="184" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M460 232h90" stroke="#166534" stroke-width="10" stroke-linecap="round" />
                <path d="M456 270h96" stroke="#166534" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'endocrine-internal-medicine':
            $themeSvg = '<rect x="132" y="150" width="220" height="212" rx="24" fill="#ffffff" fill-opacity="0.22" />
                <circle cx="242" cy="240" r="70" fill="#ffffff" fill-opacity="0.92" />
                <path d="M242 198c14 0 26 12 26 26s-12 26-26 26-26-12-26-26 12-26 26-26Z" fill="#7f1d1d" fill-opacity="0.16" />
                <path d="M242 212v56M214 240h56" stroke="#7f1d1d" stroke-width="10" stroke-linecap="round" />
                <rect x="392" y="176" width="240" height="180" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M450 232h128" stroke="#7f1d1d" stroke-width="10" stroke-linecap="round" />
                <path d="M466 272h96" stroke="#7f1d1d" stroke-width="10" stroke-linecap="round" />';
            break;
        case 'cardiology':
            $themeSvg = '<rect x="136" y="150" width="214" height="214" rx="26" fill="#ffffff" fill-opacity="0.22" />
                <circle cx="242" cy="250" r="72" fill="#ffffff" fill-opacity="0.92" />
                <path d="M206 242h28l14-28 18 56 16-28h28" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <rect x="392" y="176" width="242" height="184" rx="24" fill="#ffffff" fill-opacity="0.92" />
                <path d="M458 236h102" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" />
                <path d="M470 276h78" stroke="#1d4ed8" stroke-width="10" stroke-linecap="round" />';
            break;
        default:
            $themeSvg = '<rect x="120" y="150" width="170" height="180" rx="22" fill="#ffffff" fill-opacity="0.22" />
                <rect x="318" y="150" width="170" height="180" rx="22" fill="#ffffff" fill-opacity="0.2" />
                <rect x="516" y="150" width="170" height="180" rx="22" fill="#ffffff" fill-opacity="0.22" />
                <rect x="140" y="364" width="520" height="48" rx="16" fill="' . $accent2 . '" fill-opacity="0.75" />';
            break;
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 520">
        <rect width="800" height="520" rx="34" fill="' . $accent1 . '" />
        <rect x="48" y="86" width="704" height="356" rx="24" fill="#ffffff" fill-opacity="0.18" />
        ' . $themeSvg . '
        <rect x="84" y="392" width="632" height="38" rx="16" fill="' . $accent2 . '" fill-opacity="0.75" />
        <text x="92" y="468" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700">' . htmlspecialchars($safeTitle, ENT_XML1, 'UTF-8') . '</text>
    </svg>';

    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function department_matches_doctor(array $doctor, array $departmentDef): bool
{
    $keywords = $departmentDef['keywords'] ?? [];
    $deptName = strtolower(trim((string)($departmentDef['name'] ?? '')));

    // Build token list from keywords + department name tokens
    $parts = [];
    foreach ($keywords as $k) {
        $k = strtolower(trim((string)$k));
        if ($k !== '') {
            $parts[] = $k;
        }
    }
    if ($deptName !== '') {
        foreach (preg_split('/[\s,]+/', $deptName) as $t) {
            $t = trim($t);
            if ($t !== '') {
                $parts[] = $t;
            }
        }
    }

    // Combined searchable text from doctor's profile
    $doctorText = strtolower(trim((string)($doctor['specialty'] ?? '') . ' ' . ($doctor['department'] ?? '') . ' ' . ($doctor['name'] ?? '') . ' ' . ($doctor['qualifications'] ?? '')));

    foreach ($parts as $token) {
        if ($token === '') {
            continue;
        }
        if (stripos($doctorText, $token) !== false) {
            return true;
        }
    }

    // Also check doctor's department field explicitly against department name and keywords
    $doctorDept = strtolower(trim((string)($doctor['department'] ?? '')));
    if ($doctorDept !== '') {
        if ($deptName !== '' && stripos($doctorDept, $deptName) !== false) {
            return true;
        }
        foreach ($keywords as $keyword) {
            $k = strtolower(trim((string)$keyword));
            if ($k !== '' && stripos($doctorDept, $k) !== false) {
                return true;
            }
        }
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

    $slots = parse_doctor_slots($doctor['available_slots'] ?? '');
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
$bookingMessage = '';
$bookingError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $date = $conn->real_escape_string($_POST['appointment_date'] ?? '');
    $time = $conn->real_escape_string($_POST['appointment_time'] ?? '');
    $refreshSlots = !empty($_POST['refresh_slots']);

    $selectedDoctorId = $doctor_id;
    $selectedDate = $date;
    $selectedTime = '';

    if ($refreshSlots) {
        $bookingMessage = '';
        $bookingError = '';
    } elseif (!$doctor_id || !$date || !$time) {
        $bookingError = 'Please select a doctor, date, and available time slot.';
    } else {
        $statusCheck = $conn->prepare('SELECT availability_status, status FROM doctors WHERE id = ?');
        $statusCheck->bind_param('i', $doctor_id);
        $statusCheck->execute();
        $statusCheck->bind_result($availabilityStatus, $currentStatus);
        $statusCheck->fetch();
        $statusCheck->close();

        $effectiveStatus = $availabilityStatus ?: $currentStatus;
        if ($effectiveStatus !== 'Available') {
            $bookingError = 'Selected doctor is not available at the moment.';
        } else {
            $availableSlotsForBooking = get_available_slots($conn, $doctor_id, $date);
            if (!in_array($time, $availableSlotsForBooking, true)) {
                $bookingError = 'Please choose a genuinely available slot.';
            } elseif (has_existing_booking($conn, $patient_id, $doctor_id, $date, $time)) {
                $bookingError = 'You already have a booking for this doctor at the selected date and time.';
            } else {
                $stmt = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, appointment_slot) VALUES (?, ?, ?, ?, ?, ?)');
                $status = 'Pending';
                $stmt->bind_param('iissss', $patient_id, $doctor_id, $date, $time, $status, $time);
                if ($stmt->execute()) {
                    $bookingMessage = 'Appointment request submitted successfully.';
                    $selectedTime = $time;
                } else {
                    $bookingError = 'Unable to save the appointment. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}

$appointments = [];
$stmt = $conn->prepare("SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status,
        (SELECT ao.otp_code FROM appointment_otps ao WHERE ao.appointment_id = a.id ORDER BY ao.id DESC LIMIT 1) AS otp_code,
        (SELECT ao.verification_status FROM appointment_otps ao WHERE ao.appointment_id = a.id ORDER BY ao.id DESC LIMIT 1) AS verification_status,
        (SELECT ao.expires_at FROM appointment_otps ao WHERE ao.appointment_id = a.id ORDER BY ao.id DESC LIMIT 1) AS expires_at
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC");

if ($stmt) {
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    }
    $stmt->close();
}

$status_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
if ($status_column && $status_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
}

$availableDoctors = [];
$doctorResult = $conn->query("SELECT id, name, specialty, status, availability_status FROM doctors ORDER BY FIELD(status,'Available','Busy','Offline'), name ASC");
if ($doctorResult) {
    while ($row = $doctorResult->fetch_assoc()) {
        $availableDoctors[] = $row;
    }
}

$doctorProfiles = [];
$doctorProfileResult = $conn->query("SELECT id, name, specialty, status, availability_status, profile_photo, qualifications, experience_years, consultation_fee, department, contact_info, working_days, available_slots, about FROM doctors ORDER BY name ASC");
if ($doctorProfileResult) {
    while ($row = $doctorProfileResult->fetch_assoc()) {
        $doctorProfiles[] = $row;
    }
}

function normalize_department_name(string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = str_replace('&', ' and ', $value);
    $value = preg_replace('/[^\pL\pN\s-]+/u', '', $value);
    return trim((string)$value);
}

function slugify_department_name(string $value): string
{
    $value = strtolower(normalize_department_name($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== '' ? $value : 'department';
}

function resolve_department_id_by_name(mysqli $conn, string $departmentName): int
{
    $departmentName = trim((string)$departmentName);
    if ($departmentName === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM departments WHERE status = "Active" AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('s', $departmentName);
    $stmt->execute();
    $result = $stmt->get_result();
    $departmentId = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $departmentId = (int)($row['id'] ?? 0);
    }
    $stmt->close();

    return $departmentId;
}

$departmentDefinitions = [
    'dental-opd' => [
        'name' => 'Dental OPD',
        'description' => 'Advanced dental care, restorative treatment, cosmetic procedures, and preventive checkups.',
        'keywords' => ['dental', 'dentist', 'oral', 'tooth'],
        'accent1' => '#2563eb',
        'accent2' => '#38bdf8',
        'image' => build_department_image('dental-opd', 'Dental OPD', '#2563eb', '#38bdf8')
    ],
    'general-practice' => [
        'name' => 'General Practice',
        'description' => 'Primary health consultations, routine exams, wellness guidance, and early treatment.',
        'keywords' => ['general practice', 'general medicine', 'family medicine', 'primary care'],
        'accent1' => '#0f766e',
        'accent2' => '#2dd4bf',
        'image' => 'assets/images/general-opd-photo.svg'
    ],
    'pain-management-opd' => [
        'name' => 'Pain Management OPD',
        'description' => 'Targeted care for chronic pain, injury recovery, and long-term comfort planning.',
        'keywords' => ['pain', 'pain management'],
        'accent1' => '#7c3aed',
        'accent2' => '#a78bfa',
        'image' => build_department_image('pain-management-opd', 'Pain Management', '#7c3aed', '#a78bfa')
    ],
    'hematology-medicine' => [
        'name' => 'Hematology Medicine',
        'description' => 'Specialists for blood disorders, anemia management, clotting concerns, and diagnostics.',
        'keywords' => ['hematology', 'blood'],
        'accent1' => '#b45309',
        'accent2' => '#f59e0b',
        'image' => build_department_image('hematology-medicine', 'Hematology', '#b45309', '#f59e0b')
    ],
    'special-counselling-opd' => [
        'name' => 'Special Counselling OPD',
        'description' => 'Confidential support for mental wellness, stress management, and behavioral care.',
        'keywords' => ['counselling', 'counseling', 'psych', 'psychiatry', 'mental health'],
        'accent1' => '#be185d',
        'accent2' => '#f472b6',
        'image' => build_department_image('special-counselling-opd', 'Counselling OPD', '#be185d', '#f472b6')
    ],
    'paediatric' => [
        'name' => 'Paediatric',
        'description' => 'Child-focused care for growth, development, illness, and preventive wellness.',
        'keywords' => ['paediatric', 'pediatric', 'child', 'children'],
        'accent1' => '#0369a1',
        'accent2' => '#38bdf8',
        'image' => build_department_image('paediatric', 'Paediatric', '#0369a1', '#38bdf8')
    ],
    'family-planning-cac' => [
        'name' => 'Family Planning & CAC Center',
        'description' => 'Reproductive health guidance, family planning services, and counseling support.',
        'keywords' => ['family planning', 'cac', 'reproductive', 'women'],
        'accent1' => '#0f766e',
        'accent2' => '#34d399',
        'image' => build_department_image('family-planning-cac', 'Family Planning', '#0f766e', '#34d399')
    ],
    'neurology' => [
        'name' => 'Neurology',
        'description' => 'Advanced evaluation and treatment for headaches, seizures, stroke care, and nerve disorders.',
        'keywords' => ['neurology', 'neuro', 'brain', 'nerve'],
        'accent1' => '#1d4ed8',
        'accent2' => '#60a5fa',
        'image' => build_department_image('neurology', 'Neurology', '#1d4ed8', '#60a5fa')
    ],
    'transplant-surgery' => [
        'name' => 'Transplant Surgery',
        'description' => 'Specialized surgical consultation for transplant evaluation and recovery planning.',
        'keywords' => ['transplant', 'transplant surgery'],
        'accent1' => '#4d7c0f',
        'accent2' => '#84cc16',
        'image' => build_department_image('transplant-surgery', 'Transplant Surgery', '#4d7c0f', '#84cc16')
    ],
    'gastro-medicine' => [
        'name' => 'Gastro Medicine',
        'description' => 'Digestive health care for liver, stomach, intestine, and gut-related conditions.',
        'keywords' => ['gastro', 'gastroenterology', 'digestive', 'liver'],
        'accent1' => '#b91c1c',
        'accent2' => '#fb923c',
        'image' => build_department_image('gastro-medicine', 'Gastro Medicine', '#b91c1c', '#fb923c')
    ],
    'respiratory-medicine' => [
        'name' => 'Respiratory Medicine',
        'description' => 'Respiratory care for asthma, COPD, breathing issues, and lung health support.',
        'keywords' => ['respiratory', 'lung', 'asthma', 'copd'],
        'accent1' => '#166534',
        'accent2' => '#4ade80',
        'image' => build_department_image('respiratory-medicine', 'Respiratory', '#166534', '#4ade80')
    ],
    'endocrine-internal-medicine' => [
        'name' => 'Endocrine/Internal Medicine',
        'description' => 'Care for diabetes, thyroid, hormones, metabolic health, and chronic adult conditions.',
        'keywords' => ['endocrine', 'endocrinology', 'internal medicine', 'diabetes', 'thyroid'],
        'accent1' => '#7f1d1d',
        'accent2' => '#f87171',
        'image' => build_department_image('endocrine-internal-medicine', 'Endocrine', '#7f1d1d', '#f87171')
    ],
    'cardiology' => [
        'name' => 'Cardiology',
        'description' => 'Expert heart care, cardiac diagnostics, lifestyle counseling, and long-term monitoring.',
        'keywords' => ['cardiology', 'cardiac', 'heart'],
        'accent1' => '#1d4ed8',
        'accent2' => '#60a5fa',
        'image' => build_department_image('cardiology', 'Cardiology', '#1d4ed8', '#60a5fa')
    ]
];

$selectedDepartmentId = (int)($_GET['department_id'] ?? 0);
$selectedDepartmentSlug = isset($_GET['department']) ? trim((string)$_GET['department']) : '';
$selectedDepartment = $departmentDefinitions[$selectedDepartmentSlug] ?? null;

$departmentCatalog = [];
$departmentRows = $conn->query("SELECT id, name, description, status, image_path FROM departments WHERE status = 'Active' ORDER BY name ASC");
if ($departmentRows) {
    while ($row = $departmentRows->fetch_assoc()) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $slug = slugify_department_name($name);
        $savedImage = trim((string)($row['image_path'] ?? ''));
        $defaultDepartmentImage = $departmentDefinitions[$slug]['image'] ?? 'assets/images/default-doctor.png';
        $departmentCatalog[$slug] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => $name,
            'description' => trim((string)($row['description'] ?? '')),
            'image' => $savedImage !== '' ? $savedImage : $defaultDepartmentImage,
        ];
    }
}

if ($selectedDepartment === null && $selectedDepartmentSlug !== '') {
    foreach ($departmentCatalog as $slug => $department) {
        if ($slug === $selectedDepartmentSlug) {
            $selectedDepartment = [
                'id' => (int)($department['id'] ?? 0),
                'name' => $department['name'],
                'description' => $department['description'],
                'keywords' => [slugify_department_name($department['name'])],
                'image' => $department['image'] ?? '',
            ];
            break;
        }
    }
}

if ($selectedDepartmentId === 0 && $selectedDepartment) {
    $selectedDepartmentId = (int)($selectedDepartment['id'] ?? resolve_department_id_by_name($conn, (string)($selectedDepartment['name'] ?? '')));
}

$departmentDoctors = [];
if ($selectedDepartmentId > 0 || $selectedDepartment) {
    $departmentName = trim((string)($selectedDepartment['name'] ?? ''));
    if ($selectedDepartmentId > 0 && $departmentName === '') {
        $departmentRow = $conn->query('SELECT name FROM departments WHERE id = ' . (int)$selectedDepartmentId . ' AND status = "Active" LIMIT 1');
        if ($departmentRow && $departmentRow->num_rows > 0) {
            $departmentName = trim((string)$departmentRow->fetch_assoc()['name']);
        }
    }

    foreach ($doctorProfiles as &$doctorProfile) {
        $doctorDeptName = trim((string)($doctorProfile['department'] ?? ''));
        $doctorDepartmentId = 0;
        if ($doctorDeptName !== '') {
            $doctorDepartmentId = resolve_department_id_by_name($conn, $doctorDeptName);
        }

        $matchesDepartment = false;
        if ($selectedDepartmentId > 0 && $doctorDepartmentId > 0) {
            $matchesDepartment = $selectedDepartmentId === $doctorDepartmentId;
        } elseif ($departmentName !== '') {
            $matchesDepartment = strcasecmp($doctorDeptName, $departmentName) === 0 || strcasecmp((string)($doctorProfile['specialty'] ?? ''), $departmentName) === 0;
        }

        if ($matchesDepartment) {
            $doctorProfile['department_id'] = $doctorDepartmentId;
            $departmentDoctors[] = $doctorProfile;
        }
    }
    unset($doctorProfile);
}

require_once 'announcements.php';
$context = get_current_user_context();
$visibleAnnouncements = get_visible_announcements($conn, $context['role'], $context['id'], 5);

$departmentMeta = [
    'Critical Care Medicine' => ['icon' => 'bi-heart-pulse', 'description' => 'Intensive monitoring and advanced support for critically ill patients.'],
    'Dental' => ['icon' => 'bi-teeth', 'description' => 'Complete oral care, dental surgery, and preventive dentistry services.'],
    'Dermatology' => ['icon' => 'bi-droplet-half', 'description' => 'Skin, hair, and nail treatments from diagnosis to cosmetic care.'],
    'Emergency Medicine' => ['icon' => 'bi-activity', 'description' => 'Urgent care and rapid response for acute injuries and illnesses.'],
    'ENT (Ear, Nose & Throat)' => ['icon' => 'bi-ear', 'description' => 'Specialists for ear, nose, throat, voice and airway care.'],
    'Orthopedics and Trauma Surgery' => ['icon' => 'bi-bandage', 'description' => 'Bone, joint, muscle, and trauma surgery for comprehensive mobility care.'],
    'General Practice' => ['icon' => 'bi-house-heart', 'description' => 'Primary care services for preventive health and everyday wellness.'],
    'Internal Medicine' => ['icon' => 'bi-file-medical', 'description' => 'Complex adult medicine, chronic disease management, and diagnostics.'],
    'Neurology' => ['icon' => 'bi-brain', 'description' => 'Care for neurological disorders, headaches, seizures and brain health.'],
    'Neurosurgery' => ['icon' => 'bi-tools', 'description' => 'Surgical treatment of the brain, spine and nervous system.'],
    'Nephrology' => ['icon' => 'bi-droplet', 'description' => 'Kidney care, dialysis support and transplant evaluation services.'],
    'Obstetrics & Gynecology' => ['icon' => 'bi-gender-female', 'description' => 'Women’s health, pregnancy, and reproductive care.'],
    'Pediatrics' => ['icon' => 'bi-baby', 'description' => 'Child and adolescent care for growth, immunizations and wellness.'],
    'Psychiatry & Mental Health' => ['icon' => 'bi-emoji-smile', 'description' => 'Mental health support, therapy and psychiatric care services.'],
    'Plastic Surgery and Burns' => ['icon' => 'bi-hammer', 'description' => 'Reconstructive and cosmetic surgery with burn recovery support.'],
    'Pharmacy' => ['icon' => 'bi-bag-heart', 'description' => 'Medication dispensing, counseling and pharmacy support services.'],
    'Radiology' => ['icon' => 'bi-x-circle', 'description' => 'Imaging diagnostics including X-ray, CT, MRI and ultrasound.'],
    'Urology and Kidney Transplant Surgery' => ['icon' => 'bi-droplet', 'description' => 'Urological care and kidney transplant planning for complex cases.']
];

$departmentCounts = array_fill_keys(array_keys($departmentMeta), 0);
$departmentKeywords = [
    'Critical Care Medicine' => ['critical', 'icu', 'critical care'],
    'Dental' => ['dental', 'dentist', 'oral', 'tooth'],
    'Dermatology' => ['dermatology', 'derma', 'skin'],
    'Emergency Medicine' => ['emergency', 'er', 'urgent'],
    'ENT (Ear, Nose & Throat)' => ['ent', 'ear', 'nose', 'throat'],
    'Orthopedics and Trauma Surgery' => ['orthopedic', 'orthopaedic', 'trauma', 'bone', 'joint'],
    'General Practice' => ['general practice', 'general medicine', 'primary care', 'family medicine'],
    'Internal Medicine' => ['internal medicine', 'internal'],
    'Neurology' => ['neurology', 'neuro'],
    'Neurosurgery' => ['neurosurgery', 'neurosurgeon', 'brain surgery'],
    'Nephrology' => ['nephrology', 'kidney'],
    'Obstetrics & Gynecology' => ['obstetrics', 'gynecology', 'obgyn', 'women’s health', 'women'],
   'Pediatrics' => ['pediatrics', 'paediatrics', 'child', 'pediatric'],
    'Psychiatry & Mental Health' => ['psychiatry', 'mental health', 'psychology', 'psych'],
    'Plastic Surgery and Burns' => ['plastic surgery', 'burns', 'reconstructive'],
    'Pharmacy' => ['pharmacy', 'pharmacist', 'medicines'],
    'Radiology' => ['radiology', 'imaging', 'x-ray', 'ct', 'mri', 'ultrasound'],
    'Urology and Kidney Transplant Surgery' => ['urology', 'kidney transplant', 'kidney'],
];

$availableDoctorRows = $conn->query("SELECT specialty, department FROM doctors WHERE (status = 'Available' OR availability_status = 'Available')");
if ($availableDoctorRows) {
    while ($row = $availableDoctorRows->fetch_assoc()) {
        $doctorSpecialty = strtolower(trim((string)($row['specialty'] ?? '')));
        $doctorDepartment = strtolower(trim((string)($row['department'] ?? '')));

        foreach ($departmentKeywords as $departmentName => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && (stripos($doctorSpecialty, $keyword) !== false || stripos($doctorDepartment, $keyword) !== false)) {
                    $departmentCounts[$departmentName]++;
                    break;
                }
            }
        }
    }
}

$departmentCards = [];
foreach ($departmentMeta as $name => $meta) {
    $departmentCards[] = [
        'name' => $name,
        'icon' => $meta['icon'],
        'description' => $meta['description'],
        'count' => $departmentCounts[$name] ?? 0
    ];
}

// Fetch active health offers and packages from database
$promotionalOffers = [];
$healthPackages = [];
$today = date('Y-m-d');

// Fetch promotional offers (carousel/promotional design)
$offersResult = $conn->query("
    SELECT * FROM health_offers 
    WHERE is_active = 1 
    AND offer_type = 'offer'
    AND validity_start <= '$today' 
    AND validity_end >= '$today'
    ORDER BY sort_order ASC, is_popular DESC, id DESC
");
if ($offersResult) {
    while ($offer = $offersResult->fetch_assoc()) {
        $promotionalOffers[] = $offer;
    }
}

// Fetch health checkup packages
$packagesResult = $conn->query("
    SELECT * FROM health_offers 
    WHERE is_active = 1 
    AND offer_type = 'package'
    AND validity_start <= '$today' 
    AND validity_end >= '$today'
    ORDER BY sort_order ASC, is_popular DESC, id DESC
");
if ($packagesResult) {
    while ($package = $packagesResult->fetch_assoc()) {
        $healthPackages[] = $package;
    }
}

// Keep for backward compatibility (all offers)
$healthOffers = array_merge($promotionalOffers, $healthPackages);

$heroSlides = [];
$matchingHospitalUpdates = [];
foreach ($visibleAnnouncements as $announcement) {
    $title = trim((string)($announcement['title'] ?? ''));
    $message = trim(strip_tags((string)($announcement['message'] ?? '')));
    if (stripos($title, 'Blood Donation Camp') !== false || stripos($message, 'Blood Donation Camp') !== false) {
        $matchingHospitalUpdates[] = $announcement;
        break;
    }
}

foreach ($matchingHospitalUpdates as $announcement) {
    $heroSlides[] = [
        'badge' => 'Hospital Update',
        'title' => $announcement['title'],
        'description' => substr(strip_tags($announcement['message']), 0, 90),
        'cta' => 'View Update',
        'link' => 'view_announcement.php?id=' . (int)$announcement['id']
    ];
}

$today = date('Y-m-d');
$nextAppointments = [];
$upcomingSummary = [
    'next_appointments' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0
];
foreach ($appointments as $appointment) {
    $status = $appointment['status'] ?? '';
    if ($status === 'Pending') {
        $upcomingSummary['pending']++;
    } elseif (in_array($status, ['Approved', 'Confirmed', 'Scheduled'], true)) {
        $upcomingSummary['confirmed']++;
    } elseif ($status === 'Completed') {
        $upcomingSummary['completed']++;
    }

    if ($appointment['appointment_date'] >= $today && $status !== 'Cancelled') {
        $upcomingSummary['next_appointments']++;
        if (count($nextAppointments) < 4) {
            $nextAppointments[] = $appointment;
        }
    }
}

$recentRecords = [];
ensure_lab_tests_table($conn);
$labStmt = $conn->prepare('SELECT test_type, result, report_date FROM lab_tests WHERE patient_id = ? ORDER BY report_date DESC, id DESC LIMIT 3');
if ($labStmt) {
    $labStmt->bind_param('i', $patient_id);
    $labStmt->execute();
    $labResult = $labStmt->get_result();
    while ($row = $labResult->fetch_assoc()) {
        $recentRecords[] = [
            'type' => 'Lab Test',
            'title' => $row['test_type'],
            'detail' => $row['result'] ?: 'Result pending',
            'date' => $row['report_date']
        ];
    }
    $labStmt->close();
}
ensure_medicine_orders_table($conn);
$orderStmt = $conn->prepare('SELECT medicine_name, quantity, total_price, status, created_at FROM medicine_orders WHERE patient_name = ? ORDER BY created_at DESC LIMIT 3');
if ($orderStmt) {
    $orderStmt->bind_param('s', $patient_name);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($row = $orderResult->fetch_assoc()) {
        $recentRecords[] = [
            'type' => 'Prescription',
            'title' => $row['medicine_name'],
            'detail' => $row['quantity'] . ' item(s) • $' . number_format((float)$row['total_price'], 2) . ' • ' . $row['status'],
            'date' => substr($row['created_at'], 0, 10)
        ];
    }
    $orderStmt->close();
}

$healthTips = [
    ['icon' => 'bi-heart-pulse', 'title' => 'Stay Hydrated', 'text' => 'Drink water throughout the day for better circulation and energy.'],
    ['icon' => 'bi-nutrition', 'title' => 'Balanced Diet', 'text' => 'Choose whole foods, lean proteins, and plenty of vegetables for lasting wellness.'],
    ['icon' => 'bi-moon-stars', 'title' => 'Rest Well', 'text' => 'Aim for consistent sleep to support recovery and immune function.'],
    ['icon' => 'bi-shield-check', 'title' => 'Follow Preventive Care', 'text' => 'Keep up with screenings, vaccinations, and routine checkups.']
];

$faqItems = [
    ['question' => 'How do I book an appointment?', 'answer' => 'Use the Book Appointment button or select a specialty card to view available doctors and slots.'],
    ['question' => 'How can I view my lab reports?', 'answer' => 'Open the Reports section from the dashboard or visit the Lab Tests page to see recent results.'],
    ['question' => 'What should I do in an emergency?', 'answer' => 'Call the 24/7 Emergency Hotline immediately or use the Emergency Help button on your dashboard.'],
    ['question' => 'Can I see my medical records here?', 'answer' => 'Yes, your recent tests, prescriptions, and timeline are displayed in the Recent Medical Records section.' ]
];

$slotStatuses = [];
$availableSlots = [];
if ($selectedDoctorId > 0 && $selectedDate !== '') {
    $slotStatuses = get_slot_statuses($conn, $selectedDoctorId, $selectedDate);
    foreach ($slotStatuses as $slotStatus) {
        if (!empty($slotStatus['available'])) {
            $availableSlots[] = $slotStatus['slot'];
        }
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_number VARCHAR(50) UNIQUE,
    ward VARCHAR(100),
    is_icu TINYINT(1),
    status VARCHAR(50),
    bed_type VARCHAR(20) NOT NULL DEFAULT 'General',
    patient_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$bedTypeColumn = $conn->query("SHOW COLUMNS FROM beds LIKE 'bed_type'");
if ($bedTypeColumn && $bedTypeColumn->num_rows === 0) {
    $conn->query("ALTER TABLE beds ADD COLUMN bed_type VARCHAR(20) NOT NULL DEFAULT 'General'");
    $conn->query("UPDATE beds SET bed_type='ICU' WHERE is_icu = 1");
}

$bedAvailable = 0;
$bedIcuAvailable = 0;
$bedGeneralAvailable = 0;
$bedEmergencyAvailable = 0;
$bedTotal = 0;

$bedTotalResult = $conn->query("SELECT COUNT(*) AS total FROM beds");
if ($bedTotalResult) {
    $bedTotal = (int)$bedTotalResult->fetch_assoc()['total'];
}
$bedAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status='Available'");
if ($bedAvailableResult) {
    $bedAvailable = (int)$bedAvailableResult->fetch_assoc()['total'];
}
$bedIcuAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='ICU' AND status='Available'");
if ($bedIcuAvailableResult) {
    $bedIcuAvailable = (int)$bedIcuAvailableResult->fetch_assoc()['total'];
}
$bedGeneralAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='General' AND status='Available'");
if ($bedGeneralAvailableResult) {
    $bedGeneralAvailable = (int)$bedGeneralAvailableResult->fetch_assoc()['total'];
}
$bedEmergencyAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='Emergency' AND status='Available'");
if ($bedEmergencyAvailableResult) {
    $bedEmergencyAvailable = (int)$bedEmergencyAvailableResult->fetch_assoc()['total'];
}
?>
<?php include 'header.php'; ?>
<style>
    .topnav a[href="patient_notifications.php"] { display: none; }
    .topnav form[role="search"] { display: none; }
    .sidebar.d-none { display: none !important; }

    /* Patient dashboard presentation overrides shared compact defaults. */
    :root {
        --patient-ink: #16324f;
        --patient-muted: #6b7c93;
        --patient-blue: #1479c9;
        --patient-teal: #0f9d91;
        --patient-line: #dce7f0;
        --patient-surface: #ffffff;
        --patient-wash: #f4f8fb;
        --patient-shadow: 0 12px 34px rgba(26, 58, 88, .08);
    }

    body:has(.dashboard-page) { background: var(--patient-wash); color: var(--patient-ink); }
    body:has(.dashboard-page) .app { background: var(--patient-wash); }
    body:has(.dashboard-page) .sidebar {
        width: 252px !important;
        max-width: 252px !important;
        flex-basis: 252px !important;
        background: linear-gradient(180deg, #123e5d 0%, #102d46 100%) !important;
        box-shadow: 8px 0 28px rgba(20, 55, 84, .08);
    }
    body:has(.dashboard-page) .sidebar-brand { padding: 1.35rem 1.2rem 1.1rem !important; }
    body:has(.dashboard-page) .brand-circle { width: 40px; height: 40px; border-radius: 12px; background: #58c6ba; color: #103b4d; font-size: 1.1rem; }
    body:has(.dashboard-page) .brand-title { font-size: 1.05rem; letter-spacing: .01em; }
    body:has(.dashboard-page) .sidebar .nav { padding: .75rem .8rem !important; }
    body:has(.dashboard-page) .sidebar .nav-link { padding: .78rem .85rem; margin-bottom: .35rem; border-radius: 10px; color: #dcecf5; }
    body:has(.dashboard-page) .sidebar .nav-link:hover,
    body:has(.dashboard-page) .sidebar .nav-link:focus { background: rgba(88, 198, 186, .16); color: #fff; transform: translateX(3px); }
    body:has(.dashboard-page) .sidebar .nav-link i { width: 1.25rem; color: #83d7cf; }
    body:has(.dashboard-page) .sidebar-footer { padding: 1.1rem !important; border-top-color: rgba(255,255,255,.12); }
    body:has(.dashboard-page) .topnav { min-height: 70px; background: rgba(255,255,255,.94) !important; border-bottom: 1px solid var(--patient-line); box-shadow: 0 5px 22px rgba(26, 58, 88, .05); }
    body:has(.dashboard-page) .topnav .container-fluid { padding: .8rem 1.35rem; }
    body:has(.dashboard-page) #sidebarToggle { border-color: var(--patient-line); color: var(--patient-ink); }
    body:has(.dashboard-page) .topnav .btn-outline-primary { border-color: #b9d7e7; color: var(--patient-blue); }
    body:has(.dashboard-page) .container-fluid.p-4 { max-width: 1500px; margin: 0 auto; padding: 2rem 2.25rem !important; }
    body:has(.dashboard-page) .page-head { margin-bottom: 1.35rem; }
    body:has(.dashboard-page) .page-head .eyebrow { color: var(--patient-teal); font-size: .72rem; letter-spacing: .14em; }
    body:has(.dashboard-page) .page-head h1 { color: var(--patient-ink); font-size: clamp(1.65rem, 2.4vw, 2.35rem); letter-spacing: -.02em; }
    body:has(.dashboard-page) .page-head p { color: var(--patient-muted) !important; max-width: 700px; }
    .dashboard-page { gap: 1.35rem; }
    .dashboard-page > .card,
    .dashboard-page > section.card,
    .dashboard-page .appointment-reminders { border: 1px solid var(--patient-line) !important; border-radius: 14px !important; background: var(--patient-surface); box-shadow: var(--patient-shadow); }
    .dashboard-page > section.card,
    .dashboard-page > .card { padding: 1.45rem !important; }
    .dashboard-page h5 { color: var(--patient-ink); font-size: 1.08rem !important; font-weight: 750; }
    .dashboard-page .section-label { color: var(--patient-teal); font-size: .7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .dashboard-page .text-muted { color: var(--patient-muted) !important; }
    .dashboard-page .metric-card { border: 0 !important; border-left: 4px solid var(--patient-blue) !important; border-radius: 12px !important; background: var(--patient-surface); box-shadow: var(--patient-shadow); padding: 1.15rem !important; }
    .dashboard-page .metric-card:nth-child(2) { border-left-color: #f0ad4e !important; }
    .dashboard-page .metric-card:nth-child(3) { border-left-color: var(--patient-teal) !important; }
    .dashboard-page .metric-card:nth-child(4) { border-left-color: #7b8aa0 !important; }
    .dashboard-page .metric-card span { color: var(--patient-muted); font-size: .72rem; letter-spacing: .1em; }
    .dashboard-page .metric-card strong { color: var(--patient-ink); font-size: 2rem; }
    .dashboard-page .appointment-card > .card-body,
    .dashboard-page .appointment-card .card { border-color: var(--patient-line) !important; border-radius: 12px !important; }
    .dashboard-page .appointment-table thead th { color: var(--patient-muted); font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; border-bottom: 1px solid var(--patient-line); }
    .dashboard-page .appointment-table tbody tr { border-bottom: 1px solid #edf2f6; }
    .dashboard-page .appointment-table tbody tr:last-child { border-bottom: 0; }
    .dashboard-page .offer-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
    .dashboard-page .offer-card { overflow: hidden; border: 1px solid var(--patient-line); border-radius: 12px; background: #fff; box-shadow: 0 8px 22px rgba(26,58,88,.06); }
    .dashboard-page .offer-image { height: 150px; }
    .dashboard-page .offer-image img { width: 100%; height: 100%; object-fit: cover; }
    .dashboard-page .offer-content { padding: 1rem; }
    .dashboard-page .offer-content h3 { color: var(--patient-ink); font-size: 1.05rem; }
    .dashboard-page .health-packages-dashboard .health-container { gap: 1rem; }
    .dashboard-page .health-packages-dashboard .health-card { border-radius: 12px; box-shadow: 0 8px 22px rgba(26,58,88,.06); }
    .dashboard-page .health-packages-dashboard .health-card img { height: 150px; }
    .dashboard-page .department-showcase-card, .dashboard-page .doctor-inline-card { border-color: var(--patient-line); border-radius: 12px; box-shadow: 0 8px 22px rgba(26,58,88,.06); }
    .dashboard-page .department-showcase-card:hover, .dashboard-page .department-showcase-card.active { border-color: #8bcfc8; box-shadow: 0 14px 28px rgba(15,157,145,.12); }
    .dashboard-page .service-card { min-height: 142px; padding: 1.25rem !important; border: 1px solid var(--patient-line); border-radius: 12px; background: #fbfdff; box-shadow: none; }
    .dashboard-page .service-card:hover { border-color: #8bcfc8; background: #f2fbfa; transform: translateY(-3px); }
    .dashboard-page .service-card .sa-icon { width: 52px; height: 52px; margin-bottom: .65rem; border-radius: 14px; background: #e7f5f4; color: var(--patient-teal); font-size: 1.35rem; }
    .dashboard-page .service-card .sa-title { color: var(--patient-ink); font-size: .95rem; }
    .dashboard-page .service-card .sa-desc { color: var(--patient-muted); font-size: .78rem; }
    @media (max-width: 991.98px) {
        body:has(.dashboard-page) .sidebar { position: fixed !important; left: 0; top: 0; height: 100vh; z-index: 1050; }
        body:has(.dashboard-page) .container-fluid.p-4 { padding: 1.35rem !important; }
    }
    @media (max-width: 575.98px) {
        body:has(.dashboard-page) .topnav .container-fluid { padding: .7rem .8rem; }
        body:has(.dashboard-page) .topnav .me-3 { display: none; }
        body:has(.dashboard-page) .container-fluid.p-4 { padding: 1rem .75rem !important; }
        .dashboard-page > section.card, .dashboard-page > .card { padding: 1rem !important; }
        .dashboard-page .appointment-table { min-width: 560px; }
        .dashboard-page .health-packages-dashboard .health-container { grid-template-columns: 1fr; }
    }
</style>

<div class="page-head mb-4">
    <div>
        <div class="eyebrow text-uppercase text-primary mb-2">Patient Dashboard</div>
        <h1>Welcome back, <?php echo htmlspecialchars($patient_name); ?></h1>
        <p class="text-muted">Manage your care, view health updates, and access essential services from one modern dashboard.</p>
    </div>
</div>

<div class="dashboard-page">
    <!-- OTP: moved here to be visible immediately below header/navigation -->
    <section class="card p-4 appointment-card mb-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <span class="section-label">Verification OTP</span>
                <h5 class="mt-2 mb-1">Secure check-in codes for approved visits</h5>
                <p class="text-muted mb-0">Approved appointments will show a verification OTP here for hospital check-in.</p>
            </div>
            <a class="text-primary small" href="view_appointments.php">View all appointments</a>
        </div>

        <?php if (count($appointments) > 0): ?>
            <div class="row g-3">
                <?php foreach ($appointments as $appointment): ?>
                    <?php
                        $appointmentStatus = $appointment['status'] ?? '';
                        $otpCode = $appointment['otp_code'] ?? '';
                        $verificationStatus = $appointment['verification_status'] ?? '';
                        $expiresAt = $appointment['expires_at'] ?? '';
                        $isExpired = $expiresAt !== '' && strtotime($expiresAt) < time();
                        $isVerified = $verificationStatus === 'Verified';
                    ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <div>
                                        <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($appointment['doctor_name']); ?></h6>
                                        <div class="text-muted small"><?php echo htmlspecialchars($appointment['specialty']); ?></div>
                                    </div>
                                    <span class="status-pill status-<?php echo htmlspecialchars($appointmentStatus); ?>"><?php echo htmlspecialchars($appointmentStatus); ?></span>
                                </div>

                                <div class="small text-muted mb-3">
                                    <div><?php echo htmlspecialchars($appointment['appointment_date']); ?> • <?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></div>
                                </div>

                                <?php if ($appointmentStatus === 'Approved'): ?>
                                    <?php if ($isVerified): ?>
                                        <div class="alert alert-success py-2 mb-0 small">✓ Your appointment has already been verified and is ready for your visit.</div>
                                    <?php elseif ($isExpired): ?>
                                    <?php elseif (!empty($otpCode)): ?>
                                        <div class="border rounded-3 p-3 bg-light">
                                            <div class="fw-semibold mb-2">Verification code</div>
                                            <div class="otp-actions d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                                <div class="otp-value d-none fs-4 fw-bold text-primary letter-spacing-2"><?php echo htmlspecialchars($otpCode); ?></div>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button type="button" class="btn btn-primary btn-sm otp-toggle-btn">Show OTP</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm copy-otp-btn d-none" data-otp="<?php echo htmlspecialchars($otpCode); ?>">Copy OTP</button>
                                                </div>
                                            </div>
                                            <div class="text-muted small mt-2">Present this code at the reception desk when you arrive.</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info py-2 mb-0 small">OTP is being prepared. Please check back shortly or refresh the page.</div>
                                    <?php endif; ?>
                                <?php elseif ($appointmentStatus === 'Pending'): ?>
                                    <div class="alert alert-warning py-2 mb-0 small">⏳ Waiting for hospital approval. Your OTP will appear here once the appointment is approved.</div>
                                <?php elseif ($appointmentStatus === 'Cancelled'): ?>
                                    <div class="alert alert-danger py-2 mb-0 small">✕ This appointment was cancelled and no verification code is available.</div>
                                <?php else: ?>
                                    <div class="alert alert-secondary py-2 mb-0 small">No OTP details available for this appointment yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted">No appointments found yet. Book a new appointment to get started.</div>
        <?php endif; ?>
    </section>

    <?php if (!empty($patient_reminders)): ?>
        <section class="appointment-reminders card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <span class="section-label">Appointment Reminders</span>
                    <h5 class="mt-2 mb-1">Upcoming reminders</h5>
                    <p class="text-muted mb-0">These reminders were sent for your approved appointments.</p>
                </div>
            </div>
            <div class="row row-cols-1 g-3">
                <?php foreach ($patient_reminders as $reminder): ?>
                    <div class="col">
                        <div class="card border-primary shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                <p class="card-text mb-2"><?php echo htmlspecialchars($reminder['message']); ?></p>
                                <div class="text-muted small">
                                    Delivered at: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($reminder['delivered_at']))); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <section class="health-offers card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <span class="section-label">My Health Offers</span>
                <h5 class="mt-2 mb-1">Special health offers for your wellbeing</h5>
                <p class="text-muted mb-0">Save more with exclusive preventive care packages tailored for your health journey.</p>
            </div>
        </div>

        <div class="offer-container">
            <?php foreach ($healthOffers as $offer): ?>
                <div class="offer-card">
                    <div class="offer-image">
                        <img src="<?php echo htmlspecialchars(!empty($offer['image_path']) ? $offer['image_path'] : 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=900'); ?>" 
                             alt="<?php echo htmlspecialchars($offer['title']); ?>" 
                             loading="lazy" 
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=900';">
                        <?php if (!empty($offer['discount_percentage'])): ?>
                            <span class="discount"><?php echo htmlspecialchars($offer['discount_percentage']); ?>% OFF</span>
                        <?php endif; ?>
                        <?php if (!empty($offer['badge_emoji'])): ?>
                            <span class="popular"><?php echo htmlspecialchars($offer['badge_emoji']); ?> <?php echo htmlspecialchars($offer['badge_text'] ?? 'Offer'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="offer-content">
                        <h3><?php echo htmlspecialchars($offer['title']); ?></h3>
                        <p><?php echo htmlspecialchars($offer['description']); ?></p>
                        <div class="offer-time">⏰ Valid until <?php echo date('d M, Y', strtotime($offer['validity_end'])); ?></div>
                        <div class="price">
                            <span class="new-price">Rs.<?php echo number_format((float)$offer['discounted_price'], 0); ?></span>
                            <span class="old-price">Rs.<?php echo number_format((float)$offer['original_price'], 0); ?></span>
                        </div>
                        <a href="<?php echo htmlspecialchars($offer['booking_link']); ?>" class="book-btn">📅 Book Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($healthOffers)): ?>
                <div class="offer-card" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <p>No active health offers at the moment. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card p-4 mb-4">
        <style>
            .health-packages-dashboard .section-title {
                text-align: left;
                margin-bottom: 20px;
            }
            .health-packages-dashboard .section-title h2 {
                font-size: 28px;
                color: #0d6efd;
                margin-bottom: 6px;
            }
            .health-packages-dashboard .section-title p {
                color: #666;
                margin: 0;
            }
            .health-packages-dashboard .health-container {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 20px;
            }
            .health-packages-dashboard .health-card {
                background: #fff;
                border-radius: 18px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
                transition: .3s;
                height: 100%;
            }
            .health-packages-dashboard .health-card:hover {
                transform: translateY(-6px);
                box-shadow: 0 16px 36px rgba(0,0,0,.12);
            }
            .health-packages-dashboard .health-card img {
                width: 100%;
                height: 180px;
                object-fit: cover;
            }
            .health-packages-dashboard .health-body {
                padding: 18px;
            }
            .health-packages-dashboard .health-body h3 {
                color: #0d6efd;
                font-size: 18px;
                margin-bottom: 8px;
            }
            .health-packages-dashboard .tests {
                display: inline-block;
                background: #0d6efd;
                color: #fff;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 13px;
                margin-bottom: 12px;
            }
            .health-packages-dashboard .health-body p {
                color: #555;
                font-size: 14px;
                line-height: 1.6;
                min-height: 90px;
                margin-bottom: 12px;
            }
            .health-packages-dashboard .price {
                margin: 12px 0 14px;
            }
            .health-packages-dashboard .new-price {
                color: #28a745;
                font-size: 20px;
                font-weight: bold;
            }
            .health-packages-dashboard .old-price {
                color: #999;
                text-decoration: line-through;
                margin-left: 8px;
            }
            .health-packages-dashboard .buttons {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                margin-top: 12px;
            }
            .health-packages-dashboard .read-btn,
            .health-packages-dashboard .book-btn {
                text-decoration: none;
                padding: 9px 14px;
                border-radius: 8px;
                font-weight: 600;
                transition: .3s;
                font-size: 13px;
            }
            .health-packages-dashboard .read-btn {
                background: #eef5ff;
                color: #0d6efd;
            }
            .health-packages-dashboard .book-btn {
                background: #0d6efd;
                color: #fff;
            }
            .health-packages-dashboard .read-btn:hover { background: #d9e8ff; }
            .health-packages-dashboard .book-btn:hover { background: #0056d2; }
            @media (max-width: 1200px) {
                .health-packages-dashboard .health-container { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 768px) {
                .health-packages-dashboard .health-container { grid-template-columns: 1fr; }
                .health-packages-dashboard .buttons { flex-direction: column; }
            }
        </style>

        <div class="health-packages-dashboard">
            <div class="section-title">
                <h2>Health Checkup Packages</h2>
                <p>Choose the best preventive health package for you.</p>
            </div>

            <div class="health-container">
                <?php foreach ($healthOffers as $index => $offer): ?>
                    <?php if ($index < 4): // Limit to 4 cards in this section ?>
                        <div class="health-card">
                            <img src="<?php echo htmlspecialchars(!empty($offer['image_path']) ? $offer['image_path'] : 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=900'); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=900';">
                            <div class="health-body">
                                <h3><?php echo htmlspecialchars($offer['title']); ?></h3>
                                <span class="tests"><?php echo $offer['discount_percentage']; ?>% OFF</span>
                                <p><?php echo htmlspecialchars($offer['description']); ?></p>
                                <div class="price">
                                    <span class="new-price">Rs <?php echo number_format((float)$offer['discounted_price'], 0); ?></span>
                                    <span class="old-price">Rs <?php echo number_format((float)$offer['original_price'], 0); ?></span>
                                </div>
                                <div class="buttons">
                                    <a href="#" class="read-btn">Read More</a>
                                    <a href="<?php echo htmlspecialchars($offer['booking_link']); ?>" class="book-btn">Book Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4 stats-grid">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card p-3 h-100 d-flex flex-column justify-content-between">
                <span>Next Visits</span>
                <strong><?php echo (int)$upcomingSummary['next_appointments']; ?></strong>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card p-3 h-100 d-flex flex-column justify-content-between">
                <span>Pending</span>
                <strong><?php echo (int)$upcomingSummary['pending']; ?></strong>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card p-3 h-100 d-flex flex-column justify-content-between">
                <span>Confirmed</span>
                <strong><?php echo (int)$upcomingSummary['confirmed']; ?></strong>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card p-3 h-100 d-flex flex-column justify-content-between">
                <span>Completed</span>
                <strong><?php echo (int)$upcomingSummary['completed']; ?></strong>
            </div>
        </div>
    </section>

    <section class="card p-4 mb-4">
        <div class="mb-3">
            <span class="section-label">Announcements</span>
            <h5 class="mt-2 mb-1">Latest hospital updates</h5>
            <p class="text-muted mb-0">Stay informed with the newest service and care announcements.</p>
        </div>

        <?php if (!empty($visibleAnnouncements)): ?>
            <div class="row g-3">
                <?php foreach ($visibleAnnouncements as $announcement): ?>
                    <div class="col-12 col-lg-6">
                        <div class="record-card p-3 h-100">
                            <div class="d-flex justify-content-between mb-2">
                                <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                <span class="text-muted small"><?php echo htmlspecialchars(substr($announcement['created_at'] ?? '', 0, 10)); ?></span>
                            </div>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr(strip_tags($announcement['message']), 0, 90)); ?></p>
                            <a class="text-primary small" href="view_announcement.php?id=<?php echo (int)$announcement['id']; ?>">Read more</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted">No announcements at the moment.</div>
        <?php endif; ?>
    </section>

    <section class="card p-4 mb-4">
        <div class="mb-3">
            <span class="section-label">Doctor Roster</span>
            <h5 class="mt-2 mb-1">Our full medical team</h5>
            <p class="text-muted mb-0">Browse our doctors by specialty, availability, and experience in a clean card layout.</p>
        </div>

        <?php if (!empty($doctorProfiles)): ?>
            <div class="row g-4 justify-content-start">
                <?php foreach ($doctorProfiles as $doctor): ?>
                    <?php
                        $doctorStatus = $doctor['availability_status'] ?: $doctor['status'];
                        $profilePhoto = !empty($doctor['profile_photo']) ? trim((string)$doctor['profile_photo']) : 'assets/images/default-doctor.png';
                        $doctorName = trim((string)$doctor['name']) ?: 'Doctor';
                        $doctorSpecialty = htmlspecialchars($doctor['specialty'] ?: ($doctor['department'] ?: 'General Medicine'));
                        $doctorDept = htmlspecialchars($doctor['department'] ?: 'General Medicine');
                    ?>
                    <div class="col-12 col-md-6 col-lg-3 d-flex">
                        <div class="card h-100 shadow-sm w-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-3 gap-3">
                                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>"
                                         alt="<?php echo htmlspecialchars($doctorName); ?>"
                                         class="rounded-circle"
                                         style="width:72px; height:72px; object-fit:cover; border:2px solid #e9ecef;"
                                         onerror="this.onerror=null;this.src='assets/images/default-doctor.png';">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($doctorName); ?></h6>
                                        <p class="text-muted small mb-0"><?php echo $doctorDept; ?></p>
                                    </div>
                                </div>

                                <p class="mb-1"><strong>Specialty:</strong> <?php echo $doctorSpecialty; ?></p>
                                <p class="mb-1"><strong>Experience:</strong> <?php echo (int)($doctor['experience_years'] ?? 0); ?> yrs</p>
                                <p class="mb-1 small text-muted"><?php echo htmlspecialchars($doctor['qualifications'] ?: 'Board certified specialist'); ?></p>
                                <p class="small text-secondary mb-2"><?php echo htmlspecialchars($doctor['about'] ?: 'Bio not available.'); ?></p>

                                <div class="mt-3">
                                    <span class="badge <?php echo ($doctorStatus === 'Available') ? 'bg-success' : (($doctorStatus === 'Busy') ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($doctorStatus); ?>
                                    </span>
                                </div>

                                <div class="mt-3 mb-3">
                                    <p class="mb-1"><strong>Fee:</strong> NPR <?php echo number_format((float)($doctor['consultation_fee'] ?? 0), 2); ?></p>
                                    <p class="mb-0 small text-muted"><strong>Contact:</strong> <?php echo htmlspecialchars($doctor['contact_info'] ?: 'Not available'); ?></p>
                                    <p class="mb-0 small text-muted"><strong>Working days:</strong> <?php echo htmlspecialchars($doctor['working_days'] ?: 'Not set'); ?></p>
                                </div>

                                <div class="mt-auto d-flex gap-2">
                                    <a class="btn btn-sm btn-primary flex-fill" href="book-appointment.php?doctor_id=<?php echo (int)$doctor['id']; ?>">Book</a>
                                    <a class="btn btn-sm btn-outline-secondary flex-fill" href="doctor-profile.php?id=<?php echo (int)$doctor['id']; ?>">Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted">No doctor profiles are available yet. Please check back later.</div>
        <?php endif; ?>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-xl-6">
            <div class="card p-4 mb-3">
                <h5 class="mb-3">Your medical records</h5>
                <?php if (!empty($recentRecords)): ?>
                    <?php foreach ($recentRecords as $record): ?>
                        <div class="record-card p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?php echo htmlspecialchars($record['type']); ?></strong>
                                <span class="text-muted small"><?php echo htmlspecialchars($record['date']); ?></span>
                            </div>
                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($record['title']); ?> • <?php echo htmlspecialchars($record['detail']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted">Your latest tests and prescriptions will appear here.</div>
                <?php endif; ?>
                <div class="mt-3 text-end"><a class="small text-primary" href="medical_timeline.php">View all records</a></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card p-4">
                <h5 class="mb-3">Quick Actions</h5>
                <p class="text-muted small mb-0">Quick Actions are displayed in full width below for easier access.</p>
                <div class="mt-3"><a href="#quick-actions" class="small">Jump to Quick Actions</a></div>
            </div>
        </div>
    </section>

    <!-- Full-width Quick Actions -->
    <section id="quick-actions" class="card p-4 mb-4">
        <div class="container-fluid">
            <style>
                /* Scoped Quick Actions styles */
                #quick-actions .service-card { display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:18px; border-radius:12px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,0.04); transition:transform .12s ease, box-shadow .12s ease; color:inherit; text-decoration:none; }
                #quick-actions .service-card:focus, #quick-actions .service-card:hover { transform:translateY(-6px); box-shadow:0 14px 32px rgba(13,110,253,0.07); }
                #quick-actions .service-card .sa-icon { display:flex; align-items:center; justify-content:center; width:88px; height:88px; border-radius:50%; background:#f1f5f9; font-size:40px; margin-bottom:12px; }
                #quick-actions .service-card .sa-title { font-size:16px; font-weight:700; margin:0 0 6px 0; }
                #quick-actions .service-card .sa-desc { margin:0; color:#6b7280; font-size:13px; }
                #quick-actions .service-card { cursor:pointer; }
                @media (prefers-reduced-motion: reduce) { #quick-actions .service-card { transition:none; } }
            </style>

            <h5 class="mb-3">Quick Actions</h5>
            <div class="row gx-3 gy-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="book-appointment.php">
                        <div class="sa-icon"><i class="bi bi-calendar2-plus" aria-hidden="true"></i></div>
                        <div class="sa-title">Book Appointment</div>
                        <div class="sa-desc">Schedule care fast.</div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="my_lab_reports.php">
                        <div class="sa-icon"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></div>
                        <div class="sa-title">Lab Reports</div>
                        <div class="sa-desc">Review results.</div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="buy_medicine.php">
                        <div class="sa-icon"><i class="bi bi-capsule" aria-hidden="true"></i></div>
                        <div class="sa-title">Pharmacy</div>
                        <div class="sa-desc">Order medicines.</div>
                        
                        
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="patient-billing.php">
                        <div class="sa-icon"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i></div>
                        <div class="sa-title">Billing</div>
                        <div class="sa-desc">View invoices.</div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="patient-chat.php">
                        <div class="sa-icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i></div>
                        <div class="sa-title">Chat with Doctor</div>
                        <div class="sa-desc">Message your doctor.</div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="chatbot.php">
                        <div class="sa-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></div>
                        <div class="sa-title">Ask Hospital</div>
                        <div class="sa-desc">Search hospital information.</div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="service-card d-block h-100" href="feedback.php">
                        <div class="sa-icon"><i class="bi bi-star" aria-hidden="true"></i></div>
                        <div class="sa-title">Feedback</div>
                        <div class="sa-desc">Share your experience.</div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <span class="section-label">Hospital Departments</span>
                <h5 class="mt-2 mb-1">Specialist departments for every care need</h5>
                <p class="text-muted mb-0">Choose a department to view relevant specialists, then book a consultation with a simple fee of NPR 100.</p>
            </div>
        </div>
        <div class="department-grid">
            <?php foreach ($departmentCatalog as $slug => $department): ?>
                <?php $isActive = $selectedDepartment && $selectedDepartmentSlug === $slug; ?>
                <?php $departmentImage = !empty($department['image']) ? $department['image'] : 'assets/images/default-doctor.png'; ?>
                <div class="department-showcase-card <?php echo $isActive ? 'active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($departmentImage); ?>" alt="<?php echo htmlspecialchars($department['name']); ?>" class="department-showcase-image" onerror="this.onerror=null;this.src='assets/images/default-doctor.png';">
                    <div class="department-showcase-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h6 class="mb-1"><?php echo htmlspecialchars($department['name']); ?></h6>
                            <span class="department-fee">NPR 100</span>
                        </div>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($department['description'] ?: ($departmentDefinitions[$slug]['description'] ?? 'Specialized patient care.')); ?></p>
                        <a class="btn btn-sm btn-primary" href="dashboard.php?department=<?php echo urlencode($slug); ?>&department_id=<?php echo (int)($department['id'] ?? 0); ?>#department-doctors">Book Appointment</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="department-doctors" class="mt-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <span class="section-label">Department doctors</span>
                    <h5 class="mt-2 mb-1">
                        <?php echo $selectedDepartment ? htmlspecialchars($selectedDepartment['name']) : 'Select a department'; ?>
                    </h5>
                    <p class="text-muted mb-0">Only specialists matching the selected department are shown here.</p>
                </div>
                <span class="text-muted small">Booking fee: NPR 100</span>
            </div>
            <?php if ($selectedDepartment): ?>
                <?php if (!empty($departmentDoctors)): ?>
                    <div class="row g-3">
                        <?php foreach ($departmentDoctors as $doctor): ?>
                            <?php $doctorStatus = $doctor['availability_status'] ?: $doctor['status']; ?>
                            <div class="col-lg-6">
                                <div class="doctor-inline-card h-100 text-center p-4 shadow rounded">
                                    <img src="<?php echo !empty($doctor['profile_photo']) ? htmlspecialchars($doctor['profile_photo']) : 'assets/images/default-doctor.png'; ?>"
                                         alt="<?php echo htmlspecialchars($doctor['name']); ?>"
                                         class="rounded-circle mb-3"
                                         width="120"
                                         height="120"
                                         style="object-fit:cover;border:4px solid #0d6efd;">


                                    <h4 class="text-primary mb-1"><?php echo htmlspecialchars($doctor['name']); ?></h4>
                                    <div class="text-muted small mb-2"><?php echo htmlspecialchars($doctor['specialty'] ?: ($doctor['department'] ?: $selectedDepartment['name'])); ?></div>
                                    <p class="mb-2"><strong><?php echo (int)($doctor['experience_years'] ?? 0); ?></strong> Years Experience</p>
                                    <p class="small text-secondary mb-2"><?php echo htmlspecialchars($doctor['qualifications'] ?: 'Board certified specialist'); ?></p>
                                    <p class="small text-secondary mb-2"><?php echo htmlspecialchars($doctor['about'] ?: 'Bio not available.'); ?></p>
                                    <div class="text-warning mb-2" style="font-size:18px;">★★★★★</div>

                                    <span class="badge <?php echo ($doctorStatus === 'Available') ? 'bg-success' : (($doctorStatus === 'Busy') ? 'bg-warning text-dark' : 'bg-secondary'); ?> mb-3"><?php echo htmlspecialchars($doctorStatus); ?></span>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a class="btn btn-sm btn-outline-primary" href="doctor-profile.php?id=<?php echo (int)$doctor['id']; ?>">View profile</a>
                                        <span class="text-muted small">NPR <?php echo number_format((float)($doctor['consultation_fee'] ?? 100), 2); ?></span>
                                    </div>

                                    <div class="mt-3">
                                        <a class="btn btn-primary" href="book-appointment.php?doctor_id=<?php echo (int)$doctor['id']; ?>&department=<?php echo urlencode((string)($doctor['department'] ?: $selectedDepartment['name'] ?: $doctor['specialty'])); ?>&department_id=<?php echo (int)($doctor['department_id'] ?? resolve_department_id_by_name($conn, (string)($doctor['department'] ?: $selectedDepartment['name'] ?: $doctor['specialty']))); ?>">Book Appointment</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No doctors are currently listed for this department. Please try another department.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted">Select any department card above to view matching doctors and book a consultation.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Booking form removed from dashboard (kept in book-appointment.php) -->

    <section class="card p-4 appointment-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="section-label">Appointments</span>
                <h5 class="mt-2 mb-1">Your appointment history</h5>
            </div>
            <a class="text-primary small" href="view_appointments.php">View all</a>
        </div>
        <?php if (count($appointments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0 appointment-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="appointment-row">
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                                <td><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></td>
                                <td><span class="status-pill status-<?php echo htmlspecialchars($appointment['status']); ?>"><?php echo htmlspecialchars($appointment['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">No appointments found yet. Book a new appointment to get started.</div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('click', async function (event) {
    const toggleButton = event.target.closest('.otp-toggle-btn');
    if (toggleButton) {
        const actions = toggleButton.closest('.otp-actions');
        const otpValue = actions ? actions.querySelector('.otp-value') : null;
        const copyButton = actions ? actions.querySelector('.copy-otp-btn') : null;

        if (!otpValue || !copyButton) {
            return;
        }

        const isVisible = !otpValue.classList.contains('d-none');
        otpValue.classList.toggle('d-none', isVisible);
        copyButton.classList.toggle('d-none', isVisible);
        toggleButton.textContent = isVisible ? 'Show OTP' : 'Hide OTP';
        return;
    }

    const button = event.target.closest('.copy-otp-btn');
    if (!button) {
        return;
    }

    const otp = button.getAttribute('data-otp');
    if (!otp) {
        return;
    }

    const originalText = button.textContent;
    try {
        await navigator.clipboard.writeText(otp);
        button.textContent = 'Copied';
        window.setTimeout(function () {
            button.textContent = originalText;
        }, 1500);
    } catch (error) {
        button.textContent = 'Copy failed';
        window.setTimeout(function () {
            button.textContent = originalText;
        }, 1500);
    }
});
</script>

<?php include 'footer.php'; ?>

