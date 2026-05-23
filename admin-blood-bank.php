<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_donor'])) {
        $name = $_POST['name'];
        $blood_group = $_POST['blood_group'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];

        $sql = "INSERT INTO blood_donors (name, blood_group, phone, email) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $blood_group, $phone, $email);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['update_stock'])) {
        $blood_group = $_POST['blood_group'];
        $units = $_POST['units'];
        $expiry_date = $_POST['expiry_date'];
        $location = $_POST['storage_location'];

        $sql = "INSERT INTO blood_bank (blood_group, units_available, expiry_date, storage_location) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + VALUES(units_available), expiry_date = VALUES(expiry_date), storage_location = VALUES(storage_location)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siss", $blood_group, $units, $expiry_date, $location);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['add_request'])) {
        $patient_name = $_POST['patient_name'];
        $blood_group = $_POST['blood_group'];
        $units_needed = $_POST['units_needed'];
        $urgency = $_POST['urgency'];

        $sql = "INSERT INTO blood_requests (patient_name, blood_group, units_needed, urgency) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssis", $patient_name, $blood_group, $units_needed, $urgency);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: admin-blood-bank.php');
    exit();
}

// Get data
$donors = $conn->query('SELECT * FROM blood_donors ORDER BY last_donation_date DESC');
$blood_stock = $conn->query('SELECT blood_group, SUM(units_available) as total_units FROM blood_bank GROUP BY blood_group ORDER BY blood_group');
$requests = $conn->query('SELECT * FROM blood_requests WHERE status = "Pending" ORDER BY request_date DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blood Bank Management</title>
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
    .button.secondary { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
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
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 4px; color: #cbd5e1; font-size: 14px; }
    .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 8px; background: rgba(15, 23, 42, 0.5); color: #e2e8f0; font-size: 14px; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #60a5fa; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .blood-group { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .blood-group.A\\+ { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    .blood-group.A- { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    .blood-group.B\\+ { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .blood-group.B- { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .blood-group.O\\+ { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .blood-group.O- { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .blood-group.AB\\+ { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }
    .blood-group.AB- { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }
    .urgency { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .urgency.Normal { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
    .urgency.Urgent { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }
    .urgency.Critical { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
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
        .form-row { grid-template-columns: repeat(2, 1fr); gap: 12px; }
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
        .card { padding: 12px; }
        .card h2 { font-size: 14px; }
        table { font-size: 10px; }
        th, td { padding: 6px 8px; }
        .form-row { grid-template-columns: 1fr; gap: 10px; }
        label { font-size: 12px; }
        input, select { padding: 8px 10px; font-size: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Blood Bank Management</h1>
            <p class="subtitle">Manage blood donors, inventory, and requests</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Donors</h3>
            <div class="value"><?php echo $donors->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Blood Units</h3>
            <div class="value"><?php echo $conn->query('SELECT SUM(units_available) as total FROM blood_bank')->fetch_assoc()['total'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending Requests</h3>
            <div class="value"><?php echo $requests->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Active Donors</h3>
            <div class="value"><?php echo $conn->query("SELECT COUNT(*) as count FROM blood_donors WHERE status = 'Active'")->fetch_assoc()['count']; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Add New Blood Donor</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Donor Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group" required>
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" name="email">
                </div>
            </div>
            <button type="submit" name="add_donor" class="button">Add Donor</button>
        </form>
    </div>

    <div class="card">
        <h2>Update Blood Stock</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group" required>
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Units to Add</label>
                    <input type="number" name="units" min="1" required>
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" required>
                </div>
                <div class="form-group">
                    <label>Storage Location</label>
                    <input type="text" name="storage_location" placeholder="Refrigerator A, B, etc." required>
                </div>
            </div>
            <button type="submit" name="update_stock" class="button">Update Stock</button>
        </form>
    </div>

    <div class="card">
        <h2>Add Blood Request</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Patient Name</label>
                    <input type="text" name="patient_name" required>
                </div>
                <div class="form-group">
                    <label>Blood Group Needed</label>
                    <select name="blood_group" required>
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Units Needed</label>
                    <input type="number" name="units_needed" min="1" required>
                </div>
                <div class="form-group">
                    <label>Urgency Level</label>
                    <select name="urgency" required>
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_request" class="button">Add Request</button>
        </form>
    </div>

    <div class="card">
        <h2>Blood Stock Levels</h2>
        <table>
            <thead>
                <tr>
                    <th>Blood Group</th>
                    <th>Available Units</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($stock = $blood_stock->fetch_assoc()): ?>
                    <tr>
                        <td><span class="blood-group <?php echo str_replace('+', '\\+', $stock['blood_group']); ?>"><?php echo $stock['blood_group']; ?></span></td>
                        <td><?php echo $stock['total_units']; ?></td>
                        <td>
                            <?php if ($stock['total_units'] < 5): ?>
                                <span style="color: #fca5a5;">Low Stock</span>
                            <?php elseif ($stock['total_units'] < 10): ?>
                                <span style="color: #fcd34d;">Medium</span>
                            <?php else: ?>
                                <span style="color: #86efac;">Good</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Blood Donors</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Blood Group</th>
                    <th>Phone</th>
                    <th>Last Donation</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($donor = $donors->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($donor['name']); ?></td>
                        <td><span class="blood-group <?php echo str_replace('+', '\\+', $donor['blood_group']); ?>"><?php echo $donor['blood_group']; ?></span></td>
                        <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                        <td><?php echo $donor['last_donation_date'] ? date('M d, Y', strtotime($donor['last_donation_date'])) : 'Never'; ?></td>
                        <td><?php echo $donor['status']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Pending Blood Requests</h2>
        <table>
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Blood Group</th>
                    <th>Units Needed</th>
                    <th>Urgency</th>
                    <th>Request Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while($request = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                        <td><span class="blood-group <?php echo str_replace('+', '\\+', $request['blood_group']); ?>"><?php echo $request['blood_group']; ?></span></td>
                        <td><?php echo $request['units_needed']; ?></td>
                        <td><span class="urgency <?php echo $request['urgency']; ?>"><?php echo $request['urgency']; ?></span></td>
                        <td><?php echo date('M d, H:i', strtotime($request['request_date'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>