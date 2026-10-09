<?php
function is_staff_role(string $role): bool
{
    return in_array($role, ['Doctor', 'Nurse', 'Pharmacist', 'Receptionist', 'Lab Technician'], true);
}

function get_leave_staff_context(): array
{
    $role = (string)($_SESSION['role'] ?? '');
    $roleSessions = [
        'Doctor' => ['id' => 'doctor_id', 'name' => 'doctor_name'],
        'Nurse' => ['id' => 'nurse_id', 'name' => 'nurse_name'],
        'Pharmacist' => ['id' => 'pharmacist_id', 'name' => 'pharmacist_name'],
        'Receptionist' => ['id' => 'receptionist_id', 'name' => 'receptionist_name'],
        'Lab Technician' => ['id' => 'lab_technician_id', 'name' => 'lab_technician_name'],
    ];

    if (!isset($roleSessions[$role])) {
        foreach ($roleSessions as $sessionRole => $sessionKeys) {
            if (isset($_SESSION[$sessionKeys['id']])) {
                $role = $sessionRole;
                break;
            }
        }
    }

    if (!isset($roleSessions[$role])) {
        return ['role' => '', 'id' => 0, 'name' => ''];
    }

    $sessionKeys = $roleSessions[$role];
    return [
        'role' => $role,
        'id' => (int)($_SESSION[$sessionKeys['id']] ?? $_SESSION['user_id'] ?? 0),
        'name' => (string)($_SESSION[$sessionKeys['name']] ?? $_SESSION['user_name'] ?? '')
    ];
}

function ensure_leave_management_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_role VARCHAR(50) NOT NULL,
        staff_id INT NOT NULL,
        staff_name VARCHAR(255) NOT NULL,
        leave_type VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT NOT NULL,
        document_path VARCHAR(255) DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT DEFAULT NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL DEFAULT NULL,
        reviewed_by VARCHAR(255) DEFAULT NULL,
        INDEX idx_staff (staff_role, staff_id),
        INDEX idx_status (status),
        INDEX idx_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("ALTER TABLE leave_requests MODIFY status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");

    $conn->query("CREATE TABLE IF NOT EXISTS staff_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_role VARCHAR(50) NOT NULL,
        staff_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_notifications (staff_role, staff_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function get_staff_notifications(mysqli $conn, string $staffRole, int $staffId): array
{
    if ($staffRole === '' || $staffId <= 0) {
        return [];
    }

    $stmt = $conn->prepare('SELECT id, title, message, is_read, created_at FROM staff_notifications WHERE staff_role = ? AND staff_id = ? ORDER BY id DESC LIMIT 10');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('si', $staffRole, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

function leave_request_overlaps(mysqli $conn, string $staffRole, int $staffId, string $startDate, string $endDate): bool
{
    if ($staffRole === '' || $staffId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id FROM leave_requests WHERE staff_role = ? AND staff_id = ? AND status IN ('Pending','Approved') AND start_date <= ? AND end_date >= ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siss', $staffRole, $staffId, $endDate, $startDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}
