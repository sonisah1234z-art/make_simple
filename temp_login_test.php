<?php
require_once "db.php";
require_once "auth.php";
$email = "tempuser@example.com";
$name = "Temp User";
$password = "secret123";
$hash = password_hash($password, PASSWORD_DEFAULT);
$phone = '0000000000';
$dob = '1990-01-01';
$stmt = $conn->prepare("INSERT INTO patients (name, email, phone, date_of_birth, password, patient_type) VALUES (?, ?, ?, ?, ?, 'Online')");
if (!$stmt) { echo "INSERT PREP FAILED: " . $conn->error . "\n"; exit(1); }
$stmt->bind_param('sssss', $name, $email, $phone, $dob, $hash);
if (!$stmt->execute()) { echo "INSERT EXEC FAILED: " . $stmt->error . "\n"; exit(1); }
$newId = $conn->insert_id;
$code = 'P-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);
$update = $conn->prepare("UPDATE patients SET patient_code = ? WHERE id = ?");
$update->bind_param('si', $code, $newId);
$update->execute();
$update->close();
$stmt->close();

echo "Inserted patient id=$newId email=$email\n";

if (login_user($conn, 'patient', $email, $password)) {
    echo "LOGIN SUCCESS\n";
} else {
    echo "LOGIN FAIL\n";
}

$conn->query("DELETE FROM patients WHERE id = $newId");
$conn->close();
?>
