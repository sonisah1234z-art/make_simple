<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

$appointments = [];
$result = $conn->query("SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = $patient_id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC");

$status_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
if ($status_column && $status_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}

$availableDoctors = [];
$doctorResult = $conn->query("SELECT name, specialty, status FROM doctors ORDER BY FIELD(status,'Available','Busy','Offline'), name ASC");
if ($doctorResult) {
    while ($row = $doctorResult->fetch_assoc()) {
        $availableDoctors[] = $row;
    }
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

$bedAvailable = 0;
$bedIcuAvailable = 0;
$bedGeneralAvailable = 0;
$bedEmergencyAvailable = 0;
$bedTotal = 0;

$bedTotalResult = $conn->query("SELECT COUNT(*) AS total FROM beds");
if ($bedTotalResult) {
    $bedTotal = (int)$bedTotalResult->fetch_assoc()['total'];
}
$bedAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status='Available'");
if ($bedAvailableResult) {
    $bedAvailable = (int)$bedAvailableResult->fetch_assoc()['total'];
}
$bedIcuAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='ICU' AND status='Available'");
if ($bedIcuAvailableResult) {
    $bedIcuAvailable = (int)$bedIcuAvailableResult->fetch_assoc()['total'];
}
$bedGeneralAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='General' AND status='Available'");
if ($bedGeneralAvailableResult) {
    $bedGeneralAvailable = (int)$bedGeneralAvailableResult->fetch_assoc()['total'];
}
$bedEmergencyAvailableResult = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type='Emergency' AND status='Available'");
if ($bedEmergencyAvailableResult) {
    $bedEmergencyAvailable = (int)$bedEmergencyAvailableResult->fetch_assoc()['total'];
}
?>
<?php include 'header.php'; ?>

<div class="row mb-3">
    <div class="col">
        <h1 class="h3">Patient Dashboard</h1>
        <div class="text-muted">Welcome back, <?php echo htmlspecialchars($patient_name); ?>. Manage your appointments and records below.</div>
    </div>
    <div class="col-auto">
        <a class="btn btn-primary" href="book-appointment.php">Book Appointment</a>
        <a class="btn btn-outline-secondary" href="ambulance-booking.php">Book Ambulance</a>
        <a class="btn btn-outline-secondary" href="patient-billing.php">View Bills</a>
        <button class="button"
onclick="location.href='patient_notifications.php'">
🔔 Notifications
</button>
<a href="patient-otp-view.php">My OTP</a>

    </div>
</div>

<div class="dashboard-cards mb-3">
    <div class="stat-card card">
        <h5>Upcoming Appointments</h5>
        <p class="text-muted">Keep track of your confirmed and pending appointments.</p>
    </div>
    <div class="stat-card card">
        <h5>Doctor Network</h5>
        <p class="text-muted">See top doctors available across specialties.</p>
    </div>
    <div class="stat-card card">
        <h5>Billing & Payments</h5>
        <p class="text-muted">View billing history and payment status.</p>
    </div>
</div>

<div class="card p-3 mb-3">
    <h5>Live Doctor Availability</h5>
    <p class="text-muted">Check which doctors are currently available for appointments.</p>
    <div class="row g-3">
        <?php if (count($availableDoctors) > 0): ?>
            <?php foreach ($availableDoctors as $doctor): ?>
                <div class="col-12 col-md-4">
                    <div class="card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong><?php echo htmlspecialchars($doctor['name']); ?></strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                            </div>
                            <span class="status-pill status-<?php echo htmlspecialchars($doctor['status']); ?>"><?php echo htmlspecialchars($doctor['status']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-muted">No doctor availability information is currently available. Please check back later.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card p-3 mb-3">
    <h5>Live Hospital Bed Availability</h5>
    <p class="text-muted">See current ICU, General, and Emergency bed availability across the hospital.</p>
    <div class="bed-summary-grid mt-3">
        <div class="bed-status-card p-3">
            <div class="text-muted">Total Available Beds</div>
            <div class="h4"><?php echo $bedAvailable; ?></div>
            <span class="badge badge-available mt-2">Live</span>
        </div>
        <div class="bed-status-card p-3">
            <div class="text-muted">ICU Beds Available</div>
            <div class="h4"><?php echo $bedIcuAvailable; ?></div>
            <span class="badge <?php echo $bedIcuAvailable > 5 ? 'badge-available' : ($bedIcuAvailable > 0 ? 'badge-limited' : 'badge-full'); ?> mt-2"><?php echo $bedIcuAvailable > 5 ? 'Available' : ($bedIcuAvailable > 0 ? 'Limited' : 'Full'); ?></span>
        </div>
        <div class="bed-status-card p-3">
            <div class="text-muted">General Beds Available</div>
            <div class="h4"><?php echo $bedGeneralAvailable; ?></div>
            <span class="badge <?php echo $bedGeneralAvailable > 5 ? 'badge-available' : ($bedGeneralAvailable > 0 ? 'badge-limited' : 'badge-full'); ?> mt-2"><?php echo $bedGeneralAvailable > 5 ? 'Available' : ($bedGeneralAvailable > 0 ? 'Limited' : 'Full'); ?></span>
        </div>
        <div class="bed-status-card p-3">
            <div class="text-muted">Emergency Beds Available</div>
            <div class="h4"><?php echo $bedEmergencyAvailable; ?></div>
            <span class="badge <?php echo $bedEmergencyAvailable > 5 ? 'badge-available' : ($bedEmergencyAvailable > 0 ? 'badge-limited' : 'badge-full'); ?> mt-2"><?php echo $bedEmergencyAvailable > 5 ? 'Available' : ($bedEmergencyAvailable > 0 ? 'Limited' : 'Full'); ?></span>
        </div>
    </div>
    <div class="mt-3">
        <a class="btn btn-outline-primary" href="bed-availability.php">View Full Bed Status</a>
    </div>
</div>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="pharmacist_login.php">Open Pharmacist Panel</a>
        <a class="btn btn-outline-primary" href="buy_medicine.php">Buy Medicine</a>
        <a class="btn btn-outline-primary" href="payment.php">Make Payment</a>
        <a class="btn btn-outline-primary" href="patient_payment.php">Payment History</a>
        <a class="btn btn-outline-primary" href="medical_timeline.php">Health Timeline</a>
        <a class="btn btn-outline-primary" href="feedback.php">Give Feedback</a>
        <a class="btn btn-outline-primary" href="emergency.php">Emergency Alert</a>
    </div>
</div>

<div class="card p-3 appointment-card">
    <h5>Your Appointments</h5>
    <?php if (count($appointments) > 0): ?>
        <div class="table-responsive">
            <table class="table table-borderless align-middle mb-0 appointment-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr class="appointment-row">
                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($appointment['status']); ?>"><?php echo htmlspecialchars($appointment['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-muted">No appointments found yet. Book a new appointment to get started.</div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>