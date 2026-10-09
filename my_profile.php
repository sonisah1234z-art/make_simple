<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_technician_address_column($conn);

$techId = (int)($_SESSION['lab_technician_id'] ?? 0);
$message = '';
$error = '';

$stmt = $conn->prepare('SELECT id, name, username, email, phone, laboratory, photo, address, password FROM lab_technicians WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $techId);
    $stmt->execute();
    $tech = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $tech = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '') {
        $error = 'Full name and email are required.';
    } elseif ($password !== '' && $password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $photoPath = $tech['photo'] ?? null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed, true)) {
                $error = 'Invalid image type.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Image size must be under 2MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/profile/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                $newFile = 'tech_' . $techId . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $newFile;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $photoPath = 'uploads/profile/' . $newFile;
                } else {
                    $error = 'Failed to upload profile photo.';
                }
            }
        }

        if ($error === '') {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE lab_technicians SET name=?, email=?, phone=?, address=?, photo=?, password=? WHERE id=? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('ssssssi', $fullName, $email, $phone, $address, $photoPath, $hashed, $techId);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'Profile updated successfully.';
                }
            } else {
                $stmt = $conn->prepare('UPDATE lab_technicians SET name=?, email=?, phone=?, address=?, photo=? WHERE id=? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('sssssi', $fullName, $email, $phone, $address, $photoPath, $techId);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'Profile updated successfully.';
                }
            }
        }
    }
}

if ($tech) {
    $tech['name'] = $tech['name'] ?? '';
    $tech['email'] = $tech['email'] ?? '';
    $tech['phone'] = $tech['phone'] ?? '';
    $tech['address'] = $tech['address'] ?? '';
    $tech['laboratory'] = $tech['laboratory'] ?? '';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>My Profile</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1100px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
label{display:block;margin-top:8px;font-weight:600}
input,button,textarea{width:100%;padding:8px 10px;border-radius:8px;border:1px solid #d8e4f1;margin-top:6px}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block;background:#0d6efd;color:#fff;border:0;cursor:pointer}
.alert{padding:10px;border-radius:8px;margin-bottom:12px}
.alert-success{background:#e8f8ef;color:#157347}
.alert-danger{background:#ffe6e6;color:#a00}
.profile-photo{width:120px;height:120px;border-radius:50%;object-fit:cover;border:2px solid #d8e4f1}
@media (max-width: 900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <h2>My Profile</h2>
    <div class="card">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="grid">
            <div>
                <?php if (!empty($tech['photo'])): ?><img class="profile-photo" src="<?php echo htmlspecialchars($tech['photo']); ?>" alt="profile photo"><?php endif; ?>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($tech['username'] ?? ''); ?></p>
                <p><strong>Laboratory:</strong> <?php echo htmlspecialchars($tech['laboratory'] ?? ''); ?></p>
            </div>
            <div>
                <form method="post" enctype="multipart/form-data">
                    <label>Profile Photo</label>
                    <input type="file" name="photo" accept="image/*">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($tech['name'] ?? ''); ?>" required>
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($tech['email'] ?? ''); ?>" required>
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($tech['phone'] ?? ''); ?>">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?php echo htmlspecialchars($tech['address'] ?? ''); ?></textarea>
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current password">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password">
                    <div style="margin-top:10px"><button class="btn" type="submit">Update Profile</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
