<?php
require_once 'db.php';

// Ensure beds table exists
$createSql = "CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_number VARCHAR(50) UNIQUE NOT NULL,
    ward VARCHAR(100) DEFAULT 'General',
    is_icu TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Available',
    patient_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($createSql);

// Seed a few sample beds if table is empty
$res = $conn->query("SELECT COUNT(*) AS cnt FROM beds");
$count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
if ($count === 0) {
    $seed = "INSERT INTO beds (bed_number, ward, is_icu, status) VALUES
        ('B-001', 'General Ward', 0, 'Available'),
        ('B-002', 'General Ward', 0, 'Occupied'),
        ('B-003', 'General Ward', 0, 'Available'),
        ('ICU-01', 'ICU', 1, 'Available'),
        ('ICU-02', 'ICU', 1, 'Occupied')";
    $conn->query($seed);
}

// Fetch counts
$total = 0; $available = 0; $icu_total = 0; $icu_available = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt FROM beds");
if ($r) $total = (int)$r->fetch_assoc()['cnt'];
$r = $conn->query("SELECT COUNT(*) AS cnt FROM beds WHERE status='Available'");
if ($r) $available = (int)$r->fetch_assoc()['cnt'];
$r = $conn->query("SELECT COUNT(*) AS cnt FROM beds WHERE is_icu=1");
if ($r) $icu_total = (int)$r->fetch_assoc()['cnt'];
$r = $conn->query("SELECT COUNT(*) AS cnt FROM beds WHERE is_icu=1 AND status='Available'");
if ($r) $icu_available = (int)$r->fetch_assoc()['cnt'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bed Availability Tracker</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;padding:24px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:900px;margin:0 auto}
        .card{background:white;padding:18px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .k{font-size:28px;font-weight:700}
        .label{color:#666;margin-top:6px}
        .green{color:#1b8a4a}
        .red{color:#c0392b}
    </style>
</head>
<body>
    <h1>Bed Availability Tracker</h1>
    <p>Useful during emergencies — totals, available and ICU availability.</p>
    <div class="grid">
        <div class="card">
            <div class="k"><?php echo $total; ?></div>
            <div class="label">Total beds</div>
        </div>
        <div class="card">
            <div class="k <?php echo $available>0?"green":"red"; ?>"><?php echo $available; ?></div>
            <div class="label">Available beds</div>
        </div>
        <div class="card">
            <div class="k"><?php echo $icu_total; ?></div>
            <div class="label">Total ICU beds</div>
        </div>
        <div class="card">
            <div class="k <?php echo $icu_available>0?"green":"red"; ?>"><?php echo $icu_available; ?></div>
            <div class="label">Available ICU beds</div>
        </div>
    </div>

    <p style="max-width:900px;margin:18px auto 0;color:#444">If counts look incorrect, ensure your `beds` table is populated. To re-run setup, open <a href="setup.php">setup.php</a>.</p>
</body>
</html>
