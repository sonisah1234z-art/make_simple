<?php
// Seed script: populates realistic sample data for core modules when empty.
require_once __DIR__ . '/../db.php';

if (!($conn instanceof mysqli)) {
    echo "No database connection.\n";
    exit(1);
}

function table_count(mysqli $c, string $t): int {
    $r = $c->query("SELECT COUNT(*) AS cnt FROM `$t`");
    return $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

// Seed patients
if (table_count($conn, 'patients') === 0) {
    $patients = [
        ['Aarav Sharma','aarav.sharma@example.com','+977-9801000001'],
        ['Maya Joshi','maya.joshi@example.com','+977-9801000002'],
        ['Kiran Thapa','kiran.thapa@example.com','+977-9801000003'],
        ['Sita Rai','sita.rai@example.com','+977-9801000004'],
        ['Ramesh Adhikari','ramesh.adhikari@example.com','+977-9801000005'],
        ['Priya Lama','priya.lama@example.com','+977-9801000006'],
        ['Sunil KC','sunil.kc@example.com','+977-9801000007'],
        ['Anita Gurung','anita.gurung@example.com','+977-9801000008'],
        ['Bikram Rai','bikram.rai@example.com','+977-9801000009'],
        ['Namrata Shrestha','namrata.shrestha@example.com','+977-9801000010']
    ];
    $stmt = $conn->prepare("INSERT INTO patients (name, email, phone, password) VALUES (?, ?, ?, ?)");
    $pw = password_hash('patient123', PASSWORD_DEFAULT);
    foreach ($patients as $p) {
        [$name,$email,$phone] = $p;
        $stmt->bind_param('ssss', $name, $email, $phone, $pw);
        $stmt->execute();
    }
    $stmt->close();
    echo "Seeded patients.\n";
}

// Seed medicines
if (table_count($conn, 'medicines') === 0) {
    $meds = [
        ['Paracetamol','Analgesic',100,1.50,'2027-05-01'],
        ['Amoxicillin','Antibiotic',50,6.00,'2026-12-31'],
        ['Omeprazole','Gastro',80,4.25,'2028-03-15'],
        ['Metformin','Diabetes',60,8.75,'2027-11-20'],
        ['Cetirizine','Antihistamine',120,2.00,'2029-01-01']
    ];
    $stmt = $conn->prepare("INSERT INTO medicines (medicine_name, category, stock, price, expiry_date) VALUES (?, ?, ?, ?, ?)");
    foreach ($meds as $m) {
        [$name,$cat,$stock,$price,$exp] = $m;
        $stmt->bind_param('ssids', $name, $cat, $stock, $price, $exp);
        $stmt->execute();
    }
    $stmt->close();
    echo "Seeded medicines.\n";
}

// Seed appointments and billings
if (table_count($conn, 'appointments') === 0) {
    $patientIds = [];
    $res = $conn->query("SELECT id FROM patients ORDER BY id ASC LIMIT 10");
    while ($r = $res->fetch_assoc()) { $patientIds[] = (int)$r['id']; }

    $doctorIds = [];
    $res = $conn->query("SELECT id FROM doctors ORDER BY id ASC LIMIT 10");
    while ($r = $res->fetch_assoc()) { $doctorIds[] = (int)$r['id']; }

    if (!empty($patientIds) && !empty($doctorIds)) {
        $apptStmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'Pending')");
        $billStmt = $conn->prepare("INSERT INTO billings (appointment_id, patient_id, amount, description, status) VALUES (?, ?, ?, ?, 'Pending')");
        $i = 0;
        foreach ($patientIds as $pid) {
            $did = $doctorIds[$i % count($doctorIds)];
            $date = date('Y-m-d', strtotime("+" . ($i+1) . " days"));
            $time = sprintf('%02d:00:00', 9 + ($i % 6));
            $apptStmt->bind_param('iiss', $pid, $did, $date, $time);
            $apptStmt->execute();
            $apptId = $conn->insert_id;
            $amount = 50 + ($i * 5);
            $desc = 'Consultation fee';
            $billStmt->bind_param('iids', $apptId, $pid, $amount, $desc);
            $billStmt->execute();
            $i++;
        }
        $apptStmt->close();
        $billStmt->close();
        echo "Seeded appointments and billings.\n";
    }
}

// Seed lab tests
if (table_count($conn, 'lab_tests') === 0) {
    $pids = [];
    $res = $conn->query("SELECT id FROM patients ORDER BY id ASC LIMIT 5");
    while ($r = $res->fetch_assoc()) { $pids[] = (int)$r['id']; }
    $tech = $conn->query("SELECT id FROM lab_technicians LIMIT 1")->fetch_assoc()['id'] ?? null;
    if (!empty($pids)) {
        $stmt = $conn->prepare("INSERT INTO lab_tests (patient_id, test_type, result) VALUES (?, ?, ?)");
        foreach ($pids as $idx => $pid) {
            $type = ['Blood Test','Urine Test','X-Ray','MRI','COVID-19 PCR'][$idx % 5];
            $result = 'Normal';
            $stmt->bind_param('iss', $pid, $type, $result);
            $stmt->execute();
        }
        $stmt->close();
        echo "Seeded lab tests.\n";
    }
}

// Seed ambulance bookings
if (table_count($conn, 'ambulance_bookings') === 0) {
    $pid = $conn->query("SELECT id FROM patients LIMIT 1")->fetch_assoc()['id'] ?? null;
    $driver = $conn->query("SELECT id FROM drivers LIMIT 1")->fetch_assoc()['id'] ?? null;
    $amb = $conn->query("SELECT id FROM ambulances LIMIT 1")->fetch_assoc()['id'] ?? null;
    if ($pid) {
        $stmt = $conn->prepare("INSERT INTO ambulance_bookings (patient_id, patient_name, phone, pickup_location, destination, status, driver_id, ambulance_id) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $name = $conn->query("SELECT name FROM patients LIMIT 1")->fetch_assoc()['name'];
        $phone = $conn->query("SELECT phone FROM patients LIMIT 1")->fetch_assoc()['phone'];
        $pickup = 'Home Address'; $dest = 'City Hospital';
        $stmt->bind_param('issssii', $pid, $name, $phone, $pickup, $dest, $driver, $amb);
        $stmt->execute();
        $stmt->close();
        echo "Seeded ambulance booking.\n";
    }
}

// Seed announcements
// Ensure announcements table exists then seed
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (table_count($conn, 'announcements') === 0) {
    $stmt = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
    $stmt->bind_param('ss', $t, $m);
    $t = 'COVID-19 Vaccination Drive'; $m = 'Free vaccination camp this Saturday. Book your slot.'; $stmt->execute();
    $stmt->close();
    echo "Seeded announcements.\n";
}

// Seed health_offers if empty
if (table_count($conn, 'health_offers') === 0) {
    $stmt = $conn->prepare("INSERT INTO health_offers (title, description, image_path, original_price, discounted_price, discount_percentage, validity_start, validity_end, badge_text, badge_emoji, booking_link, is_active, is_popular, sort_order, offer_type, num_tests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 'package', 0)");
    $start = date('Y-m-d'); $end = date('Y-m-d', strtotime('+60 days'));
    $stmt->bind_param('sssddisssssi', $title, $desc, $img, $orig, $disc, $percent, $start, $end, $badge, $emoji, $link, $dummy);
    // simple offer
    $title = 'Full Body Checkup'; $desc = 'Comprehensive checkup with reports.'; $img = 'assets/images/offer-fullbody.jpg'; $orig = 7500.00; $disc = 5500.00; $percent = 27; $badge = '30% OFF'; $emoji = '⭐'; $link = 'book-appointment.php'; $dummy = 0;
    $stmt->execute();
    $stmt->close();
    echo "Seeded health offers.\n";
}

echo "Seeding complete.\n";

?>
