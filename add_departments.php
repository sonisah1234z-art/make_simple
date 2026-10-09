<?php
// One-off maintenance script: add missing departments without duplicates.
// Usage (CLI): php add_departments.php
// Usage (browser): open http://localhost/make_simple/add_departments.php

require_once __DIR__ . '/db.php';

$toAdd = [
    ['Pharmacy', 'Medication dispensing, counseling and pharmacy support services.'],
    ['Radiology', 'Imaging diagnostics including X-ray, CT, MRI and ultrasound.'],
    ['Dental', 'Complete oral care, dental surgery, and preventive dentistry services.'],
    ['Plastic Surgery and Burns', 'Reconstructive and cosmetic surgery with burn recovery support.']
];

$out = [];
$insertStmt = $conn->prepare('INSERT INTO departments (name, description, status, image_path) VALUES (?, ?, ?, NULL)');
$checkStmt = $conn->prepare('SELECT id FROM departments WHERE LOWER(name) = LOWER(?) LIMIT 1');

foreach ($toAdd as [$name, $desc]) {
    $checkStmt->bind_param('s', $name);
    $checkStmt->execute();
    $res = $checkStmt->get_result();
    if ($res && $res->num_rows > 0) {
        $out[] = "Skipped existing: $name";
        continue;
    }

    if ($insertStmt) {
        $status = 'Active';
        $insertStmt->bind_param('sss', $name, $desc, $status);
        if ($insertStmt->execute()) {
            $out[] = "Inserted: $name";
        } else {
            $out[] = "Failed to insert: $name";
        }
    }
}

if ($insertStmt) $insertStmt->close();
if ($checkStmt) $checkStmt->close();

header_remove();
if (php_sapi_name() === 'cli') {
    foreach ($out as $line) {
        echo $line . PHP_EOL;
    }
} else {
    echo '<pre>' . htmlspecialchars(implode("\n", $out)) . '</pre>';
    echo '<p>Open <a href="admin-departments.php">Admin Departments</a> to verify.</p>';
}

?>
