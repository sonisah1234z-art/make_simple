<?php
require_once 'auth.php';
require_once 'db.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$message = '';
$error = '';
$doctors = [];

// Fetch all doctors
$result = $conn->query("SELECT id, name, specialty, profile_photo, email FROM doctors ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doctor_photo'])) {
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $file = $_FILES['doctor_photo'];

    if ($doctor_id <= 0) {
        $error = 'Invalid doctor selected.';
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        $filename = $file['name'];
        $tmp_name = $file['tmp_name'];
        
        // Validate file type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            $error = 'Invalid file type. Only JPG, PNG, and WEBP are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $error = 'File size exceeds 5MB limit.';
        } else {
            // Create uploads directory if needed
            $upload_dir = __DIR__ . '/uploads/doctors/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $new_filename = 'doctor_' . $doctor_id . '_' . time() . '.' . $ext;
            $dest_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $dest_file)) {
                // Update database
                $photo_path = 'uploads/doctors/' . $new_filename;
                $update_stmt = $conn->prepare("UPDATE doctors SET profile_photo = ? WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param('si', $photo_path, $doctor_id);
                    if ($update_stmt->execute()) {
                        $message = '✓ Doctor profile photo uploaded successfully!';
                        // Refresh doctors list
                        $doctors = [];
                        $result = $conn->query("SELECT id, name, specialty, profile_photo, email FROM doctors ORDER BY name ASC");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $doctors[] = $row;
                            }
                        }
                    } else {
                        $error = 'Failed to update doctor profile in database.';
                    }
                    $update_stmt->close();
                } else {
                    $error = 'Database error: ' . $conn->error;
                }
            } else {
                $error = 'Failed to move uploaded file. Check folder permissions.';
            }
        }
    } else {
        $error = 'File upload error: ' . $file['error'];
    }
}

// Handle photo deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['doctor_id'])) {
    $doctor_id = (int)$_GET['doctor_id'];
    $update_stmt = $conn->prepare("UPDATE doctors SET profile_photo = NULL WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param('i', $doctor_id);
        if ($update_stmt->execute()) {
            $message = '✓ Doctor profile photo deleted successfully!';
            // Refresh doctors list
            $doctors = [];
            $result = $conn->query("SELECT id, name, specialty, profile_photo, email FROM doctors ORDER BY name ASC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $doctors[] = $row;
                }
            }
        } else {
            $error = 'Failed to delete photo from database.';
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Profile Photos - Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/compact-ui.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .header h1 {
            color: #0d6efd;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .upload-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .upload-section h2 {
            color: #0d6efd;
            font-size: 22px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input[type="file"]:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-primary {
            background: #0d6efd;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056d2;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(13, 110, 253, 0.3);
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        
        .doctor-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .doctor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.15);
        }
        
        .doctor-photo-container {
            position: relative;
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .doctor-photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .doctor-photo-placeholder {
            font-size: 64px;
            opacity: 0.5;
        }
        
        .doctor-info {
            padding: 20px;
        }
        
        .doctor-name {
            font-size: 18px;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 6px;
        }
        
        .doctor-specialty {
            font-size: 14px;
            color: #666;
            margin-bottom: 6px;
        }
        
        .doctor-email {
            font-size: 12px;
            color: #999;
            margin-bottom: 16px;
            word-break: break-all;
        }
        
        .photo-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .photo-status.has-photo {
            background: #d4edda;
            color: #155724;
        }
        
        .photo-status.no-photo {
            background: #fff3cd;
            color: #856404;
        }
        
        .doctor-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            flex: 1;
            min-height: 36px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .doctors-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍⚕️ Doctor Profile Photos Management</h1>
            <p>Upload and manage doctor profile photos. These images will be displayed throughout the entire system.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="upload-section">
            <h2>📤 Upload Doctor Photo</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">-- Choose a doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo (int)$doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="doctor_photo">Upload Photo</label>
                        <input type="file" id="doctor_photo" name="doctor_photo" accept="image/jpeg,image/png,image/webp" required>
                        <div class="file-info">
                            ✓ Formats: JPG, PNG, WEBP (max 5MB)<br>
                            ✓ Recommended: Square or portrait (300×400px or larger)
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">📤 Upload Photo</button>
            </form>
        </div>

        <div class="upload-section">
            <h2>👥 All Doctors</h2>
            <div class="doctors-grid">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-photo-container">
                            <?php if (!empty($doctor['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($doctor['profile_photo']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" onerror="this.onerror=null;this.parentElement.innerHTML='<span class=\'doctor-photo-placeholder\'>👨‍⚕️</span>';">
                            <?php else: ?>
                                <span class="doctor-photo-placeholder">👨‍⚕️</span>
                            <?php endif; ?>
                        </div>
                        <div class="doctor-info">
                            <div class="doctor-name"><?php echo htmlspecialchars($doctor['name']); ?></div>
                            <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                            <div class="doctor-email"><?php echo htmlspecialchars($doctor['email']); ?></div>
                            <span class="photo-status <?php echo !empty($doctor['profile_photo']) ? 'has-photo' : 'no-photo'; ?>">
                                <?php echo !empty($doctor['profile_photo']) ? '✓ Photo Uploaded' : '⚠ No Photo'; ?>
                            </span>
                            <?php if (!empty($doctor['profile_photo'])): ?>
                                <div class="doctor-actions">
                                    <a href="?action=delete&doctor_id=<?php echo (int)$doctor['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this photo?');">🗑️ Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="admin-dashboard.php" class="back-link">← Back to Admin Dashboard</a>
    </div>
</body>
</html>
