<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$doctor_status_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'status'");
if ($doctor_status_column && $doctor_status_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Offline'");
}
$doctor_password_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'password'");
if ($doctor_password_column && $doctor_password_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN password VARCHAR(255) NULL");
}

$conn->query("CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin_name = $_SESSION['admin_name'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_doctor'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $stmt = $conn->prepare('DELETE FROM doctors WHERE id = ?');
        $stmt->bind_param('i', $doctor_id);
        if ($stmt->execute()) {
            $message = 'Doctor removed successfully.';
        } else {
            $error = 'Unable to remove doctor.';
        }
        $stmt->close();
    } elseif (isset($_POST['delete_all'])) {
        $conn->query('DELETE FROM doctors');
        $message = 'All doctors have been removed.';
    } elseif (isset($_POST['update_status'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $status = $conn->real_escape_string($_POST['status']);
        if (in_array($status, ['Available', 'Busy', 'Offline'])) {
            $stmt = $conn->prepare('UPDATE doctors SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $doctor_id);
            if ($stmt->execute()) {
                $message = 'Doctor status updated successfully.';
            } else {
                $error = 'Unable to update doctor status.';
            }
            $stmt->close();
        } else {
            $error = 'Invalid status selected.';
        }
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $specialty = $conn->real_escape_string($_POST['specialty']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $password = $_POST['password'] !== '' ? $_POST['password'] : 'doctor123';
        $department = trim($conn->real_escape_string($_POST['department'] ?? ''));

        if ($name && $specialty) {
            if ($email !== '') {
                $checkStmt = $conn->prepare('SELECT id FROM doctors WHERE email = ? LIMIT 1');
                if ($checkStmt) {
                    $checkStmt->bind_param('s', $email);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    if ($checkResult && $checkResult->num_rows > 0) {
                        $error = 'Unable to add doctor. Email already exists.';
                        $checkStmt->close();
                    } else {
                        $checkStmt->close();
                    }
                }
            }

            if ($error === '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO doctors (name, specialty, email, phone, status, password, department) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $defaultStatus = 'Offline';
                if ($stmt) {
                    $stmt->bind_param('sssssss', $name, $specialty, $email, $phone, $defaultStatus, $passwordHash, $department);
                    try {
                        if ($stmt->execute()) {
                            $message = 'Doctor added successfully.';
                        } else {
                            $error = 'Unable to add doctor. Please try again.';
                        }
                    } catch (mysqli_sql_exception $ex) {
                        if ($ex->getCode() === 1062) {
                            $error = 'Unable to add doctor. Email already exists.';
                        } else {
                            $error = 'Unable to add doctor. Please try again.';
                        }
                    }
                    $stmt->close();
                } else {
                    $error = 'Unable to add doctor. Please try again.';
                }
            }
        } else {
            $error = 'Name and specialty are required.';
        }
    }
}

$departments = [];
$departmentResult = $conn->query('SELECT id, name FROM departments WHERE status = "Active" ORDER BY name ASC');
if ($departmentResult) {
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

$doctors = [];
$result = $conn->query('SELECT id, name, specialty, email, phone, status, department, created_at FROM doctors ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}
?>
<?php include 'header.php'; ?>

<div class="page-head">
    <div>
        <div class="eyebrow">Admin Console</div>
        <h1>Manage Doctors</h1>
        <p>Admin <?php echo htmlspecialchars($admin_name); ?>, add new specialists and review the current roster.</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-outline-primary btn-sm" href="admin-dashboard.php">Dashboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="index.php">Home</a>
        <a class="btn btn-primary btn-sm" href="logout.php">Logout</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
<?php elseif ($message): ?>
    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="panel-head">
                <div>
                    <h2>Doctor Roster</h2>
                    <p>Current availability and contact details.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Specialty</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doctors) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-muted py-4">No doctors have been added yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <?php $statusClass = $doctor['status'] === 'Available' ? 'bg-success-subtle text-success' : ($doctor['status'] === 'Busy' ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary'); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['department'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                    <td><span class="badge rounded-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($doctor['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($doctor['created_at']); ?></td>
                                    <td>
                                        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                            <select class="form-select form-select-sm" name="status">
                                                <option value="Available" <?php echo $doctor['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                                <option value="Busy" <?php echo $doctor['status'] === 'Busy' ? 'selected' : ''; ?>>Busy</option>
                                                <option value="Offline" <?php echo $doctor['status'] === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                                            </select>
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-outline-primary">Update</button>
                                        </form>
                                        <form method="POST" class="mt-2" onsubmit="return confirm('Are you sure you want to remove this doctor?');">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="delete_doctor" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($doctors) > 0): ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to remove ALL doctors? This action cannot be undone.');">
                    <button type="submit" name="delete_all" class="btn btn-danger w-100">Remove All Doctors</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="panel-head">
                <div>
                    <h2>Add Doctor</h2>
                    <p>Create an account for a new specialist.</p>
                </div>
            </div>
            <form method="POST" action="admin-doctors.php" class="row g-2">
                <div class="col-12">
                    <label class="form-label" for="name">Name</label>
                    <input id="name" name="name" type="text" class="form-control" placeholder="Doctor name" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="specialty">Specialty</label>
                    <input id="specialty" name="specialty" type="text" class="form-control" placeholder="Example: Cardiology" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="department">Department</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department['name']); ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" placeholder="doctor@example.com">
                </div>

                <div class="col-12">
                    <label class="form-label" for="phone">Phone</label>
                    <input id="phone" name="phone" type="text" class="form-control" placeholder="Contact number">
                </div>

                <div class="col-12">
                    <label class="form-label" for="password">Login Password</label>
                    <input id="password" name="password" type="password" class="form-control" placeholder="Default: doctor123">
                </div>

                <div class="col-12 mt-2">
                    <button class="btn btn-primary w-100" type="submit">Add Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
