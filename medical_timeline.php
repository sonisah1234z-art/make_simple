<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';

$patient_id = current_patient_id();
$patient_name = $_SESSION['patient_name'] ?? '';
$timeline = [];

/* Appointments */
$appointment_sql = "SELECT a.id, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.patient_id = " . intval($patient_id) . " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
if ($result = $conn->query($appointment_sql)) {
    while ($row = $result->fetch_assoc()) {
        $dateString = $row['appointment_date'] . ' ' . $row['appointment_time'];
        $timestamp = strtotime($dateString) ?: 0;
        $timeline[] = [
            'type' => 'appointment',
            'timestamp' => $timestamp,
            'title' => "Appointment with Dr. {$row['doctor_name']}",
            'subtitle' => "Specialty: {$row['specialty']}",
            'description' => "Status: {$row['status']}",
            'meta' => date('M d, Y \a\t H:i', $timestamp),
            'badge_text' => $row['status'],
            'badge_class' => strtolower($row['status']) === 'paid' ? 'success' : (strtolower($row['status']) === 'approved' ? 'success' : (strtolower($row['status']) === 'cancelled' ? 'danger' : 'warning')),
            'icon' => 'bi bi-calendar-check',
            'color' => 'primary',
        ];
    }
}

/* Payments */
$payment_sql = "SELECT id, amount, payment_status, created_at FROM payments WHERE patient_id = " . intval($patient_id) . " ORDER BY created_at DESC";
if ($result = $conn->query($payment_sql)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime($row['created_at']) ?: 0;
        $status = $row['payment_status'];
        $timeline[] = [
            'type' => 'payment',
            'timestamp' => $timestamp,
            'title' => "Payment of Rs. " . number_format($row['amount'], 2),
            'subtitle' => "Payment ID #{$row['id']}",
            'description' => "Status: {$status}",
            'meta' => $timestamp ? date('M d, Y \a\t H:i', $timestamp) : 'Date unavailable',
            'badge_text' => ucfirst($status),
            'badge_class' => strtolower($status) === 'paid' ? 'success' : 'warning',
            'icon' => 'bi bi-credit-card',
            'color' => 'success',
        ];
    }
}

/* Lab Tests */
$lab_sql = "SELECT id, test_type, result, report_date FROM lab_tests WHERE patient_id = " . intval($patient_id) . " ORDER BY report_date DESC, id DESC";
if ($result = $conn->query($lab_sql)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime($row['report_date']) ?: 0;
        $timeline[] = [
            'type' => 'lab',
            'timestamp' => $timestamp,
            'title' => "Lab Test: {$row['test_type']}",
            'subtitle' => "Result: {$row['result']}",
            'description' => "Report ID #{$row['id']}",
            'meta' => $timestamp ? date('M d, Y', $timestamp) : 'Date unavailable',
            'badge_text' => 'Lab Report',
            'badge_class' => 'primary',
            'icon' => 'bi bi-beaker',
            'color' => 'info',
        ];
    }
}

/* Feedback */
if ($patient_name !== '') {
    $feedback_sql = "SELECT id, rating, comments, created_at FROM feedback WHERE patient_name = '" . $conn->real_escape_string($patient_name) . "' ORDER BY created_at DESC";
    if ($result = $conn->query($feedback_sql)) {
        while ($row = $result->fetch_assoc()) {
            $timestamp = strtotime($row['created_at']) ?: 0;
            $timeline[] = [
                'type' => 'feedback',
                'timestamp' => $timestamp,
                'title' => "Feedback submitted",
                'subtitle' => "Rating: {$row['rating']} / 5",
                'description' => $row['comments'],
                'meta' => $timestamp ? date('M d, Y \a\t H:i', $timestamp) : 'Date unavailable',
                'badge_text' => 'Feedback',
                'badge_class' => 'warning',
                'icon' => 'bi bi-chat-left-text',
                'color' => 'warning',
            ];
        }
    }
}

/* Prescriptions / Medicine Orders */
if ($patient_name !== '') {
    $prescription_sql = "SELECT id, medicine_name, quantity, total_price, status FROM medicine_orders WHERE patient_name = '" . $conn->real_escape_string($patient_name) . "' ORDER BY id DESC";
    if ($result = $conn->query($prescription_sql)) {
        while ($row = $result->fetch_assoc()) {
            $timestamp = $row['id'];
            $timeline[] = [
                'type' => 'prescription',
                'timestamp' => $timestamp,
                'title' => "Medicine Order: {$row['medicine_name']}",
                'subtitle' => "Qty: {$row['quantity']} • Total: Rs. " . number_format($row['total_price'], 2),
                'description' => "Order status: {$row['status']}",
                'meta' => "Order #{$row['id']}",
                'badge_text' => 'Prescription',
                'badge_class' => strtolower($row['status']) === 'pending' ? 'warning' : 'success',
                'icon' => 'bi bi-capsule',
                'color' => 'danger',
            ];
        }
    }
}

usort($timeline, function ($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});
?>
<?php include 'header.php'; ?>

<div class="row mb-3">
    <div class="col">
        <h1 class="h3">Health Timeline</h1>
        <div class="text-muted">See your appointments, payments, lab reports, prescriptions, and feedback in one timeline.</div>
    </div>
    <div class="col-auto">
        <a class="btn btn-outline-secondary" href="dashboard.php">Back to Dashboard</a>
    </div>
</div>

<div class="card p-4">
    <div class="timeline">
        <?php if (empty($timeline)): ?>
            <div class="timeline-empty">
                <h3>No health history yet</h3>
                <p>Book an appointment, submit feedback, or complete a payment to start building your timeline.</p>
            </div>
        <?php else: ?>
            <?php foreach ($timeline as $event): ?>
                <div class="timeline-item">
                    <span class="timeline-marker bg-<?php echo htmlspecialchars($event['color']); ?>">
                        <i class="<?php echo htmlspecialchars($event['icon']); ?>"></i>
                    </span>
                    <div class="timeline-content">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <div class="text-muted"><?php echo htmlspecialchars($event['subtitle']); ?></div>
                            </div>
                            <span class="timeline-badge <?php echo htmlspecialchars($event['badge_class']); ?>"><?php echo htmlspecialchars($event['badge_text']); ?></span>
                        </div>
                        <?php if (!empty($event['description'])): ?>
                            <p class="timeline-description"><?php echo htmlspecialchars($event['description']); ?></p>
                        <?php endif; ?>
                        <div class="timeline-meta"><?php echo htmlspecialchars($event['meta']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #dbeafe;
    border-radius: 999px;
}
.timeline-item {
    position: relative;
    margin-bottom: 24px;
    padding-left: 44px;
}
.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #ffffff;
    box-shadow: 0 0 0 10px rgba(59, 130, 246, 0.08);
    font-size: 1.1rem;
}
.timeline-content {
    background: #ffffff;
    border: 1px solid #e5efff;
    border-radius: 22px;
    padding: 20px;
    box-shadow: 0 20px 50px rgba(59, 130, 246, 0.08);
}
.timeline-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.timeline-badge.success { background: #16a34a; }
.timeline-badge.primary { background: #2563eb; }
.timeline-badge.warning { background: #f59e0b; }
.timeline-badge.danger { background: #dc2626; }
.timeline-description {
    margin: 0 0 12px;
    color: #475569;
}
.timeline-meta {
    color: #94a3b8;
    font-size: 0.92rem;
}
.timeline-empty {
    padding: 60px 32px;
    text-align: center;
    color: #334155;
}
.timeline-empty h3 {
    margin-bottom: 12px;
}
@media (max-width: 768px) {
    .timeline { padding-left: 24px; }
    .timeline::before { left: 12px; }
    .timeline-item { padding-left: 36px; }
    .timeline-marker { width: 34px; height: 34px; }
}
</style>

<?php include 'footer.php'; ?>
