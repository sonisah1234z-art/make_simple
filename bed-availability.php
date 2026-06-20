<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$isAdmin = isset($_SESSION['admin_id']);
$pageTitle = $isAdmin ? 'Bed Inventory Management' : 'Live Bed Availability';
$currentName = $isAdmin ? $_SESSION['admin_name'] : $_SESSION['patient_name'];
$message = '';
$error = '';

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

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_bed'])) {
        $bed_number = $conn->real_escape_string(trim($_POST['bed_number']));
        $ward = $conn->real_escape_string(trim($_POST['ward']));
        $bed_type = in_array($_POST['bed_type'] ?? '', ['General', 'Emergency', 'ICU']) ? $_POST['bed_type'] : 'General';
        $status = in_array($_POST['status'] ?? '', ['Available', 'Occupied', 'Reserved']) ? $_POST['status'] : 'Available';
        $is_icu = $bed_type === 'ICU' ? 1 : 0;

        if ($bed_number && $ward) {
            $stmt = $conn->prepare('INSERT INTO beds (bed_number, ward, is_icu, bed_type, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sisss', $bed_number, $ward, $is_icu, $bed_type, $status);
            if ($stmt->execute()) {
                $message = 'Bed added successfully.';
            } else {
                $error = 'Unable to add bed. Bed number may already exist.';
            }
            $stmt->close();
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

        if ($bed_id && $bed_number && $ward) {
            $stmt = $conn->prepare('UPDATE beds SET bed_number = ?, ward = ?, is_icu = ?, bed_type = ?, status = ? WHERE id = ?');
            $stmt->bind_param('sisssi', $bed_number, $ward, $is_icu, $bed_type, $status, $bed_id);
            if ($stmt->execute()) {
                $message = 'Bed updated successfully.';
            } else {
                $error = 'Unable to update bed.';
            }
            $stmt->close();
        } else {
            $error = 'Bed number and ward are required for update.';
        }
    } elseif (isset($_POST['delete_bed'])) {
        $bed_id = (int)($_POST['bed_id'] ?? 0);
        if ($bed_id) {
            $stmt = $conn->prepare('DELETE FROM beds WHERE id = ?');
            $stmt->bind_param('i', $bed_id);
            if ($stmt->execute()) {
                $message = 'Bed removed successfully.';
            } else {
                $error = 'Unable to remove bed.';
            }
            $stmt->close();
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

$counts = [
    'total' => 0,
    'available' => 0,
    'icu_available' => 0,
    'general_available' => 0,
    'emergency_available' => 0,
];

$totals = $conn->query("SELECT COUNT(*) AS total FROM beds");
if ($totals) {
    $counts['total'] = (int)$totals->fetch_assoc()['total'];
}
$available = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status = 'Available'");
if ($available) {
    $counts['available'] = (int)$available->fetch_assoc()['total'];
}
$counts['icu_available'] = (int)$conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'ICU' AND status = 'Available'")->fetch_assoc()['total'];
$counts['general_available'] = (int)$conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'General' AND status = 'Available'")->fetch_assoc()['total'];
$counts['emergency_available'] = (int)$conn->query("SELECT COUNT(*) AS total FROM beds WHERE bed_type = 'Emergency' AND status = 'Available'")->fetch_assoc()['total'];

$bedList = $conn->query('SELECT * FROM beds ORDER BY created_at DESC');
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
                                        <td><span class="status-pill status-<?php echo $bed['status'] === 'Available' ? 'Available' : 'Offline'; ?>"><?php echo htmlspecialchars($bed['status']); ?></span></td>
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
                                    <td colspan="5" class="text-muted">No beds have been added yet.</td>
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
