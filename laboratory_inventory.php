<?php
require_once 'auth.php';
require_lab_technician();
require_once 'db.php';

$message = '';
$error = '';
$editId = (int)($_GET['edit'] ?? 0);
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'pcs');
    $supplier = trim($_POST['supplier'] ?? '');
    $purchaseDate = trim($_POST['purchase_date'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $action = $_POST['action'] ?? 'add';

    if ($itemName === '' || $category === '' || $quantity < 0 || $unit === '') {
        $error = 'Item name, category, quantity, and unit are required.';
    } else {
        if ($action === 'edit' && $editId > 0) {
            $stmt = $conn->prepare('UPDATE lab_inventory SET item_name=?, category=?, quantity=?, unit=?, supplier=?, purchase_date=?, expiry_date=?, status=?, updated_at=NOW() WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ssisssssi', $itemName, $category, $quantity, $unit, $supplier, $purchaseDate, $expiryDate, $status, $editId);
                $stmt->execute();
                $stmt->close();
                $message = 'Inventory item updated successfully.';
            } else {
                $error = 'Unable to update inventory item.';
            }
        } else {
            $stmt = $conn->prepare('INSERT INTO lab_inventory (item_name, category, quantity, unit, supplier, purchase_date, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('ssisssss', $itemName, $category, $quantity, $unit, $supplier, $purchaseDate, $expiryDate, $status);
                $stmt->execute();
                $stmt->close();
                $message = 'Inventory item added successfully.';
            } else {
                $error = 'Unable to add inventory item.';
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM lab_inventory WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $delId);
        $stmt->execute();
        $stmt->close();
        $message = 'Inventory item deleted.';
    }
}

$editRecord = null;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM lab_inventory WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editRecord = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$sql = 'SELECT * FROM lab_inventory WHERE 1=1';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND (item_name LIKE ? OR category LIKE ? OR supplier LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types = 'sss';
}
$sql .= ' ORDER BY expiry_date IS NULL, expiry_date ASC, quantity ASC, id DESC';
$stmt = $conn->prepare($sql);
$items = [];
if ($stmt) {
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Laboratory Inventory</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;color:#233142}
.container{max-width:1280px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:18px;border-radius:16px;box-shadow:0 12px 32px rgba(2,6,23,.06)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;margin-top:8px;font-weight:600}
input,select,button{width:100%;padding:8px 10px;border-radius:8px;border:1px solid #d8e4f1;margin-top:6px}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-block;background:#0d6efd;color:#fff;border:0;cursor:pointer}
.btn-secondary{background:#6c757d}
.alert{padding:10px;border-radius:8px;margin-bottom:12px}
.alert-success{background:#e8f8ef;color:#157347}
.alert-danger{background:#ffe6e6;color:#a00}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
.badge{padding:5px 10px;border-radius:999px;font-size:12px;font-weight:600}
.badge-low{background:#fff4d6;color:#b7791f}
.badge-expired{background:#ffe6e6;color:#a00}
@media (max-width: 900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <h2>Laboratory Inventory</h2>
    <div class="card">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="get" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
            <div><label>Search</label><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item, category, supplier"></div>
            <button class="btn" type="submit">Search</button>
            <a class="btn btn-secondary" href="laboratory_inventory.php">Reset</a>
        </form>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
            <div>
                <label>Item Name</label>
                <input type="text" name="item_name" value="<?php echo htmlspecialchars($editRecord['item_name'] ?? ''); ?>" required>
                <label>Category</label>
                <input type="text" name="category" value="<?php echo htmlspecialchars($editRecord['category'] ?? ''); ?>" required>
                <label>Quantity</label>
                <input type="number" name="quantity" min="0" value="<?php echo htmlspecialchars((string)($editRecord['quantity'] ?? 0)); ?>" required>
                <label>Unit</label>
                <input type="text" name="unit" value="<?php echo htmlspecialchars($editRecord['unit'] ?? 'pcs'); ?>" required>
            </div>
            <div>
                <label>Supplier</label>
                <input type="text" name="supplier" value="<?php echo htmlspecialchars($editRecord['supplier'] ?? ''); ?>">
                <label>Purchase Date</label>
                <input type="date" name="purchase_date" value="<?php echo htmlspecialchars($editRecord['purchase_date'] ?? ''); ?>">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($editRecord['expiry_date'] ?? ''); ?>">
                <label>Status</label>
                <select name="status">
                    <option value="Active" <?php echo (($editRecord['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Low Stock" <?php echo (($editRecord['status'] ?? 'Active') === 'Low Stock') ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="Expired" <?php echo (($editRecord['status'] ?? 'Active') === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                </select>
                <div style="margin-top:10px;display:flex;gap:8px">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn btn-secondary" href="laboratory_inventory.php">Cancel</a>
                </div>
            </div>
        </form>
        <div class="table-wrap" style="margin-top:20px">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Supplier</th>
                        <th>Purchase Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): foreach ($items as $row): ?>
                        <?php
                        $today = date('Y-m-d');
                        $statusText = $row['status'] ?? 'Active';
                        $isExpired = !empty($row['expiry_date']) && $row['expiry_date'] < $today;
                        $isLow = (int)$row['quantity'] <= 5;
                        if ($isExpired) { $statusText = 'Expired'; }
                        elseif ($isLow) { $statusText = 'Low Stock'; }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo (int)$row['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['purchase_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['expiry_date'] ?? ''); ?></td>
                            <td>
                                <?php if ($isExpired): ?><span class="badge badge-expired">Expired</span><?php elseif ($isLow): ?><span class="badge badge-low">Low Stock</span><?php else: ?><span class="badge" style="background:#e8f8ef;color:#157347">Active</span><?php endif; ?>
                            </td>
                            <td>
                                <a class="btn" href="laboratory_inventory.php?edit=<?php echo (int)$row['id']; ?>">Edit</a>
                                <a class="btn btn-secondary" href="laboratory_inventory.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this inventory item?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="9">No inventory items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
