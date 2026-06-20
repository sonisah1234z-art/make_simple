<?php
require_once 'auth.php';
require_once 'db.php';

// Determine scope
if (isset($_SESSION['admin_id'])) {
    $is_admin = true;
    $appointments_result = $conn->query("SELECT a.id, p.name AS patient_name, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
} elseif (isset($_SESSION['patient_id'])) {
    $is_admin = false;
    $patient_id = current_patient_id();
    $appointments_result = $conn->query("SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.patient_id = " . intval($patient_id) . " ORDER BY a.appointment_date DESC, a.appointment_time DESC");
} else {
    header('Location: login.php');
    exit();
}
?>
<?php include 'header.php'; ?>

<div class="container-fluid p-4">
    <div class="row mb-3">
        <div class="col">
            <h1 class="h3"><?php echo $is_admin ? 'Appointments' : 'My Appointments'; ?></h1>
            <div class="text-muted"><?php echo $is_admin ? 'Manage appointment requests' : 'Your upcoming and past appointments'; ?></div>
        </div>
    </div>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-borderless">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?><th>Patient</th><?php endif; ?>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
                        <?php while($row = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <?php if ($is_admin): ?><td><?php echo htmlspecialchars($row['patient_name']); ?></td><?php endif; ?>
                                <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['specialty']); ?></td>
                                <td><?php echo htmlspecialchars($row['appointment_date']); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['appointment_time'], 0, 5)); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-muted">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
