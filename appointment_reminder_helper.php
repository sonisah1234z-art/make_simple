<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/send_email.php';

function ensure_appointment_reminder_support(mysqli $conn): void
{
    ensure_notifications_table($conn);
}

function get_hospital_name(): string
{
    return 'Hospital Management System';
}

function buildAppointmentReminderMessage(array $appointment): string
{
    $dateLabel = date('F j, Y', strtotime($appointment['appointment_date']));
    $timeLabel = date('g:i A', strtotime($appointment['appointment_time']));
    return sprintf(
        'Reminder: You have an appointment with Dr. %s on %s at %s. Please arrive at least 15 minutes early.',
        $appointment['doctor_name'],
        $dateLabel,
        $timeLabel
    );
}

function buildAppointmentReminderTitle(string $reminderType): string
{
    switch ($reminderType) {
        case '24_hour':
            return 'Appointment Reminder - 24 Hours';
        case '2_hour':
            return 'Appointment Reminder - 2 Hours';
        case 'day_of':
            return 'Appointment Reminder - Today';
        default:
            return 'Appointment Reminder';
    }
}

function getAppointmentReminderSendAt(string $appointmentDate, string $appointmentTime, string $reminderType): ?string
{
    $appointmentTimestamp = strtotime($appointmentDate . ' ' . $appointmentTime);
    if ($appointmentTimestamp === false) {
        return null;
    }

    switch ($reminderType) {
        case '24_hour':
            $sendAt = $appointmentTimestamp - 24 * 3600;
            break;
        case '2_hour':
            $sendAt = $appointmentTimestamp - 2 * 3600;
            break;
        case 'day_of':
            $sendAt = strtotime($appointmentDate . ' 08:00:00');
            break;
        default:
            return null;
    }

    if ($sendAt === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $sendAt);
}

function scheduleAppointmentReminder(mysqli $conn, array $appointment, string $reminderType, ?string $sendAt = null): bool
{
    ensure_appointment_reminder_support($conn);

    if (empty($appointment['id']) || empty($appointment['patient_id'])) {
        return false;
    }

    $appointmentId = (int)$appointment['id'];
    $stmt = $conn->prepare('SELECT id FROM notifications WHERE appointment_id = ? AND reminder_type = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $appointmentId, $reminderType);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return false;
    }

    $sendAt = $sendAt ?? getAppointmentReminderSendAt($appointment['appointment_date'], $appointment['appointment_time'], $reminderType);
    if ($sendAt === null) {
        return false;
    }

    $title = buildAppointmentReminderTitle($reminderType);
    $message = buildAppointmentReminderMessage($appointment);
    $expiresAt = date('Y-m-d H:i:s', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'] . ' +1 hour'));

    $stmt = $conn->prepare(
        'INSERT INTO notifications (patient_id, appointment_id, reminder_type, title, message, send_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }

    $patientId = (int)$appointment['patient_id'];
    $stmt->bind_param('iisssss', $patientId, $appointmentId, $reminderType, $title, $message, $sendAt, $expiresAt);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function deliverDueAppointmentReminders(mysqli $conn): void
{
    $stmt = $conn->prepare(
        'SELECT n.id, n.patient_id, n.title, n.message, n.reminder_type, n.send_at, n.expires_at, p.email, p.name AS patient_name, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time
         FROM notifications n
         JOIN patients p ON n.patient_id = p.id
         JOIN appointments a ON n.appointment_id = a.id
         JOIN doctors d ON a.doctor_id = d.id
         WHERE n.send_at IS NOT NULL AND n.delivered_at IS NULL AND n.send_at <= NOW() AND (n.expires_at IS NULL OR n.expires_at > NOW()) AND a.status = \'Approved\''
    );

    if (!$stmt) {
        return;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return;
    }

    while ($notification = $result->fetch_assoc()) {
        $emailSubject = $notification['title'];
        $emailBody = '<p>Dear ' . htmlspecialchars($notification['patient_name']) . ',</p>' .
            '<p>' . htmlspecialchars($notification['message']) . '</p>' .
            '<p><strong>Doctor:</strong> Dr. ' . htmlspecialchars($notification['doctor_name']) . '</p>' .
            '<p><strong>Department:</strong> ' . htmlspecialchars($notification['specialty']) . '</p>' .
            '<p><strong>Date:</strong> ' . htmlspecialchars(date('F j, Y', strtotime($notification['appointment_date']))) . '</p>' .
            '<p><strong>Time:</strong> ' . htmlspecialchars(date('g:i A', strtotime($notification['appointment_time']))) . '</p>' .
            '<p><strong>Hospital:</strong> ' . htmlspecialchars(get_hospital_name()) . '</p>' .
            '<p>Thank you.</p>';

        $delivered = true;
        if (!empty($notification['email'])) {
            $delivered = sendEmail($notification['email'], $emailSubject, $emailBody);
        }

        if ($delivered) {
            $updateStmt = $conn->prepare('UPDATE notifications SET delivered_at = NOW() WHERE id = ? LIMIT 1');
            if ($updateStmt) {
                $notificationId = (int)$notification['id'];
                $updateStmt->bind_param('i', $notificationId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    $stmt->close();
}

function cleanupExpiredReminders(mysqli $conn): void
{
    $stmt = $conn->prepare('DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()');
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

function getPatientReminders(mysqli $conn, int $patientId): array
{
    $stmt = $conn->prepare(
        'SELECT id, appointment_id, reminder_type, title, message, is_read, send_at, delivered_at, created_at, expires_at FROM notifications WHERE patient_id = ? AND delivered_at IS NOT NULL ORDER BY delivered_at DESC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    $stmt->close();
    return $notifications;
}

function markReminderAsRead(mysqli $conn, int $notificationId, int $patientId): bool
{
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND patient_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $notificationId, $patientId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function buildAppointmentReminderQueryCondition(): string
{
    return "status = 'Approved' AND appointment_date >= CURDATE()";
}
