<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_tests_table($conn);

$techId = (int)($_SESSION['lab_technician_id'] ?? 0);

// handle mark completed
if (isset($_POST['mark_completed']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    // ensure the test belongs to this technician
    $stmt = $conn->prepare('SELECT technician_id, patient_id FROM lab_tests WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $r = $res ? $res->fetch_assoc() : null; $stmt->close(); }
    if ($r && (empty($r['technician_id']) || (int)$r['technician_id'] === $techId)) {
        // claim the test for this technician (if unassigned) and mark completed
        $u = $conn->prepare("UPDATE lab_tests SET status = 'Completed', technician_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($u) { $u->bind_param('ii', $techId, $id); $u->execute(); $u->close(); }
        // notify patient
        if (!empty($r['patient_id'])) {
            $nid = (int)$r['patient_id'];
            $title = 'Lab Report Completed';
            $message = 'Your lab test report #' . $id . ' has been completed.';
            $ins = $conn->prepare('INSERT INTO notifications (patient_id, title, message) VALUES (?, ?, ?)');
            if ($ins) { $ins->bind_param('iss', $nid, $title, $message); $ins->execute(); $ins->close(); }
        }
    }
    // If AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Marked completed', 'id' => $id]);
        exit();
    }
    header('Location: lab_technician_dashboard.php'); exit();
}

// handle replacement upload from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_file']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    // ensure the test belongs to this technician
    $stmt = $conn->prepare('SELECT technician_id, patient_id FROM lab_tests WHERE id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $r = $res ? $res->fetch_assoc() : null; $stmt->close(); }
    if ($r && (empty($r['technician_id']) || (int)$r['technician_id'] === $techId)) {
        $errors = [];
        if (!empty($_FILES['replacement']['name'])) {
            $f = $_FILES['replacement'];
            if (!isset($f['error']) || is_array($f['error'])) {
                $errors[] = 'Invalid file upload parameters.';
            } elseif ($f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error code: ' . $f['error'];
            } else {
                $maxBytes = 5 * 1024 * 1024;
                if ($f['size'] > $maxBytes) { $errors[] = 'File exceeds maximum allowed size of 5 MB.'; }
                $allowedExt = ['pdf','jpg','jpeg','png'];
                $origName = $f['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) { $errors[] = 'Invalid file extension. Allowed: PDF, JPG, JPEG, PNG.'; }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['application/pdf','image/jpeg','image/png'];
                if (!in_array($mime, $allowedMimes, true)) { $errors[] = 'Invalid file type detected.'; }
                if (empty($errors)) {
                    if (!is_uploaded_file($f['tmp_name'])) { $errors[] = 'Uploaded file not found.'; }
                    else {
                        $uploadName = bin2hex(random_bytes(8)) . '.' . $ext;
                        $dest = __DIR__ . '/uploads/' . $uploadName;
                        if (!move_uploaded_file($f['tmp_name'], $dest)) { $errors[] = 'Failed to move uploaded file.'; }
                    }
                }
            }
        } else {
            $errors[] = 'No file selected for replacement.';
        }

        if (empty($errors)) {
            // update record: file, set technician_id if unassigned, updated_at
            $u = $conn->prepare('UPDATE lab_tests SET file = ?, technician_id = COALESCE(technician_id, ?), updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($u) { $u->bind_param('sii', $uploadName, $techId, $id); $u->execute(); $u->close(); }
            // notify patient if present
            if (!empty($r['patient_id'])) {
                $nid = (int)$r['patient_id'];
                $title = 'New Lab Report Uploaded';
                $message = 'A new report has been uploaded for lab test #' . $id . '.';
                $ins = $conn->prepare('INSERT INTO notifications (patient_id, title, message) VALUES (?, ?, ?)');
                if ($ins) { $ins->bind_param('iss', $nid, $title, $message); $ins->execute(); $ins->close(); }
            }
            $_SESSION['flash_success'] = 'Replacement file uploaded successfully.';
        } else {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }
    }
    // AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        } else {
            echo json_encode(['success' => true, 'message' => 'File uploaded', 'file' => $uploadName, 'id' => $id]);
        }
        exit();
    }
    header('Location: lab_technician_dashboard.php'); exit();
}

// fetch assigned tests
// Determine whether technician assignment is in effect (logged-in tech)
$assignedResult = null;
if ($techId > 0) {
    // Show tests assigned to this technician plus unassigned pending tests so techs can pick them up
    $sql = 'SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE (l.technician_id = ? OR (l.technician_id IS NULL AND l.status = "Pending")) ORDER BY FIELD(l.status, "Pending", "Completed"), l.report_date ASC, l.id ASC';
    $stmt = $conn->prepare($sql);
    if ($stmt) { $stmt->bind_param('i', $techId); $stmt->execute(); $assignedResult = $stmt->get_result(); $stmt->close(); }
    // if no rows found, fall back to showing any pending tests so the technician sees work to pick up
    if ($assignedResult && $assignedResult->num_rows === 0) {
        $stmt2 = $conn->prepare("SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.status = 'Pending' ORDER BY l.report_date ASC, l.id ASC");
        if ($stmt2) { $stmt2->execute(); $assignedResult = $stmt2->get_result(); $stmt2->close(); }
    }
} else {
    // no technician logged in: show all pending tests
    $stmt = $conn->prepare("SELECT l.*, p.name as patient_name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.status = 'Pending' ORDER BY l.report_date ASC, l.id ASC");
    if ($stmt) { $stmt->execute(); $assignedResult = $stmt->get_result(); $stmt->close(); }
}

?>
<!DOCTYPE html>
<html>
<head>
<title>My Lab Tests</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
.container{max-width:1100px;margin:20px auto}
.card{background:#fff;padding:18px;border-radius:10px}
.stats{display:flex;gap:12px;margin-bottom:16px}
.stat{flex:1;padding:12px;border-radius:10px;background:linear-gradient(180deg,#f8fbff,#eef6ff);box-shadow:0 6px 18px rgba(2,6,23,.06);display:flex;align-items:center;justify-content:space-between}
.stat .value{font-size:22px;font-weight:700}
.badge-status{padding:6px 10px;border-radius:12px;color:#fff;font-weight:600}
.badge-pending{background:#ffb020}
.badge-completed{background:#27ae60}
.thumb{width:54px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #eee}
.actions a,.actions button{margin-right:6px}
.modal-backdrop{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center}
.modal{background:#fff;padding:18px;border-radius:10px;max-width:540px;width:100%}
</style>
<script>
function openReplaceModal(id){
    document.getElementById('replace_test_id').value = id;
    document.getElementById('modalBackdrop').style.display = 'flex';
}
function closeReplaceModal(){
    document.getElementById('modalBackdrop').style.display = 'none';
}
</script>
</head>
<body>
<div class="container">
    <h2>Assigned Lab Tests</h2>
    <div class="card">
        <?php
        // stats
        $totalAssigned = 0; $totalPending = 0; $totalCompleted = 0;
        $c1 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE technician_id = " . intval($techId));
        $totalAssigned = $c1 ? (int)$c1->fetch_assoc()['c'] : 0;
        $c2 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE status = 'Pending'");
        $totalPending = $c2 ? (int)$c2->fetch_assoc()['c'] : 0;
        $c3 = $conn->query("SELECT COUNT(*) AS c FROM lab_tests WHERE status = 'Completed'");
        $totalCompleted = $c3 ? (int)$c3->fetch_assoc()['c'] : 0;
        ?>
        <div class="stats">
            <div class="stat"><div><div style="color:#334e68">Assigned</div><div class="value"><?php echo $totalAssigned; ?></div></div><div><svg width="28" height="28" fill="none" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4z" fill="#2c7be5"></path><path d="M6 20v-1c0-2.21 3.58-4 6-4s6 1.79 6 4v1H6z" fill="#2c7be5"></path></svg></div></div>
            <div class="stat"><div><div style="color:#334e68">Pending</div><div class="value"><?php echo $totalPending; ?></div></div><div><svg width="28" height="28" viewBox="0 0 24 24"><path d="M12 3v10l3 3" stroke="#ffb020" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"></path><circle cx="12" cy="12" r="9" stroke="#ffb020" stroke-width="1.5" fill="none"></circle></svg></div></div>
            <div class="stat"><div><div style="color:#334e68">Completed</div><div class="value"><?php echo $totalCompleted; ?></div></div><div><svg width="28" height="28" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" stroke="#27ae60" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"></path></svg></div></div>
        </div>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background:#e6ffed;padding:10px;border-radius:6px;margin-bottom:12px;color:#02733a">
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background:#ffe6e6;padding:10px;border-radius:6px;margin-bottom:12px;color:#a00">
                <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <table width="100%" class="table-modern">
            <thead><tr><th>ID</th><th>Patient</th><th>Test</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($assignedResult && $assignedResult->num_rows>0): while ($row = $assignedResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['test_type']); ?></td>
                    <td><?php echo $row['status'] === 'Completed' ? '<span class="badge-status badge-completed">Completed</span>' : '<span class="badge-status badge-pending">Pending</span>'; ?></td>
                    <td><?php echo htmlspecialchars($row['report_date']); ?></td>
                    <td>
                        <a class="btn btn-view" href="lab_report_view.php?id=<?php echo (int)$row['id']; ?>">View</a>
                        <a class="btn btn-edit" href="lab_report_edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <?php if (!empty($row['file'])): 
                            $ext = strtolower(pathinfo($row['file'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($row['file']); ?>" class="thumb" alt="thumb">
                            <?php else: ?>
                                <a href="uploads/<?php echo htmlspecialchars($row['file']); ?>" target="_blank">Download</a>
                            <?php endif; 
                        endif; ?>
                        <div class="actions">
                            <button type="button" class="btn btn-replace" onclick="openReplaceModal(<?php echo (int)$row['id']; ?>)">Replace</button>
                        </div>
                        <?php if ($row['status'] !== 'Completed'): ?>
                            <button class="btn btn-complete" onclick="markCompleted(<?php echo (int)$row['id']; ?>)">Mark Completed</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">No assigned tests.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<!-- Replace Modal -->
<div id="modalBackdrop" class="modal-backdrop">
    <div class="modal" role="dialog">
        <h3>Replace Lab Report</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" id="replace_test_id" name="id" value="">
            <div style="margin:12px 0">
                <label>Choose file (PDF, JPG, PNG) — max 5MB</label>
                <input type="file" name="replacement" accept=".pdf,image/*" required>
            </div>
            <div style="text-align:right">
                <button type="button" onclick="closeReplaceModal()">Cancel</button>
                <button type="submit" name="replace_file">Upload</button>
            </div>
        </form>
    </div>
</div>
<!-- Toast -->
<div id="toast" style="position:fixed;right:20px;bottom:20px;display:none;background:#333;color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 6px 18px rgba(2,6,23,.18)"></div>
<div id="spinner" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.95);padding:16px;border-radius:10px;box-shadow:0 8px 30px rgba(2,6,23,.2)"><div style="width:36px;height:36px;border-radius:50%;border:4px solid #eee;border-top-color:#2c7be5;animation:spin 1s linear infinite;margin:0 auto"></div><div style="text-align:center;margin-top:8px;color:#334e68">Processing...</div></div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
// AJAX upload with progress
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('modalBackdrop');
    var modalForm = modal.querySelector('form');
    modalForm.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(modalForm);
        fd.append('replace_file', '1');
        var file = modalForm.querySelector('input[type=file]').files[0];
        if (!file) { showToast('Please choose a file'); return; }
        var allowed = ['pdf','jpg','jpeg','png'];
        var ext = file.name.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) === -1) { showToast('Invalid file type'); return; }
        if (file.size > 5 * 1024 * 1024) { showToast('File too large (max 5MB)'); return; }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'lab_technician_dashboard.php');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', function(ev){
            if (ev.lengthComputable) {
                var pct = Math.round(ev.loaded / ev.total * 100);
                showToast('Uploading ' + pct + '%');
            }
        });
        xhr.onreadystatechange = function(){
            if (xhr.readyState === 4) {
                hideSpinner();
                try {
                    var res = JSON.parse(xhr.responseText);
                } catch(e) { showToast('Server error'); closeReplaceModal(); return; }
                if (res.success) {
                    showToast(res.message || 'Upload complete');
                    // update UI: reload page to reflect changes
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    showToast(res.message || 'Upload failed');
                }
                closeReplaceModal();
            }
        };
        showSpinner();
        xhr.send(fd);
    });
});

function markCompleted(id){
    if (!confirm('Mark this test as completed?')) return;
    var fd = new FormData(); fd.append('id', id); fd.append('mark_completed', '1');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'lab_technician_dashboard.php');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function(){
        if (xhr.readyState === 4) {
            try { var res = JSON.parse(xhr.responseText); } catch(e){ showToast('Server error'); return; }
            if (res.success) { showToast(res.message || 'Marked completed'); setTimeout(function(){ location.reload(); }, 800); }
            else showToast(res.message || 'Failed');
        }
    };
    xhr.send(fd);
    showSpinner();
}

function showToast(msg){ var t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(window._toastTimer); window._toastTimer = setTimeout(function(){ t.style.display='none'; }, 3500); }
function showSpinner(){ document.getElementById('spinner').style.display = 'block'; }
function hideSpinner(){ document.getElementById('spinner').style.display = 'none'; }
</script>
</body>
</html>