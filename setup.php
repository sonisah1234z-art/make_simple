<?php
// Run once in local XAMPP to create the database, tables, and sample data.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hospital_system';

try {
    $conn = new mysqli($host, $username, $password);
    $conn->set_charset('utf8mb4');
    $conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($database);

    function runQuery(mysqli $conn, string $sql, string $message): void
    {
        $conn->query($sql);
        echo htmlspecialchars($message) . '<br>';
    }

    runQuery($conn, "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Admins table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(50),
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Patients table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        specialty VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(50),
        password VARCHAR(255),
        status VARCHAR(20) NOT NULL DEFAULT 'Offline',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Doctors table ready.");

    $doctorPasswordColumn = $conn->query("SHOW COLUMNS FROM doctors LIKE 'password'");
    if ($doctorPasswordColumn && $doctorPasswordColumn->num_rows === 0) {
        $conn->query("ALTER TABLE doctors ADD COLUMN password VARCHAR(255) NULL");
    }

    runQuery($conn, "CREATE TABLE IF NOT EXISTS lab_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        test_type VARCHAR(255) NOT NULL,
        result TEXT NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        report_date DATE DEFAULT CURRENT_DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )", "Lab tests table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        rating INT NOT NULL DEFAULT 0,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Feedback table ready.");
    $doctorStatusColumn = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
    if ($doctorStatusColumn && $doctorStatusColumn->num_rows === 0) {
        $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
    }

    runQuery($conn, "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )", "Appointments table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS billings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NULL,
        patient_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        description VARCHAR(255),
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )", "Billings table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS nurses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(50),
        department VARCHAR(255),
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Nurses table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS pharmacists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Pharmacists table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS receptionists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Receptionists table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS nurse_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_id INT NULL,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        vitals VARCHAR(255),
        note TEXT,
        attendance VARCHAR(20) DEFAULT 'Present',
        duty_notes TEXT,
        record_date DATE DEFAULT (CURRENT_DATE),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE SET NULL
    )", "Nurse records table ready.");

    foreach ([
        'patient_id INT NULL',
        'vitals VARCHAR(255)',
        'note TEXT',
        "attendance VARCHAR(20) DEFAULT 'Present'"
    ] as $columnDefinition) {
        $columnName = strtok($columnDefinition, ' ');
        $columnCheck = $conn->query("SHOW COLUMNS FROM nurse_records LIKE '$columnName'");
        if ($columnCheck && $columnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE nurse_records ADD COLUMN $columnDefinition");
        }
    }

    runQuery($conn, "CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_id INT NULL,
        shift_date DATE NOT NULL,
        shift_time VARCHAR(100) NOT NULL,
        ward VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE SET NULL
    )", "Shifts table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS nurse_shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_name VARCHAR(255) NOT NULL,
        shift_type VARCHAR(100) NOT NULL,
        shift_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Nurse shifts table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS medicines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_name VARCHAR(255) NOT NULL,
        category VARCHAR(255),
        stock INT DEFAULT 0,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        expiry_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Medicines table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS medicine_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        medicine_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Medicine orders table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        license_number VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(50) NOT NULL,
        status VARCHAR(50) DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Drivers table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS ambulances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_number VARCHAR(100) UNIQUE NOT NULL,
        driver_id INT NULL,
        status VARCHAR(50) DEFAULT 'Available',
        location VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
    )", "Ambulances table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS ambulance_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        phone VARCHAR(50),
        pickup_location VARCHAR(255) NOT NULL,
        destination VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        driver_id INT NULL,
        ambulance_id INT NULL,
        booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estimated_arrival TIMESTAMP NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
        FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
        FOREIGN KEY (ambulance_id) REFERENCES ambulances(id) ON DELETE SET NULL
    )", "Ambulance bookings table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_donors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(255),
        last_donation_date DATE,
        status VARCHAR(50) DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Blood donors table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_bank (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blood_group VARCHAR(10) NOT NULL,
        units_available INT DEFAULT 0,
        expiry_date DATE,
        storage_location VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Blood bank table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        blood_group VARCHAR(10) NOT NULL,
        units_needed INT NOT NULL,
        urgency VARCHAR(50) DEFAULT 'Normal',
        status VARCHAR(50) DEFAULT 'Pending',
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    )", "Blood requests table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS organ_donors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        organ_type VARCHAR(100) NOT NULL,
        blood_group VARCHAR(10),
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(255),
        medical_history TEXT,
        status VARCHAR(50) DEFAULT 'Registered',
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Organ donors table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS organ_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        organ_type VARCHAR(100) NOT NULL,
        urgency VARCHAR(50) DEFAULT 'Normal',
        blood_group VARCHAR(10),
        status VARCHAR(50) DEFAULT 'Pending',
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    )", "Organ requests table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccine_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vaccine_name VARCHAR(255) NOT NULL,
        batch_number VARCHAR(100) NOT NULL,
        manufacturer VARCHAR(255),
        expiry_date DATE NOT NULL,
        available_doses INT DEFAULT 0,
        storage_temp VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", "Vaccine inventory table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        vaccine_name VARCHAR(255) NOT NULL,
        dose_number INT NOT NULL,
        vaccination_date DATE NOT NULL,
        next_due_date DATE,
        administered_by VARCHAR(255),
        batch_number VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Completed',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    )", "Vaccinations table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccination_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        patient_name VARCHAR(255),
        vaccine_name VARCHAR(255) NOT NULL,
        reminder_date DATE NOT NULL,
        reminder_type VARCHAR(50) DEFAULT 'Next Dose',
        status VARCHAR(50) DEFAULT 'Pending',
        sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    )", "Vaccination reminders table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS beds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bed_number VARCHAR(50) UNIQUE NOT NULL,
        ward VARCHAR(100) DEFAULT 'General',
        is_icu TINYINT(1) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Available',
        patient_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    )", "Beds table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS appointment_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL UNIQUE,
        patient_id INT NOT NULL,
        otp_code VARCHAR(6) NOT NULL UNIQUE,
        generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        verification_status ENUM('Pending', 'Verified', 'Expired') DEFAULT 'Pending',
        verification_attempts INT DEFAULT 0,
        last_attempt_at DATETIME NULL,
        created_by INT NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        INDEX idx_appointment (appointment_id),
        INDEX idx_patient (patient_id),
        INDEX idx_status (verification_status),
        INDEX idx_expires (expires_at)
    )", "Appointment OTPs table ready.");

    runQuery($conn, "CREATE TABLE IF NOT EXISTS otp_verification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        otp_id INT NOT NULL,
        attempted_otp VARCHAR(6) NOT NULL,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        attempt_status ENUM('Success', 'Failed', 'Expired') DEFAULT 'Failed',
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        FOREIGN KEY (otp_id) REFERENCES appointment_otps(id) ON DELETE CASCADE,
        INDEX idx_otp (otp_id),
        INDEX idx_time (attempt_time)
    )", "OTP logs table ready.");

    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO admins (name, username, password) VALUES ('Hospital Admin', 'admin', ?)");
    $stmt->bind_param('s', $adminPassword);
    $stmt->execute();
    echo "Default admin ready: username=admin, password=admin123<br>";

    $pharmacistPassword = password_hash('pharmacist123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO pharmacists (name, username, password) VALUES ('Hospital Pharmacist', 'pharmacist', ?)");
    $stmt->bind_param('s', $pharmacistPassword);
    $stmt->execute();
    echo "Default pharmacist ready: username=pharmacist, password=pharmacist123<br>";

    $nursePassword = password_hash('nurse123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO nurses (nurse_name, email, phone, department, password) VALUES ('Sample Nurse', 'nurse@hospital.com', '+1234567800', 'General Ward', ?)");
    $stmt->bind_param('s', $nursePassword);
    $stmt->execute();
    echo "Default nurse ready: email=nurse@hospital.com, password=nurse123<br>";

    $receptionistPassword = password_hash('receptionist123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO receptionists (name, username, password) VALUES ('Hospital Receptionist', 'receptionist', ?)");
    $stmt->bind_param('s', $receptionistPassword);
    $stmt->execute();
    echo "Default receptionist ready: username=receptionist, password=receptionist123<br>";

    $conn->query("INSERT IGNORE INTO doctors (name, specialty, email, phone) VALUES
        ('Dr. Sara Khan', 'General Physician', 'sara.khan@hospital.com', '+1234567890'),
        ('Dr. Aisha Mahmood', 'Pediatrician', 'aisha.mahmood@hospital.com', '+1234567892'),
        ('Dr. Bilal Qureshi', 'Orthopedic Surgeon', 'bilal.qureshi@hospital.com', '+1234567893'),
        ('Dr. Nadia Farooq', 'Neurologist', 'nadia.farooq@hospital.com', '+1234567894')");
    $doctorPassword = password_hash('doctor123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE doctors SET password = ? WHERE password IS NULL OR password = ''");
    $stmt->bind_param('s', $doctorPassword);
    $stmt->execute();
    echo "Sample doctors ready.<br>";

    $conn->query("INSERT IGNORE INTO drivers (name, license_number, phone, status) VALUES
        ('Ahmed Hassan', 'DL123456789', '+1234567892', 'Available'),
        ('Fatima Ali', 'DL987654321', '+1234567893', 'Available'),
        ('Muhammad Khan', 'DL456789123', '+1234567894', 'Available')");
    echo "Sample drivers ready.<br>";

    $conn->query("INSERT IGNORE INTO ambulances (vehicle_number, driver_id, status, location) VALUES
        ('AMB-001', 1, 'Available', 'Hospital Parking'),
        ('AMB-002', 2, 'Available', 'Hospital Parking'),
        ('AMB-003', NULL, 'Maintenance', 'Service Center')");
    echo "Sample ambulances ready.<br>";

    $conn->query("INSERT IGNORE INTO blood_bank (blood_group, units_available, expiry_date, storage_location) VALUES
        ('A+', 15, '2026-12-31', 'Refrigerator A'),
        ('B+', 12, '2026-12-31', 'Refrigerator B'),
        ('O+', 20, '2026-12-31', 'Refrigerator C'),
        ('O-', 10, '2026-12-31', 'Refrigerator C')");
    echo "Sample blood inventory ready.<br>";

    $conn->query("INSERT IGNORE INTO vaccine_inventory (vaccine_name, batch_number, manufacturer, expiry_date, available_doses, storage_temp) VALUES
        ('COVID-19 Vaccine', 'COV001', 'Pfizer', '2026-12-31', 500, '2-8 C'),
        ('Hepatitis B Vaccine', 'HEP001', 'GSK', '2026-06-30', 200, '2-8 C')");
    echo "Sample vaccine inventory ready.<br>";

    $conn->query("INSERT IGNORE INTO beds (bed_number, ward, is_icu, status) VALUES
        ('B-001', 'General Ward', 0, 'Available'),
        ('B-002', 'General Ward', 0, 'Occupied'),
        ('ICU-01', 'ICU', 1, 'Available')");
    echo "Sample beds ready.<br>";

    echo "<br>Setup complete. <a href='index.php'>Go to Hospital App</a>";
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "Setup failed: " . htmlspecialchars($e->getMessage());
}
?>
