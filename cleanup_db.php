<?php
/**
 * Database Cleanup Script
 * This removes corrupted tables and recreates them properly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Database Cleanup...\n\n";

try {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'hospital_system';
    
    $conn = @new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✓ Connected to database\n\n";
    
    // Step 1: Drop corrupted tables
    echo "Step 1: Dropping corrupted tables...\n";
    $tables_to_drop = ['patients', 'admins', 'nurses'];
    foreach ($tables_to_drop as $table) {
        if ($conn->query("DROP TABLE IF EXISTS $table")) {
            echo "  ✓ Dropped $table\n";
        } else {
            echo "  ⚠ Could not drop $table: " . $conn->error . "\n";
        }
    }
    
    // Step 2: Recreate tables
    echo "\nStep 2: Creating fresh tables...\n";
    
    // Patients table
    $patients_sql = "CREATE TABLE patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        date_of_birth DATE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($patients_sql)) {
        echo "  ✓ Created patients table\n";
    } else {
        throw new Exception("Failed to create patients: " . $conn->error);
    }
    
    // Admins table
    $admins_sql = "CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($admins_sql)) {
        echo "  ✓ Created admins table\n";
    } else {
        throw new Exception("Failed to create admins: " . $conn->error);
    }
    
    // Nurses table
    $nurses_sql = "CREATE TABLE IF NOT EXISTS nurses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nurse_name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        username VARCHAR(100),
        phone VARCHAR(20),
        department VARCHAR(100),
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($nurses_sql)) {
        echo "  ✓ Created nurses table\n";
    } else {
        throw new Exception("Failed to create nurses: " . $conn->error);
    }
    
    // Step 3: Create default admin
    echo "\nStep 3: Creating default admin user...\n";
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (name, username, password) VALUES (?, ?, ?)");
    if ($stmt) {
        $name = 'Administrator';
        $username = 'admin';
        $stmt->bind_param('sss', $name, $username, $admin_password);
        if ($stmt->execute()) {
            echo "  ✓ Created admin user (username: admin, password: admin123)\n";
        } else {
            throw new Exception("Failed to insert admin: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // Step 4: Verify
    echo "\nStep 4: Verifying tables...\n";
    $verify_tables = ['patients', 'admins', 'nurses'];
    foreach ($verify_tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as cnt FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "  ✓ $table: " . $row['cnt'] . " records\n";
        } else {
            echo "  ✗ $table: " . $conn->error . "\n";
        }
    }
    
    echo "\n✓ Database cleanup complete!\n";
    echo "\nYou can now:\n";
    echo "1. Go to http://localhost/make_simple/login.php\n";
    echo "2. Select Role: Admin\n";
    echo "3. Username: admin\n";
    echo "4. Password: admin123\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
