<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];

function fetchCount(string $sql): int
{
    global $conn;
    $result = $conn->query($sql);
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

$patientCount = fetchCount('SELECT COUNT(*) AS total FROM patients');
$doctorCount = fetchCount('SELECT COUNT(*) AS total FROM doctors');
$appointmentCount = fetchCount('SELECT COUNT(*) AS total FROM appointments');
$pendingCount = fetchCount("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'");
$currentDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #1e293b 30%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
        min-height: 100vh;
    }
    .page {
        width: 100%;
        max-width: 1200px;
        padding: 40px 20px;
        background: rgba(15, 23, 42, 0.96);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 28px;
        box-shadow: 0 40px 120px rgba(15, 23, 42, 0.25);
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .brand-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        display: grid;
        place-items: center;
        font-size: 22px;
        font-weight: 700;
        color: #ffffff;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
    }
    h1 { font-size: 36px; letter-spacing: -0.04em; color: #ffffff; }
    .subtitle { color: #cbd5e1; margin-top: 8px; }
    .snapshot { color: #94a3b8; font-size: 14px; margin-top: 6px; }
    .top-right {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn-primary, .btn-secondary {
        border: none;
        border-radius: 999px;
        padding: 12px 22px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: transform .2s ease, background .2s ease, color .2s ease;
    }
    .btn-primary { background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; }
    .btn-secondary { background: rgba(255,255,255,0.12); color: #e2e8f0; }
    .btn-primary:hover, .btn-secondary:hover {
        transform: translateY(-1px);
        background: #0f172a;
        color: #ffffff;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }    @media (max-width: 768px) {
        .page { padding: 24px 16px; border-radius: 20px; }
        .header { flex-direction: column; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
        h1 { font-size: 28px; }
        .top-right { flex-direction: column; width: 100%; gap: 8px; }
        .top-right > * { width: 100%; }
        .grid { grid-template-columns: 1fr; gap: 16px; }
        .stat-card { padding: 18px; border-radius: 16px; }
        .stat-card .value { font-size: 24px; }
        .stat-card h3 { font-size: 12px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; border-radius: 16px; }
        h1 { font-size: 22px; }
        .brand-circle { width: 44px; height: 44px; font-size: 18px; }
        .btn-primary, .btn-secondary { padding: 10px 16px; font-size: 12px; border-radius: 12px; }
    }    .card {
        background: rgba(15, 23, 42, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 24px;
        padding: 24px;
        backdrop-filter: blur(20px);
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.35);
    }
    .card h2 { font-size: 20px; margin-bottom: 14px; color: #ffffff; }
    .card p { color: #cbd5e1; line-height: 1.7; }
    .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 18px; }
    .stat {
        background: rgba(255,255,255,0.06);
        border-radius: 18px;
        padding: 18px;
        min-height: 100px;
    }
    .stat span { display: block; color: #cbd5e1; }
    .stat strong { font-size: 32px; display: block; margin-top: 10px; color: #ffffff; }
    .footer-card { display: flex; flex-wrap: wrap; gap: 18px; }
    .small-card { flex: 1 1 220px; }
    .footer-card .card { min-height: 150px; }
    .action-group { margin-top: 24px; display:flex; flex-wrap:wrap; gap: 12px; }
    .action-chip {
        background: rgba(255,255,255,0.08);
        color: #e2e8f0;
        padding: 10px 16px;
        border-radius: 999px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .action-chip::before { content: '•'; color: #60a5fa; }
    .management-list { margin-top: 18px; display: grid; gap: 12px; }
    .management-link { display: block; padding: 16px 18px; border-radius: 18px; background: rgba(255,255,255,0.05); color: #e2e8f0; text-decoration: none; transition: background .2s ease, color .2s ease; }
    .management-link:hover { background: rgba(96, 165, 250, 0.12); color: #ffffff; }
    @media (max-width: 768px) {
        h1 { font-size: 28px; }
        .header { flex-direction: column; align-items: flex-start; }
        .top-right { width: 100%; }
    }
    @media (max-width: 480px) {
        .grid { grid-template-columns: 1fr; }
        .stats { grid-template-columns: 1fr; }
        .card { padding: 16px; }
        .stat { padding: 14px; }
        .stat strong { font-size: 24px; }
        .btn-primary, .btn-secondary { padding: 10px 16px; font-size: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="brand">
            <div class="brand-circle">H</div>
            <div>
                <h1>Admin Dashboard</h1>
                <div class="subtitle">Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Manage the hospital system with ease.</div>
                <div class="snapshot">Snapshot for <?php echo $currentDate; ?></div>
            </div>
        </div>
        <div class="top-right">
            <button class="btn-secondary" onclick="location.href='admin-reports.php'">Reports</button>
            <button class="btn-secondary" onclick="location.href='admin-appointments.php'">Appointments</button>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-right: 12px; font-weight: 600;">Home</a>
            <button class="btn-primary" onclick="location.href='logout.php'">Logout</button>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Patients</h2>
            <p>Registered patients in the hospital system.</p>
            <div class="stats"><span>Total patients</span><strong><?php echo $patientCount; ?></strong></div>
        </div>
        <div class="card">
            <h2>Doctors</h2>
            <p>Available doctors currently listed in the system.</p>
            <div class="stats"><span>Total doctors</span><strong><?php echo $doctorCount; ?></strong></div>
        </div>
        <div class="card">
            <h2>Appointments</h2>
            <p>All scheduled appointments with current status breakdown.</p>
            <div class="stats"><span>Total appointments</span><strong><?php echo $appointmentCount; ?></strong></div>
        </div>
        <div class="card">
            <h2>Pending</h2>
            <p>Appointments that are waiting for confirmation.</p>
            <div class="stats"><span>Pending approvals</span><strong><?php echo $pendingCount; ?></strong></div>
        </div>
    </div>

    <div class="card small-card">
        <h2>Quick Actions</h2>
        <div class="management-list">
            <a class="management-link" href="admin-patients.php">Manage Patients</a>
            <a class="management-link" href="admin-doctors.php">Manage Doctors</a>
            <a class="management-link" href="admin-appointments.php">Manage Appointments</a>
            <a class="management-link" href="admin-billing.php">Billing</a>
            <a class="management-link" href="admin-reports.php">Reports & Analytics</a>
            <a class="management-link" href="admin-ambulance.php">Ambulance Management</a>
            <a class="management-link" href="admin-blood-bank.php">Blood Bank</a>
            <a class="management-link" href="admin-organ-donation.php">Organ Donation</a>
            <a class="management-link" href="admin-vaccination.php">Vaccination Center</a>
        </div>
    </div>

    <div class="card small-card">
        <h2>Insights</h2>
        <p>Use the links above to add doctors, review patients, and update appointment status.</p>
        <div class="action-group">
            <div class="action-chip">Patient care</div>
            <div class="action-chip">Doctor roster</div>
            <div class="action-chip">Appointment flow</div>
        </div>
    </div>
</div>
</body>
</html>