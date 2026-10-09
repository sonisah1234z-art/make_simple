<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';

$patientId = (int)($_SESSION['patient_id'] ?? 0);

if ($patientId <= 0) {
    header('Location: login.php');
    exit();
}

$sql = "SELECT a.patient_id, a.doctor_id, d.name AS doctor_name, d.specialty, d.department AS doctor_department,
        a.appointment_date, a.appointment_time, a.status,
        (SELECT message FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id ORDER BY cm.created_at DESC LIMIT 1) AS last_message,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id AND cm.sender_role = 'Doctor' AND cm.is_read = 0) AS unread_count
        FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        WHERE a.patient_id = ? AND a.status <> 'Cancelled'
        ORDER BY FIELD(a.status, 'Approved', 'Pending', 'Confirmed', 'Completed'), a.appointment_date DESC, a.appointment_time DESC";

$allRows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allRows[] = $row;
    }
    $stmt->close();
}

$doctorMap = [];
foreach ($allRows as $row) {
    $doctorId = (int)($row['doctor_id'] ?? 0);
    if ($doctorId <= 0) {
        continue;
    }

    if (!isset($doctorMap[$doctorId])) {
        $doctorMap[$doctorId] = $row;
        continue;
    }

    $current = $doctorMap[$doctorId];
    $currentStatus = strtoupper((string)($current['status'] ?? ''));
    $nextStatus = strtoupper((string)($row['status'] ?? ''));

    $preferNext = false;
    if ($nextStatus === 'APPROVED' && $currentStatus !== 'APPROVED') {
        $preferNext = true;
    } elseif ($nextStatus === 'PENDING' && $currentStatus === 'CANCELLED') {
        $preferNext = true;
    }

    if ($preferNext) {
        $doctorMap[$doctorId] = $row;
        continue;
    }

    $currentStamp = ($current['appointment_date'] ?? '0000-00-00') . ' ' . ($current['appointment_time'] ?? '00:00:00');
    $nextStamp = ($row['appointment_date'] ?? '0000-00-00') . ' ' . ($row['appointment_time'] ?? '00:00:00');
    if (strtotime($nextStamp) > strtotime($currentStamp)) {
        $doctorMap[$doctorId] = $row;
    }
}

$rows = array_values($doctorMap);
usort($rows, function ($a, $b) {
    $statusOrder = ['Approved' => 0, 'Confirmed' => 1, 'Pending' => 2, 'Completed' => 3];
    $aStatus = strtoupper((string)($a['status'] ?? 'Pending'));
    $bStatus = strtoupper((string)($b['status'] ?? 'Pending'));
    $aRank = $statusOrder[$aStatus] ?? 99;
    $bRank = $statusOrder[$bStatus] ?? 99;
    if ($aRank !== $bRank) {
        return $aRank <=> $bRank;
    }

    $aStamp = ($a['appointment_date'] ?? '0000-00-00') . ' ' . ($a['appointment_time'] ?? '00:00:00');
    $bStamp = ($b['appointment_date'] ?? '0000-00-00') . ' ' . ($b['appointment_time'] ?? '00:00:00');
    return strtotime($bStamp) <=> strtotime($aStamp);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Doctor Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; color: #1f2937; font-family: Arial, sans-serif; }
        .container { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
        .panel { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06); padding: 24px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .doctor-card { border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; margin-bottom: 16px; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-top: 12px; }
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
                <h2 class="mb-1">My Doctor Messages</h2>
                <div class="muted">Your connected doctors and appointment chat access.</div>
            </div>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>

        <div class="panel">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-4">No doctors available for chat.</div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <div class="doctor-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($row['doctor_name'] ?? 'Doctor'); ?></h4>
                                <div class="muted"><?php echo htmlspecialchars($row['specialty'] ?? ($row['doctor_department'] ?? 'General care')); ?></div>
                            </div>
                            <?php $unread = (int)($row['unread_count'] ?? 0); ?>
                            <?php if ($unread > 0): ?>
                                <span class="unread"><?php echo $unread; ?> unread</span>
                            <?php endif; ?>
                        </div>

                        <div class="meta-grid">
                            <div class="meta-item"><strong>Department:</strong> <?php echo htmlspecialchars($row['doctor_department'] ?? 'N/A'); ?></div>
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
                            <a href="patient-doctor-chat.php?doctor_id=<?php echo (int)$row['doctor_id']; ?>" class="btn btn-primary">Chat</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
