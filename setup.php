<?php
// Run this script once to set up the database and seed sample data.
// It is intended for local development with XAMPP.

$host = 'localhost';
$username = 'root';
$password = '';
$database = '';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function runQuery(mysqli $conn, string $sql, string $successMessage, string $errorMessage): void
{
    if ($conn->query($sql) === TRUE) {
        echo $successMessage . '<br>';
    } else {
        echo $errorMessage . $conn->error . '<br>';
    }
}

runQuery($conn, "CREATE DATABASE IF NOT EXISTS hospital_system", "Database 'hospital_system' created or already exists.", "Error creating database: ");
$conn->select_db('hospital_system');

runQuery($conn, "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Admins table created successfully.", "Error creating admins table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Patients table created successfully.", "Error creating patients table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    specialty VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Doctors table created successfully.", "Error creating doctors table: ");

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
)", "Appointments table created successfully.", "Error creating appointments table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS billings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
)", "Billings table created successfully.", "Error creating billings table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Drivers table created successfully.", "Error creating drivers table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS ambulances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(100) UNIQUE NOT NULL,
    driver_id INT,
    status VARCHAR(50) DEFAULT 'Available',
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
)", "Ambulances table created successfully.", "Error creating ambulances table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS ambulance_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    patient_name VARCHAR(255),
    phone VARCHAR(50),
    pickup_location VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    driver_id INT,
    ambulance_id INT,
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estimated_arrival TIMESTAMP NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    FOREIGN KEY (ambulance_id) REFERENCES ambulances(id) ON DELETE SET NULL
)", "Ambulance bookings table created successfully.", "Error creating ambulance bookings table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    blood_group VARCHAR(10) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    last_donation_date DATE,
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Blood donors table created successfully.", "Error creating blood donors table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group VARCHAR(10) NOT NULL,
    units_available INT DEFAULT 0,
    expiry_date DATE,
    storage_location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Blood bank table created successfully.", "Error creating blood bank table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    patient_name VARCHAR(255),
    blood_group VARCHAR(10) NOT NULL,
    units_needed INT NOT NULL,
    urgency VARCHAR(50) DEFAULT 'Normal',
    status VARCHAR(50) DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
)", "Blood requests table created successfully.", "Error creating blood requests table: ");

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
)", "Organ donors table created successfully.", "Error creating organ donors table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS organ_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    patient_name VARCHAR(255),
    organ_type VARCHAR(100) NOT NULL,
    urgency VARCHAR(50) DEFAULT 'Normal',
    blood_group VARCHAR(10),
    status VARCHAR(50) DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
)", "Organ requests table created successfully.", "Error creating organ requests table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccine_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_name VARCHAR(255) NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(255),
    expiry_date DATE NOT NULL,
    available_doses INT DEFAULT 0,
    storage_temp VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Vaccine inventory table created successfully.", "Error creating vaccine inventory table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
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
)", "Vaccinations table created successfully.", "Error creating vaccinations table: ");

runQuery($conn, "CREATE TABLE IF NOT EXISTS vaccination_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    patient_name VARCHAR(255),
    vaccine_name VARCHAR(255) NOT NULL,
    reminder_date DATE NOT NULL,
    reminder_type VARCHAR(50) DEFAULT 'Next Dose',
    status VARCHAR(50) DEFAULT 'Pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
)", "Vaccination reminders table created successfully.", "Error creating vaccination reminders table: ");

$result = $conn->query("SELECT * FROM admins WHERE username = 'admin'");
if ($result && $result->num_rows === 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO admins (name, username, password) VALUES ('Hospital Admin', 'admin', '$password')";
    if ($conn->query($sql) === TRUE) {
        echo "Default admin created: username='admin', password='admin123'<br>";
    } else {
        echo "Error creating admin user: " . $conn->error . "<br>";
    }
        ('Hepatitis B Vaccine', 'HEP001', 'GSK', '2025-06-30', 200, '2-8°C'),
        ('MMR Vaccine', 'MMR001', 'Merck', '2025-03-15', 150, '2-8°C'),
        ('Influenza Vaccine', 'FLU001', 'Sanofi', '2024-08-31', 300, '-20°C')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample vaccine inventory created.<br>";
    } else {
        echo "Error creating vaccine inventory: " . $conn->error . "<br>";
    }
}

$conn->close();
echo "<br><a href='index.php'>Go to Hospital App</a>";
?>
<?php
// Beds table for tracking bed availability and ICU beds
runQuery($conn, "CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_number VARCHAR(50) UNIQUE NOT NULL,
    ward VARCHAR(100) DEFAULT 'General',
    is_icu TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Available',
    patient_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
)", "Beds table created successfully.", "Error creating beds table: ");

// Insert sample beds if none exist
$result = $conn->query("SELECT COUNT(*) AS count FROM beds");
$bedCount = $result ? (int)$result->fetch_assoc()['count'] : 0;
if ($bedCount === 0) {
    $sql = "INSERT INTO beds (bed_number, ward, is_icu, status) VALUES
        ('B-001', 'General Ward', 0, 'Available'),
        ('B-002', 'General Ward', 0, 'Occupied'),
        ('B-003', 'General Ward', 0, 'Available'),
        ('ICU-01', 'ICU', 1, 'Available'),
        ('ICU-02', 'ICU', 1, 'Occupied')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample beds created.<br>";
    } else {
        echo "Error creating sample beds: " . $conn->error . "<br>";
    }
}

$conn->close();
echo "<br><a href='index.php'>Go to Hospital App</a>";
?>
$sql = "CREATE TABLE IF NOT EXISTS vaccine_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_name VARCHAR(255) NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(255),
    expiry_date DATE NOT NULL,
    available_doses INT DEFAULT 0,
    storage_temp VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Vaccine inventory table created successfully.<br>";
} else {
    echo "Error creating vaccine inventory table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
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
)";

if ($conn->query($sql) === TRUE) {
    echo "Vaccinations table created successfully.<br>";
} else {
    echo "Error creating vaccinations table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS vaccination_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    patient_name VARCHAR(255),
    vaccine_name VARCHAR(255) NOT NULL,
    reminder_date DATE NOT NULL,
    reminder_type VARCHAR(50) DEFAULT 'Next Dose',
    status VARCHAR(50) DEFAULT 'Pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Vaccination reminders table created successfully.<br>";
} else {
    echo "Error creating vaccination reminders table: " . $conn->error . "<br>";
}

$result = $conn->query("SELECT * FROM admins WHERE username = 'admin'");
if ($result && $result->num_rows == 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO admins (name, username, password) VALUES ('Hospital Admin', 'admin', '$password')";
    if ($conn->query($sql) === TRUE) {
        echo "Default admin created: username='admin', password='admin123'<br>";
    } else {
        echo "Error creating admin user: " . $conn->error . "<br>";
    }
}

$result = $conn->query("SELECT COUNT(*) AS count FROM doctors");
$doctorCount = $result ? (int)$result->fetch_assoc()['count'] : 0;
if ($doctorCount < 32) {
    $sql = "INSERT IGNORE INTO doctors (name, specialty, email, phone) VALUES 
        ('Dr. Sara Khan', 'General Physician', 'sara.khan@hospital.com', '+1234567890'),
        ('Dr. Amir Shah', 'Cardiologist', 'amir.shah@hospital.com', '+1234567891'),
        ('Dr. Aisha Mahmood', 'Pediatrician', 'aisha.mahmood@hospital.com', '+1234567892'),
        ('Dr. Bilal Qureshi', 'Orthopedic Surgeon', 'bilal.qureshi@hospital.com', '+1234567893'),
        ('Dr. Nadia Farooq', 'Neurologist', 'nadia.farooq@hospital.com', '+1234567894'),
        ('Dr. Kamran Siddiqui', 'ENT Specialist', 'kamran.siddiqui@hospital.com', '+1234567895'),
        ('Dr. Sara Iqbal', 'Dermatologist', 'sara.iqbal@hospital.com', '+1234567896'),
        ('Dr. Hina Javed', 'Gynecologist', 'hina.javed@hospital.com', '+1234567897'),
        ('Dr. Faisal Khan', 'Oncologist', 'faisal.khan@hospital.com', '+1234567898'),
        ('Dr. Zoya Ahmed', 'Psychiatrist', 'zoya.ahmed@hospital.com', '+1234567899'),
        ('Dr. Omar Tariq', 'Urologist', 'omar.tariq@hospital.com', '+1234567800'),
        ('Dr. Mehwish Ali', 'Nephrologist', 'mehwish.ali@hospital.com', '+1234567801'),
        ('Dr. Salman Raza', 'Pulmonologist', 'salman.raza@hospital.com', '+1234567802'),
        ('Dr. Amina Haq', 'Gastroenterologist', 'amina.haq@hospital.com', '+1234567803'),
        ('Dr. Yasir Malik', 'Ophthalmologist', 'yasir.malik@hospital.com', '+1234567804'),
        ('Dr. Farah Khan', 'Endocrinologist', 'farah.khan@hospital.com', '+1234567805'),
        ('Dr. Aliya Shah', 'Rheumatologist', 'aliya.shah@hospital.com', '+1234567806'),
        ('Dr. Hamza Ijaz', 'Cardiologist', 'hamza.ijaz@hospital.com', '+1234567807'),
        ('Dr. Nida Saeed', 'Pediatric Surgeon', 'nida.saeed@hospital.com', '+1234567808'),
        ('Dr. Umar Ali', 'Radiologist', 'umar.ali@hospital.com', '+1234567809'),
        ('Dr. Sana Mir', 'Pathologist', 'sana.mir@hospital.com', '+1234567810'),
        ('Dr. Abbas Khan', 'General Surgeon', 'abbas.khan@hospital.com', '+1234567811'),
        ('Dr. Laila Ahmed', 'Anesthesiologist', 'laila.ahmed@hospital.com', '+1234567812'),
        ('Dr. Rashid Noman', 'Emergency Medicine', 'rashid.noman@hospital.com', '+1234567813'),
        ('Dr. Maria Yusuf', 'Dental Surgeon', 'maria.yusuf@hospital.com', '+1234567814'),
        ('Dr. Tariq Mehmood', 'Nutritionist', 'tariq.mehmood@hospital.com', '+1234567815'),
        ('Dr. Zainab Rafiq', 'Physiotherapist', 'zainab.rafiq@hospital.com', '+1234567816'),
        ('Dr. Saad Abbas', 'ENT Surgeon', 'saad.abbas@hospital.com', '+1234567817'),
        ('Dr. Rabia Khan', 'Cardiac Surgeon', 'rabia.khan@hospital.com', '+1234567818'),
        ('Dr. Noman Faraz', 'Oncologist', 'noman.faraz@hospital.com', '+1234567819'),
        ('Dr. Shazia Yousuf', 'Dermatology Specialist', 'shazia.yousuf@hospital.com', '+1234567820')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample doctors created or updated.<br>";
    } else {
        echo "Error creating doctors: " . $conn->error . "<br>";
    }
}

// Add sample drivers
$result = $conn->query("SELECT * FROM drivers LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO drivers (name, license_number, phone, status) VALUES 
        ('Ahmed Hassan', 'DL123456789', '+1234567892', 'Available'),
        ('Fatima Ali', 'DL987654321', '+1234567893', 'Available'),
        ('Muhammad Khan', 'DL456789123', '+1234567894', 'Available')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample drivers created.<br>";
    } else {
        echo "Error creating drivers: " . $conn->error . "<br>";
    }
}

// Add sample ambulances
$result = $conn->query("SELECT * FROM ambulances LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO ambulances (vehicle_number, driver_id, status, location) VALUES 
        ('AMB-001', 1, 'Available', 'Hospital Parking'),
        ('AMB-002', 2, 'Available', 'Hospital Parking'),
        ('AMB-003', NULL, 'Maintenance', 'Service Center')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample ambulances created.<br>";
    } else {
        echo "Error creating ambulances: " . $conn->error . "<br>";
    }
}

// Add sample blood donors
$result = $conn->query("SELECT * FROM blood_donors LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO blood_donors (name, blood_group, phone, email, last_donation_date, status) VALUES 
        ('Ali Ahmed', 'A+', '+1234567895', 'ali@email.com', '2024-01-15', 'Active'),
        ('Sara Malik', 'O-', '+1234567896', 'sara@email.com', '2024-02-20', 'Active'),
        ('Hassan Khan', 'B+', '+1234567897', 'hassan@email.com', '2024-03-10', 'Active'),
        ('Ayesha Fatima', 'AB+', '+1234567898', 'ayesha@email.com', '2024-01-05', 'Active')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample blood donors created.<br>";
    } else {
        echo "Error creating blood donors: " . $conn->error . "<br>";
    }
}

// Add sample blood bank inventory
$result = $conn->query("SELECT * FROM blood_bank LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO blood_bank (blood_group, units_available, expiry_date, storage_location) VALUES 
        ('A+', 15, '2024-12-31', 'Refrigerator A'),
        ('A-', 8, '2024-12-31', 'Refrigerator A'),
        ('B+', 12, '2024-12-31', 'Refrigerator B'),
        ('B-', 5, '2024-12-31', 'Refrigerator B'),
        ('O+', 20, '2024-12-31', 'Refrigerator C'),
        ('O-', 10, '2024-12-31', 'Refrigerator C'),
        ('AB+', 6, '2024-12-31', 'Refrigerator D'),
        ('AB-', 3, '2024-12-31', 'Refrigerator D')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample blood bank inventory created.<br>";
    } else {
        echo "Error creating blood bank inventory: " . $conn->error . "<br>";
    }
}

// Add sample organ donors
$result = $conn->query("SELECT * FROM organ_donors LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO organ_donors (name, organ_type, blood_group, phone, email, status) VALUES 
        ('Zahid Khan', 'Kidney', 'O+', '+1234567899', 'zahid@email.com', 'Registered'),
        ('Nadia Ahmed', 'Liver', 'A-', '+1234567800', 'nadia@email.com', 'Registered'),
        ('Omar Farooq', 'Heart', 'B+', '+1234567801', 'omar@email.com', 'Registered'),
        ('Sadia Malik', 'Lungs', 'AB-', '+1234567802', 'sadia@email.com', 'Registered')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample organ donors created.<br>";
    } else {
        echo "Error creating organ donors: " . $conn->error . "<br>";
    }
}

// Add sample vaccine inventory
$result = $conn->query("SELECT * FROM vaccine_inventory LIMIT 1");
if ($result && $result->num_rows == 0) {
    $sql = "INSERT INTO vaccine_inventory (vaccine_name, batch_number, manufacturer, expiry_date, available_doses, storage_temp) VALUES 
        ('COVID-19 Vaccine', 'COV001', 'Pfizer', '2024-12-31', 500, '2-8°C'),
        ('Hepatitis B Vaccine', 'HEP001', 'GSK', '2025-06-30', 200, '2-8°C'),
        ('MMR Vaccine', 'MMR001', 'Merck', '2025-03-15', 150, '2-8°C'),
        ('Influenza Vaccine', 'FLU001', 'Sanofi', '2024-08-31', 300, '-20°C')";
    if ($conn->query($sql) === TRUE) {
        echo "Sample vaccine inventory created.<br>";
    } else {
        echo "Error creating vaccine inventory: " . $conn->error . "<br>";
    }
}

$conn->close();
echo "<br><a href='index.php'>Go to Hospital App</a>";
?>

