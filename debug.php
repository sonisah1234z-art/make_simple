<?php
include 'db.php';

echo "=== Checking Admins Table ===<br>";
$result = $conn->query("SELECT id, name, username, password FROM admins");
if ($result && $result->num_rows > 0) {
    echo "Admins found:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Username: " . $row['username'] . " | Password Hash: " . substr($row['password'], 0, 20) . "...<br>";
    }
} else {
    echo "No admins found in database!<br>";
}

echo "<br>=== Testing Password ===<br>";
$test_password = "admin123";
$hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "Password 'admin123' hashes to: $hash<br>";
echo "Verify result: " . (password_verify($test_password, $hash) ? "PASS" : "FAIL") . "<br>";

if ($result && $result->num_rows > 0) {
    $result->data_seek(0);
    $admin = $result->fetch_assoc();
    echo "Verify existing admin password: " . (password_verify($test_password, $admin['password']) ? "PASS" : "FAIL") . "<br>";
}
?>
