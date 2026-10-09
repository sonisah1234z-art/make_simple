<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
include 'db.php';
require_once 'auth.php';

$error = '';

if (isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') === 'Admin') {
    redirect_to_role_dashboard('Admin');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        $error = defined('DB_BOOTSTRAP_ERROR') ? DB_BOOTSTRAP_ERROR : 'Database is temporarily unavailable. Please try again later.';
    } else {
        // ensure auth schema (creates login_attempts table) and apply brute-force protection
        ensure_auth_schema($conn);
        // simple brute-force protection: max 5 attempts per 15 minutes per identifier or IP
        $maxAttempts = 5;
        $lockMinutes = 15;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // count recent attempts
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM login_attempts WHERE (ip = ? OR identifier = ?) AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
    if ($stmt) {
        $stmt->bind_param('ssi', $ip, $identifier, $lockMinutes);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res ? (int)$res->fetch_assoc()['total'] : 0;
        $stmt->close();
    } else {
        $count = 0;
    }

        if ($count >= $maxAttempts) {
            $error = 'Too many failed login attempts. Please try again after ' . $lockMinutes . ' minutes.';
        } else {
            if (login_user($conn, $role, $identifier, $password)) {
                // on success clear attempts for this identifier/ip
                $del = $conn->prepare('DELETE FROM login_attempts WHERE ip = ? OR identifier = ?');
                if ($del) { $del->bind_param('ss', $ip, $identifier); $del->execute(); $del->close(); }
                redirect_to_role_dashboard($_SESSION['role'] ?? '');
            }

            // record failed attempt
            $ins = $conn->prepare('INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)');
            if ($ins) { $ins->bind_param('ss', $identifier, $ip); $ins->execute(); $ins->close(); }

            $error = 'Invalid role, username/email, or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Management Login</title>
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    color: #f8fafc;
    background:
        linear-gradient(rgba(15, 23, 42, 0.58), rgba(15, 23, 42, 0.72)),
        url("https://images.unsplash.com/photo-1586773860418-d37222d8fce3?auto=format&fit=crop&w=1800&q=80") center/cover;
}

.page {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) 440px;
    align-items: center;
    gap: 48px;
    width: min(1180px, calc(100% - 40px));
    margin: 0 auto;
    padding: 40px 0;
}

.hero {
    max-width: 660px;
}

.eyebrow {
    display: inline-block;
    padding: 8px 12px;
    border: 1px solid rgba(255, 255, 255, 0.26);
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.42);
    color: #bfdbfe;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 20px;
}

h1 {
    font-size: clamp(38px, 7vw, 76px);
    line-height: 1;
    max-width: 780px;
    margin-bottom: 22px;
}

.hero p {
    max-width: 560px;
    color: #dbeafe;
    font-size: 18px;
    line-height: 1.7;
}

.stats {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 34px;
}

.stat {
    min-width: 150px;
    padding: 16px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.48);
}

.stat strong {
    display: block;
    font-size: 26px;
}

.stat span {
    color: #cbd5e1;
    font-size: 13px;
}

.login-card {
    width: 100%;
    padding: 32px;
    border: 1px solid rgba(226, 232, 240, 0.22);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.86);
    box-shadow: 0 30px 90px rgba(2, 6, 23, 0.34);
    backdrop-filter: blur(16px);
}

.login-card h2 {
    font-size: 28px;
    margin-bottom: 8px;
}

.login-card .subtext {
    color: #cbd5e1;
    line-height: 1.5;
    margin-bottom: 24px;
}

.field {
    margin-bottom: 16px;
}

label {
    display: block;
    color: #dbeafe;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 8px;
}

input,
select {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid rgba(226, 232, 240, 0.18);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
    font-size: 15px;
    outline: none;
}

select option {
    color: #0f172a;
}

input::placeholder {
    color: rgba(226, 232, 240, 0.56);
}

input:focus,
select:focus {
    border-color: #60a5fa;
    background: rgba(255, 255, 255, 0.12);
}

.error {
    padding: 12px 14px;
    border: 1px solid rgba(248, 113, 113, 0.35);
    border-radius: 8px;
    background: rgba(127, 29, 29, 0.5);
    color: #fecaca;
    margin-bottom: 16px;
}

.actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 22px;
}

.button,
.register-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 13px 16px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 15px;
    text-decoration: none;
    cursor: pointer;
}

.button {
    border: none;
    background: #38bdf8;
    color: #082f49;
}

.button:hover {
    background: #7dd3fc;
}

.register-button {
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
}

.register-button:hover {
    background: rgba(255, 255, 255, 0.14);
}

.helper {
    margin-top: 18px;
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
}

@media (max-width: 900px) {
    .page {
        grid-template-columns: 1fr;
        gap: 28px;
    }

    .hero {
        padding-top: 20px;
    }

    .login-card {
        max-width: 520px;
    }
}

@media (max-width: 520px) {
    .page {
        width: min(100% - 24px, 520px);
        padding: 18px 0;
    }

    .hero p {
        font-size: 15px;
    }

    .stats {
        display: none;
    }

    .login-card {
        padding: 22px;
    }

    .actions {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>
<body>
<main class="page">
    <section class="hero">
        <span class="eyebrow">Hospital Management System</span>
        <h1>Care teams, patients, and pharmacy in one secure portal.</h1>
        <p>Sign in by role to manage appointments, patient records, nurse schedules, medicines, and hospital operations from one clean dashboard.</p>
        <div class="stats">
            <div class="stat">
                <strong>5</strong>
                <span>Role-based logins</span>
            </div>
            <div class="stat">
                <strong>24/7</strong>
                <span>Hospital access</span>
            </div>
            <div class="stat">
                <strong>Secure</strong>
                <span>Password hashing</span>
            </div>
        </div>
    </section>

    <section class="login-card" aria-label="Login form">
        <h2>Welcome back</h2>
        <p class="subtext">Choose your role and enter your username or email to continue.</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="patient">Patient</option>
                    <option value="nurse">Nurse</option>
                    <option value="pharmacist">Pharmacist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="lab_technician">Lab Technician</option>
                

                </select>
            </div>

            <div class="field">
                <label for="identifier">Username or Email</label>
                <input id="identifier" type="text" name="identifier" placeholder="admin or patient@example.com" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div style="position:relative">
                    <input id="password" type="password" name="password" placeholder="Enter password" required>
                    <button id="togglePwd" type="button" style="position:absolute;right:8px;top:8px;padding:6px;border:none;border-radius:6px;background:transparent;color:#cbd5e1;cursor:pointer">Show</button>
                </div>
            </div>

            <div class="actions">
                <button class="button" type="submit">Login</button>
                <a class="register-button" href="register.php">Register</a>
            </div>
            <div class="mt-3 text-center">
                <a class="text-white text-decoration-none fw-semibold" href="about.php"><i class="bi bi-info-circle me-2"></i>About Us</a>
            </div>
        </form>

        <p class="helper">New patients can register an account before booking appointments.</p>
    </section>
</main>
<script>
// show/hide password
document.getElementById('togglePwd').addEventListener('click', function(){
    var p = document.getElementById('password');
    if (p.type === 'password') { p.type = 'text'; this.textContent = 'Hide'; } else { p.type = 'password'; this.textContent = 'Show'; }
});
</script>
</body>
</html>
