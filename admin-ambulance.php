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
    if (isset($_POST['add_driver'])) {
        $name = $_POST['name'];
        $license = $_POST['license_number'];
        $phone = $_POST['phone'];

        $sql = "INSERT INTO drivers (name, license_number, phone) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $license, $phone);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['add_ambulance'])) {
        $vehicle_number = $_POST['vehicle_number'];
        $driver_id = $_POST['driver_id'] ?: null;

        $sql = "INSERT INTO ambulances (vehicle_number, driver_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $vehicle_number, $driver_id);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['update_status'])) {
        $ambulance_id = $_POST['ambulance_id'];
        $status = $_POST['status'];

        $sql = "UPDATE ambulances SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $ambulance_id);
        $stmt->execute();
        $stmt->close();
    }
    if (isset($_POST['approve_booking'])) {
        $booking_id = $_POST['booking_id'];
        $driver_id = $_POST['driver_id'] ?: null;
        $ambulance_id = $_POST['ambulance_id'] ?: null;

        $sql = "UPDATE ambulance_bookings SET status = 'Confirmed', driver_id = ?, ambulance_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $driver_id, $ambulance_id, $booking_id);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['reject_booking'])) {
        $booking_id = $_POST['booking_id'];

        $sql = "UPDATE ambulance_bookings SET status = 'Cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: admin-ambulance.php');
    exit();
}

// Get data
$drivers = $conn->query('SELECT * FROM drivers ORDER BY name');
$ambulances = $conn->query('SELECT a.*, d.name as driver_name FROM ambulances a LEFT JOIN drivers d ON a.driver_id = d.id ORDER BY a.vehicle_number');
$pending_bookings = $conn->query('SELECT ab.*, d.name as driver_name, a.vehicle_number FROM ambulance_bookings ab LEFT JOIN drivers d ON ab.driver_id = d.id LEFT JOIN ambulances a ON ab.ambulance_id = a.id WHERE ab.status = "Pending" ORDER BY ab.booking_time DESC');
$bookings = $conn->query('SELECT ab.*, d.name as driver_name, a.vehicle_number FROM ambulance_bookings ab LEFT JOIN drivers d ON ab.driver_id = d.id LEFT JOIN ambulances a ON ab.ambulance_id = a.id ORDER BY ab.booking_time DESC LIMIT 10');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ambulance Management</title>
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
    .status { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .status.Available { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.Booked { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }
    .status.OnRoute { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .status.Maintenance { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    .status.Pending { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
    .status.Confirmed { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.OnRoute { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .status.Completed { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.Cancelled { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    .status.Confirmed { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.Cancelled { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    @media (max-width: 480px) { .topbar { flex-direction: column; align-items: flex-start; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .card { padding: 16px; } .form-row { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Ambulance Management</h1>
            <p class="subtitle">Manage drivers, ambulances, and bookings</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Ambulances</h3>
            <div class="value"><?php echo $ambulances->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Available Ambulances</h3>
            <div class="value"><?php echo $conn->query("SELECT COUNT(*) as count FROM ambulances WHERE status = 'Available'")->fetch_assoc()['count']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Drivers</h3>
            <div class="value"><?php echo $drivers->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Active Bookings</h3>
            <div class="value"><?php echo $conn->query("SELECT COUNT(*) as count FROM ambulance_bookings WHERE status IN ('Pending', 'Confirmed', 'OnRoute')")->fetch_assoc()['count']; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Add New Driver</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" required>
                </div>
            </div>
            <button type="submit" name="add_driver" class="button">Add Driver</button>
        </form>
    </div>

    <div class="card">
        <h2>Add New Ambulance</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Vehicle Number</label>
                    <input type="text" name="vehicle_number" required>
                </div>
                <div class="form-group">
                    <label>Assign Driver (Optional)</label>
                    <select name="driver_id">
                        <option value="">No Driver</option>
                        <?php while($driver = $drivers->fetch_assoc()): ?>
                            <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                        <?php endwhile; $drivers->data_seek(0); ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_ambulance" class="button">Add Ambulance</button>
        </form>
    </div>

    <div class="card">
        <h2>Ambulance Fleet</h2>
        <table>
            <thead>
                <tr>
                    <th>Vehicle Number</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($ambulance = $ambulances->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ambulance['vehicle_number']); ?></td>
                        <td><?php echo htmlspecialchars($ambulance['driver_name'] ?? 'Unassigned'); ?></td>
                        <td><span class="status <?php echo $ambulance['status']; ?>"><?php echo $ambulance['status']; ?></span></td>
                        <td><?php echo htmlspecialchars($ambulance['location'] ?? 'N/A'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="ambulance_id" value="<?php echo $ambulance['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.2); color: #e2e8f0; padding: 4px;">
                                    <option value="Available" <?php echo $ambulance['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="Booked" <?php echo $ambulance['status'] == 'Booked' ? 'selected' : ''; ?>>Booked</option>
                                    <option value="OnRoute" <?php echo $ambulance['status'] == 'OnRoute' ? 'selected' : ''; ?>>On Route</option>
                                    <option value="Maintenance" <?php echo $ambulance['status'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Pending Approvals</h2>
        <?php if ($pending_bookings->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($booking = $pending_bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['patient_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['pickup_location'], 0, 40)) . (strlen($booking['pickup_location']) > 40 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['destination'], 0, 40)) . (strlen($booking['destination']) > 40 ? '...' : ''); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($booking['booking_time'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <select name="driver_id" required style="background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.2); color: #e2e8f0; padding: 4px; border-radius: 4px; font-size: 12px;">
                                            <option value="">Select Driver</option>
                                            <?php $drivers->data_seek(0); while($driver = $drivers->fetch_assoc()): ?>
                                                <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <select name="ambulance_id" required style="background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.2); color: #e2e8f0; padding: 4px; border-radius: 4px; font-size: 12px;">
                                            <option value="">Select Ambulance</option>
                                            <?php $ambulances->data_seek(0); while($amb = $ambulances->fetch_assoc()): ?>
                                                <option value="<?php echo $amb['id']; ?>"><?php echo htmlspecialchars($amb['vehicle_number']); ?> (<?php echo $amb['status']; ?>)</option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="submit" name="approve_booking" class="button" style="padding: 4px 10px; font-size: 12px;">Approve</button>
                                    </div>
                                </form>
                                <form method="POST" style="display: inline; margin-top: 6px;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit" name="reject_booking" class="button" style="padding: 4px 10px; font-size: 12px; background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3);">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #94a3b8; text-align: center; padding: 20px;">No pending ambulance bookings.</p>
        <?php endif; ?>
    </div>

    <div class="card">        <h2>Pending Approvals</h2>
        <?php if ($pending_bookings->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($booking = $pending_bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['patient_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['pickup_location'], 0, 40)); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['destination'], 0, 40)); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($booking['booking_time'])); ?></td>
                            <td>
                                <form method=\"POST\" style=\"display: inline;\">
                                    <input type=\"hidden\" name=\"booking_id\" value=\"<?php echo $booking['id']; ?>\">
                                    <div style=\"display: flex; gap: 8px; flex-wrap: wrap; align-items: center;\">
                                        <select name=\"driver_id\" required style=\"background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.2); color: #e2e8f0; padding: 4px 6px; border-radius: 4px; font-size: 12px;\">
                                            <option value=\"\">Select Driver</option>
                                            <?php
                                            $drivers_result = $conn->query('SELECT * FROM drivers ORDER BY name');
                                            while($driver = $drivers_result->fetch_assoc()): ?>
                                                <option value=\"<?php echo $driver['id']; ?>\"><?php echo htmlspecialchars($driver['name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <select name=\"ambulance_id\" required style=\"background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.2); color: #e2e8f0; padding: 4px 6px; border-radius: 4px; font-size: 12px;\">
                                            <option value=\"\">Select Ambulance</option>
                                            <?php
                                            $ambulances_result = $conn->query('SELECT * FROM ambulances ORDER BY vehicle_number');
                                            while($amb = $ambulances_result->fetch_assoc()): ?>
                                                <option value=\"<?php echo $amb['id']; ?>\"><?php echo htmlspecialchars($amb['vehicle_number']); ?> (<?php echo $amb['status']; ?>)</option>\n                                            <?php endwhile; ?>
                                        </select>
                                        <button type=\"submit\" name=\"approve_booking\" class=\"button\" style=\"padding: 4px 10px; font-size: 12px;\">Approve</button>
                                    </div>
                                </form>
                                <form method=\"POST\" style=\"display: inline; margin-left: 8px;\">
                                    <input type=\"hidden\" name=\"booking_id\" value=\"<?php echo $booking['id']; ?>\">
                                    <button type=\"submit\" name=\"reject_booking\" class=\"button\" style=\"padding: 4px 10px; font-size: 12px; background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3);\">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style=\"color: #94a3b8; text-align: center; padding: 20px;\">No pending ambulance bookings.</p>
        <?php endif; ?>
    </div>

    <div class=\"card\">        <h2>Recent Ambulance Bookings</h2>
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Pickup Location</th>
                    <th>Destination</th>
                    <th>Driver</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Booking Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while($booking = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['patient_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                        <td><?php echo htmlspecialchars($booking['destination']); ?></td>
                        <td><?php echo htmlspecialchars($booking['driver_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($booking['vehicle_number'] ?? 'Unassigned'); ?></td>
                        <td><span class="status <?php echo $booking['status']; ?>"><?php echo $booking['status']; ?></span></td>
                        <td><?php echo date('M d, H:i', strtotime($booking['booking_time'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>