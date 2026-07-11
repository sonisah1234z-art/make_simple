<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'announcements.php';

$admin_name = $_SESSION['admin_name'];

function fetchCount(string $sql): int
{
    global $conn;
    $result = $conn->query($sql);
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

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

$patientCount = fetchCount('SELECT COUNT(*) AS total FROM patients');
$doctorCount = fetchCount('SELECT COUNT(*) AS total FROM doctors');
$appointmentCount = fetchCount('SELECT COUNT(*) AS total FROM appointments');
$pendingCount = fetchCount("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'");
$nurseCount = fetchCount('SELECT COUNT(*) AS total FROM nurses');
$bedAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE status = 'Available'");
$bedIcuAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'ICU' AND status = 'Available'");
$bedGeneralAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'General' AND status = 'Available'");
$bedEmergencyAvailable = fetchCount("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'Emergency' AND status = 'Available'");
$labReportsCount = fetchCount('SELECT COUNT(*) AS total FROM lab_tests');
$labReportsPending = fetchCount("SELECT COUNT(*) AS total FROM lab_tests WHERE status = 'Pending'");
$currentDate = date('F j, Y');
?>
<?php include 'header.php'; ?>

<div class="admin-dashboard-compact">
<div class="page-head card shadow-sm p-4 mb-3 page-head-hero">
    <div class="d-flex align-items-center gap-3">
        <div class="page-head-icon">⚕️</div>
        <div>
            <div class="eyebrow">Admin Console</div>
            <h1>Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($admin_name); ?>. Today is <?php echo htmlspecialchars($currentDate); ?>.</p>
        </div>
    </div>
    <div class="head-actions">
        <a class="btn btn-outline-primary btn-sm" href="admin-appointments.php">Appointments</a>
        <a class="btn btn-outline-secondary btn-sm" href="admin-reports.php">Reports</a>
        <a class="btn btn-primary btn-sm" href="logout.php">Logout</a>
    </div>
</div>

<?php $context = get_current_user_context(); echo render_announcements_panel($conn, $context['role'], $context['id'], 3); ?>

<div class="metric-grid">
    <a class="metric-card" href="admin-patients.php">
        <span>Patients</span>
        <strong><?php echo $patientCount; ?></strong>
        <small>Registered patients</small>
    </a>
    <a class="metric-card" href="admin_lab_reports.php">
        <span>Lab Reports</span>
        <strong><?php echo $labReportsCount; ?></strong>
        <small><?php echo $labReportsPending; ?> pending</small>
    </a>
    <a class="metric-card" href="admin-doctors.php">
        <span>Doctors</span>
        <strong><?php echo $doctorCount; ?></strong>
        <small>Doctor roster</small>
    </a>
    <a class="metric-card" href="admin-appointments.php">
        <span>Appointments</span>
        <strong><?php echo $appointmentCount; ?></strong>
        <small><?php echo $pendingCount; ?> pending review</small>
    </a>
    <a class="metric-card" href="view_nurses_list.php">
        <span>Nurses</span>
        <strong><?php echo $nurseCount; ?></strong>
        <small>Active nursing staff</small>
    </a>
</div>

<div class="dashboard-layout">
    <section class="panel panel-card">
        <div class="panel-head">
            <div>
                <h2>Bed Availability</h2>
                <p>Live status across hospital wards.</p>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="bed-availability.php">Manage</a>
        </div>
        <div class="bed-grid">
            <div class="bed-card">
                <span>Total Available</span>
                <strong><?php echo $bedAvailable; ?></strong>
                <div class="small text-muted">Overall free beds</div>
            </div>
            <div class="bed-card">
                <span>ICU Beds</span>
                <strong><?php echo $bedIcuAvailable; ?></strong>
                <div class="small text-muted">Critical care capacity</div>
            </div>
            <div class="bed-card">
                <span>General Beds</span>
                <strong><?php echo $bedGeneralAvailable; ?></strong>
                <div class="small text-muted">Standard ward rooms</div>
            </div>
            <div class="bed-card">
                <span>Emergency Beds</span>
                <strong><?php echo $bedEmergencyAvailable; ?></strong>
                <div class="small text-muted">Immediate response beds</div>
            </div>
        </div>
    </section>

    <section class="panel panel-card">
        <div class="panel-head">
            <div>
                <h2>System Overview</h2>
                <p>Patients, doctors, appointments, and pending requests.</p>
            </div>
        </div>
        <div class="chart-wrap chart-card">
            <canvas id="adminChart" data-values="<?php echo $patientCount . ',' . $doctorCount . ',' . $appointmentCount . ',' . $pendingCount; ?>"></canvas>
        </div>
    </section>
</div>

<div class="panel mt-3">
    <div class="panel-head">
        <div>
            <h2>Quick Actions</h2>
            <p>Common admin tasks in one place.</p>
        </div>
    </div>
    <div class="action-grid">
        <a href="admin-patients.php">Manage Patients</a>
        <a href="admin-doctors.php">Manage Doctors</a>
        <a href="admin-appointments.php">Manage Appointments</a>
        <a href="admin-billing.php">Billing</a>
        <a href="admin-otp-dashboard.php">OTP Dashboard</a>
        <a href="verify-appointment-otp.php">Verify OTP</a>
        <a href="add_nurse.php">Add Nurse</a>
        <a href="add_shift.php">Assign Nurse Shift</a>
        <a href="view_shifts.php">View Nurse Shifts</a>
        <a href="view_emergency.php">Emergency Alerts</a>
        <a href="view_feedback.php">Patient Feedback</a>
        <a href="admin-reports.php">Reports</a>
        <a href="announcement.php">📢 Announcement Board</a>
    </div>
</div>

</div>
<?php include 'footer.php'; ?>
