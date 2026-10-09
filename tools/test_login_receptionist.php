<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

function tryLogin($role, $identifier, $password) {
    $ok = login_user($GLOBALS['conn'], $role, $identifier, $password);
    if ($ok) {
        echo "Trying identifier='$identifier' -> OK (session role=" . ($_SESSION['role'] ?? 'none') . ", id=" . ($_SESSION['receptionist_id'] ?? 'n/a') . ")\n";
    } else {
        echo "Trying identifier='$identifier' -> FAILED\n";
    }
}

tryLogin('receptionist','receptionist','reception123');
tryLogin('receptionist','Hospital Receptionist','reception123');

?>
