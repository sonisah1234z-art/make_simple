<?php
// Database connection settings for local development.
$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'hospital_system';
$port = 3320;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

function ensure_db_connection(mysqli $conn): void
{
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
}

function ensure_patients_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_code VARCHAR(50) UNIQUE NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NULL,
        phone VARCHAR(20),
        date_of_birth DATE,
        gender VARCHAR(20) DEFAULT 'Not specified',
        password VARCHAR(255) NULL,
        patient_type VARCHAR(30) NOT NULL DEFAULT 'Online',
        has_login TINYINT(1) NOT NULL DEFAULT 0,
        department VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $requiredColumns = [
        'patient_code' => "VARCHAR(50) UNIQUE NULL",
        'email' => "VARCHAR(255) UNIQUE NULL",
        'phone' => "VARCHAR(20)",
        'date_of_birth' => "DATE",
        'gender' => "VARCHAR(20) DEFAULT 'Not specified'",
        'password' => "VARCHAR(255) NULL",
        'patient_type' => "VARCHAR(30) NOT NULL DEFAULT 'Online'",
        'has_login' => "TINYINT(1) NOT NULL DEFAULT 0",
        'department' => "VARCHAR(255) DEFAULT NULL",
    ];

    $existingColumns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM patients");
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $existingColumns[$column['Field']] = $column;
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!isset($existingColumns[$column])) {
            $conn->query("ALTER TABLE patients ADD COLUMN $column $definition");
        } elseif ($column === 'email' && strtoupper($existingColumns[$column]['Null']) === 'NO') {
            $conn->query("ALTER TABLE patients MODIFY COLUMN email VARCHAR(255) UNIQUE NULL");
        } elseif ($column === 'password' && strtoupper($existingColumns[$column]['Null']) === 'NO') {
            $conn->query("ALTER TABLE patients MODIFY COLUMN password VARCHAR(255) NULL");
        }
    }

    if (isset($existingColumns['id'])) {
        $conn->query("UPDATE patients SET patient_code = CONCAT('P-', LPAD(id, 4, '0')) WHERE patient_code IS NULL OR patient_code = ''");
    }
}

ensure_patients_table($conn);
ensure_chat_messages_table($conn);
ensure_medicines_table($conn);
// ensure_doctor_seed_data($conn);
ensure_departments_table($conn);
ensure_health_offers_table($conn);
ensure_lab_tests_table($conn);
ensure_nurse_care_tables($conn);

function ensure_chat_messages_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_role ENUM('Patient', 'Doctor') NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        INDEX idx_chat_patient_doctor (patient_id, doctor_id),
        INDEX idx_chat_unread (patient_id, doctor_id, is_read),
        INDEX idx_chat_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_doctor_seed_data(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        specialty VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(50),
        password VARCHAR(255),
        status VARCHAR(20) NOT NULL DEFAULT 'Offline',
        profile_photo VARCHAR(255) NULL,
        qualifications VARCHAR(255) NULL,
        experience_years INT NOT NULL DEFAULT 0,
        consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        department VARCHAR(255) NULL,
        contact_info VARCHAR(255) NULL,
        working_days VARCHAR(255) NOT NULL DEFAULT 'Mon-Fri',
        available_slots VARCHAR(255) NOT NULL DEFAULT '09:00,11:00,14:00',
        about TEXT NULL,
        availability_status VARCHAR(20) NOT NULL DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columnsToAdd = [
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

    foreach ($columnsToAdd as $column => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM doctors LIKE '$column'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $doctorCount = $conn->query('SELECT COUNT(*) AS total FROM doctors');
    $doctorTotal = $doctorCount ? (int)$doctorCount->fetch_assoc()['total'] : 0;

    $seedDoctors = [
        [
            'Dr. Amina Rahman',
            'Cardiology',
            'amina@hospital.com',
            '+977-9801234567',
            'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'MBBS, MD (Cardiology)',
            16,
            120.00,
            'Cardiology',
            '+977-9801234567 | Cardio Wing, 3rd Floor',
            'Mon-Wed-Fri',
            '09:00,11:00,14:00',
            'Expert in heart health, hypertension management, and advanced interventional cardiology.'
        ],
        [
            'Dr. Daniel Basnet',
            'Neurology',
            'daniel@hospital.com',
            '+977-9802345678',
            'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=900&q=80',
            'MBBS, DM (Neurology)',
            14,
            115.00,
            'Neurology',
            '+977-9802345678 | Neuro Clinic, 2nd Floor',
            'Tue-Thu',
            '10:00,13:00,15:00',
            'Specializes in stroke care, epilepsy management, migraine treatment, and neuro-rehabilitation.'
        ],
        [
            'Dr. Sara Khan',
            'Pediatrics',
            'sara@hospital.com',
            '+977-9803456789',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'MBBS, DCH',
            11,
            95.00,
            'Pediatrics',
            '+977-9803456789 | Children’s Care, 1st Floor',
            'Mon-Fri',
            '08:30,12:30,16:00',
            'Dedicated to child wellness, immunizations, and family-centered pediatric care.'
        ],
        [
            'Dr. Nisha Shrestha',
            'Dermatology',
            'nisha@hospital.com',
            '+977-9804567890',
            'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=900&q=80',
            'MBBS, MD (Dermatology)',
            13,
            90.00,
            'Dermatology',
            '+977-9804567890 | Skin Clinic, 4th Floor',
            'Mon-Wed-Fri',
            '09:30,12:00,15:00',
            'Focused on acne, eczema, cosmetic dermatology, and skin cancer screening.'
        ],
        [
            'Dr. Rajendra Koirala',
            'Orthopedic Surgeon',
            'rajendra@hospital.com',
            '+977-9805678901',
            'https://images.unsplash.com/photo-1507537297725-24a1c029d3ca?auto=format&fit=crop&w=900&q=80',
            'MBBS, MS (Orthopedics)',
            18,
            130.00,
            'Orthopedics and Trauma Surgery',
            '+977-9805678901 | Orthopedic Center, Ground Floor',
            'Mon-Fri',
            '09:00,12:00,15:00',
            'Expert in joint replacement, sports injuries, fracture care, and spinal conditions.'
        ],
        [
            'Dr. Priyanka Adhikari',
            'ENT Specialist',
            'priyanka@hospital.com',
            '+977-9806789012',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
            'MBBS, MS (ENT)',
            12,
            100.00,
            'ENT (Ear, Nose & Throat)',
            '+977-9806789012 | ENT Clinic, 2nd Floor',
            'Tue-Thu',
            '10:00,13:00,16:00',
            'Comprehensive treatment for ear, nose, throat, voice and airway disorders.'
        ],
        [
            'Dr. Maya Gurung',
            'Gynecologist',
            'maya@hospital.com',
            '+977-9807890123',
            'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'MBBS, MD (Obstetrics & Gynecology)',
            15,
            125.00,
            'Obstetrics & Gynecology',
            '+977-9807890123 | Women’s Health Center, 3rd Floor',
            'Mon-Fri',
            '09:30,12:30,15:30',
            'Provides personalized maternity care, fertility guidance, and women’s health screenings.'
        ],
        [
            'Dr. Binod Pun',
            'Nephrology',
            'binod@hospital.com',
            '+977-9808901234',
            'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=900&q=80',
            'MBBS, DM (Nephrology)',
            14,
            118.00,
            'Nephrology',
            '+977-9808901234 | Kidney Unit, 4th Floor',
            'Mon-Wed-Fri',
            '09:00,11:00,14:00',
            'Specialist in kidney disease, dialysis planning, and hypertension management.'
        ],
        [
            'Dr. Sushil Rana',
            'Urology',
            'sushil@hospital.com',
            '+977-9809012345',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
            'MBBS, MS (Urology)',
            13,
            110.00,
            'Urology and Kidney Transplant Surgery',
            '+977-9809012345 | Urology Clinic, 4th Floor',
            'Tue-Thu',
            '10:00,13:00,16:00',
            'Experienced in urinary tract health, men’s reproductive care, and minimally invasive surgery.'
        ],
        [
            'Dr. Anjali Thapa',
            'Psychiatry',
            'anjali@hospital.com',
            '+977-9810123456',
            'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'MBBS, MD (Psychiatry)',
            12,
            105.00,
            'Psychiatry & Mental Health',
            '+977-9810123456 | Mental Wellness Center, 1st Floor',
            'Mon-Fri',
            '09:00,11:00,14:00',
            'Provides compassionate care for anxiety, depression, stress and emotional wellness.'
        ],
    ];

    $hash = password_hash('doctor123', PASSWORD_DEFAULT);
    $selectDoctorStmt = $conn->prepare('SELECT id FROM doctors WHERE email = ? OR name = ? LIMIT 1');
    $insertStmt = $conn->prepare("INSERT INTO doctors (name, specialty, email, phone, password, status, profile_photo, qualifications, experience_years, consultation_fee, department, contact_info, working_days, available_slots, about, availability_status) VALUES (?, ?, ?, ?, ?, 'Available', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available')");
    $updateStmt = $conn->prepare("UPDATE doctors SET specialty = ?, phone = ?, password = ?, status = 'Available', profile_photo = ?, qualifications = ?, experience_years = ?, consultation_fee = ?, department = ?, contact_info = ?, working_days = ?, available_slots = ?, about = ?, availability_status = 'Available' WHERE email = ? OR name = ?");

    foreach ($seedDoctors as $doctor) {
        $name = $doctor[0];
        $specialty = $doctor[1];
        $email = $doctor[2];
        $phone = $doctor[3];
        $photo = $doctor[4];
        $qualifications = $doctor[5];
        $experience = $doctor[6];
        $fee = $doctor[7];
        $department = $doctor[8];
        $contactInfo = $doctor[9];
        $workingDays = $doctor[10];
        $slots = $doctor[11];
        $about = $doctor[12];

        if ($selectDoctorStmt) {
            $selectDoctorStmt->bind_param('ss', $email, $name);
            $selectDoctorStmt->execute();
            $selectResult = $selectDoctorStmt->get_result();
            $exists = $selectResult && $selectResult->num_rows > 0;
            $selectResult->free();

            if ($exists && $updateStmt) {
                $updateStmt->bind_param(
                    'sssssidsssssss',
                    $specialty,
                    $phone,
                    $hash,
                    $photo,
                    $qualifications,
                    $experience,
                    $fee,
                    $department,
                    $contactInfo,
                    $workingDays,
                    $slots,
                    $about,
                    $email,
                    $name
                );
                $updateStmt->execute();
            } elseif (!$exists && $insertStmt) {
                $insertStmt->bind_param(
                    'sssssssidsssss',
                    $name,
                    $specialty,
                    $email,
                    $phone,
                    $hash,
                    $photo,
                    $qualifications,
                    $experience,
                    $fee,
                    $department,
                    $contactInfo,
                    $workingDays,
                    $slots,
                    $about
                );
                $insertStmt->execute();
            }
        }
    }
}

function ensure_departments_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        image_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $imageColumnCheck = $conn->query("SHOW COLUMNS FROM departments LIKE 'image_path'");
    if ($imageColumnCheck && $imageColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE departments ADD COLUMN image_path VARCHAR(255) NULL");
    }

    $result = $conn->query('SELECT COUNT(*) AS total FROM departments');
    $total = $result ? (int)$result->fetch_assoc()['total'] : 0;
    if ($total === 0) {
        $defaultDepartments = [
            ['Critical Care Medicine', 'Intensive monitoring and advanced support for critically ill patients.'],
            ['Dental', 'Comprehensive oral care, restorative dentistry and preventive treatment.'],
            ['Dermatology', 'Skin, hair and nail care for diagnosis, treatment and cosmetic concerns.'],
            ['Department of Emergency Medicine', 'Urgent care and rapid-response treatment for acute illnesses and injuries.'],
            ['ENT-HNS Department', 'Specialized care for ear, nose, throat, sinus and head-neck conditions.'],
            ['Orthopedics and Trauma Surgery', 'Bone, joint and trauma care including surgery and rehabilitation.'],
            ['General Practice', 'Primary care and family medicine for preventive screenings and wellness.'],
            ['Internal Medicine', 'Complex adult medicine, chronic disease management and diagnostic evaluation.'],
            ['Neurology', 'Diagnosis and treatment for neurological conditions including stroke and epilepsy.'],
            ['Neurosurgery', 'Surgical treatment of the brain, spine and nervous system.'],
            ['Department of Nephrology', 'Kidney care, dialysis planning and chronic kidney disease management.'],
            ['Obstetrics & Gynecology', 'Comprehensive women’s health, maternity care and reproductive medicine.'],
            ['Department of Paediatrics', 'Child health, immunizations and family-centered pediatric care.'],
            ['Psychiatry & Mental Health', 'Behavioral health support for anxiety, mood and daily functioning concerns.'],
            ['Plastic Surgery and Burns', 'Reconstructive surgery and burn recovery with advanced wound care.'],
            ['Pharmacy', 'Medication support, counseling and pharmacy services for safe treatment plans.'],
            ['Radiology', 'Advanced imaging diagnostics including X-ray, CT, MRI and ultrasound.'],
            ['Urology and Kidney Transplant Surgery', 'Urinary tract health, kidney transplant care and men’s reproductive health.'],
            ['General Surgery', 'Comprehensive general surgical care for abdominal and soft tissue conditions.'],
            ['Anaesthesiology', 'Safe anesthesia planning, perioperative care and pain control support.'],
            ['Pathology', 'Laboratory diagnostics and tissue analysis for accurate medical decisions.'],
            ['Gastroenterology', 'Digestive health care for stomach, intestine and liver-related conditions.'],
            ['Pulmonology & Critical Care', 'Lung and breathing disease care, including airway and intensive support.'],
            ['Surgical Gastroenterology', 'Surgical management of complex digestive and gastrointestinal conditions.']
        ];

        $stmt = $conn->prepare('INSERT INTO departments (name, description, status) VALUES (?, ?, ?)');
        if ($stmt) {
            foreach ($defaultDepartments as [$name, $description]) {
                $status = 'Active';
                $stmt->bind_param('sss', $name, $description, $status);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

function ensure_lab_tests_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS lab_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        test_type VARCHAR(255) NOT NULL,
        result TEXT NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        file VARCHAR(255) DEFAULT NULL,
        technician_id INT DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        report_date DATE DEFAULT CURRENT_DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add missing columns if table already existed
    $requiredColumns = [
        'image' => 'VARCHAR(255) DEFAULT NULL',
        'file' => 'VARCHAR(255) DEFAULT NULL',
        'technician_id' => 'INT DEFAULT NULL',
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'Pending'",
        'updated_at' => 'TIMESTAMP NULL DEFAULT NULL'
    ];

    $existingColumns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM lab_tests");
    if ($columnResult) {
        while ($col = $columnResult->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns, true)) {
            $conn->query("ALTER TABLE lab_tests ADD COLUMN $column $definition");
        }
    }
}

function ensure_lab_module_tables(mysqli $conn): void
{
    // Create lab test requests table
    $conn->query("CREATE TABLE IF NOT EXISTS lab_test_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NULL,
        patient_id INT NULL,
        department VARCHAR(255) DEFAULT NULL,
        test_category VARCHAR(255) DEFAULT NULL,
        test_name VARCHAR(255) DEFAULT NULL,
        priority VARCHAR(50) DEFAULT 'Normal',
        notes TEXT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        request_date DATE DEFAULT CURRENT_DATE,
        due_date DATE DEFAULT NULL,
        assigned_to INT DEFAULT NULL,
        assigned_by INT DEFAULT NULL,
        assigned_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES lab_technicians(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


    $requestColumns = [
        'sample_type' => 'VARCHAR(100) DEFAULT NULL',
        'collection_date' => 'DATE DEFAULT NULL'
    ];
    $requestExistingColumns = [];
    $requestColumnResult = $conn->query("SHOW COLUMNS FROM lab_test_requests");
    if ($requestColumnResult) {
        while ($column = $requestColumnResult->fetch_assoc()) {
            $requestExistingColumns[] = $column['Field'];
        }
    }
    foreach ($requestColumns as $column => $definition) {
        if (!in_array($column, $requestExistingColumns, true)) {
            $conn->query("ALTER TABLE lab_test_requests ADD COLUMN $column $definition");
        }
    }
    $conn->query("CREATE TABLE IF NOT EXISTS lab_samples (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT DEFAULT NULL,
        sample_id VARCHAR(100) DEFAULT NULL,
        sample_type VARCHAR(100) DEFAULT NULL,
        collection_datetime DATETIME DEFAULT NULL,
        collected_by VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (request_id) REFERENCES lab_test_requests(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS lab_test_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT DEFAULT NULL,
        parameters_json TEXT DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        technician_name VARCHAR(255) DEFAULT NULL,
        completed_date DATE DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (request_id) REFERENCES lab_test_requests(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS lab_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT DEFAULT NULL,
        report_id VARCHAR(100) DEFAULT NULL,
        patient_id INT DEFAULT NULL,
        doctor_id INT DEFAULT NULL,
        technician_id INT DEFAULT NULL,
        department VARCHAR(255) DEFAULT NULL,
        test_name VARCHAR(255) DEFAULT NULL,
        parameters_json TEXT DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        diagnosis TEXT DEFAULT NULL,
        treatment_notes TEXT DEFAULT NULL,
        file VARCHAR(255) DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending Review',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (request_id) REFERENCES lab_test_requests(id) ON DELETE SET NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
        FOREIGN KEY (technician_id) REFERENCES lab_technicians(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $reportColumns = [
        'sample_type' => 'VARCHAR(100) DEFAULT NULL',
        'collection_date' => 'DATE DEFAULT NULL',
        'report_status' => "VARCHAR(50) NOT NULL DEFAULT 'Sent to Doctor'",
        'doctor_remarks' => 'TEXT DEFAULT NULL',
        'doctor_reviewed_at' => 'DATETIME DEFAULT NULL',
        'released_to_patient' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'released_at' => 'DATETIME DEFAULT NULL'
    ];
    $reportExistingColumns = [];
    $reportColumnResult = $conn->query("SHOW COLUMNS FROM lab_reports");
    if ($reportColumnResult) {
        while ($column = $reportColumnResult->fetch_assoc()) {
            $reportExistingColumns[] = $column['Field'];
        }
    }
    foreach ($reportColumns as $column => $definition) {
        if (!in_array($column, $reportExistingColumns, true)) {
            $conn->query("ALTER TABLE lab_reports ADD COLUMN $column $definition");
        }
    }

    $conn->query("UPDATE lab_reports SET report_status = status WHERE report_status IS NULL OR report_status = ''");
    $conn->query("UPDATE lab_reports SET released_to_patient = 1 WHERE released_to_patient = 0 AND status IN ('Released to Patient', 'Doctor Verified', 'Approved')");
    $conn->query("UPDATE lab_reports SET status = 'Released to Patient' WHERE released_to_patient = 1 AND (status IS NULL OR status = '' OR status IN ('Doctor Verified', 'Approved'))");

    // Create sample collections table
    $conn->query("CREATE TABLE IF NOT EXISTS lab_sample_collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        test_name VARCHAR(255) DEFAULT NULL,
        sample_type VARCHAR(100) DEFAULT NULL,
        sample_id VARCHAR(100) DEFAULT NULL,
        collection_datetime DATETIME DEFAULT NULL,
        collected_by VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create lab inventory table
    $conn->query("CREATE TABLE IF NOT EXISTS lab_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(255) DEFAULT NULL,
        quantity INT DEFAULT 0,
        unit VARCHAR(50) DEFAULT 'pcs',
        supplier VARCHAR(255) DEFAULT NULL,
        purchase_date DATE DEFAULT NULL,
        expiry_date DATE DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ensure_staff_notifications_table($conn);
}

function ensure_feedback_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        rating INT NOT NULL DEFAULT 0,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_notifications_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        appointment_id INT DEFAULT NULL,
        reminder_type VARCHAR(50) DEFAULT NULL,
        title VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        send_at DATETIME DEFAULT NULL,
        delivered_at DATETIME DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_appointment_reminder (appointment_id, reminder_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columnsToAdd = [
        'appointment_id' => 'INT DEFAULT NULL',
        'reminder_type' => 'VARCHAR(50) DEFAULT NULL',
        'is_read' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'send_at' => 'DATETIME DEFAULT NULL',
        'delivered_at' => 'DATETIME DEFAULT NULL',
        'expires_at' => 'DATETIME DEFAULT NULL',
    ];

    foreach ($columnsToAdd as $column => $definition) {
        $columnResult = $conn->query("SHOW COLUMNS FROM notifications LIKE '$column'");
        if ($columnResult && $columnResult->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN $column $definition");
        }
    }

    $indexResult = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'uniq_appointment_reminder'");
    if ($indexResult && $indexResult->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD UNIQUE KEY uniq_appointment_reminder (appointment_id, reminder_type)");
    }
}

function ensure_staff_notifications_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS staff_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_role VARCHAR(50) NOT NULL,
        staff_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_notifications (staff_role, staff_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function insert_staff_notification(mysqli $conn, string $staffRole, int $staffId, string $title, string $message): bool
{
    if ($staffRole === '' || $staffId <= 0 || $title === '' || $message === '') {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO staff_notifications (staff_role, staff_id, title, message) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siss', $staffRole, $staffId, $title, $message);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function ensure_medicines_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS medicines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_name VARCHAR(255) NOT NULL,
        category VARCHAR(255),
        stock INT DEFAULT 0,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        expiry_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $requiredColumns = [
        'medicine_name' => 'VARCHAR(255) NOT NULL',
        'category' => 'VARCHAR(255)',
        'stock' => 'INT DEFAULT 0',
        'price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'expiry_date' => 'DATE',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    $existingColumns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM medicines");
    if ($columnResult) {
        while ($col = $columnResult->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns, true)) {
            $conn->query("ALTER TABLE medicines ADD COLUMN $column $definition");
            $existingColumns[] = $column;
        }
    }
}

function ensure_medicine_orders_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS medicine_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        medicine_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $requiredColumns = [
        'patient_name' => 'VARCHAR(255) NOT NULL',
        'medicine_name' => 'VARCHAR(255) NOT NULL',
        'quantity' => 'INT NOT NULL DEFAULT 1',
        'total_price' => 'DECIMAL(10,2) NOT NULL',
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'Pending'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    $existingColumns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM medicine_orders");
    if ($columnResult) {
        while ($col = $columnResult->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns, true)) {
            $conn->query("ALTER TABLE medicine_orders ADD COLUMN $column $definition");
            $existingColumns[] = $column;
        }
    }

    if (!in_array('medicine_name', $existingColumns, true) && in_array('medicine', $existingColumns, true)) {
        $conn->query("ALTER TABLE medicine_orders ADD COLUMN medicine_name VARCHAR(255) NOT NULL DEFAULT 'Medicine'");
        $conn->query("UPDATE medicine_orders SET medicine_name = medicine");
    }

    if (!in_array('total_price', $existingColumns, true) && in_array('price', $existingColumns, true)) {
        $conn->query("ALTER TABLE medicine_orders ADD COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0");
        $conn->query("UPDATE medicine_orders SET total_price = price");
    }

    if (!in_array('quantity', $existingColumns, true) && in_array('qty', $existingColumns, true)) {
        $conn->query("ALTER TABLE medicine_orders ADD COLUMN quantity INT NOT NULL DEFAULT 1");
        $conn->query("UPDATE medicine_orders SET quantity = qty");
    }
}

function ensure_nurse_care_tables(mysqli $conn): void
{
    $tableDefinitions = [
        'nurse_patient_assignments' => "CREATE TABLE IF NOT EXISTS nurse_patient_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            assigned_date DATE DEFAULT CURRENT_DATE,
            care_level VARCHAR(50) DEFAULT 'Routine',
            notes TEXT NULL,
            status VARCHAR(30) DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_vital_signs' => "CREATE TABLE IF NOT EXISTS nurse_vital_signs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            blood_pressure VARCHAR(50) DEFAULT NULL,
            pulse VARCHAR(50) DEFAULT NULL,
            temperature VARCHAR(50) DEFAULT NULL,
            respiration VARCHAR(50) DEFAULT NULL,
            oxygen_saturation VARCHAR(50) DEFAULT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_medication_admin' => "CREATE TABLE IF NOT EXISTS nurse_medication_admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            medication_name VARCHAR(255) NOT NULL,
            dosage VARCHAR(100) DEFAULT NULL,
            route VARCHAR(100) DEFAULT NULL,
            schedule_time VARCHAR(50) DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'Given',
            notes TEXT NULL,
            administered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_nursing_notes' => "CREATE TABLE IF NOT EXISTS nurse_nursing_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            note_type VARCHAR(100) DEFAULT 'General',
            notes TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_doctor_instructions' => "CREATE TABLE IF NOT EXISTS nurse_doctor_instructions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            doctor_id INT DEFAULT NULL,
            instruction_title VARCHAR(255) DEFAULT NULL,
            instruction_details TEXT NOT NULL,
            priority VARCHAR(30) DEFAULT 'Routine',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_iv_monitoring' => "CREATE TABLE IF NOT EXISTS nurse_iv_monitoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            fluid_name VARCHAR(255) DEFAULT NULL,
            rate_ml_hr VARCHAR(50) DEFAULT NULL,
            volume_remaining VARCHAR(50) DEFAULT NULL,
            site_condition VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'Monitoring',
            monitored_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_treatment_records' => "CREATE TABLE IF NOT EXISTS nurse_treatment_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            treatment_name VARCHAR(255) NOT NULL,
            treatment_type VARCHAR(100) DEFAULT NULL,
            notes TEXT NULL,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_lab_status' => "CREATE TABLE IF NOT EXISTS nurse_lab_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            test_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            result_summary TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_emergency_alerts' => "CREATE TABLE IF NOT EXISTS nurse_emergency_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            alert_type VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            severity VARCHAR(30) DEFAULT 'High',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_ward_management' => "CREATE TABLE IF NOT EXISTS nurse_ward_management (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT DEFAULT NULL,
            ward_name VARCHAR(255) DEFAULT NULL,
            room_number VARCHAR(50) DEFAULT NULL,
            bed_number VARCHAR(50) DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'Available',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_admissions' => "CREATE TABLE IF NOT EXISTS nurse_admissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            admission_date DATE DEFAULT CURRENT_DATE,
            ward_name VARCHAR(255) DEFAULT NULL,
            room_number VARCHAR(50) DEFAULT NULL,
            bed_number VARCHAR(50) DEFAULT NULL,
            discharge_date DATE DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'Admitted',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_tasks' => "CREATE TABLE IF NOT EXISTS nurse_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            task_title VARCHAR(255) NOT NULL,
            task_details TEXT NULL,
            due_datetime DATETIME DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'Open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_medication_reminders' => "CREATE TABLE IF NOT EXISTS nurse_medication_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            medication_name VARCHAR(255) NOT NULL,
            reminder_time VARCHAR(50) DEFAULT NULL,
            frequency VARCHAR(80) DEFAULT 'Daily',
            status VARCHAR(30) DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_patient_monitoring' => "CREATE TABLE IF NOT EXISTS nurse_patient_monitoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            patient_id INT NOT NULL,
            heart_rate VARCHAR(50) DEFAULT NULL,
            blood_pressure VARCHAR(50) DEFAULT NULL,
            temperature VARCHAR(50) DEFAULT NULL,
            oxygen_saturation VARCHAR(50) DEFAULT NULL,
            alert_level VARCHAR(30) DEFAULT 'Stable',
            monitored_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_notifications' => "CREATE TABLE IF NOT EXISTS nurse_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            message TEXT NOT NULL,
            priority VARCHAR(30) DEFAULT 'Normal',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'nurse_daily_reports' => "CREATE TABLE IF NOT EXISTS nurse_daily_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nurse_id INT NOT NULL,
            report_date DATE DEFAULT CURRENT_DATE,
            summary TEXT NOT NULL,
            tasks_completed VARCHAR(255) DEFAULT NULL,
            concerns TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($tableDefinitions as $tableName => $sql) {
        $conn->query($sql);
    }

    $requiredColumnMap = [
        'nurse_daily_reports' => [
            'tasks_completed' => 'VARCHAR(255) DEFAULT NULL',
            'concerns' => 'TEXT NULL',
        ],
    ];

    foreach ($requiredColumnMap as $tableName => $columns) {
        foreach ($columns as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$column'");
            if ($check && $check->num_rows === 0) {
                $conn->query("ALTER TABLE $tableName ADD COLUMN $column $definition");
            }
        }
    }

    $nurseId = null;
    $nurseResult = $conn->query("SELECT id FROM nurses ORDER BY id LIMIT 1");
    if ($nurseResult && $nurseResult->num_rows > 0) {
        $nurseId = (int) $nurseResult->fetch_assoc()['id'];
    }

    if ($nurseId) {
        $patientCount = (int) $conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'];
        if ($patientCount > 0) {
            $assignmentCount = (int) $conn->query("SELECT COUNT(*) AS total FROM nurse_patient_assignments WHERE nurse_id = $nurseId")->fetch_assoc()['total'];
            if ($assignmentCount === 0) {
                $patients = $conn->query("SELECT id, name FROM patients ORDER BY id LIMIT 3");
                while ($patient = $patients->fetch_assoc()) {
                    $pid = (int) $patient['id'];
                    $pname = $patient['name'];
                    $stmt = $conn->prepare("INSERT INTO nurse_patient_assignments (nurse_id, patient_id, assigned_date, care_level, notes, status) VALUES (?, ?, CURDATE(), 'Routine', ?, 'Active')");
                    if ($stmt) {
                        $note = 'Initial assignment for ' . $pname;
                        $stmt->bind_param('iis', $nurseId, $pid, $note);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }

        foreach (['nurse_vital_signs','nurse_medication_admin','nurse_nursing_notes','nurse_doctor_instructions','nurse_iv_monitoring','nurse_treatment_records','nurse_lab_status','nurse_emergency_alerts','nurse_ward_management','nurse_admissions','nurse_tasks','nurse_medication_reminders','nurse_patient_monitoring','nurse_notifications','nurse_daily_reports'] as $tableName) {
            $countRes = $conn->query("SELECT COUNT(*) AS total FROM $tableName WHERE nurse_id = $nurseId");
            if ($countRes && (int) $countRes->fetch_assoc()['total'] === 0) {
                $patientRow = $conn->query("SELECT id, name FROM patients ORDER BY id LIMIT 1");
                if ($patientRow && $patientRow->num_rows > 0) {
                    $patient = $patientRow->fetch_assoc();
                    $pid = (int) $patient['id'];
                    $patientName = $patient['name'];

                    if ($tableName === 'nurse_vital_signs') {
                        $conn->query("INSERT INTO nurse_vital_signs (nurse_id, patient_id, blood_pressure, pulse, temperature, respiration, oxygen_saturation, notes) VALUES ($nurseId, $pid, '120/80', '72 bpm', '36.8C', '18/min', '98%', 'Routine monitoring recorded for $patientName')");
                    } elseif ($tableName === 'nurse_medication_admin') {
                        $conn->query("INSERT INTO nurse_medication_admin (nurse_id, patient_id, medication_name, dosage, route, schedule_time, status, notes) VALUES ($nurseId, $pid, 'Paracetamol', '500 mg', 'Oral', '08:00', 'Given', 'Medication administered to $patientName')");
                    } elseif ($tableName === 'nurse_nursing_notes') {
                        $conn->query("INSERT INTO nurse_nursing_notes (nurse_id, patient_id, note_type, notes) VALUES ($nurseId, $pid, 'Observation', 'Patient status stable and comfortable. Observation updated by nursing staff.')");
                    } elseif ($tableName === 'nurse_doctor_instructions') {
                        $conn->query("INSERT INTO nurse_doctor_instructions (nurse_id, patient_id, instruction_title, instruction_details, priority) VALUES ($nurseId, $pid, 'Follow-up Observation', 'Monitor blood pressure twice daily and report any fever or dizziness.', 'Routine')");
                    } elseif ($tableName === 'nurse_iv_monitoring') {
                        $conn->query("INSERT INTO nurse_iv_monitoring (nurse_id, patient_id, fluid_name, rate_ml_hr, volume_remaining, site_condition, status, notes) VALUES ($nurseId, $pid, 'Normal Saline', '75 ml/hr', '500 ml', 'Patent', 'Monitoring', 'IV site clean and intact.')");
                    } elseif ($tableName === 'nurse_treatment_records') {
                        $conn->query("INSERT INTO nurse_treatment_records (nurse_id, patient_id, treatment_name, treatment_type, notes) VALUES ($nurseId, $pid, 'Wound Dressing', 'Procedure', 'Dressing changed and site inspected.')");
                    } elseif ($tableName === 'nurse_lab_status') {
                        $conn->query("INSERT INTO nurse_lab_status (nurse_id, patient_id, test_name, status, result_summary) VALUES ($nurseId, $pid, 'CBC', 'Pending', 'Sample collected and awaiting lab review.')");
                    } elseif ($tableName === 'nurse_emergency_alerts') {
                        $conn->query("INSERT INTO nurse_emergency_alerts (nurse_id, patient_id, alert_type, message, severity, is_active) VALUES ($nurseId, $pid, 'Monitoring', 'Patient requires urgent observation due to elevated vitals.', 'High', 1)");
                    } elseif ($tableName === 'nurse_ward_management') {
                        $conn->query("INSERT INTO nurse_ward_management (nurse_id, patient_id, ward_name, room_number, bed_number, status) VALUES ($nurseId, $pid, 'ICU', 'A-12', 'B3', 'Occupied')");
                    } elseif ($tableName === 'nurse_admissions') {
                        $conn->query("INSERT INTO nurse_admissions (nurse_id, patient_id, admission_date, ward_name, room_number, bed_number, status, notes) VALUES ($nurseId, $pid, CURDATE(), 'General Ward', 'G-08', 'A1', 'Admitted', 'Admitted for monitoring and treatment.')");
                    } elseif ($tableName === 'nurse_tasks') {
                        $conn->query("INSERT INTO nurse_tasks (nurse_id, patient_id, task_title, task_details, due_datetime, status) VALUES ($nurseId, $pid, 'Recheck vitals', 'Record current vital signs and update patient chart.', DATE_ADD(NOW(), INTERVAL 2 HOUR), 'Open')");
                    } elseif ($tableName === 'nurse_medication_reminders') {
                        $conn->query("INSERT INTO nurse_medication_reminders (nurse_id, patient_id, medication_name, reminder_time, frequency, status) VALUES ($nurseId, $pid, 'Insulin', '09:00 AM', 'Daily', 'Scheduled')");
                    } elseif ($tableName === 'nurse_patient_monitoring') {
                        $conn->query("INSERT INTO nurse_patient_monitoring (nurse_id, patient_id, heart_rate, blood_pressure, temperature, oxygen_saturation, alert_level) VALUES ($nurseId, $pid, '74 bpm', '118/76', '36.6C', '98%', 'Stable')");
                    } elseif ($tableName === 'nurse_notifications') {
                        $conn->query("INSERT INTO nurse_notifications (nurse_id, message, priority) VALUES ($nurseId, 'Patient checklist updated and ready for review.', 'Normal')");
                    } elseif ($tableName === 'nurse_daily_reports') {
                        $conn->query("INSERT INTO nurse_daily_reports (nurse_id, report_date, summary, tasks_completed, concerns) VALUES ($nurseId, CURDATE(), 'All assigned patient care tasks reviewed for the day.', 'Vitals, medication review, monitoring', 'No major issues reported.')");
                    }
                }
            }
        }
    }
}

function ensure_health_offers_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS health_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        original_price DECIMAL(10,2) NOT NULL,
        discounted_price DECIMAL(10,2) NOT NULL,
        discount_percentage INT DEFAULT 0,
        validity_start DATE NOT NULL,
        validity_end DATE NOT NULL,
        badge_text VARCHAR(100) DEFAULT NULL,
        badge_emoji VARCHAR(10) DEFAULT NULL,
        booking_link VARCHAR(255) DEFAULT 'book-appointment.php',
        is_active BOOLEAN DEFAULT TRUE,
        is_popular BOOLEAN DEFAULT FALSE,
        sort_order INT DEFAULT 0,
        offer_type ENUM('offer', 'package') DEFAULT 'package',
        num_tests INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add offer_type column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM health_offers LIKE 'offer_type'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE health_offers ADD COLUMN offer_type ENUM('offer', 'package') DEFAULT 'package' AFTER sort_order");
    }

    // Add num_tests column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM health_offers LIKE 'num_tests'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE health_offers ADD COLUMN num_tests INT DEFAULT 0 AFTER offer_type");
    }

    // Seed default health offers if empty
    $result = $conn->query('SELECT COUNT(*) AS total FROM health_offers');
    $total = $result ? (int)$result->fetch_assoc()['total'] : 0;

    if ($total === 0) {
        $defaultOffers = [
            // Promotional Offers
            [
                'Summer Health Camp',
                'Free health screening + vaccination camp at your doorstep.',
                'assets/images/offer-summer.jpg',
                5000.00,
                0.00,
                100,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+30 days')),
                'FREE',
                '🎪',
                'book-appointment.php',
                true,
                true,
                1,
                'offer',
                0
            ],
            [
                'Corporate Wellness Drive',
                'Seasonal health promotion event with special discounts and expert talks.',
                'assets/images/offer-corporate.jpg',
                8000.00,
                3999.00,
                50,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+45 days')),
                '50% DISCOUNT',
                '💼',
                'book-appointment.php',
                true,
                true,
                2,
                'offer',
                0
            ],
            [
                'Monsoon Immunity Boost',
                'Special campaign with immunity-building health packages and expert guidance.',
                'assets/images/offer-immunity.jpg',
                6500.00,
                3250.00,
                50,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+60 days')),
                '50% OFF',
                '🌧️',
                'book-appointment.php',
                true,
                false,
                3,
                'offer',
                0
            ],
            // Health Checkup Packages
            [
                'Full Body Checkup',
                'Complete preventive health screening with specialist consultation.',
                'assets/images/offer-fullbody.jpg',
                7500.00,
                5500.00,
                30,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+90 days')),
                '30% OFF',
                '⭐',
                'book-appointment.php',
                true,
                true,
                1,
                'package',
                55
            ],
            [
                'Heart Care Package',
                'Advanced cardiac screening with experienced specialists and preventive guidance.',
                'assets/images/offer-heart.jpg',
                6800.00,
                4999.00,
                25,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+90 days')),
                '25% OFF',
                '❤️',
                'book-appointment.php',
                true,
                true,
                2,
                'package',
                42
            ],
            [
                'Diabetes Screening & Coaching',
                'Early diabetes detection package with complete consultation and diet coaching.',
                'assets/images/diabetes-screening.jpg',
                3500.00,
                1999.00,
                40,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+90 days')),
                '40% OFF',
                '🔥',
                'book-appointment.php',
                true,
                true,
                3,
                'package',
                28
            ]
        ];

        $stmt = $conn->prepare(
            'INSERT INTO health_offers (title, description, image_path, original_price, discounted_price, discount_percentage, validity_start, validity_end, badge_text, badge_emoji, booking_link, is_active, is_popular, sort_order, offer_type, num_tests) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            foreach ($defaultOffers as $offer) {
                list($title, $description, $image, $orig_price, $disc_price, $discount, $start, $end, $badge, $emoji, $link, $active, $popular, $sort, $type, $tests) = $offer;
                $stmt->bind_param(
                    'sssddisssssiiisi',
                    $title,
                    $description,
                    $image,
                    $orig_price,
                    $disc_price,
                    $discount,
                    $start,
                    $end,
                    $badge,
                    $emoji,
                    $link,
                    $active,
                    $popular,
                    $sort,
                    $type,
                    $tests
                );
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

function sync_medicine_expiry_alerts(mysqli $conn): void
{
    // Ensure alerts table exists
    $conn->query("CREATE TABLE IF NOT EXISTS medicine_expiry_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_id INT NOT NULL,
        alert_message VARCHAR(255) DEFAULT NULL,
        expiry_date DATE DEFAULT NULL,
        alert_status ENUM('Pending','Acknowledged') NOT NULL DEFAULT 'Pending',
        acknowledgement_comment TEXT NULL,
        acknowledged_by VARCHAR(255) DEFAULT NULL,
        acknowledged_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY unique_medicine_alert (medicine_id),
        FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Find medicines expiring within the next 90 days or already expired
    $today = date('Y-m-d');
    $threshold = date('Y-m-d', strtotime('+90 days'));

    $stmt = $conn->prepare("SELECT id, medicine_name, expiry_date, stock FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date <= ? ORDER BY expiry_date ASC");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $threshold);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) {
        $stmt->close();
        return;
    }

    $selectAlert = $conn->prepare('SELECT id, expiry_date, alert_status FROM medicine_expiry_alerts WHERE medicine_id = ? LIMIT 1');
    $insertAlert = $conn->prepare('INSERT INTO medicine_expiry_alerts (medicine_id, alert_message, expiry_date, alert_status, created_at) VALUES (?, ?, ?, "Pending", NOW())');
    $updateAlert = $conn->prepare('UPDATE medicine_expiry_alerts SET alert_message = ?, expiry_date = ?, alert_status = "Pending", updated_at = NOW() WHERE id = ?');

    while ($row = $res->fetch_assoc()) {
        $mid = (int)$row['id'];
        $mname = $row['medicine_name'];
        $exp = $row['expiry_date'];

        $msg = sprintf('Expiry on %s for %s', $exp, $mname);

        if ($selectAlert) {
            $selectAlert->bind_param('i', $mid);
            $selectAlert->execute();
            $ar = $selectAlert->get_result()->fetch_assoc();
            if ($ar) {
                // update if expiry changed
                $aid = (int)$ar['id'];
                if ($ar['expiry_date'] !== $exp || $ar['alert_status'] !== 'Pending') {
                    if ($updateAlert) {
                        $updateAlert->bind_param('ssi', $msg, $exp, $aid);
                        $updateAlert->execute();
                    }
                }
                continue;
            }
        }

        // create new alert
        if ($insertAlert) {
            $insertAlert->bind_param('iss', $mid, $msg, $exp);
            $insertAlert->execute();
        }
    }

    if ($selectAlert) { $selectAlert->close(); }
    if ($insertAlert) { $insertAlert->close(); }
    if ($updateAlert) { $updateAlert->close(); }
    $stmt->close();
}
?>
