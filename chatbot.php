<?php
session_start();
include 'auth.php';
require_patient();
include 'db.php';

$conn->query("CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chatbot_patient_created (patient_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$patientId = (int)($_SESSION['patient_id'] ?? 0);
$patientName = $_SESSION['patient_name'] ?? 'Patient';

function escapeText($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetchDoctors(mysqli $conn, int $limit = 5): array
{
    $result = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name LIMIT $limit");
    $rows = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetchMedicines(mysqli $conn, int $limit = 6): array
{
    $result = $conn->query("SELECT medicine_name, stock FROM medicines WHERE stock > 0 ORDER BY stock DESC, medicine_name ASC LIMIT $limit");
    $rows = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetchAppointments(mysqli $conn, int $patientId, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT a.appointment_date, a.appointment_time, a.status, d.name AS doctor_name, d.specialty
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ?");
    $stmt->bind_param('ii', $patientId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetchLabReports(mysqli $conn, int $patientId, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT test_type, result, report_date FROM lab_tests WHERE patient_id = ? ORDER BY report_date DESC, id DESC LIMIT ?");
    $stmt->bind_param('ii', $patientId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetchPrescriptions(mysqli $conn, string $patientName, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT medicine_name, quantity, total_price, status, created_at FROM medicine_orders WHERE patient_name = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('si', $patientName, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function bookAppointment(mysqli $conn, int $patientId, string $patientName, string $message): string
{
    $normalized = trim($message);

    if (preg_match('/with\s+([a-zA-Z .-]+)/i', $normalized, $doctorMatch)) {
        $doctorName = trim($doctorMatch[1]);
    } elseif (preg_match('/doctor\s+([a-zA-Z .-]+)/i', $normalized, $doctorMatch)) {
        $doctorName = trim($doctorMatch[1]);
    } else {
        return "I can book an appointment for you. Try: 'Book appointment with Dr. Sarah on 2026-07-03 at 10:30'.";
    }

    $date = null;
    $time = null;

    if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $normalized, $dateMatch)) {
        $date = $dateMatch[1];
    }

    if (preg_match('/\b(\d{1,2}:\d{2})\b/', $normalized, $timeMatch)) {
        $time = $timeMatch[1];
    }

    if (!$date || !$time) {
        return "I found the doctor request, but I still need the appointment date and time. Please send it like 'Book appointment with Dr. Sarah on 2026-07-03 at 10:30'.";
    }

    $stmt = $conn->prepare("SELECT id, name, specialty FROM doctors WHERE LOWER(name) LIKE LOWER(?) ORDER BY name LIMIT 1");
    $like = '%' . $doctorName . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $doctorResult = $stmt->get_result();
    $doctor = $doctorResult ? $doctorResult->fetch_assoc() : null;
    $stmt->close();

    if (!$doctor) {
        return "I could not find that doctor in the system. Please choose one from the doctor list or spell the name carefully.";
    }

    $insert = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'Pending')");
    $insert->bind_param('iiss', $patientId, $doctor['id'], $date, $time);
    $insert->execute();
    $insert->close();

    return "Appointment booked successfully for {$patientName} with {$doctor['name']} ({$doctor['specialty']}) on {$date} at {$time}.";
}

function buildReply(mysqli $conn, int $patientId, string $patientName, string $message): string
{
    $normalized = strtolower(trim($message));

    if ($normalized === '') {
        return 'Please type a question or request.';
    }

    if (preg_match('/\b(book|schedule)\b/i', $message) && preg_match('/\b(appointment|visit)\b/i', $message)) {
        return bookAppointment($conn, $patientId, $patientName, $message);
    }

    if (preg_match('/\b(doctor|doctors|specialist|specialists)\b/i', $normalized)) {
        $doctors = fetchDoctors($conn, 5);
        if (!$doctors) {
            return 'No doctors are currently available in the system.';
        }

        $lines = ['Here are some available doctors:'];
        foreach ($doctors as $doctor) {
            $lines[] = '• ' . escapeText($doctor['name']) . ' — ' . escapeText($doctor['specialty'] ?? 'General');
        }
        $lines[] = 'You can also say: “Book appointment with Dr. Sarah on 2026-07-03 at 10:30”.';
        return implode("\n", $lines);
    }

    if (preg_match('/\b(medicine|medicines|pharmacy|drug|drugs)\b/i', $normalized)) {
        $medicines = fetchMedicines($conn, 6);
        if (!$medicines) {
            return 'There are no medicines currently marked as available.';
        }

        $lines = ['Available medicines right now:'];
        foreach ($medicines as $medicine) {
            $lines[] = '• ' . escapeText($medicine['medicine_name']) . ' — Stock: ' . (int)$medicine['stock'];
        }
        return implode("\n", $lines);
    }

    if (preg_match('/\b(appointment|appointments|visit|visits)\b/i', $normalized)) {
        $appointments = fetchAppointments($conn, $patientId, 5);
        if (!$appointments) {
            return 'You do not have any appointments yet.';
        }

        $lines = ['Your recent appointments:'];
        foreach ($appointments as $appointment) {
            $lines[] = '• ' . escapeText($appointment['doctor_name']) . ' — ' . escapeText($appointment['appointment_date']) . ' ' . escapeText(substr($appointment['appointment_time'], 0, 5)) . ' — ' . escapeText($appointment['status']);
        }
        return implode("\n", $lines);
    }

    if (preg_match('/\b(prescription|prescriptions)\b/i', $normalized)) {
        $prescriptions = fetchPrescriptions($conn, $patientName, 5);
        if (!$prescriptions) {
            return 'No prescriptions were found for your profile yet.';
        }

        $lines = ['Your recent prescriptions:'];
        foreach ($prescriptions as $prescription) {
            $lines[] = '• ' . escapeText($prescription['medicine_name']) . ' — Qty: ' . (int)$prescription['quantity'] . ' — Status: ' . escapeText($prescription['status']);
        }
        return implode("\n", $lines);
    }

    if (preg_match('/\b(lab|report|reports|result|results)\b/i', $normalized)) {
        $reports = fetchLabReports($conn, $patientId, 5);
        if (!$reports) {
            return 'No lab reports were found for your account yet.';
        }

        $lines = ['Your recent lab reports:'];
        foreach ($reports as $report) {
            $lines[] = '• ' . escapeText($report['test_type']) . ' — ' . escapeText($report['report_date']) . ' — ' . escapeText($report['result']);
        }
        return implode("\n", $lines);
    }

    if (preg_match('/\b(hello|hi|hey|help)\b/i', $normalized)) {
        return "Hello {$patientName}! I can help you book appointments, search doctors, view medicines, show your appointments, prescriptions, and lab reports.";
    }

    return "I can help with appointments, doctors, medicines, prescriptions, reports, and general hospital support. Try asking: 'Show my appointments' or 'Book appointment with Dr. Sarah on 2026-07-03 at 10:30'.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = trim($_POST['message']);
    $reply = buildReply($conn, $patientId, $patientName, $userMessage);

    $stmt = $conn->prepare("INSERT INTO chatbot_messages (patient_id, role, message) VALUES (?, 'user', ?)");
    $stmt->bind_param('is', $patientId, $userMessage);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO chatbot_messages (patient_id, role, message) VALUES (?, 'assistant', ?)");
    $stmt->bind_param('is', $patientId, $reply);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['reply' => $reply]);
    exit;
}

$history = [];
$historyResult = $conn->query("SELECT role, message FROM chatbot_messages WHERE patient_id = $patientId ORDER BY id ASC");
if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }
}

$quickReplies = [
    'Book an appointment',
    'Show my appointments',
    'Find doctors',
    'Show available medicines',
    'Show my lab reports'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hospital AI Assistant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --panel: #ffffff;
            --panel-2: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #2563eb;
            --accent-2: #38bdf8;
            --user: linear-gradient(135deg, #2563eb, #3b82f6);
            --assistant: #ffffff;
            --shadow: 0 20px 55px rgba(15, 23, 42, 0.12);
        }

        body[data-theme="dark"] {
            color-scheme: dark;
            --bg: #020617;
            --panel: #0f172a;
            --panel-2: #111c34;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #1e293b;
            --accent: #60a5fa;
            --accent-2: #38bdf8;
            --user: linear-gradient(135deg, #3b82f6, #6366f1);
            --assistant: #111c34;
            --shadow: 0 20px 55px rgba(2, 6, 23, 0.5);
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: Inter, 'Segoe UI', Arial, sans-serif;
        }

        .chat-page {
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-shell {
            width: min(960px, 100%);
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(56, 189, 248, 0.08));
        }

        .chat-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: white;
            font-weight: 800;
        }

        .chat-title h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .chat-title p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle, .back-link {
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            border-radius: 999px;
            padding: 8px 12px;
            text-decoration: none;
            font-size: 13px;
        }

        .chat-messages {
            height: 560px;
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.03), transparent);
        }

        .bubble-row {
            display: flex;
            margin-bottom: 12px;
        }

        .bubble-row.user {
            justify-content: flex-end;
        }

        .bubble {
            max-width: 78%;
            padding: 12px 14px;
            border-radius: 18px;
            line-height: 1.55;
            white-space: pre-wrap;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .bubble.assistant {
            background: var(--assistant);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .bubble.user {
            background: var(--user);
            color: #fff;
        }

        .typing {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 84px;
        }

        .typing span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            display: inline-block;
            animation: pulse 1s infinite ease-in-out;
        }

        .typing span:nth-child(2) { animation-delay: 0.15s; }
        .typing span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes pulse {
            0%, 80%, 100% { transform: scale(0.7); opacity: 0.6; }
            40% { transform: scale(1); opacity: 1; }
        }

        .quick-replies {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 20px 14px;
        }

        .quick-chip {
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
        }

        .chat-footer {
            display: flex;
            gap: 10px;
            padding: 14px 20px 20px;
            border-top: 1px solid var(--border);
            background: var(--panel);
        }

        .chat-footer input {
            flex: 1;
            border-radius: 999px;
            border: 1px solid var(--border);
            padding: 12px 16px;
            background: var(--panel-2);
            color: var(--text);
        }

        .chat-footer button {
            border-radius: 999px;
            padding: 0 18px;
            background: var(--accent);
            border: none;
            color: white;
            font-weight: 700;
        }

        .voice-btn {
            border-radius: 999px;
            padding: 0 14px;
            background: var(--panel-2);
            color: var(--text);
            border: 1px solid var(--border);
            font-size: 16px;
        }

        .voice-btn.listening {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #ef4444;
        }

        @media (max-width: 768px) {
            .chat-page {
                padding: 10px;
            }

            .chat-messages {
                height: 430px;
            }

            .bubble {
                max-width: 90%;
            }
        }
    </style>
</head>
<body class="chat-page" data-theme="light">

<div class="chat-shell">
    <div class="chat-header">
        <div class="chat-title">
            <div class="avatar">AI</div>
            <div>
                <h3>Hospital AI Assistant</h3>
                <p>Book visits, search doctors, and review care info</p>
            </div>
        </div>
        <div class="header-actions">
            <button type="button" class="theme-toggle" id="themeToggle">🌙</button>
            <a class="back-link" href="dashboard.php">Back</a>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <div class="bubble-row assistant">
            <div class="bubble assistant">Hello <?php echo escapeText($patientName); ?>! I can help you book appointments, search doctors, view medicines, and review appointments, prescriptions, and lab reports.</div>
        </div>

        <?php foreach ($history as $entry): ?>
            <div class="bubble-row <?php echo $entry['role'] === 'assistant' ? 'assistant' : 'user'; ?>">
                <div class="bubble <?php echo $entry['role'] === 'assistant' ? 'assistant' : 'user'; ?>"><?php echo nl2br(escapeText($entry['message'])); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="quick-replies">
        <?php foreach ($quickReplies as $reply): ?>
            <button type="button" class="quick-chip" data-message="<?php echo escapeText($reply); ?>"><?php echo escapeText($reply); ?></button>
        <?php endforeach; ?>
    </div>

    <form class="chat-footer" id="chatForm">
        <button type="button" class="voice-btn" id="voiceBtn" title="Speak your request">🎤</button>
        <input type="text" id="messageInput" placeholder="Ask anything about your care..." autocomplete="off" required>
        <button type="submit">Send</button>
    </form>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const themeToggle = document.getElementById('themeToggle');
const voiceBtn = document.getElementById('voiceBtn');

function setTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem('hospital-chat-theme', theme);
    themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
}

const savedTheme = localStorage.getItem('hospital-chat-theme') || 'light';
setTheme(savedTheme);

themeToggle.addEventListener('click', () => {
    const nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    setTheme(nextTheme);
});

function appendBubble(role, text) {
    const row = document.createElement('div');
    row.className = 'bubble-row ' + role;

    const bubble = document.createElement('div');
    bubble.className = 'bubble ' + role;
    bubble.textContent = text;

    row.appendChild(bubble);
    chatMessages.appendChild(row);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTyping() {
    const row = document.createElement('div');
    row.className = 'bubble-row assistant';
    const bubble = document.createElement('div');
    bubble.className = 'bubble assistant typing';
    bubble.innerHTML = '<span></span><span></span><span></span>';
    row.appendChild(bubble);
    chatMessages.appendChild(row);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return row;
}

document.querySelectorAll('.quick-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        sendMessage(chip.dataset.message);
    });
});

function sendMessage(messageText = null) {
    const message = (messageText || input.value || '').trim();
    if (!message) {
        return;
    }

    appendBubble('user', message);
    input.value = '';
    const typingRow = showTyping();

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: 'message=' + encodeURIComponent(message)
    })
    .then((response) => response.json())
    .then((data) => {
        typingRow.remove();
        appendBubble('assistant', data.reply || 'Sorry, I could not respond right now.');
    })
    .catch(() => {
        typingRow.remove();
        appendBubble('assistant', 'Sorry, something went wrong. Please try again.');
    });
}

form.addEventListener('submit', (event) => {
    event.preventDefault();
    sendMessage();
});

input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
});

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

if (SpeechRecognition) {
    const recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.lang = 'en-US';
    recognition.interimResults = false;

    voiceBtn.addEventListener('click', () => {
        if (voiceBtn.classList.contains('listening')) {
            recognition.stop();
            voiceBtn.classList.remove('listening');
            return;
        }

        recognition.start();
        voiceBtn.classList.add('listening');
    });

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        input.value = transcript;
        sendMessage(transcript);
        voiceBtn.classList.remove('listening');
    };

    recognition.onerror = (event) => {
        voiceBtn.classList.remove('listening');
        const reason = event.error === 'not-allowed'
            ? 'Microphone access was blocked. Please allow microphone permission in your browser and try again.'
            : 'Voice recognition is not available in this browser or was cancelled. You can still type your request manually.';
        appendBubble('assistant', reason);
    };

    recognition.onend = () => {
        voiceBtn.classList.remove('listening');
    };
} else {
    voiceBtn.title = 'Voice input is not supported in this browser';
    voiceBtn.disabled = true;
    voiceBtn.textContent = '🎤';
}
</script>

</body>
</html>