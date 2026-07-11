<?php
session_start();
include 'db.php';
require_once 'auth.php';
require_receptionist();
require_once 'announcements.php';

$receptionistName = $_SESSION['receptionist_name'] ?? 'Receptionist';

// Statistics
$totalPatients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM patients"))['total'] ?? 0;
$totalDoctors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM doctors"))['total'] ?? 0;
$totalAppointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments"))['total'] ?? 0;
$totalBeds = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM beds"))['total'] ?? 0;

// Recent appointments
$appointments = mysqli_query(
    $conn,
    "SELECT a.id, p.name AS patient_name, d.name AS doctor_name, a.appointment_date, a.status FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id ORDER BY a.id DESC LIMIT 10"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receptionist Dashboard</title>

<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    background:#f4f6f9;
}

.sidebar{
    width:250px;
    height:100vh;
    background:#1e293b;
    color:white;
    position:fixed;
    left:0;
    top:0;
}

.sidebar h2{
    text-align:center;
    padding:20px;
    border-bottom:1px solid #334155;
}

.sidebar a{
    display:block;
    color:white;
    text-decoration:none;
    padding:15px 20px;
}

.sidebar a:hover{
    background:#334155;
}

.main{
    margin-left:250px;
    padding:20px;
}

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:20px;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,.1);
}

.card h3{
    margin:0;
    color:#64748b;
}

.card p{
    font-size:30px;
    font-weight:bold;
    margin-top:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
}

.table-responsive{
    overflow-x:auto;
    margin-top:20px;
}

table th,
table td{
    border:1px solid #ddd;
    padding:12px;
    text-align:left;
    white-space:nowrap;
}

table th{
    background:#0ea5e9;
    color:white;
}

@media (max-width: 767px) {
    table th,
    table td {
        padding:10px;
        font-size:14px;
    }
}
</style>
</head>
<body>

<div class="sidebar">
    <h2>Receptionist</h2>

    <a href="receptionist_dashboard.php">Dashboard</a>
    <a href="register.php">Register Patient</a>
    <a href="view_patients.php">Patients</a>
    <a href="view_appointments.php">Appointments</a>
    <a href="view_doctors.php">Doctors</a>
    <a href="bed-availability.php">Beds</a>
    <a href="ambulance-booking.php">Ambulances</a>
    <a href="about.php">About Us</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main">
<?php $context = get_current_user_context(); echo render_announcements_panel($conn, $context['role'], $context['id'], 3); ?>

    <h1>Welcome, <?php echo htmlspecialchars($receptionistName); ?></h1>

    <div class="cards">

        <div class="card">
            <h3>Total Patients</h3>
            <p><?php echo $totalPatients; ?></p>
        </div>

        <div class="card">
            <h3>Total Doctors</h3>
            <p><?php echo $totalDoctors; ?></p>
        </div>

        <div class="card">
            <h3>Total Appointments</h3>
            <p><?php echo $totalAppointments; ?></p>
        </div>

        <div class="card">
            <h3>Total Beds</h3>
            <p><?php echo $totalBeds; ?></p>
        </div>

    </div>

    <h2 style="margin-top:40px;">Recent Appointments</h2>

    <div class="table-responsive">
        <table>
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Status</th>
            </tr>

            <?php $displayIndex = 1; while($row = mysqli_fetch_assoc($appointments)): ?>
            <tr>
                <td><?php echo $displayIndex++; ?></td>
                <td><?php echo htmlspecialchars($row['patient_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['doctor_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['appointment_date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['status'] ?? 'Pending'); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

</div>

</body>
</html>