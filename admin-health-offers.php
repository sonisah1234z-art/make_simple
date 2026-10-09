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

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['diabetes_image'])) {
    $file = $_FILES['diabetes_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
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
            // Move file to assets/images directory
            $dest_dir = __DIR__ . '/assets/images/';
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }
            
            $dest_file = $dest_dir . 'diabetes-screening.jpg';
            
            if (move_uploaded_file($tmp_name, $dest_file)) {
                $message = '✓ Diabetes Screening image uploaded successfully! The card will now display your new image.';
            } else {
                $error = 'Failed to move uploaded file. Check folder permissions.';
            }
        }
    } else {
        $error = 'File upload error: ' . $file['error'];
    }
}

// Check if image exists
$image_path = __DIR__ . '/assets/images/diabetes-screening.jpg';
$image_exists = file_exists($image_path);
$image_info = $image_exists ? 'Image size: ' . round(filesize($image_path) / 1024, 2) . ' KB' : 'No custom image uploaded yet';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Health Offers Management - Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/compact-ui.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f6ff;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #0d6efd;
            text-align: center;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h3 {
            color: #0d6efd;
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        input[type="file"] {
            display: block;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
        button {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            transition: background 0.3s;
        }
        button:hover {
            background: #0056d2;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .image-status {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        .image-status.active {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .image-status strong {
            display: block;
            margin-bottom: 4px;
        }
        .preview-info {
            font-size: 13px;
            color: #555;
            margin-top: 15px;
            padding: 10px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #0d6efd;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 Health Offers Management</h1>
        <p class="subtitle">Manage your health offer card images</p>

        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>📊 Diabetes Screening & Coaching Card</h3>
            
            <div class="image-status <?php echo $image_exists ? 'active' : ''; ?>">
                <strong><?php echo $image_exists ? '✓ Custom Image Uploaded' : '⚠ Using Default Image'; ?></strong>
                <?php echo htmlspecialchars($image_info); ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="diabetes_image">Upload New Image</label>
                    <input type="file" id="diabetes_image" name="diabetes_image" accept="image/jpeg,image/png,image/webp" required>
                    <div class="file-info">
                        ✓ Supported formats: JPG, PNG, WEBP<br>
                        ✓ Max file size: 5MB<br>
                        ✓ Recommended: 900×600px (16:9 aspect ratio)
                    </div>
                </div>
                <button type="submit">📤 Upload Image</button>
            </form>

            <div class="preview-info">
                <strong>ℹ️ Image Information:</strong>
                The image will be displayed on the Diabetes Screening card in the patient dashboard's "My Health Offers" section. It will automatically:
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li>Maintain aspect ratio</li>
                    <li>Fit to 220px height</li>
                    <li>Scale smoothly on hover</li>
                    <li>Display responsively on all devices</li>
                </ul>
            </div>
        </div>

        <div class="back-link">
            <a href="admin-dashboard.php">← Back to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>
