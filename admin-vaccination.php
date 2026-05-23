<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$admin_name = $_SESSION['admin_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_vaccine'])) {
        $vaccine_name = $_POST['vaccine_name'];
        $batch_number = $_POST['batch_number'];
        $manufacturer = $_POST['manufacturer'];
        $expiry_date = $_POST['expiry_date'];
        $available_doses = $_POST['available_doses'];
        $storage_temp = $_POST['storage_temp'];

        $sql = "INSERT INTO vaccine_inventory (vaccine_name, batch_number, manufacturer, expiry_date, available_doses, storage_temp) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssis", $vaccine_name, $batch_number, $manufacturer, $expiry_date, $available_doses, $storage_temp);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['record_vaccination'])) {
        $patient_name = $_POST['patient_name'];
        $vaccine_name = $_POST['vaccine_name'];
        $dose_number = $_POST['dose_number'];
        $vaccination_date = $_POST['vaccination_date'];
        $administered_by = $_POST['administered_by'];
        $batch_number = $_POST['batch_number'];
        $notes = $_POST['notes'];

        // Calculate next due date based on vaccine type
        $next_due_date = null;
        if ($vaccine_name == 'COVID-19 Vaccine') {
            if ($dose_number == 1) {
                $next_due_date = date('Y-m-d', strtotime($vaccination_date . ' +21 days'));
            } elseif ($dose_number == 2) {
                $next_due_date = date('Y-m-d', strtotime($vaccination_date . ' +6 months'));
            }
        } elseif ($vaccine_name == 'Hepatitis B Vaccine') {
            if ($dose_number == 1) {
                $next_due_date = date('Y-m-d', strtotime($vaccination_date . ' +1 month'));
            } elseif ($dose_number == 2) {
                $next_due_date = date('Y-m-d', strtotime($vaccination_date . ' +5 months'));
            }
        } elseif ($vaccine_name == 'MMR Vaccine') {
            if ($dose_number == 1) {
                $next_due_date = date('Y-m-d', strtotime($vaccination_date . ' +1 month'));
            }
        }

        $sql = "INSERT INTO vaccinations (patient_name, vaccine_name, dose_number, vaccination_date, next_due_date, administered_by, batch_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssss", $patient_name, $vaccine_name, $dose_number, $vaccination_date, $next_due_date, $administered_by, $batch_number, $notes);
        $stmt->execute();
        $stmt->close();

        // Create reminder if next dose is needed
        if ($next_due_date) {
            $reminder_date = date('Y-m-d', strtotime($next_due_date . ' -3 days'));
            $sql = "INSERT INTO vaccination_reminders (patient_name, vaccine_name, reminder_date, reminder_type) VALUES (?, ?, ?, 'Next Dose')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $patient_name, $vaccine_name, $reminder_date);
            $stmt->execute();
            $stmt->close();
        }
    }

    header('Location: admin-vaccination.php');
    exit();
}

// Get data
$inventory = $conn->query('SELECT * FROM vaccine_inventory ORDER BY expiry_date ASC');
$vaccinations = $conn->query('SELECT * FROM vaccinations ORDER BY vaccination_date DESC LIMIT 20');
$reminders = $conn->query("SELECT * FROM vaccination_reminders WHERE status = 'Pending' AND reminder_date >= CURDATE() ORDER BY reminder_date ASC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vaccination Management</title>
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
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 8px; background: rgba(15, 23, 42, 0.5); color: #e2e8f0; font-size: 14px; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #60a5fa; }
    .form-group textarea { resize: vertical; min-height: 60px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .vaccine-type { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .dose-number { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .expiry-warning { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 8px; margin-top: 8px; }
    .expiry-warning span { color: #fca5a5; font-size: 12px; }
    .reminder-alert { background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 8px; padding: 8px; margin-top: 8px; }
    .reminder-alert span { color: #fcd34d; font-size: 12px; }
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
        input, select, textarea { padding: 8px 10px; font-size: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Vaccination Management</h1>
            <p class="subtitle">Track vaccine inventory and patient vaccinations</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Vaccines</h3>
            <div class="value"><?php echo $inventory->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Doses Available</h3>
            <div class="value"><?php echo $conn->query('SELECT SUM(available_doses) as total FROM vaccine_inventory')->fetch_assoc()['total'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Vaccinations Today</h3>
            <div class="value"><?php echo $conn->query("SELECT COUNT(*) as count FROM vaccinations WHERE DATE(vaccination_date) = CURDATE()")->fetch_assoc()['count']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending Reminders</h3>
            <div class="value"><?php echo $reminders->num_rows; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Add Vaccine to Inventory</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Vaccine Name</label>
                    <select name="vaccine_name" required>
                        <option value="">Select Vaccine</option>
                        <option value="COVID-19 Vaccine">COVID-19 Vaccine</option>
                        <option value="Hepatitis B Vaccine">Hepatitis B Vaccine</option>
                        <option value="MMR Vaccine">MMR Vaccine</option>
                        <option value="Influenza Vaccine">Influenza Vaccine</option>
                        <option value="Polio Vaccine">Polio Vaccine</option>
                        <option value="DTP Vaccine">DTP Vaccine</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Batch Number</label>
                    <input type="text" name="batch_number" required>
                </div>
                <div class="form-group">
                    <label>Manufacturer</label>
                    <input type="text" name="manufacturer" required>
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" required>
                </div>
                <div class="form-group">
                    <label>Available Doses</label>
                    <input type="number" name="available_doses" min="1" required>
                </div>
                <div class="form-group">
                    <label>Storage Temperature</label>
                    <input type="text" name="storage_temp" placeholder="e.g., 2-8°C, -20°C" required>
                </div>
            </div>
            <button type="submit" name="add_vaccine" class="button">Add to Inventory</button>
        </form>
    </div>

    <div class="card">
        <h2>Record Vaccination</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Patient Name</label>
                    <input type="text" name="patient_name" required>
                </div>
                <div class="form-group">
                    <label>Vaccine Name</label>
                    <select name="vaccine_name" required>
                        <option value="">Select Vaccine</option>
                        <option value="COVID-19 Vaccine">COVID-19 Vaccine</option>
                        <option value="Hepatitis B Vaccine">Hepatitis B Vaccine</option>
                        <option value="MMR Vaccine">MMR Vaccine</option>
                        <option value="Influenza Vaccine">Influenza Vaccine</option>
                        <option value="Polio Vaccine">Polio Vaccine</option>
                        <option value="DTP Vaccine">DTP Vaccine</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Dose Number</label>
                    <select name="dose_number" required>
                        <option value="1">1st Dose</option>
                        <option value="2">2nd Dose</option>
                        <option value="3">3rd Dose</option>
                        <option value="4">Booster</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vaccination Date</label>
                    <input type="date" name="vaccination_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Administered By</label>
                    <input type="text" name="administered_by" required>
                </div>
                <div class="form-group">
                    <label>Batch Number</label>
                    <input type="text" name="batch_number" required>
                </div>
            </div>
            <div class="form-group">
                <label>Notes (Optional)</label>
                <textarea name="notes" placeholder="Any additional notes or observations..."></textarea>
            </div>
            <button type="submit" name="record_vaccination" class="button">Record Vaccination</button>
        </form>
    </div>

    <div class="card">
        <h2>Vaccine Inventory</h2>
        <table>
            <thead>
                <tr>
                    <th>Vaccine Name</th>
                    <th>Batch Number</th>
                    <th>Available Doses</th>
                    <th>Expiry Date</th>
                    <th>Storage</th>
                </tr>
            </thead>
            <tbody>
                <?php while($vaccine = $inventory->fetch_assoc()): ?>
                    <tr>
                        <td><span class="vaccine-type"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($vaccine['batch_number']); ?></td>
                        <td><?php echo $vaccine['available_doses']; ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($vaccine['expiry_date'])); ?>
                            <?php if (strtotime($vaccine['expiry_date']) < strtotime('+30 days')): ?>
                                <div class="expiry-warning"><span>⚠️ Expires Soon</span></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($vaccine['storage_temp']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Vaccinations</h2>
        <table>
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Date</th>
                    <th>Next Due</th>
                    <th>Administered By</th>
                </tr>
            </thead>
            <tbody>
                <?php while($vaccination = $vaccinations->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vaccination['patient_name']); ?></td>
                        <td><span class="vaccine-type"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></span></td>
                        <td><span class="dose-number"><?php echo $vaccination['dose_number']; ?><?php echo $vaccination['dose_number'] == 1 ? 'st' : ($vaccination['dose_number'] == 2 ? 'nd' : ($vaccination['dose_number'] == 3 ? 'rd' : 'th')); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($vaccination['vaccination_date'])); ?></td>
                        <td><?php echo $vaccination['next_due_date'] ? date('M d, Y', strtotime($vaccination['next_due_date'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($vaccination['administered_by']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Upcoming Vaccination Reminders</h2>
        <table>
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Vaccine</th>
                    <th>Reminder Date</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php while($reminder = $reminders->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reminder['patient_name']); ?></td>
                        <td><span class="vaccine-type"><?php echo htmlspecialchars($reminder['vaccine_name']); ?></span></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($reminder['reminder_date'])); ?>
                            <?php if (strtotime($reminder['reminder_date']) <= strtotime('+3 days')): ?>
                                <div class="reminder-alert"><span>📅 Due Soon</span></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($reminder['reminder_type']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>