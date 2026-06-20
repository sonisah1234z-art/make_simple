<?php
// header.php - shared header, sidebar and top navbar
// Assumes session already started before include
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hospital Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app d-flex">
    <aside class="sidebar bg-dark text-white">
        <div class="sidebar-brand p-3 d-flex align-items-center gap-2">
            <div class="brand-circle">H</div>
            <div class="brand-title">HospitalMS</div>
        </div>
        <nav class="nav flex-column p-2">
                <?php if (isset($_SESSION['admin_id'])): ?>
                <a class="nav-link text-white" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
                <a class="nav-link text-white" href="admin-patients.php"><i class="bi bi-people me-2"></i>Patients</a>
                <a class="nav-link text-white" href="admin-doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
                <a class="nav-link nav-appointment text-white" href="admin-appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
                <a class="nav-link text-white" href="bed-availability.php"><i class="bi bi-hospital me-2"></i>Bed Availability</a>
                <a class="nav-link text-white" href="admin-billing.php"><i class="bi bi-receipt me-2"></i>Billing</a>
                <a class="nav-link text-white" href="admin-reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
            <?php elseif (isset($_SESSION['patient_id'])): ?>
                <a class="nav-link text-white" href="dashboard.php"><i class="bi bi-house-heart me-2"></i>Dashboard</a>
                <a class="nav-link text-white" href="book-appointment.php"><i class="bi bi-plus-circle me-2"></i>Book Appointment</a>
                <a class="nav-link nav-appointment text-white" href="view_appointments.php"><i class="bi bi-calendar-check me-2"></i>My Appointments</a>
                <a class="nav-link text-white" href="bed-availability.php"><i class="bi bi-hospital me-2"></i>Bed Availability</a>
                <a class="nav-link text-white" href="buy_medicine.php"><i class="bi bi-bag-heart me-2"></i>Buy Medicine</a>
                <a class="nav-link text-white" href="patient-billing.php"><i class="bi bi-wallet2 me-2"></i>Bills</a>
                <a class="nav-link text-white" href="view_lab_test.php"><i class="bi bi-beaker me-2"></i>Lab Tests</a>
                <a class="nav-link text-white" href="medical_timeline.php"><i class="bi bi-clock-history me-2"></i>Health Timeline</a>
            <?php else: ?>
                <a class="nav-link text-white" href="index.php"><i class="bi bi-house me-2"></i>Home</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer p-3">
            <?php if (isset($_SESSION['admin_name'])): ?>
                <div class="small text-muted">Signed in as</div>
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
            <?php elseif (isset($_SESSION['patient_name'])): ?>
                <div class="small text-muted">Signed in as</div>
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['patient_name']); ?></div>
            <?php endif; ?>
            <div class="mt-2">
                <a class="btn btn-sm btn-light" href="logout.php">Logout</a>
            </div>
        </div>
    </aside>

    <div class="main flex-fill">
        <nav class="topnav navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary me-2" id="sidebarToggle">☰</button>
                <form class="d-flex ms-2 me-auto" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search" id="globalSearch">
                </form>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <?php if (isset($_SESSION['admin_name'])): ?>
                            <small class="text-muted">Admin</small><br>
                            <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong>
                        <?php elseif (isset($_SESSION['patient_name'])): ?>
                            <small class="text-muted">Patient</small><br>
                            <strong><?php echo htmlspecialchars($_SESSION['patient_name']); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a class="btn btn-outline-primary" href="#" id="notifBtn">Notifications</a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- page content starts -->
<?php
// end of header.php
?>
