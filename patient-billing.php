<?php
session_start();
include 'db.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

$billings = [];
$result = $conn->query("SELECT b.id, d.name AS doctor_name, b.amount, b.description, b.status, b.created_at FROM billings b JOIN appointments a ON b.appointment_id = a.id JOIN doctors d ON a.doctor_id = d.id WHERE b.patient_id = $patient_id ORDER BY b.created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }
}

$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;
foreach ($billings as $billing) {
    if ($billing['status'] === 'Cancelled') {
        continue;
    }
    $total_amount += $billing['amount'];
    if ($billing['status'] === 'Paid') {
        $paid_amount += $billing['amount'];
    } elseif ($billing['status'] === 'Pending') {
        $pending_amount += $billing['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bills</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #1e293b 34%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
    }
    .page { max-width: 1000px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    h1 { font-size: 28px; }
    @media (max-width: 640px) { h1 { font-size: 22px; } }
    .subtitle { color: #94a3b8; margin-top: 4px; font-size: 14px; }
    .link { color: #93c5fd; text-decoration: none; font-weight: 600; }
    .link:hover { text-decoration: underline; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 16px; padding: 18px; }
    .stat-card h3 { font-size: 13px; color: #cbd5e1; margin-bottom: 8px; }
    .stat-card .value { font-size: 28px; font-weight: 700; }
    .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 20px; padding: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22); }
    .card h2 { font-size: 18px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
    @media (max-width: 640px) { table { font-size: 12px; } }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 600; }
    td { color: #e2e8f0; }
    .status { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .status.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
    .status.Paid { background: rgba(34,197,94,0.16); color: #22c55e; }
    .status.Cancelled { background: rgba(239,68,68,0.16); color: #ef4444; }
    .empty { color: #94a3b8; padding: 16px 0; text-align: center; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 14px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 10px; margin-bottom: 18px; }
        h1 { font-size: 24px; }
        .stats { gap: 12px; margin-bottom: 18px; }
        .stat-card { padding: 14px; border-radius: 14px; }
        .stat-card .value { font-size: 20px; }
        .stat-card h3 { font-size: 12px; }
        .card { padding: 16px; border-radius: 16px; }
        .card h2 { font-size: 16px; }
        table { font-size: 12px; }
        th, td { padding: 8px 10px; }
    }
    @media (max-width: 480px) {
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .stats { grid-template-columns: 1fr; gap: 10px; }
        .stat-card { padding: 12px; }
        .stat-card .value { font-size: 18px; }
        .stat-card h3 { font-size: 11px; }
        .button { padding: 8px 12px; font-size: 12px; }
        .card { padding: 12px; }
        .card h2 { font-size: 14px; }
        table { font-size: 10px; }
        th, td { padding: 6px 8px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>My Bills</h1>
            <p class="subtitle">View and track your medical billing records</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="link" href="dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Due</h3>
            <div class="value"><?php echo number_format($pending_amount, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Paid</h3>
            <div class="value"><?php echo number_format($paid_amount, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Billed</h3>
            <div class="value"><?php echo number_format($total_amount, 2); ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Billing History</h2>
        <?php if (count($billings) === 0): ?>
            <div class="empty">No billing records found. Your medical services will be billed here.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billings as $billing): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($billing['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($billing['description']); ?></td>
                            <td><?php echo number_format($billing['amount'], 2); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($billing['status']); ?>"><?php echo htmlspecialchars($billing['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($billing['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>