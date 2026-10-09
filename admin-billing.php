<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'billing_helpers.php';

ensure_billing_schema($conn);

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$message = '';
$error = '';

$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterDate = trim($_GET['billing_date'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR b.bill_number LIKE ? OR b.description LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($filterStatus !== '') {
    $where[] = 'b.payment_status = ?';
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterDate !== '') {
    $where[] = 'b.billing_date = ?';
    $params[] = $filterDate;
    $types .= 's';
}

$sql = 'SELECT b.id, b.bill_number, b.billing_date, b.grand_total, b.amount_paid, b.remaining_balance, b.payment_status, p.name AS patient_name, b.description, b.created_at FROM billings b JOIN patients p ON b.patient_id = p.id';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY b.created_at DESC';

$billings = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }
    $stmt->close();
}

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
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#111827;} .box{border:1px solid #cbd5e1;padding:18px;border-radius:12px;} table{width:100%;border-collapse:collapse;margin-top:12px;} th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;} .right{text-align:right;}</style></head><body><div class="box"><h2>Invoice</h2><p><strong>Bill Number:</strong> ' . htmlspecialchars($viewingBilling['bill_number']) . '</p><p><strong>Patient:</strong> ' . htmlspecialchars($viewingBilling['patient_name']) . '</p><p><strong>Date:</strong> ' . htmlspecialchars($viewingBilling['billing_date']) . '</p><table><tr><th>Item</th><th class="right">Amount</th></tr><tr><td>Consultation Fee</td><td class="right">' . format_currency($viewingBilling['consultation_fee']) . '</td></tr><tr><td>Lab Test Fee</td><td class="right">' . format_currency($viewingBilling['lab_test_fee']) . '</td></tr><tr><td>Medicine Charges</td><td class="right">' . format_currency($viewingBilling['medicine_charges']) . '</td></tr><tr><td>Room Charges</td><td class="right">' . format_currency($viewingBilling['room_charges']) . '</td></tr><tr><td>Other Charges</td><td class="right">' . format_currency($viewingBilling['other_charges']) . '</td></tr><tr><td>Discount</td><td class="right">-' . format_currency($viewingBilling['discount']) . '</td></tr><tr><td>Tax</td><td class="right">' . format_currency($viewingBilling['tax']) . '</td></tr><tr><th>Grand Total</th><th class="right">' . format_currency($viewingBilling['grand_total']) . '</th></tr><tr><td>Amount Paid</td><td class="right">' . format_currency($viewingBilling['amount_paid']) . '</td></tr><tr><td>Remaining Balance</td><td class="right">' . format_currency($viewingBilling['remaining_balance']) . '</td></tr></table><p><strong>Payment Method:</strong> ' . htmlspecialchars($viewingBilling['payment_method']) . '</p><p><strong>Payment Status:</strong> ' . htmlspecialchars($viewingBilling['payment_status']) . '</p></div><script>window.print();</script></body></html>';
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
<title>Billing Management</title>
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
    .page { max-width: 1400px; margin: 0 auto; padding: 24px; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 18px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 12px 30px rgba(15,23,42,0.08); padding: 18px; margin-bottom: 18px; }
    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .toolbar input, .toolbar select, .toolbar button { padding: 10px 12px; border-radius: 10px; border: 1px solid #dbe2ea; }
    .btn { border: none; padding: 10px 14px; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: #2563eb; color: white; }
    .btn-secondary { background: #e2e8f0; color: #0f172a; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    th { background: #f8fafc; color: #475569; }
    .pill { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .pill.Paid { background: #dcfce7; color: #166534; }
    .pill.Partial { background: #fef3c7; color: #92400e; }
    .pill.Unpaid { background: #f3f4f6; color: #374151; }
    .muted { color: #64748b; }
    .actions a { margin-right: 8px; }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Billing Overview</h1>
            <p class="muted">Read-only billing access for administrators. Receptionists manage the billing records.</p>
        </div>
        <div class="toolbar">
            <a class="btn btn-secondary" href="admin-dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="admin-billing.php" class="toolbar">
            <input type="text" name="search" placeholder="Search by patient, invoice, or note" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All statuses</option>
                <option value="Paid" <?php echo $filterStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Partial" <?php echo $filterStatus === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                <option value="Unpaid" <?php echo $filterStatus === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            </select>
            <input type="date" name="billing_date" value="<?php echo htmlspecialchars($filterDate); ?>">
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>
    </div>

    <?php if ($viewingBilling): ?>
        <div class="card">
            <h3 style="margin-top:0;">Invoice Preview</h3>
            <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div><strong>Bill Number:</strong> <?php echo htmlspecialchars($viewingBilling['bill_number']); ?></div>
                <div><strong>Patient:</strong> <?php echo htmlspecialchars($viewingBilling['patient_name']); ?></div>
                <div><strong>Billing Date:</strong> <?php echo htmlspecialchars($viewingBilling['billing_date']); ?></div>
                <div><strong>Payment Status:</strong> <?php echo htmlspecialchars($viewingBilling['payment_status']); ?></div>
            </div>
            <div style="margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
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

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Patient</th>
                        <th>Billing Date</th>
                        <th>Grand Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($billings) === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#64748b;">No billing records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($billings as $billing): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($billing['bill_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($billing['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($billing['billing_date'] ?? ''); ?></td>
                                <td><?php echo format_currency($billing['grand_total'] ?? 0); ?></td>
                                <td><?php echo format_currency($billing['amount_paid'] ?? 0); ?></td>
                                <td><?php echo format_currency($billing['remaining_balance'] ?? 0); ?></td>
                                <td><span class="pill <?php echo htmlspecialchars($billing['payment_status'] ?? 'Unpaid'); ?>"><?php echo htmlspecialchars($billing['payment_status'] ?? 'Unpaid'); ?></span></td>
                                <td class="actions">
                                    <a class="btn btn-secondary" href="admin-billing.php?view=<?php echo (int)$billing['id']; ?>">Preview</a>
                                    <a class="btn btn-secondary" href="admin-billing.php?view=<?php echo (int)$billing['id']; ?>&print=1" target="_blank">Print</a>
                                    <a class="btn btn-secondary" href="admin-billing.php?view=<?php echo (int)$billing['id']; ?>&download=1">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
