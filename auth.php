<?php
// auth.php - central role-based access helpers
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_admin()
{
    if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        header('Location: dashboard.php');
        exit();
    }

    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin-login.php');
        exit();
    }
}

function require_patient()
{
    if (isset($_SESSION['admin_id']) && !isset($_SESSION['patient_id'])) {
        header('Location: admin-dashboard.php');
        exit();
    }

    if (!isset($_SESSION['patient_id'])) {
        header('Location: login.php');
        exit();
    }
}

function current_patient_id()
{
    return $_SESSION['patient_id'] ?? null;
}

// Prevent patients from accessing admin pages
function ensure_not_patient_on_admin()
{
    if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        // Logged in as patient but trying to access admin resource
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit();
    }
}
