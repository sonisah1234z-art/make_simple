<?php
session_start();
include 'db.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

$appointments = [];
$result = $conn->query("SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = $patient_id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Dashboard</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #1e293b 34%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
    }
    .layout {
        max-width: 1100px;
        margin: 0 auto;
    }
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .brand {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .brand-icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        background: rgba(99, 102, 241, 0.2);
        display: grid;
        place-items: center;
        font-size: 24px;
        font-weight: 700;
        color: #e0e7ff;
    }
    h1 { font-size: 34px; letter-spacing: -0.04em; }
    p.subtitle { color: #94a3b8; margin-top: 8px; line-height: 1.7; font-size: 14px; }
    .button { background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; border: none; border-radius: 14px; padding: 12px 18px; cursor: pointer; font-weight: 700; transition: transform .2s ease; font-size: 14px; }
    .button:hover { transform: translateY(-2px); }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 22px; }
    .card {
        background: rgba(15, 23, 42, 0.92);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
    }
    .card h2 { font-size: 20px; margin-bottom: 14px; }
    .card p { color: #cbd5e1; line-height: 1.7; }
    .card small { color: #94a3b8; }
    .appointments {
        margin-top: 30px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
        font-size: 14px;
    }
    th, td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    }
    th { color: #cbd5e1; font-weight: 700; }
    td { color: #e2e8f0; }
    .status {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }
    .status.Pending { background: rgba(245, 158, 11, 0.16); color: #f59e0b; }
    .status.Approved { background: rgba(34, 197, 94, 0.16); color: #22c55e; }
    .status.Cancelled { background: rgba(239, 68, 68, 0.16); color: #ef4444; }
    .empty { padding: 20px 0; color: #94a3b8; }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { flex-direction: column; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
        .brand { gap: 12px; }
        .button { padding: 10px 14px; font-size: 13px; }
        h1 { font-size: 26px; }
        .card { padding: 16px; }
        table { font-size: 12px; }
        th, td { padding: 10px 8px; }
    }
    @media (max-width: 480px) {
        body { padding: 12px; }
        .page { padding: 16px 12px; }
        .grid { grid-template-columns: 1fr; gap: 12px; }
        .card { padding: 14px; }
        table { font-size: 11px; }
        th, td { padding: 6px; }
        .brand-icon { width: 44px; height: 44px; font-size: 18px; }
        h1 { font-size: 20px; }
        .button { padding: 8px 12px; font-size: 11px; }
    }
</style>
</head>
<body>
<div class="layout">
    <div class="topbar">
        <div class="brand">
            <div class="brand-icon">H</div>
            <div>
                <h1>Patient Dashboard</h1>
                <p class="subtitle">Welcome back, <?php echo htmlspecialchars($patient_name); ?>. Manage your appointments and records below.</p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="button" onclick="location.href='book-appointment.php'">Book Appointment</button>
            <button class="button" onclick="location.href='ambulance-booking.php'" style="background: rgba(255,255,255,0.12); color: #e2e8f0;">Book Ambulance</button>
            <button class="button" onclick="location.href='patient-billing.php'" style="background: rgba(255,255,255,0.12); color: #e2e8f0;">View Bills</button>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 8px; margin-right: 8px; font-weight: 600; align-self: center;">Home</a>
            <button class="button" onclick="location.href='logout.php'" style="background: rgba(255,255,255,0.12); color: #e2e8f0;">Logout</button>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Upcoming Appointments</h2>
            <p>Keep track of your confirmed and pending appointments with hospital specialists.</p>
        </div>
        <div class="card">
            <h2>Doctor Network</h2>
            <p>See top doctors available in cardiology, general medicine, pediatrics, and more.</p>
        </div>
        <div class="card">
            <h2>Billing & Payments</h2>
            <p>View your billing history and payment status for all medical services.</p>
        </div>
    </div>

    <div class="card appointments">
        <h2>Your Appointments</h2>
        <?php if (count($appointments) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($appointment['status']); ?>"><?php echo htmlspecialchars($appointment['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty">No appointments found yet. Book a new appointment to get started.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>