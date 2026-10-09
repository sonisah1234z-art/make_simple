<?php
require_once 'auth.php';
require_doctor();
require_once 'db.php';

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$message = '';
$error = '';

if (empty($_SESSION['doctor_profile_csrf'])) {
    $_SESSION['doctor_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['doctor_profile_csrf'];

$departments = [];
$departmentResult = $conn->query("SELECT name FROM departments WHERE status = 'Active' ORDER BY name ASC");
if ($departmentResult) {
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = (string)$row['name'];
    }
}

$doctor = null;
$doctorStmt = $conn->prepare('SELECT id, name, email, specialty, profile_photo, qualifications, experience_years, department, consultation_fee, contact_info, working_days, available_slots, about FROM doctors WHERE id = ? LIMIT 1');
if ($doctorStmt) {
    $doctorStmt->bind_param('i', $doctorId);
    $doctorStmt->execute();
    $doctorResult = $doctorStmt->get_result();
    $doctor = $doctorResult ? $doctorResult->fetch_assoc() : null;
    $doctorStmt->close();
}

if (!$doctor) {
    http_response_code(404);
    exit('Doctor profile not found.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $specialty = trim((string)($_POST['specialty'] ?? ''));
        $qualifications = trim((string)($_POST['qualifications'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $experienceInput = trim((string)($_POST['experience_years'] ?? '0'));
        $consultationFeeInput = trim((string)($_POST['consultation_fee'] ?? '0'));
        $contactInfo = trim((string)($_POST['contact_info'] ?? ''));
        $workingDays = trim((string)($_POST['working_days'] ?? ''));
        $availableSlots = trim((string)($_POST['available_slots'] ?? ''));
        $about = trim((string)($_POST['about'] ?? ''));
        $experienceYears = filter_var($experienceInput, FILTER_VALIDATE_INT);
        $consultationFee = filter_var($consultationFeeInput, FILTER_VALIDATE_FLOAT);

        if ($specialty === '') {
            $error = 'Specialization is required.';
        } elseif ($experienceYears === false || $experienceYears < 0 || $experienceYears > 100) {
            $error = 'Years of experience must be between 0 and 100.';
        } elseif ($consultationFee === false || $consultationFee < 0) {
            $error = 'Consultation fee must be a valid non-negative amount.';
        } elseif (mb_strlen($qualifications) > 255 || mb_strlen($department) > 255 || mb_strlen($contactInfo) > 255 || mb_strlen($workingDays) > 255 || mb_strlen($availableSlots) > 255) {
            $error = 'One or more profile fields are too long.';
        } elseif (mb_strlen($about) > 5000) {
            $error = 'Bio / About Me must be 5000 characters or fewer.';
        }

        $newPhotoPath = null;
        $newPhotoFile = null;
        if ($error === '' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profile_photo'];
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'The profile photo could not be uploaded.';
            } elseif ((int)$file['size'] > 5 * 1024 * 1024) {
                $error = 'Profile photo must be 5 MB or smaller.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $error = 'Invalid profile photo upload.';
            } else {
                if (!class_exists('finfo')) {
                    $error = 'Secure image validation is unavailable on this server.';
                }
                $fileInfo = $error === '' ? new finfo(FILEINFO_MIME_TYPE) : null;
                $mimeType = $fileInfo ? $fileInfo->file($file['tmp_name']) : '';
                $imageInfo = $error === '' ? @getimagesize($file['tmp_name']) : false;
                if ($error === '' && (!isset($allowedMimeTypes[$mimeType]) || !$imageInfo)) {
                    $error = 'Only valid JPG, JPEG, PNG, and WEBP images are allowed.';
                } elseif ($error === '') {
                    $uploadDirectory = __DIR__ . '/uploads/doctors';
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                        $error = 'The profile photo directory is not available.';
                    } else {
                        try {
                            $fileToken = bin2hex(random_bytes(16));
                        } catch (Throwable $exception) {
                            $fileToken = '';
                        }
                        if ($fileToken === '') {
                            $error = 'Unable to prepare the profile photo.';
                        } else {
                            $newPhotoFile = $uploadDirectory . '/doctor_' . $doctorId . '_' . $fileToken . '.' . $allowedMimeTypes[$mimeType];
                            $newPhotoPath = 'uploads/doctors/' . basename($newPhotoFile);
                            if (!move_uploaded_file($file['tmp_name'], $newPhotoFile)) {
                                $error = 'Unable to save the profile photo.';
                                $newPhotoPath = null;
                                $newPhotoFile = null;
                            }
                        }
                    }
                }
            }
        }

        if ($error === '') {
            if ($newPhotoPath !== null) {
                $updateStmt = $conn->prepare('UPDATE doctors SET profile_photo = ?, specialty = ?, experience_years = ?, qualifications = ?, department = ?, consultation_fee = ?, contact_info = ?, working_days = ?, available_slots = ?, about = ? WHERE id = ?');
                if ($updateStmt) {
                    $updateStmt->bind_param('ssissdssssi', $newPhotoPath, $specialty, $experienceYears, $qualifications, $department, $consultationFee, $contactInfo, $workingDays, $availableSlots, $about, $doctorId);
                }
            } else {
                $updateStmt = $conn->prepare('UPDATE doctors SET specialty = ?, experience_years = ?, qualifications = ?, department = ?, consultation_fee = ?, contact_info = ?, working_days = ?, available_slots = ?, about = ? WHERE id = ?');
                if ($updateStmt) {
                    $updateStmt->bind_param('sissdssssi', $specialty, $experienceYears, $qualifications, $department, $consultationFee, $contactInfo, $workingDays, $availableSlots, $about, $doctorId);
                }
            }

            if (!$updateStmt) {
                $error = 'Unable to prepare the profile update.';
            } elseif (!$updateStmt->execute()) {
                $error = 'Unable to save your profile. Please try again.';
            } else {
                if ($newPhotoPath !== null && !empty($doctor['profile_photo']) && str_starts_with((string)$doctor['profile_photo'], 'uploads/doctors/')) {
                    $oldPhotoFile = __DIR__ . '/' . ltrim((string)$doctor['profile_photo'], '/');
                    if (is_file($oldPhotoFile)) {
                        @unlink($oldPhotoFile);
                    }
                }
                $message = 'Your doctor profile was updated successfully.';
                $doctor['specialty'] = $specialty;
                $doctor['qualifications'] = $qualifications;
                $doctor['experience_years'] = $experienceYears;
                $doctor['department'] = $department;
                $doctor['consultation_fee'] = $consultationFee;
                $doctor['contact_info'] = $contactInfo;
                $doctor['working_days'] = $workingDays;
                $doctor['available_slots'] = $availableSlots;
                $doctor['about'] = $about;
                if ($newPhotoPath !== null) {
                    $doctor['profile_photo'] = $newPhotoPath;
                }
            }
            if ($updateStmt) {
                $updateStmt->close();
            }
            if ($error !== '' && $newPhotoFile !== null && is_file($newPhotoFile)) {
                @unlink($newPhotoFile);
            }
        } elseif ($newPhotoFile !== null && is_file($newPhotoFile)) {
            @unlink($newPhotoFile);
        }
    }
}

$photoUrl = !empty($doctor['profile_photo']) ? (string)$doctor['profile_photo'] : 'assets/images/default-doctor.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Doctor Profile</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f7fb;color:#172033;font-family:Segoe UI,Arial,sans-serif}.page{max-width:1000px;margin:0 auto;padding:28px 20px}.panel{background:#fff;border-radius:16px;padding:26px;box-shadow:0 12px 32px rgba(15,23,42,.08)}.topbar{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px;flex-wrap:wrap}.topbar a{color:#2563eb;text-decoration:none;font-weight:600}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field{display:flex;flex-direction:column;gap:7px}.field.full{grid-column:1/-1}label{font-weight:600;color:#334155}input,textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:11px;font:inherit}textarea{min-height:120px;resize:vertical}.help{font-size:13px;color:#64748b}.photo{width:128px;height:128px;border-radius:14px;object-fit:cover;border:1px solid #cbd5e1}.photo-row{display:flex;align-items:center;gap:18px;margin-bottom:22px}.actions{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}.button{border:0;border-radius:8px;padding:12px 18px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}.secondary{background:#e2e8f0;color:#172033;text-decoration:none}.alert{padding:12px 14px;border-radius:8px;margin-bottom:18px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}@media(max-width:700px){.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.photo-row{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Edit Doctor Profile</h1>
            <p>Update the information patients see on your doctor profile.</p>
        </div>
        <div><a href="doctor-dashboard.php">Back to dashboard</a></div>
    </div>
    <div class="panel">
        <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="photo-row">
                <img class="photo" src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Current doctor profile photo" onerror="this.onerror=null;this.src='assets/images/default-doctor.png';">
                <div class="field">
                    <label for="profile_photo">Profile photo</label>
                    <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <span class="help">JPG, JPEG, PNG, or WEBP. Maximum 5 MB.</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field"><label for="specialty">Specialization</label><input id="specialty" name="specialty" maxlength="255" required value="<?php echo htmlspecialchars((string)$doctor['specialty']); ?>"></div>
                <div class="field"><label for="experience_years">Years of experience</label><input id="experience_years" name="experience_years" type="number" min="0" max="100" required value="<?php echo (int)$doctor['experience_years']; ?>"></div>
                <div class="field"><label for="qualifications">Qualifications</label><input id="qualifications" name="qualifications" maxlength="255" value="<?php echo htmlspecialchars((string)$doctor['qualifications']); ?>"></div>
                <div class="field"><label for="department">Department</label><input id="department" name="department" list="department-options" maxlength="255" value="<?php echo htmlspecialchars((string)$doctor['department']); ?>"><datalist id="department-options"><?php foreach ($departments as $department): ?><option value="<?php echo htmlspecialchars($department); ?>"><?php endforeach; ?></datalist></div>
                <div class="field"><label for="consultation_fee">Consultation fee</label><input id="consultation_fee" name="consultation_fee" type="number" min="0" step="0.01" required value="<?php echo htmlspecialchars((string)$doctor['consultation_fee']); ?>"></div>
                <div class="field"><label for="contact_info">Consultation contact information</label><input id="contact_info" name="contact_info" maxlength="255" value="<?php echo htmlspecialchars((string)$doctor['contact_info']); ?>"></div>
                <div class="field"><label for="working_days">Consultation days</label><input id="working_days" name="working_days" maxlength="255" value="<?php echo htmlspecialchars((string)$doctor['working_days']); ?>"></div>
                <div class="field"><label for="available_slots">Consultation time slots</label><input id="available_slots" name="available_slots" maxlength="255" placeholder="09:00, 11:00, 14:00" value="<?php echo htmlspecialchars((string)$doctor['available_slots']); ?>"></div>
                <div class="field full"><label for="about">Bio / About Me</label><textarea id="about" name="about" maxlength="5000"><?php echo htmlspecialchars((string)$doctor['about']); ?></textarea></div>
            </div>
            <div class="actions"><button class="button" type="submit">Save profile</button><a class="button secondary" href="doctor-profile.php?id=<?php echo $doctorId; ?>">View public profile</a></div>
        </form>
    </div>
</div>
</body>
</html>
