<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$admin_name = $_SESSION['admin_name'];
$patients = [];
$result = $conn->query('SELECT id, name, email, phone, created_at FROM patients ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Patients</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
        color: #e2e8f0;
        padding: 24px;
    }
    .page { max-width: 1200px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
    h1 { font-size: 34px; }
    .subtitle { color: #94a3b8; margin-top: 6px; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: 16px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; text-decoration: none; }
    .button:hover { transform: translateY(-1px); }
    .table-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 700; }
    td { color: #e2e8f0; }
    .empty { color: #94a3b8; padding: 20px 0; }
    @media (max-width: 900px) { .topbar { flex-direction: column; align-items: flex-start; } .table-card { overflow-x: auto; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; }
        h1 { font-size: 26px; }
        .table-card { padding: 16px; border-radius: 20px; }
        table { font-size: 13px; }
        th, td { padding: 10px 12px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        h1 { font-size: 20px; }
        .button { padding: 10px 14px; font-size: 12px; }
        table { font-size: 11px; }
        th, td { padding: 8px; }
        .table-card { padding: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Manage Patients</h1>
            <p class="subtitle">Admin <?php echo htmlspecialchars($admin_name); ?>, review patient registrations and details.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 12px; font-weight: 600;">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($patients) === 0): ?>
                    <tr><td colspan="4" class="empty">No registered patients yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                            <td><?php echo htmlspecialchars($patient['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>