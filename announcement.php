<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'announcements.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

ensure_announcements_schema($conn);

$message = '';
$mode = 'create';
$announcementId = 0;
$announcement = null;

if (isset($_POST['save'])) {
    $title = trim($_POST['title'] ?? '');
    $messageText = trim($_POST['message'] ?? '');
    $targetRole = $_POST['target_role'] ?? 'All Users';
    $publishDate = $_POST['publish_date'] ?? date('Y-m-d');
    $expiryDate = $_POST['expiry_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    if ($title !== '' && $messageText !== '') {
        $stmt = $conn->prepare('INSERT INTO announcements (title, message, target_role, publish_date, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $title, $messageText, $targetRole, $publishDate, $expiryDate, $status);
        $stmt->execute();
        $stmt->close();
        $message = 'Announcement created successfully.';
    } else {
        $message = 'Title and message are required.';
    }
}

if (isset($_GET['edit_id'])) {
    $mode = 'edit';
    $announcementId = (int)$_GET['edit_id'];
    $stmt = $conn->prepare('SELECT * FROM announcements WHERE id = ?');
    $stmt->bind_param('i', $announcementId);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcement = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (isset($_POST['update'])) {
    $announcementId = (int)$_POST['announcement_id'];
    $title = trim($_POST['title'] ?? '');
    $messageText = trim($_POST['message'] ?? '');
    $targetRole = $_POST['target_role'] ?? 'All Users';
    $publishDate = $_POST['publish_date'] ?? date('Y-m-d');
    $expiryDate = $_POST['expiry_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    if ($title !== '' && $messageText !== '') {
        $stmt = $conn->prepare('UPDATE announcements SET title = ?, message = ?, target_role = ?, publish_date = ?, expiry_date = ?, status = ? WHERE id = ?');
        $stmt->bind_param('ssssssi', $title, $messageText, $targetRole, $publishDate, $expiryDate, $status, $announcementId);
        $stmt->execute();
        $stmt->close();
        $message = 'Announcement updated successfully.';
        $mode = 'create';
    } else {
        $message = 'Title and message are required.';
    }
}

if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    $stmt->close();
    $message = 'Announcement deleted successfully.';
}

$announcements = [];
$result = $conn->query('SELECT * FROM announcements ORDER BY publish_date DESC, id DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcement Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .card { border-radius: 12px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); }
        .form-control, .form-select { border-radius: 10px; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-expired { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">📢 Announcement Management</h2>
            <div class="text-muted">Create, edit, and target announcements to the right audience.</div>
        </div>
        <a class="btn btn-outline-secondary" href="admin-dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card p-3">
                <h5 class="mb-3"><?php echo $mode === 'edit' ? 'Edit Announcement' : 'Create Announcement'; ?></h5>
                <form method="POST">
                    <?php if ($mode === 'edit' && $announcement): ?>
                        <input type="hidden" name="announcement_id" value="<?php echo (int)$announcement['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" value="<?php echo isset($announcement['title']) ? htmlspecialchars($announcement['title']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="5" required><?php echo isset($announcement['message']) ? htmlspecialchars($announcement['message']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select class="form-select" name="target_role">
                            <?php foreach (['All Users','Admin','Doctor','Patient','Nurse','Pharmacist','Receptionist'] as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo (isset($announcement['target_role']) && $announcement['target_role'] === $role) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Publish Date</label>
                            <input type="date" class="form-control" name="publish_date" value="<?php echo isset($announcement['publish_date']) ? htmlspecialchars($announcement['publish_date']) : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" value="<?php echo isset($announcement['expiry_date']) ? htmlspecialchars($announcement['expiry_date']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="Active" <?php echo (isset($announcement['status']) && $announcement['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Expired" <?php echo (isset($announcement['status']) && $announcement['status'] === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <?php if ($mode === 'edit' && $announcement): ?>
                        <button type="submit" name="update" class="btn btn-primary w-100">Update Announcement</button>
                    <?php else: ?>
                        <button type="submit" name="save" class="btn btn-primary w-100">Create Announcement</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <h5 class="mb-3">Existing Announcements</h5>
                <?php if (empty($announcements)): ?>
                    <div class="text-muted">No announcements have been created yet.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($announcements as $row): ?>
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                        <div class="text-muted small">To: <?php echo htmlspecialchars($row['target_role']); ?></div>
                                    </div>
                                    <span class="badge <?php echo $row['status'] === 'Active' ? 'badge-active' : 'badge-expired'; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                </div>
                                <p class="mt-2 mb-2"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                                <div class="text-muted small">Published: <?php echo htmlspecialchars($row['publish_date']); ?> • Expires: <?php echo htmlspecialchars($row['expiry_date'] ?? 'Never'); ?></div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="announcement.php?edit_id=<?php echo (int)$row['id']; ?>">Edit</a>
                                    <a class="btn btn-outline-danger btn-sm" href="announcement.php?delete_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this announcement?');">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>