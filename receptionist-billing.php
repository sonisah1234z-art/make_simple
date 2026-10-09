<?php
require_once 'auth.php';
require_receptionist();
require_once 'db.php';
require_once 'billing_helpers.php';

ensure_billing_schema($conn);

function get_appointment_consultation_fee(mysqli $conn, ?int $appointmentId): string
{
    if ($appointmentId === null || $appointmentId <= 0) {
        return '0';
    }

    $stmt = $conn->prepare('SELECT COALESCE(d.consultation_fee, 0) AS consultation_fee FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? LIMIT 1');
    if (!$stmt) {
        return '0';
    }

    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['consultation_fee']) ? (string)number_format((float)$row['consultation_fee'], 2, '.', '') : '0';
}

$message = '';
$error = '';
$formData = [
    'patient_id' => '',
    'appointment_id' => '',
    'description' => '',
    'consultation_fee' => '0',
    'lab_test_fee' => '0',
    'medicine_charges' => '0',
    'room_charges' => '0',
    'other_charges' => '0',
    'discount' => '0',
    'tax' => '0',
    'amount_paid' => '0',
    'payment_method' => '',
    'payment_status' => 'Unpaid',
    'billing_date' => date('Y-m-d'),
];

$editingId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_billing') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $appointmentId = isset($_POST['appointment_id']) && $_POST['appointment_id'] !== '' ? (int)$_POST['appointment_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $billingDate = trim($_POST['billing_date'] ?? date('Y-m-d'));
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $paymentStatus = in_array($_POST['payment_status'] ?? 'Unpaid', ['Paid', 'Unpaid', 'Partial'], true) ? $_POST['payment_status'] : 'Unpaid';

        if ($patientId <= 0) {
            $error = 'Please select a patient.';
        } else {
            $consultationFeeValue = $appointmentId ? get_appointment_consultation_fee($conn, $appointmentId) : ($_POST['consultation_fee'] ?? '0');
            $formData = [
                'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
                'description' => $description,
                'consultation_fee' => $consultationFeeValue,
                'lab_test_fee' => $_POST['lab_test_fee'] ?? '0',
                'medicine_charges' => $_POST['medicine_charges'] ?? '0',
                'room_charges' => $_POST['room_charges'] ?? '0',
                'other_charges' => $_POST['other_charges'] ?? '0',
                'discount' => $_POST['discount'] ?? '0',
                'tax' => $_POST['tax'] ?? '0',
                'amount_paid' => $_POST['amount_paid'] ?? '0',
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'billing_date' => $billingDate,
            ];

            $totals = calculate_billing_totals($formData);
            $billNumber = generate_bill_number($conn);
            $stmt = $conn->prepare('INSERT INTO billings (appointment_id, patient_id, amount, description, status, bill_number, consultation_fee, lab_test_fee, medicine_charges, room_charges, other_charges, discount, tax, grand_total, amount_paid, remaining_balance, payment_method, payment_status, billing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $status = $paymentStatus === 'Paid' ? 'Paid' : ($paymentStatus === 'Partial' ? 'Partial' : 'Pending');
            $amount = (float)$totals['grand_total'];
            $stmt->bind_param('iidsssddddddddddsss', $appointmentId, $patientId, $amount, $description, $status, $billNumber, $formData['consultation_fee'], $formData['lab_test_fee'], $formData['medicine_charges'], $formData['room_charges'], $formData['other_charges'], $formData['discount'], $formData['tax'], $totals['grand_total'], $totals['amount_paid'], $totals['remaining_balance'], $paymentMethod, $paymentStatus, $billingDate);
            if ($stmt->execute()) {
                $message = 'Billing record created successfully.';
                $formData = [
                    'patient_id' => '',
                    'appointment_id' => '',
                    'description' => '',
                    'consultation_fee' => '0',
                    'lab_test_fee' => '0',
                    'medicine_charges' => '0',
                    'room_charges' => '0',
                    'other_charges' => '0',
                    'discount' => '0',
                    'tax' => '0',
                    'amount_paid' => '0',
                    'payment_method' => '',
                    'payment_status' => 'Unpaid',
                    'billing_date' => date('Y-m-d'),
                ];
            } else {
                $error = 'Unable to create billing record.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'update_billing') {
        $billingId = (int)($_POST['billing_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $appointmentId = isset($_POST['appointment_id']) && $_POST['appointment_id'] !== '' ? (int)$_POST['appointment_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $billingDate = trim($_POST['billing_date'] ?? date('Y-m-d'));
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $paymentStatus = in_array($_POST['payment_status'] ?? 'Unpaid', ['Paid', 'Unpaid', 'Partial'], true) ? $_POST['payment_status'] : 'Unpaid';

        if ($billingId > 0 && $patientId > 0) {
            $consultationFeeValue = $appointmentId ? get_appointment_consultation_fee($conn, $appointmentId) : ($_POST['consultation_fee'] ?? '0');
            $formData = [
                'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
                'description' => $description,
                'consultation_fee' => $consultationFeeValue,
                'lab_test_fee' => $_POST['lab_test_fee'] ?? '0',
                'medicine_charges' => $_POST['medicine_charges'] ?? '0',
                'room_charges' => $_POST['room_charges'] ?? '0',
                'other_charges' => $_POST['other_charges'] ?? '0',
                'discount' => $_POST['discount'] ?? '0',
                'tax' => $_POST['tax'] ?? '0',
                'amount_paid' => $_POST['amount_paid'] ?? '0',
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'billing_date' => $billingDate,
            ];
            $totals = calculate_billing_totals($formData);
            $status = $paymentStatus === 'Paid' ? 'Paid' : ($paymentStatus === 'Partial' ? 'Partial' : 'Pending');
            $existingBillNumberStmt = $conn->prepare('SELECT bill_number FROM billings WHERE id = ?');
            $existingBillNumberStmt->bind_param('i', $billingId);
            $existingBillNumberStmt->execute();
            $existingBillNumberRow = $existingBillNumberStmt->get_result()->fetch_assoc();
            $existingBillNumberStmt->close();
            $billNumber = trim((string)($existingBillNumberRow['bill_number'] ?? '')) !== '' ? $existingBillNumberRow['bill_number'] : generate_bill_number($conn);
            $stmt = $conn->prepare('UPDATE billings SET appointment_id = ?, patient_id = ?, amount = ?, description = ?, status = ?, bill_number = ?, consultation_fee = ?, lab_test_fee = ?, medicine_charges = ?, room_charges = ?, other_charges = ?, discount = ?, tax = ?, grand_total = ?, amount_paid = ?, remaining_balance = ?, payment_method = ?, payment_status = ?, billing_date = ? WHERE id = ?');
            $stmt->bind_param('iidsssddddddddddsssi', $appointmentId, $patientId, $totals['grand_total'], $description, $status, $billNumber, $formData['consultation_fee'], $formData['lab_test_fee'], $formData['medicine_charges'], $formData['room_charges'], $formData['other_charges'], $formData['discount'], $formData['tax'], $totals['grand_total'], $totals['amount_paid'], $totals['remaining_balance'], $paymentMethod, $paymentStatus, $billingDate, $billingId);
            if ($stmt->execute()) {
                $message = 'Billing record updated successfully.';
            } else {
                $error = 'Unable to update billing record.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete_billing') {
        $billingId = (int)($_POST['billing_id'] ?? 0);
        if ($billingId > 0) {
            $stmt = $conn->prepare('DELETE FROM billings WHERE id = ?');
            $stmt->bind_param('i', $billingId);
            if ($stmt->execute()) {
                $message = 'Billing record deleted successfully.';
            } else {
                $error = 'Unable to delete billing record.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $editingId = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT id, patient_id, appointment_id, description, consultation_fee, lab_test_fee, medicine_charges, room_charges, other_charges, discount, tax, grand_total, amount_paid, remaining_balance, payment_method, payment_status, billing_date, bill_number FROM billings WHERE id = ?');
    $stmt->bind_param('i', $editingId);
    $stmt->execute();
    $editingRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editingRow) {
        $formData = [
            'patient_id' => (string)($editingRow['patient_id'] ?? ''),
            'appointment_id' => (string)($editingRow['appointment_id'] ?? ''),
            'description' => $editingRow['description'] ?? '',
            'consultation_fee' => (string)($editingRow['consultation_fee'] ?? '0'),
            'lab_test_fee' => (string)($editingRow['lab_test_fee'] ?? '0'),
            'medicine_charges' => (string)($editingRow['medicine_charges'] ?? '0'),
            'room_charges' => (string)($editingRow['room_charges'] ?? '0'),
            'other_charges' => (string)($editingRow['other_charges'] ?? '0'),
            'discount' => (string)($editingRow['discount'] ?? '0'),
            'tax' => (string)($editingRow['tax'] ?? '0'),
            'amount_paid' => (string)($editingRow['amount_paid'] ?? '0'),
            'payment_method' => $editingRow['payment_method'] ?? '',
            'payment_status' => $editingRow['payment_status'] ?? 'Unpaid',
            'billing_date' => $editingRow['billing_date'] ?? date('Y-m-d'),
        ];
    }
}

$patients = [];
$stmt = $conn->prepare('SELECT id, name, phone, email FROM patients ORDER BY name');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}
$stmt->close();

$appointments = [];
$stmt = $conn->prepare('SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.status, d.name AS doctor_name, COALESCE(d.consultation_fee, 0) AS consultation_fee FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

$billings = [];
$stmt = $conn->prepare('SELECT b.id, b.bill_number, b.billing_date, b.grand_total, b.amount_paid, b.remaining_balance, b.payment_status, p.name AS patient_name, b.created_at FROM billings b JOIN patients p ON b.patient_id = p.id ORDER BY b.created_at DESC');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $billings[] = $row;
}
$stmt->close();

$viewingId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewingBilling = null;
if ($viewingId > 0) {
    $stmt = $conn->prepare('SELECT b.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email FROM billings b JOIN patients p ON b.patient_id = p.id WHERE b.id = ?');
    $stmt->bind_param('i', $viewingId);
    $stmt->execute();
    $viewingBilling = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($viewingBilling && isset($_GET['print'])) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#111827;} .box{border:1px solid #cbd5e1;padding:18px;border-radius:12px;} table{width:100%;border-collapse:collapse;margin-top:12px;} th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;} .right{text-align:right;} .muted{color:#64748b;}</style></head><body><div class="box"><h2>Invoice</h2><p><strong>Bill Number:</strong> ' . htmlspecialchars($viewingBilling['bill_number']) . '</p><p><strong>Patient:</strong> ' . htmlspecialchars($viewingBilling['patient_name']) . '</p><p><strong>Date:</strong> ' . htmlspecialchars($viewingBilling['billing_date']) . '</p><table><tr><th>Item</th><th class="right">Amount</th></tr><tr><td>Consultation Fee</td><td class="right">' . format_currency($viewingBilling['consultation_fee']) . '</td></tr><tr><td>Lab Test Fee</td><td class="right">' . format_currency($viewingBilling['lab_test_fee']) . '</td></tr><tr><td>Medicine Charges</td><td class="right">' . format_currency($viewingBilling['medicine_charges']) . '</td></tr><tr><td>Room Charges</td><td class="right">' . format_currency($viewingBilling['room_charges']) . '</td></tr><tr><td>Other Charges</td><td class="right">' . format_currency($viewingBilling['other_charges']) . '</td></tr><tr><td>Discount</td><td class="right">-' . format_currency($viewingBilling['discount']) . '</td></tr><tr><td>Tax</td><td class="right">' . format_currency($viewingBilling['tax']) . '</td></tr><tr><th>Grand Total</th><th class="right">' . format_currency($viewingBilling['grand_total']) . '</th></tr><tr><td>Amount Paid</td><td class="right">' . format_currency($viewingBilling['amount_paid']) . '</td></tr><tr><td>Remaining Balance</td><td class="right">' . format_currency($viewingBilling['remaining_balance']) . '</td></tr></table><p class="muted">Payment Method: ' . htmlspecialchars($viewingBilling['payment_method']) . '</p><p class="muted">Payment Status: ' . htmlspecialchars($viewingBilling['payment_status']) . '</p></div><script>window.print();</script></body></html>';
    exit();
}

if ($viewingBilling && isset($_GET['download'])) {
    $filename = ($viewingBilling['bill_number'] ?: 'invoice') . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice</title></head><body><h2>Invoice</h2><p><strong>Bill Number:</strong> ' . htmlspecialchars($viewingBilling['bill_number']) . '</p><p><strong>Patient:</strong> ' . htmlspecialchars($viewingBilling['patient_name']) . '</p><p><strong>Date:</strong> ' . htmlspecialchars($viewingBilling['billing_date']) . '</p><p><strong>Grand Total:</strong> ' . format_currency($viewingBilling['grand_total']) . '</p><p><strong>Amount Paid:</strong> ' . format_currency($viewingBilling['amount_paid']) . '</p><p><strong>Remaining Balance:</strong> ' . format_currency($viewingBilling['remaining_balance']) . '</p><p><strong>Payment Status:</strong> ' . htmlspecialchars($viewingBilling['payment_status']) . '</p></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receptionist Billing</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); color: #0f172a; }
.page { max-width: 1420px; margin: 0 auto; padding: 24px; }
.topbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
.topbar h1 { margin: 0; font-size: 28px; color: #102a43; }
.topbar p { margin: 4px 0 0; color: #64748b; }
.card { background: #fff; border: 1px solid #e5ecf7; border-radius: 18px; box-shadow: 0 14px 40px rgba(15,23,42,0.08); padding: 20px; margin-bottom: 18px; }
.grid { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 20px; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.form-grid > div { display: flex; flex-direction: column; }
label { display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 7px; letter-spacing: 0.01em; }
input, select, textarea { width: 100%; padding: 11px 12px; border: 1px solid #d8e1eb; border-radius: 12px; font-size: 14px; color: #0f172a; background: #fcfdff; box-sizing: border-box; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
input:focus, select:focus, textarea:focus { outline: none; border-color: #3399ff; box-shadow: 0 0 0 3px rgba(51,153,255,0.18); }
textarea { min-height: 92px; resize: vertical; }
.btn { border: none; padding: 10px 14px; border-radius: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; transition: transform 0.15s ease, box-shadow 0.15s ease; }
.btn:hover { transform: translateY(-1px); }
.btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; box-shadow: 0 8px 20px rgba(37,99,235,0.2); }
.btn-secondary { background: #f1f5f9; color: #0f172a; }
.btn-danger { background: #dc2626; color: #fff; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
th, td { padding: 12px 10px; border-bottom: 1px solid #e5ecf7; text-align: left; vertical-align: middle; }
th { background: #f8fbff; color: #475569; font-weight: 700; }
tr:last-child td { border-bottom: none; }
.billing-row:hover { background: #f8fbff; }
.billing-row:nth-child(even) { background: #fcfdff; }
.invoice-code { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #e8f1ff; color: #1d4ed8; font-weight: 700; }
.badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.badge.Paid { background: #dcfce7; color: #166534; }
.badge.Partial { background: #fef3c7; color: #92400e; }
.badge.Unpaid { background: #f3f4f6; color: #374151; }
.badge.Pending { background: #f3f4f6; color: #374151; }
.alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 14px; font-weight: 600; }
.alert.success { background: #ecfdf3; color: #166534; }
.alert.error { background: #fef2f2; color: #b91c1c; }
.actions a, .actions button { margin: 2px 4px 2px 0; }
.actions form { display: inline; }
.icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; color: #475569; text-decoration: none; font-size: 14px; cursor: pointer; }
.icon-btn:hover { background: #f8fbff; transform: translateY(-1px); }
.icon-btn.delete { color: #dc2626; }
.billing-list-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
.subtle { color: #64748b; margin: 4px 0 0; font-size: 13px; }
.billing-summary-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #eff6ff; color: #1d4ed8; border-radius: 999px; font-weight: 700; }
.summary-count { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; border-radius: 999px; background: #2563eb; color: #fff; padding: 0 6px; }
.empty-state { text-align: center; padding: 28px 12px; color: #64748b; }
.empty-icon { font-size: 34px; margin-bottom: 8px; }
.empty-title { font-size: 18px; font-weight: 700; color: #334155; margin-bottom: 4px; }
.empty-text { font-size: 13px; color: #64748b; }
@media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .page { padding: 16px; } .topbar { flex-direction: column; align-items: flex-start; } .form-grid { grid-template-columns: 1fr; } .billing-list-head { flex-direction: column; align-items: flex-start; } table { font-size: 12px; } th, td { padding: 10px 8px; } .icon-btn { width: 30px; height: 30px; font-size: 12px; } }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Billing Management</h1>
            <p style="margin: 4px 0 0; color: #64748b;">Create, update, and manage patient invoices as the receptionist.</p>
        </div>
        <div>
            <a class="btn btn-secondary" href="receptionist_dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0;"><?php echo $editingId > 0 ? 'Update Billing Record' : 'Create New Bill'; ?></h2>
            <form method="POST" action="receptionist-billing.php<?php echo $editingId > 0 ? '?edit=' . $editingId : ''; ?>" style="display:block;">
                <input type="hidden" name="action" value="<?php echo $editingId > 0 ? 'update_billing' : 'add_billing'; ?>">
                <?php if ($editingId > 0): ?><input type="hidden" name="billing_id" value="<?php echo (int)$editingId; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div>
                        <label for="patient_id">Patient</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo (int)$patient['id']; ?>" <?php echo ((string)$formData['patient_id']) === (string)$patient['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($patient['name'] . ' (' . $patient['phone'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="appointment_id">Appointment (optional)</label>
                        <select id="appointment_id" name="appointment_id">
                            <option value="" data-fee="0">No appointment linked</option>
                            <?php foreach ($appointments as $appointment): ?>
                                <option value="<?php echo (int)$appointment['id']; ?>" data-fee="<?php echo htmlspecialchars(number_format((float)($appointment['consultation_fee'] ?? 0), 2, '.', '')); ?>" <?php echo ((string)$formData['appointment_id']) === (string)$appointment['id'] ? 'selected' : ''; ?>>#<?php echo (int)$appointment['id']; ?> - <?php echo htmlspecialchars($appointment['appointment_date'] . ' ' . $appointment['appointment_time']); ?><?php echo !empty($appointment['doctor_name']) ? ' - Dr. ' . htmlspecialchars($appointment['doctor_name']) : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="billing_date">Billing Date</label>
                        <input id="billing_date" type="date" name="billing_date" value="<?php echo htmlspecialchars($formData['billing_date']); ?>" required>
                    </div>
                    <div>
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">Select method</option>
                            <option value="Cash" <?php echo $formData['payment_method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Card" <?php echo $formData['payment_method'] === 'Card' ? 'selected' : ''; ?>>Card</option>
                            <option value="Online" <?php echo $formData['payment_method'] === 'Online' ? 'selected' : ''; ?>>Online</option>
                            <option value="Insurance" <?php echo $formData['payment_method'] === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
                        </select>
                    </div>
                    <div>
                        <label for="payment_status">Payment Status</label>
                        <select id="payment_status" name="payment_status">
                            <option value="Unpaid" <?php echo $formData['payment_status'] === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Partial" <?php echo $formData['payment_status'] === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="Paid" <?php echo $formData['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    <div>
                        <label for="consultation_fee">Consultation Fee</label>
                        <input id="consultation_fee" type="number" step="0.01" name="consultation_fee" value="<?php echo htmlspecialchars($formData['consultation_fee']); ?>" oninput="recalculateTotals()" <?php echo $formData['appointment_id'] !== '' ? 'readonly' : ''; ?> <?php echo $formData['appointment_id'] !== '' ? 'style="background:#f1f5f9;"' : ''; ?>>
                    </div>
                    <div>
                        <label for="lab_test_fee">Lab Test Fee</label>
                        <input id="lab_test_fee" type="number" step="0.01" name="lab_test_fee" value="<?php echo htmlspecialchars($formData['lab_test_fee']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="medicine_charges">Medicine Charges</label>
                        <input id="medicine_charges" type="number" step="0.01" name="medicine_charges" value="<?php echo htmlspecialchars($formData['medicine_charges']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="room_charges">Room Charges</label>
                        <input id="room_charges" type="number" step="0.01" name="room_charges" value="<?php echo htmlspecialchars($formData['room_charges']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="other_charges">Other Charges</label>
                        <input id="other_charges" type="number" step="0.01" name="other_charges" value="<?php echo htmlspecialchars($formData['other_charges']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="discount">Discount</label>
                        <input id="discount" type="number" step="0.01" name="discount" value="<?php echo htmlspecialchars($formData['discount']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="tax">Tax</label>
                        <input id="tax" type="number" step="0.01" name="tax" value="<?php echo htmlspecialchars($formData['tax']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="amount_paid">Amount Paid</label>
                        <input id="amount_paid" type="number" step="0.01" name="amount_paid" value="<?php echo htmlspecialchars($formData['amount_paid']); ?>" oninput="recalculateTotals()">
                    </div>
                    <div>
                        <label for="grand_total_display">Grand Total</label>
                        <input id="grand_total_display" type="text" value="0.00" readonly>
                    </div>
                    <div>
                        <label for="remaining_balance_display">Remaining Balance</label>
                        <input id="remaining_balance_display" type="text" value="0.00" readonly>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="description">Description / Notes</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                    </div>
                </div>
                <div style="margin-top: 18px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <button class="btn btn-primary" type="submit"><?php echo $editingId > 0 ? 'Update Bill' : 'Create Bill'; ?></button>
                    <?php if ($editingId > 0): ?>
                        <a class="btn btn-secondary" href="receptionist-billing.php">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="billing-list-head">
                <div>
                    <h2 style="margin:0;">Existing Bills</h2>
                    <p class="subtle">Review recent invoices with clear payment and billing details.</p>
                </div>
                <div class="billing-summary-pill">
                    <span class="summary-count"><?php echo count($billings); ?></span>
                    <span>records</span>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 18%;">Invoice No.</th>
                            <th style="width: 22%;">Patient Name</th>
                            <th style="width: 15%;">Billing Date</th>
                            <th style="width: 15%;">Total Amount</th>
                            <th style="width: 12%;">Payment Status</th>
                            <th style="width: 12%;">Payment Method</th>
                            <th style="width: 18%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($billings) === 0): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <div class="empty-icon">🧾</div>
                                    <div class="empty-title">No bills yet</div>
                                    <div class="empty-text">New invoices will appear here once the receptionist creates a billing record.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($billings as $billing): ?>
                                <tr class="billing-row">
                                    <td><span class="invoice-code"><?php echo htmlspecialchars($billing['bill_number'] ?? ''); ?></span></td>
                                    <td><?php echo htmlspecialchars($billing['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['billing_date'] ?? ''); ?></td>
                                    <td><strong><?php echo format_currency($billing['grand_total']); ?></strong></td>
                                    <td><span class="badge <?php echo htmlspecialchars($billing['payment_status'] ?? 'Pending'); ?>"><?php echo htmlspecialchars($billing['payment_status'] ?? 'Pending'); ?></span></td>
                                    <td><?php echo htmlspecialchars($billing['payment_method'] ?? '—'); ?></td>
                                    <td class="actions">
                                        <a class="icon-btn view" href="receptionist-billing.php?view=<?php echo (int)$billing['id']; ?>" title="View">👁️</a>
                                        <a class="icon-btn edit" href="receptionist-billing.php?edit=<?php echo (int)$billing['id']; ?>" title="Edit">✏️</a>
                                        <a class="icon-btn print" href="receptionist-billing.php?view=<?php echo (int)$billing['id']; ?>&print=1" target="_blank" title="Print">🖨️</a>
                                        <a class="icon-btn download" href="receptionist-billing.php?view=<?php echo (int)$billing['id']; ?>&download=1" title="Download">⬇️</a>
                                        <form method="POST" action="receptionist-billing.php" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_billing">
                                            <input type="hidden" name="billing_id" value="<?php echo (int)$billing['id']; ?>">
                                            <button class="icon-btn delete" type="submit" onclick="return confirm('Delete this billing record?');" title="Delete">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($viewingBilling): ?>
        <div class="card">
            <h2 style="margin-top:0;">Invoice Preview</h2>
            <div style="display:grid; gap:14px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <div><strong>Bill Number:</strong> <?php echo htmlspecialchars($viewingBilling['bill_number']); ?></div>
                <div><strong>Patient:</strong> <?php echo htmlspecialchars($viewingBilling['patient_name']); ?></div>
                <div><strong>Billing Date:</strong> <?php echo htmlspecialchars($viewingBilling['billing_date']); ?></div>
                <div><strong>Payment Status:</strong> <?php echo htmlspecialchars($viewingBilling['payment_status']); ?></div>
            </div>
            <div style="margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                <p><strong>Consultation Fee:</strong> <?php echo format_currency($viewingBilling['consultation_fee']); ?></p>
                <p><strong>Lab Test Fee:</strong> <?php echo format_currency($viewingBilling['lab_test_fee']); ?></p>
                <p><strong>Medicine Charges:</strong> <?php echo format_currency($viewingBilling['medicine_charges']); ?></p>
                <p><strong>Room Charges:</strong> <?php echo format_currency($viewingBilling['room_charges']); ?></p>
                <p><strong>Other Charges:</strong> <?php echo format_currency($viewingBilling['other_charges']); ?></p>
                <p><strong>Discount:</strong> -<?php echo format_currency($viewingBilling['discount']); ?></p>
                <p><strong>Tax:</strong> <?php echo format_currency($viewingBilling['tax']); ?></p>
                <p><strong>Grand Total:</strong> <?php echo format_currency($viewingBilling['grand_total']); ?></p>
                <p><strong>Amount Paid:</strong> <?php echo format_currency($viewingBilling['amount_paid']); ?></p>
                <p><strong>Remaining Balance:</strong> <?php echo format_currency($viewingBilling['remaining_balance']); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($viewingBilling['payment_method']); ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
function recalculateTotals() {
    const values = ['consultation_fee','lab_test_fee','medicine_charges','room_charges','other_charges','discount','tax','amount_paid'];
    const getValue = (id) => parseFloat(document.getElementById(id)?.value || 0);
    const subtotal = getValue('consultation_fee') + getValue('lab_test_fee') + getValue('medicine_charges') + getValue('room_charges') + getValue('other_charges');
    const discount = getValue('discount');
    const tax = getValue('tax');
    const amountPaid = getValue('amount_paid');
    const grandTotal = Math.max(0, subtotal - discount + tax);
    const remainingBalance = Math.max(0, grandTotal - amountPaid);
    document.getElementById('grand_total_display').value = grandTotal.toFixed(2);
    document.getElementById('remaining_balance_display').value = remainingBalance.toFixed(2);
}
function updateConsultationFeeFromAppointment() {
    const appointmentSelect = document.getElementById('appointment_id');
    const consultationFeeInput = document.getElementById('consultation_fee');
    if (!appointmentSelect || !consultationFeeInput) {
        return;
    }

    const selectedOption = appointmentSelect.selectedOptions[0];
    const fee = parseFloat(selectedOption?.dataset?.fee || '0');
    if (appointmentSelect.value !== '') {
        consultationFeeInput.value = fee.toFixed(2);
        consultationFeeInput.readOnly = true;
        consultationFeeInput.style.backgroundColor = '#f1f5f9';
    } else {
        consultationFeeInput.readOnly = false;
        consultationFeeInput.style.backgroundColor = '';
    }
    recalculateTotals();
}

window.addEventListener('DOMContentLoaded', function () {
    updateConsultationFeeFromAppointment();
    const appointmentSelect = document.getElementById('appointment_id');
    if (appointmentSelect) {
        appointmentSelect.addEventListener('change', updateConsultationFeeFromAppointment);
    }
});
</script>
</body>
</html>
