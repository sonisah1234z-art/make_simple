<?php
// One-off maintenance script: add missing departments without duplicates and list departments.
// Usage (CLI): php add_departments_more.php
// Usage (browser): open http://localhost/make_simple/add_departments_more.php

require_once __DIR__ . '/db.php';

$toAdd = [
    ['Surgical Gastroenterology', 'Surgical management of complex digestive and gastrointestinal conditions.'],
    ['Anaesthesiology', 'Perioperative care, anesthesia and pain management.'],
    ['Pathology', 'Laboratory diagnostics and tissue analysis for accurate medical decisions.']
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

// Fetch and print active departments
$rows = [];
$res = $conn->query("SELECT id, name, description, status, image_path FROM departments ORDER BY name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

header_remove();
if (php_sapi_name() === 'cli') {
    foreach ($out as $line) {
        echo $line . PHP_EOL;
    }
    echo PHP_EOL . "Departments in DB:" . PHP_EOL;
    foreach ($rows as $r) {
        echo sprintf("%3d  %-40s  %s", $r['id'], $r['name'], $r['status']) . PHP_EOL;
    }
} else {
    echo '<pre>' . htmlspecialchars(implode("\n", $out)) . "\n\nDepartments in DB:\n";
    foreach ($rows as $r) {
        echo htmlspecialchars(sprintf("%3d  %-40s  %s\n", $r['id'], $r['name'], $r['status']));
    }
    echo '</pre>';
    echo '<p>Open <a href="admin-departments.php">Admin Departments</a> and Patient Dashboard to verify.</p>';
}

?>
