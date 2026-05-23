<?php
include 'db.php';

echo "=== Checking Patients Table ===<br>";
$result = $conn->query("SELECT id, name, email, password FROM patients LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "Patients found:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Email: " . $row['email'] . "<br>";
    }
} else {
    echo "No patients found in database. You need to register first!<br>";
}

echo "<br>=== Testing Password ===<br>";
$test_password = "test123";
$hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "Password '$test_password' hashes to: $hash<br>";
echo "Verify result: " . (password_verify($test_password, $hash) ? "PASS" : "FAIL") . "<br>";

echo "<br>=== Steps to login as patient ===<br>";
echo "1. Go to <a href='index.php'>Hospital Home</a><br>";
echo "2. Click 'Patient Register'<br>";
echo "3. Create a new account with email and password<br>";
echo "4. Then login with that email and password<br>";
?>
