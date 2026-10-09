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
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure image_path column exists for older installations
$checkImageCol = $conn->query("SHOW COLUMNS FROM departments LIKE 'image_path'");
if ($checkImageCol && $checkImageCol->num_rows === 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN image_path VARCHAR(255) NULL");
}

// Seed a default set of departments if they are missing so Patient Dashboard and Admin stay in sync
$defaultDepartments = [
    'Critical Care Medicine' => 'Intensive monitoring and advanced support for critically ill patients.',
    'Dental' => 'Complete oral care, dental surgery, and preventive dentistry services.',
    'Dermatology' => 'Skin, hair, and nail treatments from diagnosis to cosmetic care.',
    'Emergency Medicine' => 'Urgent care and rapid response for acute injuries and illnesses.',
    'ENT-HNS Department' => 'Specialists for ear, nose, throat, voice and airway care.',
    'Orthopedics and Trauma Surgery' => 'Bone, joint, muscle, and trauma surgery for comprehensive mobility care.',
    'General Practice' => 'Primary care services for preventive health and everyday wellness.',
    'Internal Medicine' => 'Complex adult medicine, chronic disease management, and diagnostics.',
    'Neurology' => 'Care for neurological disorders, headaches, seizures and brain health.',
    'Neurosurgery' => 'Surgical treatment of the brain, spine and nervous system.',
    'Nephrology' => 'Kidney care, dialysis support and transplant evaluation services.',
    'Obstetrics & Gynecology' => 'Women’s health, pregnancy, and reproductive care.',
    'Paediatrics' => 'Child and adolescent care for growth, immunizations and wellness.',
    'Psychiatry & Mental Health' => 'Mental health support, therapy and psychiatric care services.',
    'Plastic Surgery and Burns' => 'Reconstructive and cosmetic surgery with burn recovery support.',
    'Pharmacy' => 'Medication dispensing, counseling and pharmacy support services.',
    'Radiology' => 'Imaging diagnostics including X-ray, CT, MRI and ultrasound.',
    'Urology and Kidney Transplant Surgery' => 'Urological care and kidney transplant planning for complex cases.',
    'General Surgery' => 'Surgical care across a broad range of procedures.',
    'Anaesthesiology' => 'Perioperative care, anesthesia and pain management.',
    'Pathology' => 'Diagnostic laboratory services and pathology reporting.',
    'Gastroenterology' => 'Digestive health care for liver, stomach, intestine, and gut-related conditions.',
    'Pulmonology & Critical Care' => 'Respiratory care, ICU and advanced lung support services.',
    'Surgical Gastroenterology' => 'Surgical treatment for gastrointestinal conditions.'
];

// Insert missing default departments (no duplicates, case-insensitive check)
$insertDeptStmt = $conn->prepare('INSERT INTO departments (name, description, status, image_path) VALUES (?, ?, ?, ?)');
foreach ($defaultDepartments as $dname => $ddesc) {
    $check = $conn->prepare('SELECT id FROM departments WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $check->bind_param('s', $dname);
    $check->execute();
    $checkRes = $check->get_result();
    if (!($checkRes && $checkRes->num_rows > 0)) {
        $emptyImg = null;
        $status = 'Active';
        $insertDeptStmt->bind_param('ssss', $dname, $ddesc, $status, $emptyImg);
        $insertDeptStmt->execute();
    }
    $check->close();
}
if ($insertDeptStmt) $insertDeptStmt->close();

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

        // Handle optional image upload
        $imagePath = null;
        if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'])) {
            $uploadDir = __DIR__ . '/uploads/departments';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $f = $_FILES['image_file'];
            if ($f['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $filename = 'dept_' . time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
                $target = $uploadDir . '/' . $filename;
                if (@move_uploaded_file($f['tmp_name'], $target)) {
                    $imagePath = 'uploads/departments/' . $filename;
                }
            }
        }

        if ($name === '') {
            $error = 'Department name is required.';
        } else {
            if ($department_id > 0) {
                if ($imagePath !== null) {
                    $stmt = $conn->prepare('UPDATE departments SET name = ?, description = ?, status = ?, image_path = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $name, $description, $status, $imagePath, $department_id);
                } else {
                    $stmt = $conn->prepare('UPDATE departments SET name = ?, description = ?, status = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $name, $description, $status, $department_id);
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO departments (name, description, status, image_path) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssss', $name, $description, $status, $imagePath);
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
$result = $conn->query('SELECT id, name, description, status, image_path, created_at FROM departments ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$editDepartment = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $conn->prepare('SELECT id, name, description, status, image_path FROM departments WHERE id = ? LIMIT 1');
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
                            <th>Logo</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($departments) === 0): ?>
                            <tr><td colspan="5" class="text-muted py-4">No departments have been created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($department['name']); ?></td>
                                    <td>
                                        <?php if (!empty($department['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($department['image_path']); ?>" alt="logo" style="height:48px; width:48px; object-fit:cover; border-radius:6px;">
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
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
            <form method="POST" action="admin-departments.php" class="row g-2" enctype="multipart/form-data">
                <?php if ($editDepartment): ?>
                    <input type="hidden" name="department_id" value="<?php echo (int)$editDepartment['id']; ?>">
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label" for="name">Department Name</label>
                    <input id="name" name="name" type="text" class="form-control" value="<?php echo htmlspecialchars($editDepartment['name'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="image_file">Department Photo / Logo (optional)</label>
                    <?php if (!empty($editDepartment['image_path'])): ?>
                        <div class="mb-2"><img src="<?php echo htmlspecialchars($editDepartment['image_path']); ?>" alt="current" style="height:72px; width:72px; object-fit:cover; border-radius:8px;"></div>
                    <?php endif; ?>
                    <input id="image_file" name="image_file" type="file" accept="image/*" class="form-control">
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