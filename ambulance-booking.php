<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pickup_location = $_POST['pickup_location'];
    $destination = $_POST['destination'];
    $phone = $_POST['phone'];

    $sql = "INSERT INTO ambulance_bookings (patient_id, patient_name, phone, pickup_location, destination) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $patient_id, $patient_name, $phone, $pickup_location, $destination);
    $stmt->execute();
    $stmt->close();

    $success_message = "Ambulance booking request submitted successfully! We will contact you shortly.";
}

// Get user's booking history with driver info
$bookings = $conn->query("SELECT ab.*, d.name as driver_name FROM ambulance_bookings ab LEFT JOIN drivers d ON ab.driver_id = d.id WHERE ab.patient_id = $patient_id ORDER BY ab.booking_time DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ambulance Booking</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
    }
    .page { max-width: 800px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    h1 { font-size: 28px; }
    @media (max-width: 640px) { h1 { font-size: 22px; } }
    .subtitle { color: #94a3b8; margin-top: 4px; font-size: 14px; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 14px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 600; cursor: pointer; transition: transform .18s ease; text-decoration: none; font-size: 14px; }
    .button:hover { transform: translateY(-1px); }
    .button.secondary { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
    .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 20px; padding: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22); margin-bottom: 20px; }
    .card h2 { font-size: 18px; margin-bottom: 16px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 4px; color: #cbd5e1; font-size: 14px; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 8px; background: rgba(15, 23, 42, 0.5); color: #e2e8f0; font-size: 14px; }
    .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #60a5fa; }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .status { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .status.Pending { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
    .status.Confirmed { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.OnRoute { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .status.Completed { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .status.Cancelled { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 600; }
    td { color: #e2e8f0; }
    .success-message { background: rgba(34, 197, 94, 0.2); color: #86efac; padding: 12px; border-radius: 8px; margin-bottom: 16px; border: 1px solid rgba(34, 197, 94, 0.3); }
    .emergency-notice { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px; }
    .emergency-notice h3 { color: #fca5a5; margin-bottom: 8px; }
    .emergency-notice p { color: #e2e8f0; font-size: 14px; }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 10px; margin-bottom: 18px; }
        h1 { font-size: 24px; }
        .card { padding: 16px; border-radius: 16px; }
        .card h2 { font-size: 16px; }
        table { font-size: 12px; }
        th, td { padding: 8px 10px; }
        .emergency-notice { padding: 12px; }
        .emergency-notice h3 { font-size: 16px; }
        .emergency-notice p { font-size: 12px; }
    }
    @media (max-width: 480px) {
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .button { padding: 8px 12px; font-size: 11px; }
        .card { padding: 12px; }
        .card h2 { font-size: 14px; }
        table { font-size: 10px; }
        th, td { padding: 6px 8px; }
        label { font-size: 12px; }
        input, textarea { padding: 8px 10px; font-size: 12px; }
        .emergency-notice { padding: 10px; margin-bottom: 14px; }
        .emergency-notice h3 { font-size: 14px; }
        .emergency-notice p { font-size: 11px; }
        .status { font-size: 10px; padding: 4px 6px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Ambulance Booking</h1>
            <p class="subtitle">Request emergency medical transportation</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button secondary" href="dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="emergency-notice">
        <h3>🚨 Emergency Notice</h3>
        <p>For life-threatening emergencies, please call emergency services directly at <strong>102</strong> or <strong>112</strong> instead of using this booking system. This service is for non-emergency medical transportation.</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Book Ambulance</h2>
        <form method="POST">
            <div class="form-group">
                <label>Phone Number (for coordination)</label>
                <input type="tel" name="phone" required placeholder="Enter your contact number">
            </div>
            <div class="form-group">
                <label>Pickup Location</label>
                <textarea name="pickup_location" required placeholder="Enter detailed pickup address, landmarks, etc."></textarea>
            </div>
            <div class="form-group">
                <label>Destination</label>
                <textarea name="destination" required placeholder="Enter destination address (usually hospital name and address)"></textarea>
            </div>
            <button type="submit" class="button">Request Ambulance</button>
        </form>
    </div>

    <div class="card">
        <h2>Your Booking History</h2>
        <?php if ($bookings->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>Driver Assigned</th>
                        <th>Booking Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($booking['pickup_location'], 0, 50)) . (strlen($booking['pickup_location']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['destination'], 0, 50)) . (strlen($booking['destination']) > 50 ? '...' : ''); ?></td>
                            <td><span class="status <?php echo $booking['status']; ?>"><?php echo $booking['status']; ?></span><?php if ($booking['status'] == 'Pending'): ?><div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Awaiting admin approval</div><?php elseif ($booking['status'] == 'Confirmed'): ?><div style="font-size: 12px; color: #86efac; margin-top: 4px;">Approved and assigned</div><?php elseif ($booking['status'] == 'Cancelled'): ?><div style="font-size: 12px; color: #fca5a5; margin-top: 4px;">Rejected by admin</div><?php endif; ?></td>
                            <td><?php echo $booking['driver_name'] ? htmlspecialchars($booking['driver_name']) : 'Not assigned'; ?></td>
                            <td><?php echo date('M d, H:i', strtotime($booking['booking_time'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #94a3b8; text-align: center; padding: 20px;">No ambulance bookings found.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>