<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['patient_id']) && !isset($_SESSION['receptionist_id'])) {
    header('Location: login.php');
    exit();
}

$isAdmin = isset($_SESSION['admin_id']);
$isReceptionist = isset($_SESSION['receptionist_id']);
$pageTitle = $isAdmin ? 'Bed Inventory Management' : ($isReceptionist ? 'Receptionist Bed Availability' : 'Live Bed Availability');
$currentName = $isAdmin ? $_SESSION['admin_name'] : ($isReceptionist ? $_SESSION['receptionist_name'] : $_SESSION['patient_name']);
$message = '';
$error = '';

ensure_beds_schema($conn);
$patientList = $conn->query('SELECT id, name, patient_type FROM patients ORDER BY name ASC');

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_bed'])) {
        $bed_number = $conn->real_escape_string(trim($_POST['bed_number']));
        $ward = $conn->real_escape_string(trim($_POST['ward']));
        $bed_type = in_array($_POST['bed_type'] ?? '', ['General', 'Emergency', 'ICU']) ? $_POST['bed_type'] : 'General';
        $status = in_array($_POST['status'] ?? '', ['Available', 'Occupied', 'Reserved']) ? $_POST['status'] : 'Available';
        $is_icu = $bed_type === 'ICU' ? 1 : 0;
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $patient_id = $patient_id > 0 ? $patient_id : null;
        if ($patient_id !== null && $status === 'Available') {
            $status = 'Occupied';
        }

        if ($bed_number && $ward) {
            $stmt = $conn->prepare('INSERT INTO beds (bed_number, ward, is_icu, bed_type, status, patient_id) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sisssi', $bed_number, $ward, $is_icu, $bed_type, $status, $patient_id);
                try {
                    if ($stmt->execute()) {
                        $message = 'Bed added successfully.';
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        $error = 'Unable to add bed. Bed number already exists.';
                    } else {
                        $error = 'Unable to add bed. ' . $e->getMessage();
                    }
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare bed insert query: ' . $conn->error;
            }
        } else {
            $error = 'Bed number and ward are required.';
        }
    } elseif (isset($_POST['update_bed'])) {
        $bed_id = (int)($_POST['bed_id'] ?? 0);
        $bed_number = $conn->real_escape_string(trim($_POST['bed_number']));
        $ward = $conn->real_escape_string(trim($_POST['ward']));
        $bed_type = in_array($_POST['bed_type'] ?? '', ['General', 'Emergency', 'ICU']) ? $_POST['bed_type'] : 'General';
        $status = in_array($_POST['status'] ?? '', ['Available', 'Occupied', 'Reserved']) ? $_POST['status'] : 'Available';
        $is_icu = $bed_type === 'ICU' ? 1 : 0;
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $patient_id = $patient_id > 0 ? $patient_id : null;
        if ($patient_id !== null && $status === 'Available') {
            $status = 'Occupied';
        }

        if ($bed_id && $bed_number && $ward) {
            $existingBedStmt = $conn->prepare('SELECT status, patient_id FROM beds WHERE id = ? LIMIT 1');
            if ($existingBedStmt) {
                $existingBedStmt->bind_param('i', $bed_id);
                $existingBedStmt->execute();
                $existingBedResult = $existingBedStmt->get_result();
                $existingBed = $existingBedResult ? $existingBedResult->fetch_assoc() : null;
                $existingBedStmt->close();
            } else {
                $existingBed = null;
            }

            if ($existingBed && $existingBed['status'] === 'Occupied' && $existingBed['patient_id'] !== null && $patient_id !== null && $patient_id !== (int)$existingBed['patient_id'] && $status === 'Occupied') {
                $error = 'This bed is already occupied. Mark it available before assigning a new patient.';
            } else {
                $stmt = $conn->prepare('UPDATE beds SET bed_number = ?, ward = ?, is_icu = ?, bed_type = ?, status = ?, patient_id = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('ssissii', $bed_number, $ward, $is_icu, $bed_type, $status, $patient_id, $bed_id);
                    if ($stmt->execute()) {
                        $message = 'Bed updated successfully.';
                    } else {
                        $error = 'Unable to update bed.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Unable to prepare bed update query: ' . $conn->error;
                }
            }
        } else {
            $error = 'Bed number and ward are required for update.';
        }
    } elseif (isset($_POST['delete_bed'])) {
        $bed_id = (int)($_POST['bed_id'] ?? 0);
        if ($bed_id) {
            $stmt = $conn->prepare('DELETE FROM beds WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $bed_id);
                if ($stmt->execute()) {
                    $message = 'Bed removed successfully.';
                } else {
                    $error = 'Unable to remove bed.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare bed delete query: ' . $conn->error;
            }
        }
    }
}

function badgeState(int $count): array
{
    if ($count === 0) {
        return ['Full', 'badge-full'];
    }
    if ($count <= 5) {
        return ['Limited', 'badge-limited'];
    }
    return ['Available', 'badge-available'];
}

function bedTypeClass(string $type): string
{
    $normalized = strtolower(trim($type));
    if (strpos($normalized, 'icu') !== false) {
        return 'badge-type-ICU';
    }
    if (strpos($normalized, 'emergency') !== false) {
        return 'badge-type-Emergency';
    }
    return 'badge-type-General';
}

function ensure_beds_schema(mysqli $conn): void
{
    ensure_db_connection($conn);

    $tableCreated = $conn->query("CREATE TABLE IF NOT EXISTS beds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bed_number VARCHAR(50) UNIQUE,
        ward VARCHAR(100),
        is_icu TINYINT(1),
        status VARCHAR(50) DEFAULT 'Available',
        bed_type VARCHAR(20) NOT NULL DEFAULT 'General',
        patient_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ($tableCreated === false) {
        error_log('Failed to create beds table: ' . $conn->error);
    }

    $requiredColumns = [
        'bed_type' => "VARCHAR(20) NOT NULL DEFAULT 'General'",
        'status' => "VARCHAR(50) DEFAULT 'Available'",
        'is_icu' => 'TINYINT(1) DEFAULT 0',
        'patient_id' => 'INT NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];

    $existingColumns = [];
    $columnsResult = $conn->query('SHOW COLUMNS FROM beds');
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $existingColumns[] = $column['Field'];
        }
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns, true)) {
            $conn->query("ALTER TABLE beds ADD COLUMN $column $definition");
        }
    }
}

function fetch_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();
    return (int)($row['total'] ?? 0);
}

ensure_beds_schema($conn);

$counts = [
    'total' => 0,
    'available' => 0,
    'icu_available' => 0,
    'general_available' => 0,
    'emergency_available' => 0,
];

$counts['total'] = fetch_count($conn, 'SELECT COUNT(*) AS total FROM beds');
$counts['available'] = fetch_count($conn, "SELECT COUNT(*) AS total FROM beds WHERE patient_id IS NULL AND status = 'Available'");
$counts['icu_available'] = fetch_count($conn, "SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'ICU' AND patient_id IS NULL AND status = 'Available'");
$counts['general_available'] = fetch_count($conn, "SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'General' AND patient_id IS NULL AND status = 'Available'");
$counts['emergency_available'] = fetch_count($conn, "SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'Emergency' AND patient_id IS NULL AND status = 'Available'");

$bedList = $conn->query("SELECT b.*, p.name AS patient_name, p.patient_type, CASE WHEN b.patient_id IS NOT NULL THEN 'Occupied' ELSE b.status END AS effective_status FROM beds b LEFT JOIN patients p ON p.id = b.patient_id ORDER BY b.created_at DESC");
?>

<?php include 'header.php'; ?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h1 class="h3"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="text-muted">Hello <?php echo htmlspecialchars($currentName); ?>. View bed availability in real time.</div>
    </div>
    <div class="col-auto">
        <a class="btn btn-outline-secondary me-2" href="dashboard.php">Dashboard</a>
        <?php if ($isAdmin): ?><a class="btn btn-primary" href="admin-dashboard.php">Admin Home</a><?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php elseif ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="dashboard-cards mb-4">
    <div class="stat-card card">
        <div class="text-muted">Total Beds</div>
        <div class="h4"><?php echo $counts['total']; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">Available Beds</div>
        <div class="h4"><?php echo $counts['available']; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">ICU Beds Available</div>
        <div class="h4"><?php echo $counts['icu_available']; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">Emergency Beds Available</div>
        <div class="h4"><?php echo $counts['emergency_available']; ?></div>
    </div>
    <div class="stat-card card">
        <div class="text-muted">General Beds Available</div>
        <div class="h4"><?php echo $counts['general_available']; ?></div>
    </div>
</div>

<div class="card p-3 mb-4">
    <h5 class="dashboard-heading">Live Bed Status</h5>
    <p class="text-muted">All counts reflect the latest bed inventory maintained by hospital staff.</p>
    <div class="bed-summary-grid mt-3">
        <?php foreach (['icu_available' => 'ICU Beds', 'general_available' => 'General Beds', 'emergency_available' => 'Emergency Beds'] as $key => $label):
            $count = $counts[$key];
            list($statusLabel, $statusClass) = badgeState($count);
        ?>
        <div class="bed-status-card p-3">
            <div class="text-muted"><?php echo htmlspecialchars($label); ?></div>
            <div class="h4"><?php echo $count; ?></div>
            <span class="badge <?php echo $statusClass; ?> mt-2"><?php echo $statusLabel; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
    <div class="row">
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card bed-form-card p-3 h-100">
                <h5 class="dashboard-heading">Add New Bed</h5>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Bed Number</label>
                        <input type="text" name="bed_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ward</label>
                        <input type="text" name="ward" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bed Type</label>
                        <select name="bed_type" class="form-select">
                            <option value="General">General</option>
                            <option value="Emergency">Emergency</option>
                            <option value="ICU">ICU</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Available">Available</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Patient (optional)</label>
                        <select name="patient_id" class="form-select">
                            <option value="">None</option>
                            <?php if ($patientList): ?>
                                <?php while ($patientOption = $patientList->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$patientOption['id']; ?>"><?php echo htmlspecialchars($patientOption['name'] . ' (' . ($patientOption['patient_type'] ?: 'Online') . ')'); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Selecting a patient will automatically mark the bed as Occupied.</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" name="add_bed">Add Bed</button>
                </form>
            </div>
        </div>
        <div class="col-xl-8 col-lg-7">
            <div class="card p-3 mb-4">
                <h5 class="dashboard-heading">Bed Inventory</h5>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0 bed-inventory-table">
                        <thead>
                            <tr>
                                <th>Bed #</th>
                                <th>Ward</th>
                                <th>Type</th>
                                <th>Patient</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bedList && $bedList->num_rows): ?>
                                <?php while ($bed = $bedList->fetch_assoc()): ?>
                                    <tr>
                                        <td class="bed-table-cell bed-table-bed-number"><?php echo htmlspecialchars($bed['bed_number']); ?></td>
                                        <td class="bed-table-cell bed-table-ward"><?php echo htmlspecialchars($bed['ward']); ?></td>
                                        <td><span class="badge <?php echo bedTypeClass($bed['bed_type']); ?>"><?php echo htmlspecialchars($bed['bed_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($bed['patient_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($bed['patient_type'] ?? 'N/A'); ?></td>
                                        <td><span class="status-pill status-<?php echo $bed['effective_status'] === 'Available' ? 'Available' : 'Offline'; ?>"><?php echo htmlspecialchars($bed['effective_status']); ?></span></td>
                                        <td>
                                            <form method="post" class="d-flex gap-2 justify-content-end flex-wrap">
                                                <input type="hidden" name="bed_id" value="<?php echo $bed['id']; ?>">
                                                <input type="hidden" name="bed_number" value="<?php echo htmlspecialchars($bed['bed_number']); ?>">
                                                <input type="hidden" name="ward" value="<?php echo htmlspecialchars($bed['ward']); ?>">
                                                <input type="hidden" name="bed_type" value="<?php echo htmlspecialchars($bed['bed_type']); ?>">
                                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($bed['status']); ?>">
                                                <button type="submit" name="delete_bed" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this bed from inventory?');">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-muted">No beds have been added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
