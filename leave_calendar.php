<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'leave_management.php';

ensure_leave_management_schema($conn);

$role = $_SESSION['role'] ?? '';
if (!is_staff_role($role) && ($role !== 'Admin')) {
    header('Location: login.php');
    exit();
}

$month = $_GET['month'] ?? date('Y-m');
$timestamp = strtotime($month . '-01');
$year = date('Y', $timestamp);
$monthNumber = date('m', $timestamp);
$daysInMonth = (int)date('t', $timestamp);
$firstDay = (int)date('w', $timestamp);
$monthName = date('F Y', $timestamp);

$approvedLeaves = [];
$stmt = $conn->prepare("SELECT staff_name, staff_role, start_date, end_date, leave_type FROM leave_requests WHERE status = 'Approved' AND YEAR(start_date) = ? AND MONTH(start_date) = ? ORDER BY start_date ASC");
if ($stmt) {
    $stmt->bind_param('ii', $year, $monthNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $approvedLeaves[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave Calendar</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.container{max-width:1200px;margin:24px auto;padding:0 20px}
.card{background:white;border-radius:12px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.08);margin-bottom:20px}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.day-header{font-weight:bold;text-align:center;padding:8px;background:#e2e8f0;border-radius:8px}
.day-cell{min-height:90px;border:1px solid #e2e8f0;border-radius:8px;padding:8px;background:#fff}
.day-number{font-size:13px;font-weight:bold;color:#475569}
.event{font-size:12px;background:#dcfce7;color:#166534;padding:4px 6px;border-radius:6px;margin-top:4px}
.nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="nav">
            <h2>Approved Leave Calendar</h2>
            <div>
                <a href="leave_calendar.php?month=<?php echo date('Y-m', strtotime('-1 month', $timestamp)); ?>">Previous</a> |
                <strong><?php echo htmlspecialchars($monthName); ?></strong> |
                <a href="leave_calendar.php?month=<?php echo date('Y-m', strtotime('+1 month', $timestamp)); ?>">Next</a>
            </div>
        </div>
        <div class="calendar">
            <div class="day-header">Sun</div><div class="day-header">Mon</div><div class="day-header">Tue</div><div class="day-header">Wed</div><div class="day-header">Thu</div><div class="day-header">Fri</div><div class="day-header">Sat</div>
            <?php for ($i = 0; $i < $firstDay; $i++): ?><div class="day-cell"></div><?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php $dateKey = sprintf('%04d-%02d-%02d', $year, $monthNumber, $day); ?>
                <div class="day-cell">
                    <div class="day-number"><?php echo $day; ?></div>
                    <?php foreach ($approvedLeaves as $leave): ?>
                        <?php if ($leave['start_date'] <= $dateKey && $leave['end_date'] >= $dateKey): ?>
                            <div class="event"><?php echo htmlspecialchars($leave['staff_name']); ?> (<?php echo htmlspecialchars($leave['leave_type']); ?>)</div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
</body>
</html>
