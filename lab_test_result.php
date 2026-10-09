<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';
ensure_lab_module_tables($conn);

$message = '';
$messageType = '';
$technicianName = $_SESSION['lab_technician_name'] ?? 'Lab Technician';
$techId = (int)($_SESSION['lab_technician_id'] ?? 0);
$selectedRequestId = (int)($_GET['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $parameterNames = $_POST['parameter_name'] ?? [];
    $parameterResults = $_POST['result_value'] ?? [];
    $parameterUnits = $_POST['unit'] ?? [];
    $referenceRanges = $_POST['reference_range'] ?? [];
    $resultStatuses = $_POST['result_status'] ?? [];
    $submittedSampleType = trim((string)($_POST['sample_type'] ?? ''));
    $submittedCollectionDateTime = trim((string)($_POST['collection_datetime'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $errors = [];
    $uploadedFile = null;
    $requestData = null;

    $requestStmt = $conn->prepare('SELECT ltr.doctor_id, ltr.patient_id, ltr.department, ltr.test_name, COALESCE(ltr.sample_type, sc.sample_type) AS sample_type, COALESCE(ltr.collection_date, DATE(sc.collection_datetime)) AS collection_date, sc.collection_datetime, d.name AS doctor_name, p.name AS patient_name FROM lab_test_requests ltr LEFT JOIN patients p ON p.id = ltr.patient_id LEFT JOIN doctors d ON d.id = ltr.doctor_id LEFT JOIN lab_sample_collections sc ON sc.patient_id = ltr.patient_id AND sc.test_name = ltr.test_name WHERE ltr.id = ? AND ltr.assigned_to = ? AND (ltr.status IN (\'Assigned\', \'Processing\', \'In Progress\', \'Sample Collected\', \'Result Entered\') OR (ltr.status = \'Completed\' AND NOT EXISTS (SELECT 1 FROM lab_reports existing_report WHERE existing_report.request_id = ltr.id))) LIMIT 1');
    if ($requestStmt) {
        $requestStmt->bind_param('ii', $requestId, $techId);
        $requestStmt->execute();
        $requestData = $requestStmt->get_result()->fetch_assoc();
        $requestStmt->close();
    }
    if (empty($requestData)) { $errors[] = 'This lab request is not assigned to your account.'; }

    $parameters = [];
    $parameterCount = max(count($parameterNames), count($parameterResults));
    for ($index = 0; $index < $parameterCount; $index++) {
        $name = trim((string)($parameterNames[$index] ?? ''));
        $resultValue = trim((string)($parameterResults[$index] ?? ''));
        if ($name === '' && $resultValue === '') {
            continue;
        }
        if ($name === '' || $resultValue === '') {
            $errors[] = 'Each result row needs both a test parameter and result.';
            continue;
        }
        $status = $resultStatuses[$index] ?? 'Normal';
        if (!in_array($status, ['Normal', 'High', 'Low', 'Critical'], true)) {
            $status = 'Normal';
        }
        $parameters[] = [
            'parameter' => $name,
            'result' => $resultValue,
            'unit' => trim((string)($parameterUnits[$index] ?? '')),
            'reference_range' => trim((string)($referenceRanges[$index] ?? '')),
            'status' => $status
        ];
    }
    if (empty($parameters)) { $errors[] = 'Please enter at least one test parameter and result.'; }

    if (empty($errors)) {
        $parametersJson = json_encode(['parameters' => $parameters, 'summary' => implode('; ', array_map(static function ($parameter) {
            return $parameter['parameter'] . ': ' . $parameter['result'] . ($parameter['unit'] !== '' ? ' ' . $parameter['unit'] : '');
        }, $parameters))], JSON_UNESCAPED_SLASHES);
        $existingStmt = $conn->prepare('SELECT id FROM lab_reports WHERE request_id = ? LIMIT 1');
        $existingReport = null;
        if ($existingStmt) {
            $existingStmt->bind_param('i', $requestId);
            $existingStmt->execute();
            $existingReport = $existingStmt->get_result()->fetch_assoc();
            $existingStmt->close();
        }

        if ($existingReport) {
            $message = 'A report already exists for this laboratory test.';
            $messageType = 'warning';
        } else {
            $conn->begin_transaction();
            $status = 'Result Entered';
            $resultSaved = false;
            $stmt = $conn->prepare('INSERT INTO lab_test_results (request_id, parameters_json, remarks, technician_name, completed_date, status) VALUES (?, ?, ?, ?, CURDATE(), ?)');
            if ($stmt) {
                $stmt->bind_param('issss', $requestId, $parametersJson, $remarks, $technicianName, $status);
                $resultSaved = $stmt->execute();
                $stmt->close();
            }

            $reportSaved = false;
            if ($resultSaved) {
                $reportId = 'LAB-' . date('Ymd') . '-' . $requestId;
                $reportStmt = $conn->prepare('INSERT INTO lab_reports (request_id, report_id, patient_id, doctor_id, technician_id, department, test_name, sample_type, collection_date, parameters_json, remarks, file, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($reportStmt) {
                    $sampleType = $submittedSampleType !== '' ? $submittedSampleType : ($requestData['sample_type'] ?? null);
                    $collectionDate = $submittedCollectionDateTime !== '' ? substr($submittedCollectionDateTime, 0, 10) : ($requestData['collection_date'] ?? null);
                    $reportStmt->bind_param('isiiissssssss', $requestId, $reportId, $requestData['patient_id'], $requestData['doctor_id'], $techId, $requestData['department'], $requestData['test_name'], $sampleType, $collectionDate, $parametersJson, $remarks, $uploadedFile, $status);
                    $reportSaved = $reportStmt->execute();
                    $reportStmt->close();
                }
            }

            if (!$resultSaved || !$reportSaved) {
                $conn->rollback();
                $message = !$resultSaved ? 'Unable to save the test result.' : 'Unable to save the uploaded report.';
                $messageType = 'danger';
            } else {
                $conn->commit();
                $update = $conn->prepare('UPDATE lab_test_requests SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ? LIMIT 1');
                if ($update) {
                    $update->bind_param('sii', $status, $requestId, $techId);
                    $update->execute();
                    $update->close();
                }
                $message = !empty($requestData['doctor_id'])
                    ? 'Results entered. Mark the report as Completed to send it to the assigned doctor.'
                    : 'Results entered. The report has no assigned doctor yet.';
                $messageType = 'success';
            }
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'danger';
    }
}

$requests = [];
$selectedRequest = null;
$requestStmt = $conn->prepare('SELECT ltr.id, ltr.test_name, ltr.department, COALESCE(ltr.sample_type, sc.sample_type) AS sample_type, COALESCE(ltr.collection_date, DATE(sc.collection_datetime)) AS collection_date, sc.collection_datetime, ltr.status, p.id AS patient_id, p.name AS patient_name, d.name AS doctor_name FROM lab_test_requests ltr LEFT JOIN patients p ON p.id = ltr.patient_id LEFT JOIN doctors d ON d.id = ltr.doctor_id LEFT JOIN lab_sample_collections sc ON sc.patient_id = ltr.patient_id AND sc.test_name = ltr.test_name WHERE ltr.assigned_to = ? AND (ltr.status IN (\'Assigned\', \'Processing\', \'In Progress\', \'Sample Collected\', \'Result Entered\') OR (ltr.status = \'Completed\' AND NOT EXISTS (SELECT 1 FROM lab_reports existing_report WHERE existing_report.request_id = ltr.id))) ORDER BY ltr.request_date DESC, ltr.id DESC');
if ($requestStmt) {
    $requestStmt->bind_param('i', $techId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    while ($row = $requestResult->fetch_assoc()) {
        $requests[] = $row;
        if ($selectedRequestId === (int)$row['id']) {
            $selectedRequest = $row;
        }
    }
    $requestStmt->close();
}

if (!$selectedRequest && count($requests) === 1) {
    $selectedRequest = $requests[0];
    $selectedRequestId = (int)$selectedRequest['id'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $selectedRequest && $selectedRequest['status'] === 'Assigned') {
    $processing = 'Processing';
    $processingStmt = $conn->prepare('UPDATE lab_test_requests SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ? AND status = \'Assigned\' LIMIT 1');
    if ($processingStmt) { $processingStmt->bind_param('sii', $processing, $selectedRequestId, $techId); $processingStmt->execute(); $processingStmt->close(); }
}

require_once 'header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="hero-badge"><i class="bi bi-file-earmark-medical"></i> Add Test Result</span>
            <h2 class="mt-2 mb-1">Upload completed lab report</h2>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="lab-card mb-4">
        <h4 class="mb-3">Laboratory Report Entry</h4>
        <form method="post" class="row g-3" id="reportForm">
            <div class="col-12">
                <label class="form-label">Assigned Test</label>
                <select class="form-select" name="request_id" id="requestSelect" required>
                    <option value="">Select assigned test</option>
                    <?php foreach ($requests as $request): ?>
                        <option value="<?php echo (int)$request['id']; ?>"
                            data-patient="<?php echo htmlspecialchars($request['patient_name'] . ' (#' . $request['patient_id'] . ')', ENT_QUOTES); ?>"
                            data-patient-id="<?php echo (int)$request['patient_id']; ?>"
                            data-doctor="<?php echo htmlspecialchars($request['doctor_name'] ?? 'Not assigned', ENT_QUOTES); ?>"
                            data-department="<?php echo htmlspecialchars($request['department'] ?? 'Not specified', ENT_QUOTES); ?>"
                            data-test="<?php echo htmlspecialchars($request['test_name'] ?? '', ENT_QUOTES); ?>"
                            data-sample="<?php echo htmlspecialchars($request['sample_type'] ?? 'Not recorded', ENT_QUOTES); ?>"
                            data-collection="<?php echo htmlspecialchars($request['collection_datetime'] ?? ($request['collection_date'] ?? 'Not recorded'), ENT_QUOTES); ?>"
                            <?php echo $selectedRequestId === (int)$request['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($request['patient_name'] . ' - ' . $request['test_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4"><label class="form-label">Patient Name</label><input class="form-control" id="patientDisplay" value="<?php echo htmlspecialchars($selectedRequest['patient_name'] ?? '', ENT_QUOTES); ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Patient ID</label><input class="form-control" id="patientIdDisplay" value="<?php echo htmlspecialchars(isset($selectedRequest['patient_id']) ? (string)$selectedRequest['patient_id'] : '', ENT_QUOTES); ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Doctor</label><input class="form-control" id="doctorDisplay" value="<?php echo htmlspecialchars($selectedRequest['doctor_name'] ?? '', ENT_QUOTES); ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Department</label><input class="form-control" id="departmentDisplay" value="<?php echo htmlspecialchars($selectedRequest['department'] ?? '', ENT_QUOTES); ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Test Name</label><input class="form-control" id="testDisplay" value="<?php echo htmlspecialchars($selectedRequest['test_name'] ?? '', ENT_QUOTES); ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Sample Type</label><input class="form-control" name="sample_type" id="sampleDisplay" value="<?php echo htmlspecialchars($selectedRequest['sample_type'] ?? '', ENT_QUOTES); ?>" placeholder="e.g. Blood, Urine"></div>
            <div class="col-md-4"><label class="form-label">Sample Collection Date/Time</label><input class="form-control" type="datetime-local" name="collection_datetime" id="collectionDisplay" value="<?php echo htmlspecialchars(isset($selectedRequest['collection_datetime']) && $selectedRequest['collection_datetime'] !== '' ? date('Y-m-d\\TH:i', strtotime($selectedRequest['collection_datetime'])) : '', ENT_QUOTES); ?>"></div>

            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Test Results</h5>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="addParameter">Add Parameter</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="parameterTable">
                        <thead><tr><th>Test Parameter</th><th>Result</th><th>Unit</th><th>Reference Range</th><th>Normal/High/Low/Critical</th><th></th></tr></thead>
                        <tbody>
                            <tr class="parameter-row">
                                <td><input class="form-control" name="parameter_name[]" required></td>
                                <td><input class="form-control" name="result_value[]" required></td>
                                <td><input class="form-control" name="unit[]"></td>
                                <td><input class="form-control" name="reference_range[]"></td>
                                <td><select class="form-select" name="result_status[]"><option>Normal</option><option>High</option><option>Low</option><option>Critical</option></select></td>
                                <td><button class="btn btn-sm btn-outline-danger remove-parameter" type="button">Remove</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Technician Remarks</label>
                <textarea class="form-control" name="remarks" rows="2" placeholder="Optional remarks"></textarea>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Complete Report &amp; Send for Verification</button></div>
        </form>
    </div>

    <div class="lab-card">
        <h4 class="mb-3">Requests Ready for Results</h4>
        <div class="table-panel">
            <table class="table table-hover align-middle">
                <thead><tr><th>Patient</th><th>Test</th><th>Department</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['department']); ?></td>
                            <td><span class="status-pill status-sample-collected"><?php echo htmlspecialchars($request['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
const requestSelect = document.getElementById('requestSelect');
const displayFields = {
    patient: document.getElementById('patientDisplay'),
    patientId: document.getElementById('patientIdDisplay'),
    doctor: document.getElementById('doctorDisplay'),
    department: document.getElementById('departmentDisplay'),
    test: document.getElementById('testDisplay'),
    sample: document.getElementById('sampleDisplay'),
    collection: document.getElementById('collectionDisplay')
};
function updateRequestDetails() {
    const option = requestSelect.options[requestSelect.selectedIndex];
    if (!option || !option.dataset || !option.value) {
        Object.keys(displayFields).forEach(function (key) { displayFields[key].value = ''; });
        return;
    }
    displayFields.patient.value = option.dataset.patient || '';
    displayFields.patientId.value = option.dataset.patientId || '';
    displayFields.doctor.value = option.dataset.doctor || '';
    displayFields.department.value = option.dataset.department || '';
    displayFields.test.value = option.dataset.test || '';
    displayFields.sample.value = option.dataset.sample === 'Not recorded' ? '' : (option.dataset.sample || '');
    const collectionValue = option.dataset.collection || '';
    displayFields.collection.value = collectionValue && collectionValue !== 'Not recorded'
        ? collectionValue.replace(' ', 'T').slice(0, 16)
        : '';
}
requestSelect.addEventListener('change', updateRequestDetails);
updateRequestDetails();

document.getElementById('addParameter').addEventListener('click', function () {
    const template = document.querySelector('.parameter-row').cloneNode(true);
    template.querySelectorAll('input').forEach(function (input) { input.value = ''; });
    template.querySelector('select').value = 'Normal';
    document.querySelector('#parameterTable tbody').appendChild(template);
});
document.getElementById('parameterTable').addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-parameter')) {
        const rows = document.querySelectorAll('.parameter-row');
        if (rows.length > 1) event.target.closest('tr').remove();
    }
});
</script>
<?php require_once 'footer.php'; ?>
