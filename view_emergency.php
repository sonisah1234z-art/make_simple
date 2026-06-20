<?php
require_once 'auth.php';
require_admin();
include 'db.php';

$result = mysqli_query($conn, "SELECT * FROM emergency_alerts ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Emergency Alerts</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #eef7ff 0%, #f8fbff 100%);
            color: #303451;
        }
        .page-wrapper {
            max-width: 1100px;
            margin: 30px auto 40px;
            padding: 0 18px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            letter-spacing: 0.02em;
        }
        .page-header p {
            margin: 8px auto 0;
            max-width: 700px;
            color: #5d6b8a;
            font-size: 0.98rem;
            line-height: 1.6;
        }
        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(20, 45, 85, 0.08);
            overflow: hidden;
            border: 1px solid rgba(100, 116, 139, 0.12);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }
        thead {
            background: linear-gradient(90deg, #4f9cf5 0%, #0073e8 100%);
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
        }
        th {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
        }
        tbody tr {
            border-bottom: 1px solid #eef1f7;
        }
        tbody tr:nth-child(even) {
            background: #fbfcff;
        }
        td {
            color: #33415b;
            font-size: 0.95rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.86rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .status-pending {
            background: rgba(255, 193, 7, 0.16);
            color: #b38600;
        }
        .status-resolved {
            background: rgba(40, 167, 69, 0.16);
            color: #176c2f;
        }
        .status-in-progress {
            background: rgba(23, 162, 184, 0.16);
            color: #0f5d68;
        }
        .no-records {
            padding: 40px;
            text-align: center;
            color: #6f7c98;
        }
        @media (max-width: 780px) {
            .page-wrapper {
                margin: 20px 12px 30px;
            }
            table {
                display: block;
                overflow-x: auto;
                min-width: 560px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-header">
            <h1><img src="assets/icons/emergency.svg" class="icon">Emergency Alerts</h1>
            <p>Review the most recent emergency alerts raised by patients. The table below shows status, message details, and submission time.</p>
        </div>

        <div class="card">
            <?php if(mysqli_num_rows($result) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['message']); ?></td>
                        <td>
                            <?php
                                $status = strtolower(trim($row['status']));
                                $badgeClass = 'status-pending';
                                if($status === 'resolved' || $status === 'completed') {
                                    $badgeClass = 'status-resolved';
                                } elseif($status === 'in progress' || $status === 'in-progress' || $status === 'ongoing') {
                                    $badgeClass = 'status-in-progress';
                                }
                            ?>
                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="no-records">
                    <strong>No emergency alerts found.</strong>
                    <div>Once an alert is raised, it will appear here immediately.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>