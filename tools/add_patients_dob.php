<?php
require_once __DIR__ . '/..//db.php';

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "No DB connection available.\n");
    exit(1);
}

$check = $conn->query("SHOW COLUMNS FROM patients LIKE 'date_of_birth'");
if ($check && $check->num_rows > 0) {
    echo "Column date_of_birth already exists on patients table.\n";
    exit(0);
}

$sql = "ALTER TABLE patients ADD COLUMN date_of_birth DATE DEFAULT NULL";
if ($conn->query($sql) === TRUE) {
    echo "Added column date_of_birth to patients table.\n";
    exit(0);
} else {
    fwrite(STDERR, "Failed to add column: " . $conn->error . "\n");
    exit(2);
}
