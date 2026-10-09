<?php
// Repair script: finds InnoDB tables that are present in information_schema but missing in the engine,
// drops them so they can be recreated by setup.php or application code.
require_once __DIR__ . '/../db.php';

if (!($conn instanceof mysqli)) {
    echo "No database connection available.\n";
    exit(1);
}

$db = $database ?? 'hospital_system';

echo "Scanning InnoDB tables in database: $db\n\n";

$res = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "' AND ENGINE = 'InnoDB'");
if (!$res) {
    echo "Failed to query information_schema: " . $conn->error . "\n";
    exit(1);
}

$corrupted = [];
while ($row = $res->fetch_assoc()) {
    $table = $row['TABLE_NAME'];
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM `$table`");
    if (!$countRes) {
        $err = $conn->error;
        if (stripos($err, "doesn't exist in engine") !== false) {
            $corrupted[] = $table;
            echo "Marked as corrupted: $table (error: $err)\n";
        }
    }
}

if (empty($corrupted)) {
    echo "No corrupted InnoDB tables detected.\n";
    exit(0);
}

echo "\nThe following tables will be dropped and can be recreated by running setup.php:\n";
foreach ($corrupted as $t) {
    echo " - $t\n";
}

// Proceed to drop
foreach ($corrupted as $t) {
    echo "Dropping $t... ";
    if ($conn->query("DROP TABLE IF EXISTS `$t`")) {
        echo "OK\n";
    } else {
        echo "Failed: " . $conn->error . "\n";
    }
}

echo "\nRepair pass complete. Run setup.php to recreate tables and seed data.\n";

?>
