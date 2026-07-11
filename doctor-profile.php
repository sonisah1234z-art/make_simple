<?php
session_start();
include 'db.php';

function ensure_doctor_profile_columns(mysqli $conn): void
{
    $columns = [
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

    foreach ($columns as $column => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM doctors LIKE '$column'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

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

function get_doctor_profile_defaults(string $specialty): array
{
    $defaults = [
        'photo' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=900&q=80',
        'qualifications' => 'Board certified specialist',
        'about' => 'Committed to providing compassionate, evidence-based treatment for every patient.',
        'contact_info' => 'Please contact the hospital reception at +977-9800000000',
        'working_days' => 'Mon-Fri',
        'available_slots' => '09:00,11:00,14:00',
        'fee' => 100.00,
    ];

    $specialty = strtolower(trim($specialty));
    $map = [
        'cardiology' => [
            'photo' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Cardiology)',
            'about' => 'Expert in heart health, hypertension management and advanced cardiac care.',
            'contact_info' => 'Call +977-9801234567 for Cardiology appointments',
            'working_days' => 'Mon-Wed-Fri',
            'available_slots' => '09:00,11:00,14:00',
            'fee' => 120.00,
        ],
        'cardiologist' => [
            'photo' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Cardiology)',
            'about' => 'Expert in heart health, hypertension management and advanced cardiac care.',
            'contact_info' => 'Call +977-9801234567 for Cardiology appointments',
            'working_days' => 'Mon-Wed-Fri',
            'available_slots' => '09:00,11:00,14:00',
            'fee' => 120.00,
        ],
        'neurology' => [
            'photo' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, DM (Neurology)',
            'about' => 'Specialized in stroke care, epilepsy treatment and neuro-rehabilitation.',
            'contact_info' => 'Call +977-9802345678 for Neurology appointments',
            'working_days' => 'Tue-Thu',
            'available_slots' => '10:00,13:00,15:00',
            'fee' => 115.00,
        ],
        'pediatrics' => [
            'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, DCH',
            'about' => 'Dedicated to child wellness, preventive care and family-centered medicine.',
            'contact_info' => 'Call +977-9803456789 for Pediatrics appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '08:30,12:30,16:00',
            'fee' => 95.00,
        ],
        'pediatrician' => [
            'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, DCH',
            'about' => 'Dedicated to child wellness, preventive care and family-centered medicine.',
            'contact_info' => 'Call +977-9803456789 for Pediatrics appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '08:30,12:30,16:00',
            'fee' => 95.00,
        ],
        'dermatology' => [
            'photo' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Dermatology)',
            'about' => 'Focused on skin health, cosmetic dermatology and chronic condition management.',
            'contact_info' => 'Call +977-9804567890 for Dermatology appointments',
            'working_days' => 'Mon-Wed-Fri',
            'available_slots' => '09:30,12:00,15:00',
            'fee' => 90.00,
        ],
        'orthopedic' => [
            'photo' => 'https://images.unsplash.com/photo-1507537297725-24a1c029d3ca?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MS (Orthopedics)',
            'about' => 'Expert in joint care, sports injuries, fractures and orthopedic surgery.',
            'contact_info' => 'Call +977-9805678901 for Orthopedics appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '09:00,12:00,15:00',
            'fee' => 130.00,
        ],
        'ent' => [
            'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MS (ENT)',
            'about' => 'Provides complete ear, nose and throat care with a patient-focused approach.',
            'contact_info' => 'Call +977-9806789012 for ENT appointments',
            'working_days' => 'Tue-Thu',
            'available_slots' => '10:00,13:00,16:00',
            'fee' => 100.00,
        ],
        'gynecology' => [
            'photo' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Obstetrics & Gynecology)',
            'about' => 'Experienced in maternity care, women’s health and reproductive medicine.',
            'contact_info' => 'Call +977-9807890123 for Gynecology appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '09:30,12:30,15:30',
            'fee' => 125.00,
        ],
        'obstetrics' => [
            'photo' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Obstetrics & Gynecology)',
            'about' => 'Experienced in maternity care, women’s health and reproductive medicine.',
            'contact_info' => 'Call +977-9807890123 for Gynecology appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '09:30,12:30,15:30',
            'fee' => 125.00,
        ],
        'nephrology' => [
            'photo' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, DM (Nephrology)',
            'about' => 'Expert in kidney disease management, dialysis planning and hypertension care.',
            'contact_info' => 'Call +977-9808901234 for Nephrology appointments',
            'working_days' => 'Mon-Wed-Fri',
            'available_slots' => '09:00,11:00,14:00',
            'fee' => 118.00,
        ],
        'urology' => [
            'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MS (Urology)',
            'about' => 'Specializes in urological health, minimally invasive surgery and transplant planning.',
            'contact_info' => 'Call +977-9809012345 for Urology appointments',
            'working_days' => 'Tue-Thu',
            'available_slots' => '10:00,13:00,16:00',
            'fee' => 110.00,
        ],
        'psychiatry' => [
            'photo' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'qualifications' => 'MBBS, MD (Psychiatry)',
            'about' => 'Provides compassionate mental health care for anxiety, depression and stress management.',
            'contact_info' => 'Call +977-9810123456 for Psychiatry appointments',
            'working_days' => 'Mon-Fri',
            'available_slots' => '09:00,11:00,14:00',
            'fee' => 105.00,
        ],
    ];

    foreach ($map as $key => $value) {
        if ($key !== '' && str_contains($specialty, $key)) {
            return $value;
        }
    }

    return $defaults;
}

ensure_doctor_profile_columns($conn);

$doctorId = (int)($_GET['id'] ?? 0);
$doctor = null;

if ($doctorId > 0) {
    $stmt = $conn->prepare('SELECT id, name, specialty, email, phone, status, profile_photo, qualifications, experience_years, consultation_fee, department, contact_info, working_days, available_slots, about, availability_status FROM doctors WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$doctor) {
    header('Location: book-appointment.php');
    exit();
}

$defaults = get_doctor_profile_defaults($doctor['specialty'] ?? '');
$doctorSlots = parse_doctor_slots($doctor['available_slots'] ?: $defaults['available_slots']);
$doctorStatus = $doctor['availability_status'] ?: $doctor['status'];
$doctorStatusLabel = $doctorStatus === 'Available' ? 'Available' : 'Unavailable';
$photoUrl = $doctor['profile_photo'] ?: $defaults['photo'];
$consultationFeeValue = (float)($doctor['consultation_fee'] ?? 0);
$consultationFee = number_format($consultationFeeValue > 0 ? $consultationFeeValue : $defaults['fee'], 2);
$department = $doctor['department'] ?: $doctor['specialty'];
$qualifications = $doctor['qualifications'] ?: $defaults['qualifications'];
$about = $doctor['about'] ?: $defaults['about'];
$contactInfo = $doctor['contact_info'] ?: ($doctor['phone'] ?: $defaults['contact_info']);
$workingDays = $doctor['working_days'] ?: $defaults['working_days'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($doctor['name']); ?> - Doctor Profile</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7fb; color: #0f172a; }
    .page { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .card { background: #fff; border-radius: 24px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); overflow: hidden; }
    .hero { display: grid; grid-template-columns: 300px 1fr; gap: 24px; padding: 24px; }
    .hero img { width: 100%; height: 100%; max-height: 320px; object-fit: cover; border-radius: 20px; }
    .pill { display: inline-block; padding: 8px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    .pill.available { background: #dcfce7; color: #166534; }
    .pill.unavailable { background: #fee2e2; color: #b91c1c; }
    .content { display: grid; gap: 16px; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 0 24px 24px; }
    .info-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; background: #f8fafc; }
    .info-card h3 { font-size: 15px; margin-bottom: 8px; color: #2563eb; }
    .info-card p, .info-card li { color: #475569; margin: 0; line-height: 1.6; }
    .actions { padding: 0 24px 24px; display: flex; flex-wrap: wrap; gap: 10px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; text-decoration: none; border-radius: 12px; font-weight: 700; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-secondary { background: #e2e8f0; color: #0f172a; }
    ul { padding-left: 18px; }
    @media (max-width: 900px) { .hero { grid-template-columns: 1fr; } .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="hero">
            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>">
            <div class="content">
                <div class="pill <?php echo $doctorStatusLabel === 'Available' ? 'available' : 'unavailable'; ?>"><?php echo htmlspecialchars($doctorStatusLabel); ?></div>
                <h1><?php echo htmlspecialchars($doctor['name']); ?></h1>
                <p style="color:#64748b; font-size:16px;"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                <p style="line-height:1.7; color:#334155;"><?php echo htmlspecialchars($about); ?></p>
                <div class="actions">
                    <a class="btn btn-primary" href="book-appointment.php?doctor_id=<?php echo (int)$doctor['id']; ?>&department=<?php echo urlencode($doctor['department'] ?: $doctor['specialty']); ?>">Book Appointment</a>
                    <a class="btn btn-secondary" href="book-appointment.php">View All Doctors</a>
                </div>
            </div>
        </div>
        <div class="grid">
            <div class="info-card">
                <h3>Qualifications</h3>
                <p><?php echo htmlspecialchars($qualifications); ?></p>
            </div>
            <div class="info-card">
                <h3>Experience</h3>
                <p><?php echo (int)$doctor['experience_years']; ?> years of professional experience</p>
            </div>
            <div class="info-card">
                <h3>Department</h3>
                <p><?php echo htmlspecialchars($department); ?></p>
            </div>
            <div class="info-card">
                <h3>Consultation Fee</h3>
                <p>$<?php echo htmlspecialchars($consultationFee); ?></p>
            </div>
            <div class="info-card">
                <h3>Contact Information</h3>
                <p><?php echo htmlspecialchars($doctor['contact_info'] ?: ($doctor['phone'] ?: 'Please contact the hospital reception')); ?></p>
            </div>
            <div class="info-card">
                <h3>Working Days</h3>
                <p><?php echo htmlspecialchars($doctor['working_days'] ?: 'Mon-Fri'); ?></p>
            </div>
            <div class="info-card">
                <h3>Available Time Slots</h3>
                <ul>
                    <?php foreach ($doctorSlots as $slot): ?>
                        <li><?php echo htmlspecialchars($slot); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="info-card">
                <h3>Availability</h3>
                <p><?php echo htmlspecialchars($doctorStatusLabel === 'Available' ? 'Currently accepting appointments' : 'Temporarily unavailable for new bookings'); ?></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
