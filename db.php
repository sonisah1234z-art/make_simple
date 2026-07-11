<?php
// Database connection settings for local development.
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hospital_system';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

ensure_medicines_table($conn);
ensure_doctor_seed_data($conn);
ensure_departments_table($conn);

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $result = $conn->query('SELECT COUNT(*) AS total FROM departments');
    $total = $result ? (int)$result->fetch_assoc()['total'] : 0;
    if ($total === 0) {
        $defaultDepartments = [
            ['Cardiology', 'Heart care, hypertension management and advanced cardiology services.'],
            ['Neurology', 'Diagnosis and treatment for neurological disorders including stroke and epilepsy.'],
            ['Obstetrics & Gynecology', 'Comprehensive women’s health, maternity care and reproductive medicine.'],
            ['Pediatrics', 'Child health, immunizations, and family-centered pediatric care.'],
            ['Dermatology', 'Skin care, cosmetic dermatology, and chronic skin condition management.'],
            ['Orthopedics and Trauma Surgery', 'Bone, joint and trauma care including surgery and rehabilitation.'],
            ['ENT (Ear, Nose & Throat)', 'Care for conditions affecting the ear, nose, throat and voice.'],
            ['Nephrology', 'Kidney care, dialysis planning and chronic kidney disease management.'],
            ['Urology and Kidney Transplant Surgery', 'Urinary tract health, kidney transplant care and men’s reproductive health.'],
            ['Psychiatry & Mental Health', 'Behavioral health, anxiety and depression treatment with compassionate support.'],
            ['General Practice', 'Primary care and family medicine for preventive screenings and wellness.']
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
        title VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
?>
