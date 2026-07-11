<?php
require_once 'auth.php';
require_receptionist();
require_once 'db.php';

$receptionist_name = $_SESSION['receptionist_name'] ?? 'Receptionist';
$doctors = [];
$result = $conn->query('SELECT id, name, specialty, email, phone, status, department, created_at FROM doctors ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Management</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f4f6f9;
        color: #1f2937;
        padding: 24px;
    }
    .page { max-width: 1200px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 24px; }
    h1 { font-size: 32px; color: #0f172a; }
    .subtitle { color: #64748b; margin-top: 6px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 12px; text-decoration: none; font-weight: 600; }
    .btn-primary { background: #0ea5e9; color: #fff; }
    .btn-secondary { background: #e2e8f0; color: #0f172a; }
    .card { background: #fff; border-radius: 18px; padding: 20px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background: #f8fafc; color: #334155; font-weight: 700; }
    .status { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .status-available { background: #dcfce7; color: #166534; }
    .status-busy { background: #fef3c7; color: #92400e; }
    .status-offline { background: #e2e8f0; color: #475569; }
    .empty { color: #64748b; padding: 18px 0; }
    @media (max-width: 768px) {
        body { padding: 16px; }
        h1 { font-size: 24px; }
        .topbar { align-items: flex-start; }
        .card { padding: 14px; overflow-x: auto; }
        table { font-size: 14px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Doctor Management</h1>
            <p class="subtitle">Welcome, <?php echo htmlspecialchars($receptionist_name); ?>. Review the available doctors and their contact details.</p>
        </div>
        <div class="actions">
            <a class="btn btn-secondary" href="receptionist_dashboard.php">Dashboard</a>
            <a class="btn btn-primary" href="view_appointments.php">Appointments</a>
        </div>
    </div>

    <div class="card">
        <?php if (!empty($doctors)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialty</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Department</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                        <?php $statusClass = 'status-offline'; $statusLabel = 'Offline'; if (($doctor['status'] ?? '') === 'Available') { $statusClass = 'status-available'; $statusLabel = 'Available'; } elseif (($doctor['status'] ?? '') === 'Busy') { $statusClass = 'status-busy'; $statusLabel = 'Busy'; } ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doctor['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($doctor['specialty'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($doctor['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($doctor['phone'] ?? ''); ?></td>
                            <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                            <td><?php echo htmlspecialchars($doctor['department'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($doctor['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty">No doctor records found yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
