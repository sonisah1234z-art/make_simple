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

$doctor_fee_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'consultation_fee'");
if ($doctor_fee_column && $doctor_fee_column->num_rows === 0) {
    $conn->query("ALTER TABLE doctors ADD COLUMN consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00");
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
    } elseif (isset($_POST['update_fee'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $consultation_fee = trim($_POST['consultation_fee'] ?? '0');
        $consultation_fee_value = filter_var($consultation_fee, FILTER_VALIDATE_FLOAT);
        if ($consultation_fee_value === false || $consultation_fee_value < 0) {
            $error = 'Consultation fee must be a valid positive amount.';
        } else {
            $stmt = $conn->prepare('UPDATE doctors SET consultation_fee = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('di', $consultation_fee_value, $doctor_id);
                if ($stmt->execute()) {
                    $message = 'Consultation fee updated successfully.';
                } else {
                    $error = 'Unable to update consultation fee.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to update consultation fee.';
            }
        }
    } elseif (isset($_POST['update_experience'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $experience = trim($_POST['experience_years'] ?? '0');
        $experience_value = filter_var($experience, FILTER_VALIDATE_INT);
        if ($experience_value === false || $experience_value < 0) {
            $error = 'Years of experience must be a valid non-negative number.';
        } else {
            $stmt = $conn->prepare('UPDATE doctors SET experience_years = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $experience_value, $doctor_id);
                if ($stmt->execute()) {
                    $message = 'Experience updated successfully.';
                } else {
                    $error = 'Unable to update experience.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to update experience.';
            }
        }
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $specialty = $conn->real_escape_string($_POST['specialty']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $password = $_POST['password'] !== '' ? $_POST['password'] : 'doctor123';
        $department = trim($conn->real_escape_string($_POST['department'] ?? ''));
        $consultation_fee = trim($_POST['consultation_fee'] ?? '0');
        $consultation_fee_value = filter_var($consultation_fee, FILTER_VALIDATE_FLOAT);
        if ($consultation_fee_value === false || $consultation_fee_value < 0) {
            $error = 'Consultation fee must be a valid positive amount.';
            $consultation_fee_value = 0.00;
        }
        $experience = trim($_POST['experience_years'] ?? '0');
        $experience_value = filter_var($experience, FILTER_VALIDATE_INT);
        if ($experience_value === false || $experience_value < 0) {
            $error = 'Years of experience must be a valid non-negative number.';
            $experience_value = 0;
        }

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
                $stmt = $conn->prepare('INSERT INTO doctors (name, specialty, email, phone, status, password, consultation_fee, department, experience_years) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $defaultStatus = 'Offline';
                if ($stmt) {
                    $stmt->bind_param('ssssssdsi', $name, $specialty, $email, $phone, $defaultStatus, $passwordHash, $consultation_fee_value, $department, $experience_value);
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
$result = $conn->query('SELECT id, name, specialty, email, phone, status, department, consultation_fee, experience_years, created_at FROM doctors ORDER BY created_at DESC');
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

<div class="card mb-4">
    <div class="panel-head">
        <div>
            <h2>Add Doctor</h2>
            <p>Create an account for a new specialist.</p>
        </div>
    </div>
    <form method="POST" action="admin-doctors.php" class="row g-3" style="padding: 20px;">
        <div class="col-md-6">
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" type="text" class="form-control" placeholder="Doctor name" required>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="specialty">Specialty</label>
            <input id="specialty" name="specialty" type="text" class="form-control" placeholder="Example: Cardiology" required>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="department">Department</label>
            <select id="department" name="department" class="form-select">
                <option value="">Select department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department['name']); ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control" placeholder="doctor@example.com">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="phone">Phone</label>
            <input id="phone" name="phone" type="text" class="form-control" placeholder="Contact number">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="consultation_fee">Consultation Fee</label>
            <input id="consultation_fee" name="consultation_fee" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 125.00" value="0.00">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="experience_years">Years of Experience</label>
            <input id="experience_years" name="experience_years" type="number" min="0" class="form-control" placeholder="e.g. 5" value="0">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password">Login Password</label>
            <input id="password" name="password" type="password" class="form-control" placeholder="Default: doctor123">
        </div>

        <div class="col-12 mt-3">
            <button class="btn btn-primary w-100" type="submit">Add Doctor</button>
        </div>
    </form>
</div>

<div class="card">
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
                    <th>Experience</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Consultation Fee</th>
                    <th>Status</th>
                    <th>Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                        <?php if (count($doctors) === 0): ?>
                            <tr>
                                <td colspan="9" class="text-muted py-4">No doctors have been added yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <?php $statusClass = $doctor['status'] === 'Available' ? 'bg-success-subtle text-success' : ($doctor['status'] === 'Busy' ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary'); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                    <td><small><?php echo htmlspecialchars($doctor['specialty']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($doctor['department'] ?: 'Unassigned'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars((int)($doctor['experience_years'] ?? 0) . ' years'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($doctor['email']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($doctor['phone']); ?></small></td>
                                    <td><small>₹ <?php echo htmlspecialchars(number_format((float)($doctor['consultation_fee'] ?? 0), 2)); ?></small></td>
                                    <td><span class="badge rounded-pill <?php echo $statusClass; ?>"><small><?php echo htmlspecialchars($doctor['status']); ?></small></span></td>
                                    <td><small><?php echo htmlspecialchars($doctor['created_at']); ?></small></td>
                                    <td>
                                        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center" style="margin-bottom: 8px;">
                                            <select class="form-select form-select-sm" name="status">
                                                <option value="Available" <?php echo $doctor['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                                <option value="Busy" <?php echo $doctor['status'] === 'Busy' ? 'selected' : ''; ?>>Busy</option>
                                                <option value="Offline" <?php echo $doctor['status'] === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                                            </select>
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-outline-primary">Update</button>
                                        </form>
                                        <form method="POST" class="mt-2 d-flex gap-2 align-items-center" onsubmit="return true;">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <input type="number" step="0.01" min="0" name="consultation_fee" value="<?php echo htmlspecialchars(number_format((float)($doctor['consultation_fee'] ?? 0), 2, '.', '')); ?>" class="form-control form-control-sm" style="width:110px;">
                                            <button type="submit" name="update_fee" class="btn btn-sm btn-outline-secondary">Update Fee</button>
                                        </form>
                                        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center" style="margin-bottom: 8px;">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <input type="number" min="0" name="experience_years" value="<?php echo htmlspecialchars((int)($doctor['experience_years'] ?? 0)); ?>" class="form-control form-control-sm" style="width:80px;" placeholder="Years">
                                            <button type="submit" name="update_experience" class="btn btn-sm btn-outline-info">Update Exp</button>
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
        <form method="POST" class="mt-0 ps-3 pe-3 pb-3" onsubmit="return confirm('Are you sure you want to remove ALL doctors? This action cannot be undone');">
            <button type="submit" name="delete_all" class="btn btn-danger w-100">Remove All Doctors</button>
        </form>
    <?php endif; ?>
    </div>

<?php include 'footer.php'; ?>
