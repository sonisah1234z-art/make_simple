<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== HOSPITAL SYSTEM DEBUG ===\n\n";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    include 'db.php';
    if ($conn instanceof mysqli && !$conn->connect_error) {
        echo "   ✓ Connected to hospital_system database\n";
    } else {
        echo "   ✗ Database connection failed\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Check if tables exist
echo "\n2. Checking required tables...\n";
$tables = ['patients', 'admins', 'doctors', 'nurses'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $countResult = $conn->query("SELECT COUNT(*) as cnt FROM $table");
        if ($countResult && $countResult instanceof mysqli_result) {
            $count = $countResult->fetch_assoc()['cnt'];
            echo "   ✓ $table exists ($count records)\n";
        } else {
            $error = $conn->error;
            echo "   ✓ $table exists (query error: $error)\n";
        }
    } else {
        echo "   ✗ $table MISSING\n";
    }
}

// Test 3: Check admin user
echo "\n3. Checking admin user...\n";
$adminResult = $conn->query("SELECT id, username, password FROM admins WHERE username='admin'");
if ($adminResult === false) {
    echo "   ✗ Query error: " . $conn->error . "\n";
} elseif ($adminResult->num_rows > 0) {
    $admin = $adminResult->fetch_assoc();
    echo "   ✓ Admin user found (ID: " . $admin['id'] . ")\n";
    
    // Test password
    echo "\n4. Testing admin password...\n";
    if (password_verify('admin123', $admin['password'])) {
        echo "   ✓ Password 'admin123' is correct\n";
    } else {
        echo "   ✗ Password verification failed\n";
        echo "   Hash: " . substr($admin['password'], 0, 20) . "...\n";
    }
} else {
    echo "   ✗ Admin user NOT found (no rows returned)\n";
    // Check if table has any records
    $anyAdmin = $conn->query("SELECT id, username FROM admins LIMIT 1");
    if ($anyAdmin === false) {
        echo "      Table query error: " . $conn->error . "\n";
    } elseif ($anyAdmin->num_rows > 0) {
        echo "      But found other users in admins table\n";
    } else {
        echo "      The admins table is completely empty\n";
    }
}

// Test 4: Test login function
echo "\n5. Testing login_user() function...\n";
require_once 'auth.php';
$oldSession = $_SESSION;
$loginResult = login_user($conn, 'admin', 'admin', 'admin123');
$_SESSION = $oldSession;

if ($loginResult) {
    echo "   ✓ Login successful!\n";
} else {
    echo "   ✗ Login failed\n";
    
    // Debug: try to get the admin record
    echo "\n   Debug info:\n";
    $debug = $conn->query("SELECT id, username FROM admins LIMIT 1");
    if ($debug && $debug->num_rows > 0) {
        $row = $debug->fetch_assoc();
        echo "   - Sample admin: " . $row['username'] . " (ID: " . $row['id'] . ")\n";
    }
}

// Test 5: Check register functionality
echo "\n6. Checking patient registration...\n";
$patientCountResult = $conn->query("SELECT COUNT(*) as cnt FROM patients");
if ($patientCountResult && $patientCountResult instanceof mysqli_result) {
    $patientCount = $patientCountResult->fetch_assoc()['cnt'];
    echo "   Total patients: $patientCount\n";
} else {
    echo "   Could not get patient count\n";
}

$conn->close();

echo "\n=== END DEBUG ===\n";
?>
