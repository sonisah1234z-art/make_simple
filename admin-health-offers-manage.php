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
$offers = [];
$edit_offer = null;

// Fetch all health offers
$result = $conn->query("SELECT * FROM health_offers ORDER BY sort_order ASC, id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $offers[] = $row;
    }
}

// Handle form submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $offer_id = isset($_POST['offer_id']) ? (int)$_POST['offer_id'] : 0;
    
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $original_price = isset($_POST['original_price']) ? (float)$_POST['original_price'] : 0;
    $discounted_price = isset($_POST['discounted_price']) ? (float)$_POST['discounted_price'] : 0;
    $validity_start = isset($_POST['validity_start']) ? $_POST['validity_start'] : date('Y-m-d');
    $validity_end = isset($_POST['validity_end']) ? $_POST['validity_end'] : date('Y-m-d', strtotime('+30 days'));
    $badge_text = isset($_POST['badge_text']) ? trim($_POST['badge_text']) : '';
    $badge_emoji = isset($_POST['badge_emoji']) ? trim($_POST['badge_emoji']) : '';
    $booking_link = isset($_POST['booking_link']) ? trim($_POST['booking_link']) : 'book-appointment.php';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $offer_type = isset($_POST['offer_type']) ? $_POST['offer_type'] : 'package';
    $num_tests = isset($_POST['num_tests']) ? (int)$_POST['num_tests'] : 0;
    
    // Validate inputs
    if (empty($title) || empty($description)) {
        $error = 'Title and description are required.';
    } elseif ($original_price <= 0 || $discounted_price <= 0) {
        $error = 'Prices must be greater than 0.';
    } elseif (strtotime($validity_end) <= strtotime($validity_start)) {
        $error = 'End date must be after start date.';
    } else {
        // Calculate discount percentage
        $discount = $original_price > 0 ? round((($original_price - $discounted_price) / $original_price) * 100) : 0;
        
        if ($action === 'add') {
            // Handle image upload
            $image_path = null;
            if (isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['offer_image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    $error = 'Invalid image file type.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Image size exceeds 5MB limit.';
                } else {
                    $upload_dir = __DIR__ . '/assets/images/offers/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $new_filename = 'offer_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest_file = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $dest_file)) {
                        $image_path = 'assets/images/offers/' . $new_filename;
                    } else {
                        $error = 'Failed to upload image.';
                    }
                }
            }
            
            if (empty($error)) {
                $stmt = $conn->prepare(
                    'INSERT INTO health_offers (title, description, image_path, original_price, discounted_price, discount_percentage, validity_start, validity_end, badge_text, badge_emoji, booking_link, is_active, is_popular, sort_order, offer_type, num_tests) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                
                if ($stmt) {
                    $stmt->bind_param(
                        'sssddiissssiisi',
                        $title,
                        $description,
                        $image_path,
                        $original_price,
                        $discounted_price,
                        $discount,
                        $validity_start,
                        $validity_end,
                        $badge_text,
                        $badge_emoji,
                        $booking_link,
                        $is_active,
                        $is_popular,
                        $sort_order,
                        $offer_type,
                        $num_tests
                    );
                    
                    if ($stmt->execute()) {
                        $message = '✓ Health offer added successfully!';
                        // Refresh offers
                        $offers = [];
                        $result = $conn->query("SELECT * FROM health_offers ORDER BY sort_order ASC, id DESC");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $offers[] = $row;
                            }
                        }
                    } else {
                        $error = 'Failed to add health offer.';
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'edit' && $offer_id > 0) {
            // Fetch current offer
            $current_offer_result = $conn->query("SELECT * FROM health_offers WHERE id = " . $offer_id);
            $current_offer = $current_offer_result ? $current_offer_result->fetch_assoc() : null;
            
            if (!$current_offer) {
                $error = 'Health offer not found.';
            } else {
                $image_path = $current_offer['image_path'];
                
                // Handle image upload/replacement
                if (isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['offer_image'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (!in_array($ext, $allowed)) {
                        $error = 'Invalid image file type.';
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $error = 'Image size exceeds 5MB limit.';
                    } else {
                        $upload_dir = __DIR__ . '/assets/images/offers/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Delete old image if exists
                        if (!empty($current_offer['image_path']) && file_exists(__DIR__ . '/' . $current_offer['image_path'])) {
                            unlink(__DIR__ . '/' . $current_offer['image_path']);
                        }
                        
                        $new_filename = 'offer_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $dest_file = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $dest_file)) {
                            $image_path = 'assets/images/offers/' . $new_filename;
                        } else {
                            $error = 'Failed to upload image.';
                        }
                    }
                }
                
                if (empty($error)) {
                    $stmt = $conn->prepare(
                        'UPDATE health_offers SET title = ?, description = ?, image_path = ?, original_price = ?, discounted_price = ?, discount_percentage = ?, validity_start = ?, validity_end = ?, badge_text = ?, badge_emoji = ?, booking_link = ?, is_active = ?, is_popular = ?, sort_order = ?, offer_type = ?, num_tests = ? WHERE id = ?'
                    );
                    
                    if ($stmt) {
                        $stmt->bind_param(
                            'sssddiissssiisii',
                            $title,
                            $description,
                            $image_path,
                            $original_price,
                            $discounted_price,
                            $discount,
                            $validity_start,
                            $validity_end,
                            $badge_text,
                            $badge_emoji,
                            $booking_link,
                            $is_active,
                            $is_popular,
                            $sort_order,
                            $offer_type,
                            $num_tests,
                            $offer_id
                        );
                        
                        if ($stmt->execute()) {
                            $message = '✓ Health offer updated successfully!';
                            // Refresh offers
                            $offers = [];
                            $result = $conn->query("SELECT * FROM health_offers ORDER BY sort_order ASC, id DESC");
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $offers[] = $row;
                                }
                            }
                        } else {
                            $error = 'Failed to update health offer.';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $offer_id = (int)$_GET['id'];
    
    // Fetch offer to get image path
    $offer_result = $conn->query("SELECT image_path FROM health_offers WHERE id = " . $offer_id);
    $offer = $offer_result ? $offer_result->fetch_assoc() : null;
    
    if ($offer && !empty($offer['image_path']) && file_exists(__DIR__ . '/' . $offer['image_path'])) {
        unlink(__DIR__ . '/' . $offer['image_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM health_offers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $offer_id);
        if ($stmt->execute()) {
            $message = '✓ Health offer deleted successfully!';
            // Refresh offers
            $offers = [];
            $result = $conn->query("SELECT * FROM health_offers ORDER BY sort_order ASC, id DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $offers[] = $row;
                }
            }
        } else {
            $error = 'Failed to delete health offer.';
        }
        $stmt->close();
    }
}

// Handle edit mode
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $offer_id = (int)$_GET['id'];
    $edit_result = $conn->query("SELECT * FROM health_offers WHERE id = " . $offer_id);
    $edit_offer = $edit_result ? $edit_result->fetch_assoc() : null;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Health Offers Management - Admin</title>
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
            background: #f0f4f8;
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
        }
        
        .tab-btn:hover {
            color: #0d6efd;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #0d6efd;
            font-size: 22px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .offers-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .offers-table thead {
            background: #0d6efd;
            color: white;
        }
        
        .offers-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
        }
        
        .offers-table td {
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .offers-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .offer-image-thumb {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .popular-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #ffc107;
            color: #000;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .btn-edit {
            background: #0d6efd;
            color: white;
            text-decoration: none;
        }
        
        .btn-edit:hover {
            background: #0056d2;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            text-decoration: none;
        }
        
        .btn-delete:hover {
            background: #c82333;
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
        
        @media (max-width: 768px) {
            .header h1 { font-size: 24px; }
            .form-row { grid-template-columns: 1fr; }
            .offers-table { overflow-x: auto; }
            .tabs { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💊 Health Offers Management</h1>
            <p>Create, edit, and manage all health offer cards displayed on the patient dashboard.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('add-offer')">➕ Add New Offer</button>
            <button class="tab-btn" onclick="switchTab('manage-offers')">📋 Manage Offers</button>
        </div>

        <!-- Add/Edit Offer Tab -->
        <div id="add-offer" class="tab-content active">
            <div class="form-section">
                <h2><?php echo $edit_offer ? '✏️ Edit Health Offer' : '➕ Add New Health Offer'; ?></h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $edit_offer ? 'edit' : 'add'; ?>">
                    <?php if ($edit_offer): ?>
                        <input type="hidden" name="offer_id" value="<?php echo (int)$edit_offer['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Offer Title *</label>
                            <input type="text" id="title" name="title" required value="<?php echo $edit_offer ? htmlspecialchars($edit_offer['title']) : ''; ?>" placeholder="e.g., Full Body Checkup">
                        </div>
                        <div class="form-group">
                            <label for="badge_text">Badge Text</label>
                            <input type="text" id="badge_text" name="badge_text" value="<?php echo $edit_offer ? htmlspecialchars($edit_offer['badge_text']) : ''; ?>" placeholder="e.g., 30% OFF">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="badge_emoji">Badge Emoji</label>
                            <input type="text" id="badge_emoji" name="badge_emoji" maxlength="2" value="<?php echo $edit_offer ? htmlspecialchars($edit_offer['badge_emoji']) : ''; ?>" placeholder="e.g., ⭐">
                        </div>
                        <div class="form-group">
                            <label for="booking_link">Booking Link</label>
                            <input type="text" id="booking_link" name="booking_link" value="<?php echo $edit_offer ? htmlspecialchars($edit_offer['booking_link']) : 'book-appointment.php'; ?>" placeholder="book-appointment.php">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required placeholder="Describe the health offer..."><?php echo $edit_offer ? htmlspecialchars($edit_offer['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="original_price">Original Price (Rs) *</label>
                            <input type="number" id="original_price" name="original_price" step="0.01" required value="<?php echo $edit_offer ? (float)$edit_offer['original_price'] : ''; ?>" placeholder="e.g., 5000">
                        </div>
                        <div class="form-group">
                            <label for="discounted_price">Discounted Price (Rs) *</label>
                            <input type="number" id="discounted_price" name="discounted_price" step="0.01" required value="<?php echo $edit_offer ? (float)$edit_offer['discounted_price'] : ''; ?>" placeholder="e.g., 3500">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_start">Validity Start *</label>
                            <input type="date" id="validity_start" name="validity_start" required value="<?php echo $edit_offer ? $edit_offer['validity_start'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="validity_end">Validity End *</label>
                            <input type="date" id="validity_end" name="validity_end" required value="<?php echo $edit_offer ? $edit_offer['validity_end'] : date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="sort_order">Display Order</label>
                            <input type="number" id="sort_order" name="sort_order" value="<?php echo $edit_offer ? (int)$edit_offer['sort_order'] : '0'; ?>" placeholder="0 = first, 1 = second, etc.">
                        </div>
                        <div class="form-group">
                            <label for="offer_type">Type</label>
                            <select id="offer_type" name="offer_type" required>
                                <option value="package" <?php echo (!$edit_offer || $edit_offer['offer_type'] === 'package') ? 'selected' : ''; ?>>📋 Health Package</option>
                                <option value="offer" <?php echo ($edit_offer && $edit_offer['offer_type'] === 'offer') ? 'selected' : ''; ?>>🎯 Promotional Offer</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="num_tests_field" style="display: none;">
                        <label for="num_tests">Number of Tests (for packages only)</label>
                        <input type="number" id="num_tests" name="num_tests" min="0" value="<?php echo $edit_offer ? (int)$edit_offer['num_tests'] : '0'; ?>" placeholder="e.g., 55 tests">
                    </div>

                    <div class="form-group">
                        <label for="offer_image">Offer Image <?php echo $edit_offer ? '(Optional - Leave empty to keep current)' : '*'; ?></label>
                        <input type="file" id="offer_image" name="offer_image" accept="image/jpeg,image/png,image/webp" <?php echo !$edit_offer ? 'required' : ''; ?>>
                        <div class="file-info">
                            ✓ Formats: JPG, PNG, WEBP (max 5MB)<br>
                            ✓ Recommended: 900×220px (16:2 aspect ratio)
                            <?php if ($edit_offer && !empty($edit_offer['image_path'])): ?>
                                <br>✓ Current image: <?php echo htmlspecialchars(basename($edit_offer['image_path'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" <?php echo $edit_offer && $edit_offer['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">Active (Display on Dashboard)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_popular" name="is_popular" <?php echo $edit_offer && $edit_offer['is_popular'] ? 'checked' : ''; ?>>
                            <label for="is_popular">Mark as Popular</label>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_offer ? '✏️ Update Offer' : '➕ Create Offer'; ?>
                        </button>
                        <?php if ($edit_offer): ?>
                            <a href="admin-health-offers-manage.php" class="btn btn-secondary">❌ Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Offers Tab -->
        <div id="manage-offers" class="tab-content">
            <table class="offers-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Prices (Original / Discounted)</th>
                        <th>Validity Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($offers)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px;">No health offers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($offers as $offer): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($offer['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($offer['image_path']); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>" class="offer-image-thumb" onerror="this.style.background='#f0f0f0';">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 24px;">🖼️</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($offer['title']); ?></strong>
                                    <?php if ($offer['is_popular']): ?>
                                        <div><span class="popular-badge">★ Popular</span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Rs <?php echo number_format($offer['original_price'], 2); ?> / 
                                    <strong style="color: #28a745;">Rs <?php echo number_format($offer['discounted_price'], 2); ?></strong>
                                    <br><small style="color: #666;"><?php echo $offer['discount_percentage']; ?>% off</small>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($offer['validity_start'])); ?> to <?php echo date('M d, Y', strtotime($offer['validity_end'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $offer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $offer['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?php echo (int)$offer['id']; ?>" class="btn btn-sm btn-edit">✏️ Edit</a>
                                    <a href="?action=delete&id=<?php echo (int)$offer['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Delete this offer?');">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="admin-dashboard.php" class="back-link">← Back to Admin Dashboard</a>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Show/hide num_tests field based on offer type
        const offerTypeSelect = document.getElementById('offer_type');
        const numTestsField = document.getElementById('num_tests_field');
        
        function toggleNumTestsField() {
            if (offerTypeSelect && numTestsField) {
                numTestsField.style.display = offerTypeSelect.value === 'package' ? 'block' : 'none';
            }
        }
        
        if (offerTypeSelect) {
            offerTypeSelect.addEventListener('change', toggleNumTestsField);
            // Initialize on page load
            toggleNumTestsField();
        }

        // Auto-calculate discount percentage
        const originalPrice = document.getElementById('original_price');
        const discountedPrice = document.getElementById('discounted_price');
        
        function updateDiscount() {
            if (originalPrice && discountedPrice) {
                const orig = parseFloat(originalPrice.value) || 0;
                const disc = parseFloat(discountedPrice.value) || 0;
                if (orig > 0) {
                    const percentage = Math.round(((orig - disc) / orig) * 100);
                    console.log('Discount: ' + percentage + '%');
                }
            }
        }
        
        if (originalPrice) originalPrice.addEventListener('change', updateDiscount);
        if (discountedPrice) discountedPrice.addEventListener('change', updateDiscount);
    </script>
</body>
</html>
