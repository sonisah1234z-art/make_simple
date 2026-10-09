<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'leave_management.php';

ensure_leave_management_schema($conn);

$staffContext = get_leave_staff_context();
$role = $staffContext['role'];
$staffId = $staffContext['id'];
$staffName = $staffContext['name'];
if (!is_staff_role($role) || $staffId <= 0) {
    header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leaveType = trim($_POST['leave_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $documentPath = null;

    if ($leaveType === '' || $startDate === '' || $endDate === '' || $reason === '') {
        $message = 'Please fill in all required fields.';
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $message = 'End date cannot be earlier than start date.';
    } elseif (leave_request_overlaps($conn, $role, $staffId, $startDate, $endDate)) {
        $message = 'You already have a pending or approved leave request that overlaps with the selected dates.';
    } else {
        if (!empty($_FILES['supporting_document']['name'])) {
            $uploadDir = __DIR__ . '/uploads/leave_documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['supporting_document']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['supporting_document']['tmp_name'], $targetPath)) {
                $documentPath = 'uploads/leave_documents/' . $fileName;
            } else {
                $message = 'Unable to upload the supporting document.';
            }
        }

        if ($message === '') {
            $stmt = $conn->prepare("INSERT INTO leave_requests (staff_role, staff_id, staff_name, leave_type, start_date, end_date, reason, document_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            if ($stmt) {
                $stmt->bind_param('sissssss', $role, $staffId, $staffName, $leaveType, $startDate, $endDate, $reason, $documentPath);
                if ($stmt->execute()) {
                    $message = 'Leave Request Submitted Successfully';
                } else {
                    $message = 'Unable to save your leave request.';
                }
                $stmt->close();
            } else {
                $message = 'Unable to prepare your leave request.';
            }
        }
    }
}

$requests = [];
$stmt = $conn->prepare('SELECT * FROM leave_requests WHERE staff_role = ? AND staff_id = ? ORDER BY start_date DESC, id DESC');
if ($stmt) {
    $stmt->bind_param('si', $role, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave Request</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
:root{
    --primary:#2563eb;
    --primary-dark:#153b8a;
    --accent:#0f766e;
    --surface:#ffffff;
    --surface-soft:#f8fbff;
    --border:#dce7f3;
    --text:#14213d;
    --muted:#64748b;
    --pending:#f59e0b;
    --approved:#10b981;
    --rejected:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:linear-gradient(135deg,#eef6ff 0%,#f8fbff 100%);color:var(--text)}
.container{max-width:1180px;margin:24px auto;padding:0 20px}
.page-shell{display:flex;flex-direction:column;gap:20px}
.hero-card{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);border-radius:24px;box-shadow:0 22px 48px rgba(15,23,42,.18);overflow:hidden}
.hero-banner{display:flex;align-items:center;gap:16px;padding:28px 30px;color:#fff}
.hero-icon{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-size:28px;backdrop-filter:blur(6px)}
.eyebrow{margin:0 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.22em;opacity:.75}
.hero-banner h1{margin:0;font-size:28px;font-weight:700}
.hero-banner p{margin:6px 0 0;opacity:.9}
.hero-body{display:grid;grid-template-columns:1.05fr .95fr;gap:20px;padding:24px;background:rgba(255,255,255,.96)}
.form-card,.history-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:22px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.section-heading{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.section-icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:grid;place-items:center;font-size:20px}
.section-heading h3{margin:0;font-size:20px}
.section-heading p{margin:2px 0 0;color:var(--muted);font-size:13px}
.message-box{padding:12px 14px;border-radius:12px;margin-bottom:14px;font-size:14px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.message-box.error{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.leave-form{display:grid;gap:14px}
.form-grid{display:grid;grid-template-columns:repeat(3, minmax(0,1fr));gap:12px}
.field-group{display:flex;flex-direction:column;gap:8px}
.field-group label{font-weight:600;font-size:14px;color:#334155}
.field-shell{position:relative}
.field-shell select,.field-shell input,.field-shell textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#fbfdff;color:var(--text);font-size:14px;transition:all .2s ease;outline:none}
.field-shell select:focus,.field-shell input:focus,.field-shell textarea:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.field-shell textarea{min-height:120px;resize:vertical}
.upload-zone{border:1.5px dashed #9fb9d8;border-radius:16px;padding:16px;text-align:center;background:linear-gradient(180deg,#f9fcff 0%,#f3f8ff 100%);cursor:pointer;transition:all .2s ease}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--primary);transform:translateY(-1px);box-shadow:0 10px 20px rgba(37,99,235,.12)}
.upload-zone.has-file{border-color:var(--accent);background:linear-gradient(180deg,#f1fffb 0%,#edfcf8 100%)}
.upload-icon{font-size:24px;margin-bottom:6px}
.upload-copy strong{display:block;font-size:15px;color:#16324f}
.upload-copy p{margin:4px 0 8px;color:var(--muted);font-size:13px}
.upload-filename{display:inline-block;padding:7px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px;color:var(--primary);font-weight:600}
.submit-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 18px;border:0;border-radius:999px;background:linear-gradient(135deg,var(--primary),#4f8cff);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:transform .2s ease, box-shadow .2s ease}
.submit-btn:hover{transform:translateY(-1px);box-shadow:0 12px 22px rgba(37,99,235,.25)}
.submit-btn.is-loading{opacity:.9;pointer-events:none}
.btn-loader{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .9s linear infinite}
.submit-btn.is-loading .btn-loader{display:inline-block}
.submit-btn.is-loading .btn-label{opacity:.9}
@keyframes spin{to{transform:rotate(360deg)}}
.history-list{display:flex;flex-direction:column;gap:12px}
.history-item{padding:14px 15px;border:1px solid #e6eef8;border-radius:14px;background:linear-gradient(180deg,#fff 0%,#f9fcff 100%)}
.history-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.history-title{font-weight:700;color:#12233f}
.history-dates{margin-top:4px;font-size:13px;color:var(--muted)}
.status-badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.status-pending{background:#fff7ed;color:var(--pending)}
.status-approved{background:#ecfdf5;color:var(--approved)}
.status-rejected{background:#fef2f2;color:var(--rejected)}
.history-meta{margin-top:10px;font-size:13px;color:var(--muted)}
.history-meta strong{display:block;margin-bottom:3px;color:#274060}
.empty-state{padding:24px;border-radius:16px;border:1px dashed #cbdcf0;background:linear-gradient(180deg,#fcfeff 0%,#f5faff 100%);text-align:center}
.empty-illustration{font-size:42px;margin-bottom:10px}
.empty-state h4{margin:0 0 6px;color:#12304e}
.empty-state p{margin:0;color:var(--muted)}
@media (max-width: 900px){.hero-body{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}}
@media (max-width: 640px){.hero-banner{flex-direction:column;align-items:flex-start}.container{padding:0 14px}.form-card,.history-card{padding:18px}.hero-banner h1{font-size:24px}}
</style>
</head>
<body>
<div class="container">
    <div class="page-shell">
        <div class="hero-card">
            <div class="hero-banner">
                <div class="hero-icon">🏥</div>
                <div>
                    <p class="eyebrow">Staff Portal</p>
                    <h1>Submit Leave Request</h1>
                    <p>Manage your time-off request with a polished, professional experience.</p>
                </div>
            </div>
            <div class="hero-body">
                <div class="form-card">
                    <div class="section-heading">
                        <div class="section-icon">📝</div>
                        <div>
                            <h3>Leave Application</h3>
                            <p>Apply for leave and track the status in real time.</p>
                        </div>
                    </div>
                    <?php if ($message !== ''): ?>
                        <div class="message-box<?php echo strpos($message, 'Please') === 0 || strpos($message, 'Unable') === 0 || strpos($message, 'End date') === 0 ? ' error' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <form id="leaveForm" class="leave-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="submit_leave" value="1">
                        <div class="form-grid">
                            <div class="field-group">
                                <label for="leave_type">Leave Type</label>
                                <div class="field-shell">
                                    <select id="leave_type" name="leave_type" required>
                                        <option value="">Select</option>
                                        <option>Annual Leave</option>
                                        <option>Sick Leave</option>
                                        <option>Personal Leave</option>
                                        <option>Emergency Leave</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="start_date">Start Date</label>
                                <div class="field-shell">
                                    <input id="start_date" type="date" name="start_date" required>
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="end_date">End Date</label>
                                <div class="field-shell">
                                    <input id="end_date" type="date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="reason">Reason</label>
                            <div class="field-shell">
                                <textarea id="reason" name="reason" rows="4" required placeholder="Share a brief reason for your leave request."></textarea>
                            </div>
                        </div>
                        <div class="field-group">
                            <label>Supporting Document</label>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="supporting_document" id="supporting_document" hidden>
                                <div class="upload-icon">📎</div>
                                <div class="upload-copy">
                                    <strong>Drag and drop your document</strong>
                                    <p>or click to browse from your device</p>
                                </div>
                                <span class="upload-filename" id="uploadFilename">No file selected</span>
                            </div>
                        </div>
                        <button class="submit-btn" type="submit" id="submitBtn">
                            <span class="btn-label">Submit Leave Request</span>
                            <span class="btn-loader"></span>
                        </button>
                    </form>
                </div>

                <div class="history-card">
                    <div class="section-heading">
                        <div class="section-icon">📅</div>
                        <div>
                            <h3>My Leave History</h3>
                            <p>Review your past requests and statuses.</p>
                        </div>
                    </div>
                    <?php if (empty($requests)): ?>
                        <div class="empty-state">
                            <div class="empty-illustration">🧾</div>
                            <h4>No leave requests yet</h4>
                            <p>Your submitted leave requests will appear here with their review status.</p>
                        </div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($requests as $request): ?>
                                <div class="history-item">
                                    <div class="history-top">
                                        <div>
                                            <div class="history-title">#<?php echo (int)$request['id']; ?> · <?php echo htmlspecialchars($request['leave_type']); ?></div>
                                            <div class="history-dates"><?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></div>
                                        </div>
                                        <span class="status-badge status-<?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                    </div>
                                    <div class="history-meta">
                                        <strong>Reason</strong>
                                        <div><?php echo htmlspecialchars($request['reason']); ?></div>
                                    </div>
                                    <?php if (!empty($request['remarks'])): ?>
                                        <div class="history-meta">
                                            <strong>Remarks</strong>
                                            <div><?php echo htmlspecialchars($request['remarks']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const input = document.getElementById('supporting_document');
    const zone = document.getElementById('uploadZone');
    const filename = document.getElementById('uploadFilename');
    if (input && zone && filename) {
        const updateLabel = function () {
            const file = input.files && input.files[0];
            const label = file ? file.name : 'No file selected';
            filename.textContent = label;
            zone.classList.toggle('has-file', !!file);
        };
        input.addEventListener('change', updateLabel);
        zone.addEventListener('click', function () {
            input.click();
        });
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('dragover');
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
                input.files = event.dataTransfer.files;
                updateLabel();
            }
        });
    }

    const form = document.getElementById('leaveForm');
    const button = document.getElementById('submitBtn');
    if (form && button) {
        form.addEventListener('submit', function () {
            button.classList.add('is-loading');
            button.disabled = true;
        });
    }
})();
</script>
</body>
</html>
