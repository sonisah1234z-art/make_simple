<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

// ensure table exists via auth.ensure_auth_schema
ensure_lab_tests_table($conn);

// handle create/edit/delete
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare('DELETE FROM lab_technicians WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: admin_lab_technicians.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'create' || $action === 'edit')) {
    $id = (int)($_POST['id'] ?? 0);
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? null);
    $phone = $conn->real_escape_string($_POST['phone'] ?? null);
    $laboratory = $conn->real_escape_string($_POST['laboratory'] ?? null);
    $password = $_POST['password'] ?? '';

    // handle photo
    $photoName = '';
    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $photoName = uniqid('tech_') . '.' . $ext;
        move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $photoName);
    }

    if ($action === 'create') {
        $hash = password_hash($password ?: 'labtech123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO lab_technicians (name, username, email, phone, laboratory, photo, password) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) { $stmt->bind_param('sssssss', $name, $username, $email, $phone, $laboratory, $photoName, $hash); $stmt->execute(); $stmt->close(); }
    } else {
        // update
        if ($photoName) {
            $sql = 'UPDATE lab_technicians SET name=?, username=?, email=?, phone=?, laboratory=?, photo=?' . (!empty($password) ? ', password=?' : '') . ' WHERE id=? LIMIT 1';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param('sssssssi', $name, $username, $email, $phone, $laboratory, $photoName, $hash, $id);
                } else {
                    $stmt->bind_param('ssssssi', $name, $username, $email, $phone, $laboratory, $photoName, $id);
                }
                $stmt->execute(); $stmt->close();
            }
        } else {
            $sql = 'UPDATE lab_technicians SET name=?, username=?, email=?, phone=?, laboratory=?' . (!empty($password) ? ', password=?' : '') . ' WHERE id=? LIMIT 1';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param('ssssssi', $name, $username, $email, $phone, $laboratory, $hash, $id);
                } else {
                    $stmt->bind_param('sssssi', $name, $username, $email, $phone, $laboratory, $id);
                }
                $stmt->execute(); $stmt->close();
            }
        }
    }

    header('Location: admin_lab_technicians.php'); exit();
}

// fetch technicians
$techs = $conn->query('SELECT * FROM lab_technicians ORDER BY id DESC');
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Lab Technicians</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>.container{max-width:1100px;margin:20px auto}.card{background:#fff;padding:18px;border-radius:10px}</style>
</head>
<body>
<div class="container">
    <h2>Lab Technicians</h2>
    <div class="card">
        <h3>Add Technician</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div><label>Name</label><input name="name" required></div>
            <div><label>Username</label><input name="username" required></div>
            <div><label>Email</label><input name="email" type="email"></div>
            <div><label>Phone</label><input name="phone"></div>
            <div><label>Laboratory</label><input name="laboratory"></div>
            <div><label>Photo</label><input name="photo" type="file" accept="image/*"></div>
            <div><label>Password (optional)</label><input name="password" type="password"></div>
            <div><button type="submit">Create</button></div>
        </form>
    </div>

    <div class="card" style="margin-top:20px">
        <h3>Existing Technicians</h3>
        <table border="1" width="100%">
            <tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Lab</th><th>Photo</th><th>Actions</th></tr>
            <?php while ($t = $techs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$t['id']; ?></td>
                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                    <td><?php echo htmlspecialchars($t['username']); ?></td>
                    <td><?php echo htmlspecialchars($t['email']); ?></td>
                    <td><?php echo htmlspecialchars($t['phone']); ?></td>
                    <td><?php echo htmlspecialchars($t['laboratory']); ?></td>
                    <td><?php if (!empty($t['photo'])): ?><img src="uploads/<?php echo htmlspecialchars($t['photo']); ?>" style="height:40px"><?php endif; ?></td>
                    <td>
                        <a href="admin_lab_technicians.php?action=edit&id=<?php echo (int)$t['id']; ?>">Edit</a> |
                        <a href="admin_lab_technicians.php?action=delete&id=<?php echo (int)$t['id']; ?>" onclick="return confirm('Delete?')">Delete</a>
                    </td>
                </tr>
                <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && (int)$_GET['id'] === (int)$t['id']): ?>
                    <tr><td colspan="8">
                        <h4>Edit Technician</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <div><label>Name</label><input name="name" required value="<?php echo htmlspecialchars($t['name']); ?>"></div>
                            <div><label>Username</label><input name="username" required value="<?php echo htmlspecialchars($t['username']); ?>"></div>
                            <div><label>Email</label><input name="email" type="email" value="<?php echo htmlspecialchars($t['email']); ?>"></div>
                            <div><label>Phone</label><input name="phone" value="<?php echo htmlspecialchars($t['phone']); ?>"></div>
                            <div><label>Laboratory</label><input name="laboratory" value="<?php echo htmlspecialchars($t['laboratory']); ?>"></div>
                            <div><label>Photo</label><input name="photo" type="file" accept="image/*"></div>
                            <div><label>New Password (leave blank to keep)</label><input name="password" type="password"></div>
                            <div><button type="submit">Save</button></div>
                        </form>
                    </td></tr>
                <?php endif; ?>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>