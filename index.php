<?php
// Landing page for the hospital management portal.
// Provides quick links for patients and administrators.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Hospital Management System</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top, #0f172a, #1e293b 25%, #0f172a 100%);
        color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .hero {
        width: 100%;
        max-width: 960px;
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 32px;
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 32px;
        padding: 40px;
        box-shadow: 0 30px 100px rgba(15, 23, 42, 0.35);
    }
    .hero h1 { font-size: 44px; line-height: 1.05; margin-bottom: 18px; }
    .hero p { color: #cbd5e1; font-size: 17px; line-height: 1.8; margin-bottom: 28px; }
    .actions { display: grid; gap: 14px; }
    @media (max-width: 768px) {
        body { padding: 16px; }
        .hero { grid-template-columns: 1fr; gap: 24px; padding: 28px; border-radius: 24px; }
        .hero h1 { font-size: 32px; }
        .hero p { font-size: 15px; }
        .button { padding: 14px 18px; font-size: 15px; }
    }
    @media (max-width: 480px) {
        body { padding: 12px; }
        .hero { gap: 16px; padding: 20px; border-radius: 20px; }
        .hero h1 { font-size: 26px; margin-bottom: 12px; }
        .hero p { font-size: 14px; margin-bottom: 18px; }
        .button { padding: 12px 16px; font-size: 13px; }
    }
    .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 16px 22px;
        border-radius: 16px;
        border: none;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.18s ease, background 0.18s ease;
    }
    .button-primary { background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; }
    .button-secondary { background: rgba(255,255,255,0.08); color: #e2e8f0; }
    .button:hover { transform: translateY(-2px); }
    .panel {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 28px;
        padding: 28px;
        display: grid;
        gap: 18px;
    }
    .panel h2 { font-size: 22px; margin-bottom: 8px; }
    .panel p { color: #cbd5e1; line-height: 1.7; }
    .panel a { color: #93c5fd; text-decoration: none; }
    .panel a:hover { text-decoration: underline; }
    .feature-list { display: grid; gap: 12px; margin-top: 16px; }
    .feature-item { display: flex; gap: 12px; align-items: flex-start; }
    .feature-item span { color: #60a5fa; font-size: 18px; line-height: 1; }
    .feature-item div { color: #cbd5e1; }
    @media (max-width: 820px) {
        .hero { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<div class="hero">
    <div>
        <h1>Hospital Management System</h1>
        <p>Use this portal to log in, register, or prepare the application for first use. Everything starts from here.</p>
        <div class="actions">
            <a class="button button-primary" href="login.php">Patient Login</a>
            <a class="button button-secondary" href="register.php">Patient Register</a>
            <a class="button button-secondary" href="admin-login.php">Admin Login</a>
            <a class="button button-secondary" href="setup.php">Initialize Database</a>
        </div>
    </div>
    <div class="panel">
        <h2>What this app covers</h2>
        <div class="feature-list">
            <div class="feature-item"><span>✓</span><div>Patient login and account registration.</div></div>
            <div class="feature-item"><span>✓</span><div>Book appointments with doctors from the clinic.</div></div>
            <div class="feature-item"><span>✓</span><div>Admin login for managing patients, doctors, and schedules.</div></div>
            <div class="feature-item"><span>✓</span><div>Basic local database setup with XAMPP.</div></div>
        </div>
    </div>
</div>
</body>
</html>