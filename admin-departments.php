<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$admin_name = $_SESSION['admin_name'];
$message = '';
$error = '';

$conn->query("CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_department'])) {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->bind_param('i', $department_id);
        if ($stmt->execute()) {
            $message = 'Department removed successfully.';
        } else {
            $error = 'Unable to remove department.';
        }
        $stmt->close();
    } elseif (isset($_POST['save_department'])) {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
        $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
        $status = in_array($_POST['status'] ?? 'Active', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';

        if ($name === '') {
            $error = 'Department name is required.';
        } else {
            if ($department_id > 0) {
                $stmt = $conn->prepare('UPDATE departments SET name = ?, description = ?, status = ? WHERE id = ?');
                $stmt->bind_param('sssi', $name, $description, $status, $department_id);
            } else {
                $stmt = $conn->prepare('INSERT INTO departments (name, description, status) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $name, $description, $status);
            }

            if ($stmt->execute()) {
                $message = $department_id > 0 ? 'Department updated successfully.' : 'Department added successfully.';
            } else {
                $error = 'Unable to save department.';
            }
            $stmt->close();
        }
    }
}

$departments = [];
$result = $conn->query('SELECT id, name, description, status, created_at FROM departments ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$editDepartment = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $conn->prepare('SELECT id, name, description, status FROM departments WHERE id = ? LIMIT 1');
    $editStmt->bind_param('i', $editId);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    if ($editResult && $editResult->num_rows > 0) {
        $editDepartment = $editResult->fetch_assoc();
    }
    $editStmt->close();
}
?>
<?php include 'header.php'; ?>

<div class="page-head">
    <div>
        <div class="eyebrow">Admin Console</div>
        <h1>Manage Departments</h1>
        <p>Admin <?php echo htmlspecialchars($admin_name); ?>, create and maintain hospital departments from one central place.</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-outline-primary btn-sm" href="admin-dashboard.php">Dashboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="admin-doctors.php">Doctors</a>
        <a class="btn btn-primary btn-sm" href="logout.php">Logout</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
<?php elseif ($message): ?>
    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="panel-head">
                <div>
                    <h2>Department List</h2>
                    <p>Use these departments to organize doctors and booking options.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($departments) === 0): ?>
                            <tr><td colspan="4" class="text-muted py-4">No departments have been created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($department['name']); ?></td>
                                    <td><?php echo htmlspecialchars($department['description'] ?: '—'); ?></td>
                                    <td><span class="badge rounded-pill <?php echo $department['status'] === 'Active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>"><?php echo htmlspecialchars($department['status']); ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="admin-departments.php?edit=<?php echo (int)$department['id']; ?>">Edit</a>
                                            <form method="POST" onsubmit="return confirm('Remove this department?');">
                                                <input type="hidden" name="department_id" value="<?php echo (int)$department['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_department">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="panel-head">
                <div>
                    <h2><?php echo $editDepartment ? 'Edit Department' : 'Add Department'; ?></h2>
                    <p><?php echo $editDepartment ? 'Update department details.' : 'Create a new hospital department.'; ?></p>
                </div>
            </div>
            <form method="POST" action="admin-departments.php" class="row g-2">
                <?php if ($editDepartment): ?>
                    <input type="hidden" name="department_id" value="<?php echo (int)$editDepartment['id']; ?>">
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label" for="name">Department Name</label>
                    <input id="name" name="name" type="text" class="form-control" value="<?php echo htmlspecialchars($editDepartment['name'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editDepartment['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?php echo ($editDepartment['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($editDepartment['status'] ?? 'Active') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <button class="btn btn-primary w-100" type="submit" name="save_department"><?php echo $editDepartment ? 'Save Changes' : 'Add Department'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>