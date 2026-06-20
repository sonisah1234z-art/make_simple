<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$admin_name = $_SESSION['admin_name'];

// Get report data
$patientCount = $conn->query('SELECT COUNT(*) AS total FROM patients')->fetch_assoc()['total'];
$doctorCount = $conn->query('SELECT COUNT(*) AS total FROM doctors')->fetch_assoc()['total'];
$appointmentCount = $conn->query('SELECT COUNT(*) AS total FROM appointments')->fetch_assoc()['total'];
$appointmentApproved = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Approved'")->fetch_assoc()['total'];
$appointmentCancelled = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Cancelled'")->fetch_assoc()['total'];

$totalBilled = $conn->query('SELECT SUM(amount) AS total FROM billings')->fetch_assoc()['total'] ?? 0;
$totalPaid = $conn->query("SELECT SUM(amount) AS total FROM billings WHERE status = 'Paid'")->fetch_assoc()['total'] ?? 0;
$totalPending = $totalBilled - $totalPaid;

$appointmentsByDoctor = [];
$result = $conn->query("SELECT d.name, COUNT(a.id) AS count FROM appointments a JOIN doctors d ON a.doctor_id = d.id GROUP BY d.id, d.name ORDER BY count DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointmentsByDoctor[] = $row;
    }
}

$appointmentsByStatus = [];
$result = $conn->query("SELECT status, COUNT(*) AS count FROM appointments GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointmentsByStatus[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reports</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
    }
    .page { max-width: 1400px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    h1 { font-size: 28px; }
    @media (max-width: 640px) { h1 { font-size: 22px; } }
    .subtitle { color: #94a3b8; margin-top: 4px; font-size: 14px; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 14px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 600; cursor: pointer; transition: transform .18s ease; text-decoration: none; font-size: 14px; }
    .button:hover { transform: translateY(-1px); }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 16px; padding: 16px; }
    .stat-card h3 { font-size: 12px; color: #cbd5e1; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-card .value { font-size: 32px; font-weight: 700; }
    .stat-card .subtext { font-size: 12px; color: #94a3b8; margin-top: 6px; }
    .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 20px; padding: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22); margin-bottom: 20px; }
    .card h2 { font-size: 18px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
    @media (max-width: 640px) { table { font-size: 12px; } }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 600; }
    td { color: #e2e8f0; }
    .chart-bar { display: flex; align-items: center; gap: 12px; margin: 10px 0; }
    .bar-label { min-width: 120px; font-size: 14px; }
    .bar { height: 24px; background: linear-gradient(90deg, #60a5fa, #2563eb); border-radius: 6px; min-width: 20px; }
    .bar-value { font-weight: 600; color: #cbd5e1; }
    .empty { color: #94a3b8; padding: 16px 0; text-align: center; }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 10px; margin-bottom: 18px; }
        h1 { font-size: 24px; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .stat-card { padding: 12px; border-radius: 14px; }
        .stat-card .value { font-size: 24px; }
        .stat-card h3 { font-size: 10px; }
        .card { padding: 16px; border-radius: 16px; margin-bottom: 16px; }
        .card h2 { font-size: 16px; margin-bottom: 12px; }
        table { font-size: 12px; }
        th, td { padding: 8px 10px; }
        .chart-bar { gap: 8px; margin: 8px 0; }
        .bar-label { min-width: 100px; font-size: 12px; }
    }
    @media (max-width: 480px) {
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .button { padding: 8px 12px; font-size: 11px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 10px; }
        .stat-card .value { font-size: 18px; }
        .stat-card h3 { font-size: 9px; }
        .stat-card .subtext { font-size: 10px; }
        .card { padding: 12px; }
        .card h2 { font-size: 14px; }
        table { font-size: 10px; }
        th, td { padding: 6px 8px; }
        .chart-bar { gap: 6px; margin: 6px 0; }
        .bar-label { min-width: 70px; font-size: 11px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Reports & Analytics</h1>
            <p class="subtitle">Comprehensive system reports and statistics</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Patients</h3>
            <div class="value"><?php echo $patientCount; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Doctors</h3>
            <div class="value"><?php echo $doctorCount; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Appointments</h3>
            <div class="value"><?php echo $appointmentCount; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <div class="value">$<?php echo number_format($totalBilled ?? 0, 0); ?></div>
            <div class="subtext">Billed amount</div>
        </div>
        <div class="stat-card">
            <h3>Amount Received</h3>
            <div class="value">$<?php echo number_format($totalPaid ?? 0, 0); ?></div>
            <div class="subtext">Paid</div>
        </div>
        <div class="stat-card">
            <h3>Outstanding</h3>
            <div class="value">$<?php echo number_format($totalPending ?? 0, 0); ?></div>
            <div class="subtext">Due</div>
        </div>
    </div>

    <div class="card">
        <h2>Appointments by Status</h2>
        <?php if (count($appointmentsByStatus) === 0): ?>
            <div class="empty">No appointment data available.</div>
        <?php else: ?>
            <?php 
            $maxCount = max(array_column($appointmentsByStatus, 'count'));
            foreach ($appointmentsByStatus as $item): 
            ?>
                <div class="chart-bar">
                    <div class="bar-label"><?php echo htmlspecialchars($item['status']); ?></div>
                    <div class="bar" style="width: <?php echo ($item['count'] / max(1, $maxCount)) * 300; ?>px;"></div>
                    <div class="bar-value"><?php echo (int)$item['count']; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Top Doctors by Appointments</h2>
        <?php if (count($appointmentsByDoctor) === 0): ?>
            <div class="empty">No doctor appointment data available.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Appointments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointmentsByDoctor as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo (int)$item['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Key Metrics</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
            <div style="padding: 16px; background: rgba(255,255,255,0.06); border-radius: 12px;">
                <div style="color: #94a3b8; font-size: 13px; margin-bottom: 8px;">Approved Appointments</div>
                <div style="font-size: 28px; font-weight: 700;"><?php echo $appointmentApproved; ?></div>
            </div>
            <div style="padding: 16px; background: rgba(255,255,255,0.06); border-radius: 12px;">
                <div style="color: #94a3b8; font-size: 13px; margin-bottom: 8px;">Cancelled Appointments</div>
                <div style="font-size: 28px; font-weight: 700;"><?php echo $appointmentCancelled; ?></div>
            </div>
            <div style="padding: 16px; background: rgba(255,255,255,0.06); border-radius: 12px;">
                <div style="color: #94a3b8; font-size: 13px; margin-bottom: 8px;">Billing Collection Rate</div>
                <div style="font-size: 28px; font-weight: 700;"><?php echo $totalBilled > 0 ? number_format(($totalPaid / $totalBilled) * 100, 1) : 0; ?>%</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>