<?php
// auth.php - central role-based access helpers
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirect_to_role_dashboard(?string $role = null): void
{
    $role = $role ?? ($_SESSION['role'] ?? '');
    $target = role_dashboard($role);
    header('Location: ' . $target);
    exit();
}

function require_admin()
{
    if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        header('Location: dashboard.php');
        exit();
    }

    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin-login.php');
        exit();
    }
}

function role_dashboard(string $role): string
{
    $dashboards = [
        'Admin' => 'admin-dashboard.php',
        'Doctor' => 'doctor-dashboard.php',
        'Patient' => 'dashboard.php',
        'Nurse' => 'nurse_dashboard.php',
        'Pharmacist' => 'pharmacist_dashboard.php',
                    'Receptionist' => 'receptionist_dashboard.php',
                'Lab Technician' => 'lab_technician_dashboard.php',
    ];

    return $dashboards[$role] ?? 'login.php';
}

function set_role_session(string $role, array $user): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['doctor_id'],
        $_SESSION['doctor_name'],
        $_SESSION['patient_id'],
        $_SESSION['patient_name'],
        $_SESSION['nurse_id'],
        $_SESSION['nurse_name'],
        $_SESSION['pharmacist_id'],
        $_SESSION['pharmacist_name'],
        $_SESSION['receptionist_id'],
$_SESSION['receptionist_name'],
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['username']
    );

    $_SESSION['role'] = $role;
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['name'];

    if ($role === 'Admin') {
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_name'] = $user['name'];
    } elseif ($role === 'Doctor') {
        $_SESSION['doctor_id'] = (int)$user['id'];
        $_SESSION['doctor_name'] = $user['name'];
    } elseif ($role === 'Patient') {
        $_SESSION['patient_id'] = (int)$user['id'];
        $_SESSION['patient_name'] = $user['name'];
    } elseif ($role === 'Nurse') {
        $_SESSION['nurse_id'] = (int)$user['id'];
        $_SESSION['nurse_name'] = $user['name'];
   } elseif ($role === 'Pharmacist') {
    $_SESSION['pharmacist_id'] = (int)$user['id'];
    $_SESSION['pharmacist_name'] = $user['name'];
} elseif ($role === 'Receptionist') {
    $_SESSION['receptionist_id'] = (int)$user['id'];
    $_SESSION['receptionist_name'] = $user['name'];
} elseif ($role === 'Lab Technician') {
    $_SESSION['lab_technician_id'] = (int)$user['id'];
    $_SESSION['lab_technician_name'] = $user['name'];
}
}

function login_user(?mysqli $conn, string $role, string $identifier, string $password): bool
{
    if (!$conn instanceof mysqli) {
        error_log("auth: missing mysqli connection when attempting login for role={$role}");
        return false;
    }

    ensure_auth_schema($conn);

    $roleMap = [
        'admin' => ['Admin', 'admins', 'username', 'name'],
        'lab_technician' => ['Lab Technician', 'lab_technicians', 'username', 'name'],
        'doctor' => ['Doctor', 'doctors', 'email', 'name'],
        'patient' => ['Patient', 'patients', 'email', 'name'],
        'nurse' => ['Nurse', 'nurses', 'email', 'nurse_name'],
        'pharmacist' => ['Pharmacist', 'pharmacists', 'username', 'name'],
        'receptionist' => ['Receptionist', 'receptionists', 'username', 'name'],
    ];

    if (!isset($roleMap[$role]) || $identifier === '' || $password === '') {
        error_log("auth: invalid input - role={$role} identifier_present=" . ($identifier !== '') . " password_present=" . ($password !== ''));
        return false;
    }

    [$sessionRole, $table, $field, $nameField] = $roleMap[$role];

    if ($role === 'doctor') {
        $sql = "SELECT id, name, password FROM doctors WHERE LOWER(email) = LOWER(?) OR LOWER(name) = LOWER(?) OR LOWER(name) LIKE LOWER(CONCAT('%', ?, '%')) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (doctor branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower($identifier);
        $stmt->bind_param('sss', $lookup, $lookup, $lookup);
    } elseif ($role === 'patient') {
        $sql = "SELECT id, name, password FROM patients WHERE LOWER(email) = LOWER(?) OR LOWER(name) = LOWER(?) OR LOWER(name) LIKE LOWER(CONCAT('%', ?, '%')) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (patient branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('sss', $lookup, $lookup, $lookup);
    } elseif ($role === 'nurse') {
        $sql = "SELECT id, $nameField AS name, password FROM $table WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (nurse branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('ss', $lookup, $lookup);
    } elseif ($role === 'pharmacist') {
        $sql = "SELECT id, $nameField AS name, password FROM $table WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (pharmacist branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('ss', $lookup, $lookup);
    } elseif ($role === 'receptionist') {
        // Allow receptionists to login by username OR name (case-insensitive)
        $sql = "SELECT id, $nameField AS name, password FROM $table WHERE LOWER(username) = LOWER(?) OR LOWER(name) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (receptionist branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('ss', $lookup, $lookup);
    } else {
        $sql = "SELECT id, $nameField AS name, password FROM $table WHERE LOWER($field) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('s', $lookup);
    }

    // allow lab technicians to login by username or email
    if ($role === 'lab_technician') {
        $sql = "SELECT id, name, password FROM lab_technicians WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("auth prepare failed (lab technician branch): " . $conn->error . " | sql=" . $sql);
            return false;
        }
        $lookup = mb_strtolower(trim($identifier));
        $stmt->bind_param('ss', $lookup, $lookup);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        error_log("auth: no user found for role={$role} table={$table} identifier={$identifier}");
        return false;
    }

    $storedPassword = isset($user['password']) ? $user['password'] : '';
    $passwordIsValid = is_string($storedPassword) && password_verify($password, $storedPassword);

    if (!$passwordIsValid && is_string($storedPassword)) {
        $storedInfo = password_get_info($storedPassword);

        if ($storedInfo['algo'] === 0 && hash_equals($storedPassword, $password)) {
            $passwordIsValid = true;
        }
    }

    if ($passwordIsValid && is_string($storedPassword) && password_get_info($storedPassword)['algo'] === 0) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
        if ($update) {
            $userId = (int)$user['id'];
            $update->bind_param("si", $newHash, $userId);
            $update->execute();
            $update->close();
        }
    }

    if (!$passwordIsValid) {
        error_log("auth: invalid password for role={$role} user_id=" . ($user['id'] ?? 'unknown'));
        return false;
    }

    if (!headers_sent() && php_sapi_name() !== 'cli') {
        session_regenerate_id(true);
    }

    set_role_session($sessionRole, $user);
    return true;
}

function ensure_nurse_profile_columns(?mysqli $conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }

    $columnsResult = $conn->query('SHOW COLUMNS FROM nurses');
    $existingColumns = [];
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $existingColumns[$column['Field']] = true;
        }
    }

    if (!isset($existingColumns['address'])) {
        $conn->query("ALTER TABLE nurses ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER department");
    }

    if (!isset($existingColumns['profile_photo'])) {
        $conn->query("ALTER TABLE nurses ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER address");
    }
}

function ensure_lab_technician_address_column(mysqli $conn): void
{
    $columnResult = $conn->query("SHOW COLUMNS FROM lab_technicians LIKE 'address'");
    if ($columnResult && $columnResult->num_rows === 0) {
        $conn->query("ALTER TABLE lab_technicians ADD COLUMN address VARCHAR(255) DEFAULT NULL");
    }
}

function ensure_auth_schema(?mysqli $conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }

    // Ensure InnoDB is available
    $engines = $conn->query("SHOW ENGINES");
    $hasInnoDB = false;
    if ($engines instanceof mysqli_result) {
        while ($row = $engines->fetch_assoc()) {
            if ($row['Engine'] === 'InnoDB' && $row['Support'] !== 'NO') {
                $hasInnoDB = true;
                break;
            }
        }
    }
    $engine = $hasInnoDB ? 'InnoDB' : 'MyISAM';

    // Try to create patients table - if it fails due to existing invalid table, drop it first
    $createPatients = "CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_code VARCHAR(50) UNIQUE NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NULL,
        phone VARCHAR(20),
        date_of_birth DATE,
        password VARCHAR(255) NULL,
        patient_type VARCHAR(30) NOT NULL DEFAULT 'Online',
        has_login TINYINT(1) NOT NULL DEFAULT 0,
        department VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine DEFAULT CHARSET=utf8mb4";
    
    $result = $conn->query($createPatients);
    if (!$result) {
        // If creation failed, try to drop and recreate
        if (stripos($conn->error, 'exists') !== false || stripos($conn->error, 'engine') !== false) {
            $conn->query("DROP TABLE IF EXISTS patients");
            $result = $conn->query($createPatients);
            if (!$result) {
                error_log("Failed to create patients table: " . $conn->error);
            }
        }
    }

    $existingColumns = [];
    $columnsResult = $conn->query('SHOW COLUMNS FROM patients');
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $existingColumns[$column['Field']] = $column;
        }
    }

    if (!isset($existingColumns['patient_code'])) {
        $conn->query("ALTER TABLE patients ADD COLUMN patient_code VARCHAR(50) UNIQUE NULL AFTER id");
    }

    if (isset($existingColumns['email']) && strtoupper($existingColumns['email']['Null']) === 'NO') {
        $conn->query("ALTER TABLE patients MODIFY COLUMN email VARCHAR(255) UNIQUE NULL");
    }

    if (isset($existingColumns['password']) && strtoupper($existingColumns['password']['Null']) === 'NO') {
        $conn->query("ALTER TABLE patients MODIFY COLUMN password VARCHAR(255) NULL");
    }

    if (!isset($existingColumns['patient_type'])) {
        $conn->query("ALTER TABLE patients ADD COLUMN patient_type VARCHAR(30) NOT NULL DEFAULT 'Online' AFTER password");
    }

    if (!isset($existingColumns['has_login'])) {
        $conn->query("ALTER TABLE patients ADD COLUMN has_login TINYINT(1) NOT NULL DEFAULT 0 AFTER patient_type");
    }

    if (!isset($existingColumns['department'])) {
        $conn->query("ALTER TABLE patients ADD COLUMN department VARCHAR(255) DEFAULT NULL AFTER has_login");
    }

    $conn->query("UPDATE patients SET patient_code = CONCAT('P-', LPAD(id, 4, '0')) WHERE patient_code IS NULL");

    // Same for admins table
    $createAdmins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine DEFAULT CHARSET=utf8mb4";
    
    $result = $conn->query($createAdmins);
    if (!$result) {
        // If creation failed, try to drop and recreate
        if (stripos($conn->error, 'exists') !== false || stripos($conn->error, 'engine') !== false) {
            $conn->query("DROP TABLE IF EXISTS admins");
            $result = $conn->query($createAdmins);
            if (!$result) {
                error_log("Failed to create admins table: " . $conn->error);
            }
        }
    }

    // Create default admin if none exists
    $adminCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM admins");
    if ($adminCheckStmt) {
        $adminCheckStmt->execute();
        $adminCheckResult = $adminCheckStmt->get_result();
        if ($adminCheckResult) {
            $row = $adminCheckResult->fetch_assoc();
            if ((int)($row['count'] ?? 0) === 0) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admins (name, username, password) VALUES (?, ?, ?)");
                if ($stmt) {
                    $name = 'Administrator';
                    $username = 'admin';
                    $stmt->bind_param('sss', $name, $username, $hash);
                    if (!$stmt->execute()) {
                        error_log("Failed to insert default admin: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
        $adminCheckStmt->close();
    }

    $doctorPasswordColumn = $conn->query("SHOW COLUMNS FROM doctors LIKE 'password'");
    if ($doctorPasswordColumn && $doctorPasswordColumn->num_rows === 0) {
        $conn->query("ALTER TABLE doctors ADD COLUMN password VARCHAR(255) NULL");
    }

    $doctorStatusColumn = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
    if ($doctorStatusColumn && $doctorStatusColumn->num_rows === 0) {
        $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
    }

    $nurseUsernameColumn = $conn->query("SHOW COLUMNS FROM nurses LIKE 'username'");
    if ($nurseUsernameColumn && $nurseUsernameColumn->num_rows === 0) {
        $conn->query("ALTER TABLE nurses ADD COLUMN username VARCHAR(100) UNIQUE NULL AFTER email");
        $conn->query("UPDATE nurses SET username = CONCAT('nurse', id) WHERE username IS NULL OR username = ''");
    }

    $emptyDoctorPasswords = $conn->query("SELECT COUNT(*) AS total FROM doctors WHERE password IS NULL OR password = ''");
    if ($emptyDoctorPasswords && (int)$emptyDoctorPasswords->fetch_assoc()['total'] > 0) {
        $defaultDoctorPassword = password_hash('doctor123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE doctors SET password = ? WHERE password IS NULL OR password = ''");
        if ($stmt) {
            $stmt->bind_param('s', $defaultDoctorPassword);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS pharmacists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pharmacistEmailColumn = $conn->query("SHOW COLUMNS FROM pharmacists LIKE 'email'");
    if ($pharmacistEmailColumn && $pharmacistEmailColumn->num_rows === 0) {
        $conn->query("ALTER TABLE pharmacists ADD COLUMN email VARCHAR(255) UNIQUE NULL AFTER username");
    }

    $pharmacistCount = $conn->query("SELECT COUNT(*) AS total FROM pharmacists");
    if ($pharmacistCount && (int)$pharmacistCount->fetch_assoc()['total'] === 0) {
        $hash = password_hash('pharmacist123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO pharmacists (name, username, password) VALUES ('Hospital Pharmacist', 'pharmacist', ?)");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->close();
    }

    $conn->query("CREATE TABLE IF NOT EXISTS receptionists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $receptionistCount = $conn->query("SELECT COUNT(*) AS total FROM receptionists");
    if ($receptionistCount && (int)$receptionistCount->fetch_assoc()['total'] === 0) {
        $hash = password_hash('reception123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO receptionists (name, username, password) VALUES ('Hospital Receptionist', 'receptionist', ?)");
        if ($stmt) {
            $stmt->bind_param('s', $hash);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS nurses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NULL,
        username VARCHAR(100) UNIQUE NULL,
        phone VARCHAR(50) DEFAULT NULL,
        department VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine DEFAULT CHARSET=utf8mb4");

    $defaultNurseCheck = $conn->query("SELECT COUNT(*) AS total FROM nurses WHERE username = 'nurse' OR email = 'nurse@hospital.com'");
    if ($defaultNurseCheck && (int)$defaultNurseCheck->fetch_assoc()['total'] === 0) {
        $nursePassword = password_hash('nurse123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO nurses (nurse_name, email, username, phone, department, password) VALUES ('Sample Nurse', 'nurse@hospital.com', 'nurse', '+1234567800', 'General Ward', ?)");
        if ($stmt) {
            $stmt->bind_param('s', $nursePassword);
            $stmt->execute();
            $stmt->close();
        }

    }

    // Track login attempts for throttling (identifier or IP)
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure lab_technicians table exists and seed a default technician if none exists
    $conn->query("CREATE TABLE IF NOT EXISTS lab_technicians (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        laboratory VARCHAR(255) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ensure_lab_technician_address_column($conn);

    $labTechCount = $conn->query("SELECT COUNT(*) AS total FROM lab_technicians");
    if ($labTechCount && (int)$labTechCount->fetch_assoc()['total'] === 0) {
        $hash = password_hash('labtech123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO lab_technicians (name, username, password) VALUES ('Lab Technician', 'labtech', ?)");
        if ($stmt) { $stmt->bind_param('s', $hash); $stmt->execute(); $stmt->close(); }
    }
}

// Lab technician access helper (declared once globally)
function require_lab_technician()
{
    require_role('Lab Technician', 'lab_technician_login.php');
}

// (moved DB table creation into ensure_auth_schema to run when $conn is available)
// nothing to run at file-load time
// End of auth.php top-level


function require_role(string $role, string $loginPage = 'login.php'): void
{
    $sessionRole = $_SESSION['role'] ?? '';
    $roleSessionMap = [
        'Admin' => 'admin_id',
        'Doctor' => 'doctor_id',
        'Patient' => 'patient_id',
        'Nurse' => 'nurse_id',
        'Pharmacist' => 'pharmacist_id',
        'Receptionist' => 'receptionist_id',
        'Lab Technician' => 'lab_technician_id',
    ];

    $hasMatchingSession = ($sessionRole === $role) || isset($_SESSION[$roleSessionMap[$role] ?? '']);

    if (!$hasMatchingSession) {
        header('Location: ' . $loginPage);
        exit();
    }
}

function require_doctor()
{
    require_role('Doctor');
}

function require_nurse()
{
    require_role('Nurse', 'nurse_login.php');
}

function require_pharmacist()
{
    require_role('Pharmacist', 'pharmacist_login.php');
}
function require_receptionist()
{
    require_role('Receptionist');
}

function require_patient()
{
    if (isset($_SESSION['admin_id']) && !isset($_SESSION['patient_id'])) {
        header('Location: admin-dashboard.php');
        exit();
    }

    if (!isset($_SESSION['patient_id'])) {
        header('Location: login.php');
        exit();
    }
}

function current_patient_id()
{
    return $_SESSION['patient_id'] ?? null;
}

// Prevent patients from accessing admin pages
function ensure_not_patient_on_admin()
{
    if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        // Logged in as patient but trying to access admin resource
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit();
    }
}
