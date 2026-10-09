<?php
// Diagnostic script: lists tables, row counts, engines, and foreign keys
require_once __DIR__ . '/../db.php';

if (!($conn instanceof mysqli)) {
    echo "No database connection available.\n";
    exit(1);
}

$db = $database ?? 'hospital_system';

echo "Database: $db\n\n";

$tablesRes = $conn->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "'");
if (!$tablesRes) {
    echo "Failed to list tables: " . $conn->error . "\n";
    exit(1);
}

while ($row = $tablesRes->fetch_assoc()) {
    $table = $row['TABLE_NAME'];
    $engine = $row['ENGINE'];
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM `$table`");
    if ($countRes) {
        $cnt = (int)$countRes->fetch_assoc()['cnt'];
        echo "Table: $table | Engine: $engine | Rows: $cnt\n";
    } else {
        echo "Table: $table | Engine: $engine | Rows: n/a (error: " . $conn->error . ")\n";
    }

    // Show foreign keys for this table
    $fkRes = $conn->query("SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "' AND TABLE_NAME = '" . $conn->real_escape_string($table) . "' AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ($fkRes && $fkRes->num_rows > 0) {
        while ($fk = $fkRes->fetch_assoc()) {
            echo "  FK: {$fk['CONSTRAINT_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']}) via {$fk['COLUMN_NAME']}\n";
        }
    }

    echo "\n";
}

echo "Diagnostic complete.\n";

?>
