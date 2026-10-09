<?php
require_once 'auth.php';
require_doctor();
require_once 'db.php';

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

$sql = "SELECT a.patient_id, a.doctor_id, p.name AS patient_name, p.patient_code, p.date_of_birth, p.gender, p.department,
        a.appointment_date, a.appointment_time, a.status,
        (SELECT message FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id ORDER BY cm.created_at DESC LIMIT 1) AS last_message,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id AND cm.sender_role = 'Patient' AND cm.is_read = 0) AS unread_count
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

function patient_age(string $dob): string
{
    if ($dob === '' || $dob === null) {
        return 'N/A';
    }

    $date = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$date) {
        return 'N/A';
    }

    $today = new DateTime('today');
    $age = $today->diff($date)->y;
    return (string)$age;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; color: #1f2937; font-family: Arial, sans-serif; }
        .container { max-width: 1180px; margin: 32px auto; padding: 0 20px; }
        .panel { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06); padding: 24px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .patient-card { border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; margin-bottom: 16px; transition: box-shadow .2s ease; }
        .patient-card:hover { box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08); }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 12px; }
        .meta-item { background: #f8fafc; border-radius: 10px; padding: 8px 10px; font-size: 0.92rem; }
        .badge { display: inline-flex; align-items: center; padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .unread { background: #2563eb; color: white; border-radius: 999px; padding: 4px 8px; font-size: 12px; font-weight: 700; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-row">
            <div>
                <h2 class="mb-1">Doctor Patient Conversations</h2>
                <div class="muted">All patients with appointments scheduled with you.</div>
            </div>
            <a href="doctor-dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>

        <div class="panel">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-4">No patient conversations found for your appointments yet.</div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <div class="patient-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($row['patient_name'] ?? 'Unknown patient'); ?></h4>
                                <div class="muted">Patient ID: <?php echo htmlspecialchars($row['patient_code'] ?? ('P-' . str_pad((string)($row['patient_id'] ?? 0), 4, '0', STR_PAD_LEFT))); ?></div>
                            </div>
                            <?php $unread = (int)($row['unread_count'] ?? 0); ?>
                            <?php if ($unread > 0): ?>
                                <span class="unread"><?php echo $unread; ?> unread</span>
                            <?php endif; ?>
                        </div>

                        <div class="meta-grid">
                            <div class="meta-item"><strong>Age:</strong> <?php echo htmlspecialchars(patient_age((string)($row['date_of_birth'] ?? ''))); ?></div>
                            <div class="meta-item"><strong>Gender:</strong> <?php echo htmlspecialchars($row['gender'] ?? 'Not specified'); ?></div>
                            <div class="meta-item"><strong>Department:</strong> <?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></div>
                            <div class="meta-item"><strong>Appointment:</strong> <?php echo htmlspecialchars($row['appointment_date'] ?? 'N/A'); ?></div>
                            <div class="meta-item"><strong>Time:</strong> <?php echo htmlspecialchars(substr((string)($row['appointment_time'] ?? '00:00:00'), 0, 5)); ?></div>
                            <div class="meta-item"><strong>Status:</strong>
                                <?php
                                    $status = (string)($row['status'] ?? 'Pending');
                                    $statusClass = strtolower($status) === 'approved' ? 'badge-approved' : (strtolower($status) === 'cancelled' ? 'badge-cancelled' : 'badge-pending');
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <strong>Last message:</strong>
                            <div class="muted mt-1"><?php echo htmlspecialchars((string)($row['last_message'] ?? 'No messages yet.')); ?></div>
                        </div>

                        <div class="mt-3">
                            <a href="patient-doctor-chat.php?patient_id=<?php echo (int)$row['patient_id']; ?>&doctor_id=<?php echo (int)$row['doctor_id']; ?>" class="btn btn-primary">Open Chat</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
