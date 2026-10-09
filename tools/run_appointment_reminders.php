<?php
require_once __DIR__ . '/../appointment_reminder_helper.php';

if (!($conn instanceof mysqli)) {
    die('Database connection not established.');
}

ensure_appointment_reminder_support($conn);
cleanupExpiredReminders($conn);
generateDueAppointmentReminders($conn);
deliverDueAppointmentReminders($conn);
echo "Reminder scheduling and delivery run completed.\n";
