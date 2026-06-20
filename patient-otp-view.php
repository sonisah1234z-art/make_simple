<?php
session_start();
require_once 'db.php';

// Ensure patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header("Location: patient-login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];

$stmt = $conn->prepare("
    SELECT 
        otp_code,
        appointment_id,
        generated_at,
        expires_at,
        verification_status
    FROM appointment_otps
    WHERE patient_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My OTPs</title>
    <style>
        body { font-family: Arial; background:#f4f4f4; padding:20px; }
        table { width:100%; background:white; border-collapse: collapse; }
        th, td { border:1px solid #ccc; padding:10px; text-align:center; }
        th { background:#007bff; color:white; }
    </style>
</head>

<body>

<h2>My Appointment OTPs</h2>

<table>
    <tr>
        <th>Appointment ID</th>
        <th>OTP</th>
        <th>Status</th>
        <th>Generated At</th>
        <th>Expires At</th>
    </tr>

    <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['appointment_id']}</td>
                <td><b>{$row['otp_code']}</b></td>
                <td>{$row['verification_status']}</td>
                <td>{$row['generated_at']}</td>
                <td>{$row['expires_at']}</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='5'>No OTP found</td></tr>";
    }
    ?>

</table>

</body>
</html>