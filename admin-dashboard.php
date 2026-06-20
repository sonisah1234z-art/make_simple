<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

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
$conn->query("CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_number VARCHAR(50) UNIQUE,
    ward VARCHAR(100),
    is_icu TINYINT(1),
    status VARCHAR(50),
    bed_type VARCHAR(20) NOT NULL DEFAULT 'General',
    patient_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$bedTypeColumn = $conn->query("SHOW COLUMNS FROM beds LIKE 'bed_type'");
if ($bedTypeColumn && $bedTypeColumn->num_rows === 0) {
    $conn->query("ALTER TABLE beds ADD COLUMN bed_type VARCHAR(20) NOT NULL DEFAULT 'General'");
    $conn->query("UPDATE beds SET bed_type='ICU' WHERE is_icu = 1");
}
$bedCount = fetchCount('SELECT COUNT(*) AS total FROM beds');
$bedAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE status = 'Available'");
$bedIcuAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'ICU' AND status = 'Available'");
$bedGeneralAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'General' AND status = 'Available'");
$bedEmergencyAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'Emergency' AND status = 'Available'");
$currentDate = date('F j, Y');
?>
<?php include 'header.php'; ?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h1 class="h3">Admin Dashboard</h1>
        <div class="text-muted">Welcome back, <?php echo htmlspecialchars($admin_name); ?> — Snapshot for <?php echo $currentDate; ?></div>
    </div>
    <div class="col-auto">
        <a class="btn btn-outline-secondary me-2" href="admin-reports.php">Reports</a>
        <a class="btn btn-outline-secondary me-2" href="admin-appointments.php">Appointments</a>
        <a href="admin-otp-dashboard.php">OTP Dashboard</a>
        <a class="btn btn-primary" href="logout.php">Logout</a>
        
<a href="nurse_dashboard.php">Open Nurse Dashboard</a>
<a href="add_shift.php">🩺 Nurse Shift Management</a>

<a href="view_shifts.php">📅 View Nurse Shifts</a>
<a href="add_nurse.php">👩‍⚕ Add Nurse</a>

<a href="view_nurses_list.php">📋 View Nurses</a> 
    </div>
</div>

<div class="dashboard-cards mb-4">
    <div class="stat-card card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="text-muted">Total Patients</div>
                <div class="h4"><?php echo $patientCount; ?></div>
            </div>
            <div style="width:120px;">
                <canvas id="adminChart" data-values="<?php echo $patientCount.','.$doctorCount.','.$appointmentCount.','.$pendingCount; ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">Total Doctors</div>
        <div class="h4"><?php echo $doctorCount; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">Appointments</div>
        <div class="h4"><?php echo $appointmentCount; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">Pending</div>
        <div class="h4"><?php echo $pendingCount; ?></div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card p-3">
            <h5 class="dashboard-heading">Live Bed Availability</h5>
            <div class="row gy-3 mt-3">
                <div class="col-md-3">
                    <div class="stat-card card p-3">
                        <div class="text-muted">Beds Available</div>
                        <div class="h4"><?php echo $bedAvailable; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card card p-3">
                        <div class="text-muted">ICU Available</div>
                        <div class="h4"><?php echo $bedIcuAvailable; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card card p-3">
                        <div class="text-muted">General Available</div>
                        <div class="h4"><?php echo $bedGeneralAvailable; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card card p-3">
                        <div class="text-muted">Emergency Available</div>
                        <div class="h4"><?php echo $bedEmergencyAvailable; ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <a class="btn btn-primary" href="bed-availability.php">Manage Bed Inventory</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card p-3 mb-3">
            <h5 class="dashboard-heading">Quick Actions</h5>
            <div class="d-flex flex-wrap gap-2 mt-2 quick-actions">
                <a class="btn btn-outline-primary" href="admin-patients.php">Manage Patients</a>
                <a class="btn btn-outline-primary" href="admin-doctors.php">Manage Doctors</a>
                <a class="btn btn-outline-primary" href="admin-appointments.php">Manage Appointments</a>
                <a class="btn btn-outline-primary" href="admin-billing.php">Billing</a>
            </div>
        </div>
        <div class="card p-3">
            <h5 class="dashboard-heading">Insights</h5>
            <p class="text-muted">Use the links to review patients, doctors and appointment flows.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 mb-3">
            <h5 class="dashboard-heading">Recent Notifications</h5>
            <p class="text-muted">No new alerts.</p>
        </div>
        <div class="card p-3">
            <h5 class="dashboard-heading">System Links</h5>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-secondary" href="view_emergency.php">View Emergency Alerts</a>
                <a class="btn btn-outline-secondary" href="view_feedback.php">View Patient Feedback</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>