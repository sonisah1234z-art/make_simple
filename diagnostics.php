<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Diagnostics</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        h2 { color: #0066cc; margin-top: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Hospital System - Database Diagnostics</h1>
";

include 'db.php';

// Check database connection
echo "<h2>1. Database Connection</h2>";
if ($conn instanceof mysqli) {
    if ($conn->connect_error) {
        echo "<div class='status error'>Connection Failed: " . htmlspecialchars($conn->connect_error) . "</div>";
    } else {
        echo "<div class='status success'>✓ Database Connected Successfully</div>";
        echo "<div class='code'>Database: hospital_system<br>Host: localhost</div>";
    }
} else {
    echo "<div class='status error'>✗ No database connection</div>";
    exit;
}

// Check tables
echo "<h2>2. Required Tables</h2>";
$tables = ['patients', 'admins', 'doctors', 'nurses'];
$tableStatus = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
        echo "<div class='status success'>✓ $table ($count records)</div>";
        $tableStatus[$table] = true;
    } else {
        echo "<div class='status error'>✗ $table table missing</div>";
        $tableStatus[$table] = false;
    }
}

// Check default users
echo "<h2>3. Default User Accounts</h2>";
echo "<table>";
echo "<tr><th>Role</th><th>Username/Email</th><th>Password</th><th>Status</th></tr>";

$users = [
    ['Admin', 'admin', 'admin123', 'admins', 'username'],
    ['Patient (Test)', 'test@hospital.com', 'test123', 'patients', 'email'],
    ['Doctor (Seed)', 'amina@hospital.com', 'doctor123', 'doctors', 'email'],
    ['Nurse', 'nurse@hospital.com', 'nurse123', 'nurses', 'email'],
];

foreach ($users as [$role, $identifier, $password, $table, $field]) {
    $query = $conn->query("SELECT * FROM $table WHERE $field = '$identifier'");
    $exists = $query && $query->num_rows > 0;
    $status = $exists ? '<span style="color:green">✓ Exists</span>' : '<span style="color:red">✗ Not found</span>';
    echo "<tr><td>$role</td><td>$identifier</td><td>$password</td><td>$status</td></tr>";
}

echo "</table>";

// Test login function
echo "<h2>4. Login Function Test</h2>";
require_once 'auth.php';

// Test admin login
if ($tableStatus['admins']) {
    $oldSession = $_SESSION;
    $testLogin = login_user($conn, 'admin', 'admin', 'admin123');
    $_SESSION = $oldSession;
    
    if ($testLogin) {
        echo "<div class='status success'>✓ Admin login works</div>";
    } else {
        echo "<div class='status error'>✗ Admin login failed - check password hash</div>";
    }
}

// Next steps
echo "<h2>5. Next Steps</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 4px; border-left: 4px solid #0066cc;'>";
echo "<h3>To Test Login:</h3>";
echo "<ol>";
echo "<li>Go to <a href='login.php' target='_blank'>login.php</a></li>";
echo "<li>Select Role: <strong>Admin</strong></li>";
echo "<li>Username: <strong>admin</strong></li>";
echo "<li>Password: <strong>admin123</strong></li>";
echo "<li>Click Login</li>";
echo "</ol>";
echo "<h3>To Register as Patient:</h3>";
echo "<ol>";
echo "<li>Go to <a href='register.php' target='_blank'>register.php</a></li>";
echo "<li>Fill in all fields</li>";
echo "<li>Password must be at least 6 characters</li>";
echo "<li>After registration, use your email and password to login as Patient</li>";
echo "</ol>";
echo "</div>";

// MySQL version
echo "<h2>6. System Information</h2>";
$version = $conn->server_info;
echo "<div class='code'>MySQL Version: $version</div>";

$conn->close();

echo "</div></body></html>";
?>
