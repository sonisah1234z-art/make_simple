<?php
require_once __DIR__ . '/db.php';

function ensure_announcements_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target_role VARCHAR(50) NOT NULL DEFAULT 'All Users',
        publish_date DATE NOT NULL DEFAULT (CURRENT_DATE),
        expiry_date DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $requiredColumns = [
        'title' => 'VARCHAR(255) NOT NULL',
        'message' => 'TEXT NOT NULL',
        'target_role' => "VARCHAR(50) NOT NULL DEFAULT 'All Users'",
        'publish_date' => 'DATE NOT NULL DEFAULT (CURRENT_DATE)',
        'expiry_date' => 'DATE NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'Active'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];

    $existingColumns = [];
    $columnResult = $conn->query('SHOW COLUMNS FROM announcements');
    if ($columnResult) {
        while ($col = $columnResult->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns, true)) {
            $conn->query("ALTER TABLE announcements ADD COLUMN $column $definition");
            $existingColumns[] = $column;
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS announcement_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_key VARCHAR(100) NOT NULL UNIQUE,
        last_seen_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("UPDATE announcements SET status = CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURRENT_DATE THEN 'Expired' ELSE 'Active' END WHERE status IS NULL OR status = ''");
}

function role_to_target_value(string $role): string
{
    return match ($role) {
        'Admin' => 'Admin',
        'Doctor' => 'Doctor',
        'Patient' => 'Patient',
        'Nurse' => 'Nurse',
        'Pharmacist' => 'Pharmacist',
        'Receptionist' => 'Receptionist',
        default => 'All Users',
    };
}

function get_current_user_context(): array
{
    $role = $_SESSION['role'] ?? '';
    $userId = null;

    if (isset($_SESSION['admin_id'])) {
        $role = 'Admin';
        $userId = (int)$_SESSION['admin_id'];
    } elseif (isset($_SESSION['doctor_id'])) {
        $role = 'Doctor';
        $userId = (int)$_SESSION['doctor_id'];
    } elseif (isset($_SESSION['patient_id'])) {
        $role = 'Patient';
        $userId = (int)$_SESSION['patient_id'];
    } elseif (isset($_SESSION['nurse_id'])) {
        $role = 'Nurse';
        $userId = (int)$_SESSION['nurse_id'];
    } elseif (isset($_SESSION['pharmacist_id'])) {
        $role = 'Pharmacist';
        $userId = (int)$_SESSION['pharmacist_id'];
    } elseif (isset($_SESSION['receptionist_id'])) {
        $role = 'Receptionist';
        $userId = (int)$_SESSION['receptionist_id'];
    }

    return [
        'role' => $role,
        'id' => $userId,
        'user_key' => $role . ':' . ($userId ?? 0),
    ];
}

function get_visible_announcements(mysqli $conn, string $role, ?int $userId = null, int $limit = 0): array
{
    ensure_announcements_schema($conn);
    $targetRole = role_to_target_value($role);
    $escapedTarget = $conn->real_escape_string($targetRole);
    $sql = "SELECT * FROM announcements WHERE status = 'Active' AND (publish_date IS NULL OR publish_date <= CURRENT_DATE) AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE) AND (target_role = 'All Users' OR target_role = '$escapedTarget') ORDER BY publish_date DESC, id DESC";

    if ($limit > 0) {
        $sql .= " LIMIT $limit";
    }

    $result = $conn->query($sql);
    $items = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    return $items;
}

function get_new_announcement_count(mysqli $conn, string $role, ?int $userId = null): int
{
    ensure_announcements_schema($conn);
    $context = get_current_user_context();
    $role = $role ?: $context['role'];
    $userId = $userId ?? $context['id'];
    $userKey = $role . ':' . ($userId ?? 0);
    $escapedUserKey = $conn->real_escape_string($userKey);
    $viewResult = $conn->query("SELECT last_seen_at FROM announcement_views WHERE user_key = '$escapedUserKey' LIMIT 1");
    $lastSeenAt = null;

    if ($viewResult && $viewResult->num_rows > 0) {
        $lastSeenAt = $viewResult->fetch_assoc()['last_seen_at'];
    }

    if (!$lastSeenAt) {
        $targetRole = role_to_target_value($role);
        $escapedTarget = $conn->real_escape_string($targetRole);
        $sql = "SELECT COUNT(*) AS total FROM announcements WHERE status = 'Active' AND (publish_date IS NULL OR publish_date <= CURRENT_DATE) AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE) AND (target_role = 'All Users' OR target_role = '$escapedTarget')";
        $result = $conn->query($sql);
        return $result ? (int)$result->fetch_assoc()['total'] : 0;
    }

    $targetRole = role_to_target_value($role);
    $escapedTarget = $conn->real_escape_string($targetRole);
    $sql = "SELECT COUNT(*) AS total FROM announcements WHERE status = 'Active' AND (publish_date IS NULL OR publish_date <= CURRENT_DATE) AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE) AND (target_role = 'All Users' OR target_role = '$escapedTarget') AND publish_date > DATE('$lastSeenAt')";
    $result = $conn->query($sql);
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

function mark_announcements_seen(mysqli $conn, string $role, ?int $userId = null): void
{
    ensure_announcements_schema($conn);
    $context = get_current_user_context();
    $role = $role ?: $context['role'];
    $userId = $userId ?? $context['id'];
    $userKey = $role . ':' . ($userId ?? 0);
    $escapedUserKey = $conn->real_escape_string($userKey);
    $now = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO announcement_views (user_key, last_seen_at) VALUES ('$escapedUserKey', '$now') ON DUPLICATE KEY UPDATE last_seen_at = '$now'");
}

function render_announcements_panel(mysqli $conn, string $role, ?int $userId = null, int $limit = 3): string
{
    ensure_announcements_schema($conn);
    $context = get_current_user_context();
    $role = $role ?: $context['role'];
    $userId = $userId ?? $context['id'];
    $newCount = get_new_announcement_count($conn, $role, $userId);
    $announcements = get_visible_announcements($conn, $role, $userId, $limit);
    mark_announcements_seen($conn, $role, $userId);

    ob_start();
    ?>
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h5 class="mb-1">Announcements</h5>
                <div class="text-muted small">Relevant updates for your role</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($newCount > 0): ?>
                    <span class="badge badge-available"><?php echo (int)$newCount; ?> new</span>
                <?php endif; ?>
                <a class="btn btn-outline-primary btn-sm" href="view_announcement.php">View All</a>
            </div>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="text-muted">No active announcements are available for your role right now.</div>
        <?php else: ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="border rounded p-2">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                            <span class="badge badge-available"><?php echo htmlspecialchars($announcement['publish_date']); ?></span>
                        </div>
                        <div class="text-muted small mt-1">
                            <?php echo htmlspecialchars(substr(strip_tags($announcement['message']), 0, 110)); ?><?php echo strlen(strip_tags($announcement['message'])) > 110 ? '…' : ''; ?>
                        </div>
                        <div class="mt-2">
                            <a class="btn btn-outline-secondary btn-sm" href="view_announcement.php?id=<?php echo (int)$announcement['id']; ?>">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
