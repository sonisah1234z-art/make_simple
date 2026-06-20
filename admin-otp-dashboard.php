<?php
session_start();
require_once 'db.php';

// OPTIONAL: protect page (admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: admin-login.php");
    exit();
}

// Fetch OTP data
$sql = "
    SELECT 
        ao.id,
        ao.otp_code,
        ao.verification_status,
        ao.expires_at,
        ao.generated_at,
        a.id AS appointment_id,
        p.name AS patient_name,
        d.name AS doctor_name
    FROM appointment_otps ao
    JOIN appointments a ON ao.appointment_id = a.id
    JOIN patients p ON ao.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    ORDER BY ao.id DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>OTP Dashboard</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            padding: 20px;
        }

        h2 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }

        th {
            background: #007bff;
            color: white;
        }

        .Pending {
            color: orange;
            font-weight: bold;
        }

        .Verified {
            color: green;
            font-weight: bold;
        }

        .Expired {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>

<h2>OTP Management Dashboard</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Patient</th>
        <th>Doctor</th>
        <th>Appointment ID</th>
        <th>OTP</th>
        <th>Status</th>
        <th>Generated At</th>
        <th>Expires At</th>
    </tr>

    <?php
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['patient_name']}</td>
                <td>{$row['doctor_name']}</td>
                <td>{$row['appointment_id']}</td>
                <td><b>{$row['otp_code']}</b></td>
                <td class='{$row['verification_status']}'>{$row['verification_status']}</td>
                <td>{$row['generated_at']}</td>
                <td>{$row['expires_at']}</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='8'>No OTP records found</td></tr>";
    }
    ?>

</table>

</body>
</html>