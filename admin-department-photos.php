<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$message = '';
$error = '';

$departmentQuery = $conn->query("SELECT id, name, description, status, image_path FROM departments ORDER BY name ASC");
$departments = [];
if ($departmentQuery) {
    while ($row = $departmentQuery->fetch_assoc()) {
        $departments[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departmentId = (int)($_POST['department_id'] ?? 0);

    if (isset($_POST['delete_department_photo'])) {
        if ($departmentId <= 0) {
            $error = 'Invalid department selected.';
        } else {
            $currentStmt = $conn->prepare('SELECT image_path FROM departments WHERE id = ? LIMIT 1');
            $currentImage = '';
            if ($currentStmt) {
                $currentStmt->bind_param('i', $departmentId);
                $currentStmt->execute();
                $currentResult = $currentStmt->get_result();
                if ($currentResult && $currentResult->num_rows > 0) {
                    $currentRecord = $currentResult->fetch_assoc();
                    $currentImage = trim((string)($currentRecord['image_path'] ?? ''));
                }
                $currentStmt->close();
            }

            if ($currentImage !== '' && file_exists(__DIR__ . '/' . $currentImage)) {
                unlink(__DIR__ . '/' . $currentImage);
            }

            $updateStmt = $conn->prepare('UPDATE departments SET image_path = NULL WHERE id = ?');
            if ($updateStmt) {
                $updateStmt->bind_param('i', $departmentId);
                if ($updateStmt->execute()) {
                    $message = 'Department photo removed successfully.';
                } else {
                    $error = 'Unable to remove department photo.';
                }
                $updateStmt->close();
            }
        }
    } elseif (isset($_POST['upload_department_photo'])) {
        if ($departmentId <= 0) {
            $error = 'Invalid department selected.';
        } elseif (!isset($_FILES['department_photo']) || $_FILES['department_photo']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Please choose a photo to upload.';
        } else {
            $upload = $_FILES['department_photo'];
            $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if ($upload['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload failed. Please try again.';
            } elseif (!in_array($ext, $allowed, true)) {
                $error = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
            } elseif ($upload['size'] > 5 * 1024 * 1024) {
                $error = 'Photo size must be 5MB or less.';
            } else {
                $uploadDir = __DIR__ . '/uploads/departments';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $currentStmt = $conn->prepare('SELECT image_path FROM departments WHERE id = ? LIMIT 1');
                $currentImage = '';
                if ($currentStmt) {
                    $currentStmt->bind_param('i', $departmentId);
                    $currentStmt->execute();
                    $currentResult = $currentStmt->get_result();
                    if ($currentResult && $currentResult->num_rows > 0) {
                        $currentRecord = $currentResult->fetch_assoc();
                        $currentImage = trim((string)($currentRecord['image_path'] ?? ''));
                    }
                    $currentStmt->close();
                }

                if ($currentImage !== '' && file_exists(__DIR__ . '/' . $currentImage)) {
                    unlink(__DIR__ . '/' . $currentImage);
                }

                $filename = 'department_' . $departmentId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $uploadDir . '/' . $filename;

                if (move_uploaded_file($upload['tmp_name'], $destination)) {
                    $storedPath = 'uploads/departments/' . $filename;
                    $updateStmt = $conn->prepare('UPDATE departments SET image_path = ? WHERE id = ?');
                    if ($updateStmt) {
                        $updateStmt->bind_param('si', $storedPath, $departmentId);
                        if ($updateStmt->execute()) {
                            $message = 'Department photo uploaded successfully.';
                        } else {
                            $error = 'Unable to save department photo.';
                        }
                        $updateStmt->close();
                    }
                } else {
                    $error = 'Unable to save uploaded photo.';
                }
            }
        }
    }

    $departmentQuery = $conn->query("SELECT id, name, description, status, image_path FROM departments ORDER BY name ASC");
    $departments = [];
    if ($departmentQuery) {
        while ($row = $departmentQuery->fetch_assoc()) {
            $departments[] = $row;
        }
    }
}
?>
<?php include 'header.php'; ?>
<div class="admin-dashboard-compact">
    <div class="panel panel-card mb-4">
        <div class="panel-head">
            <div>
                <h2>Department Photos</h2>
                <p>Admin-only image management for each department. These photos also appear on the patient dashboard.</p>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="admin-dashboard.php">Back to Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($message): ?>
            <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
    </div>

    <div class="panel panel-card">
        <div class="panel-head">
            <div>
                <h2>All Departments</h2>
                <p>Upload, replace, or remove the photo for each department.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Current Photo</th>
                        <th>Upload Photo</th>
                        <th>Change Photo</th>
                        <th>Remove Photo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="5" class="text-muted py-4">No departments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($department['name']); ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($department['image_path']) && file_exists(__DIR__ . '/' . $department['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($department['image_path']); ?>" alt="<?php echo htmlspecialchars($department['name']); ?>" style="width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid #dee2e6;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center rounded" style="width:72px;height:72px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                                            <i class="bi bi-hospital" style="font-size:1.6rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="department_id" value="<?php echo (int)$department['id']; ?>">
                                        <input type="file" name="department_photo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                        <button type="submit" name="upload_department_photo" class="btn btn-sm btn-primary mt-2">Upload Photo</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="department_id" value="<?php echo (int)$department['id']; ?>">
                                        <input type="file" name="department_photo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                        <button type="submit" name="upload_department_photo" class="btn btn-sm btn-outline-primary mt-2">Change Photo</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove this department photo?');">
                                        <input type="hidden" name="department_id" value="<?php echo (int)$department['id']; ?>">
                                        <button type="submit" name="delete_department_photo" class="btn btn-sm btn-outline-danger">Remove Photo</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
