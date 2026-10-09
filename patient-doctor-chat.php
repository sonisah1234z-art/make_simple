<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (isset($_SESSION['doctor_id'])) {
    require_doctor();
    $currentRole = 'Doctor';
    $doctorId = (int)($_SESSION['doctor_id'] ?? 0);
    $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $profileBackLink = 'doctor-chat-conversations.php';
} elseif (isset($_SESSION['patient_id'])) {
    require_patient();
    $currentRole = 'Patient';
    $patientId = (int)($_SESSION['patient_id'] ?? 0);
    $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
    $profileBackLink = 'patient-chat-conversations.php';
} else {
    header('Location: login.php');
    exit();
}

if ($patientId <= 0 || $doctorId <= 0) {
    header('Location: ' . $profileBackLink);
    exit();
}

$appointment = null;
$stmt = $conn->prepare("SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status,
                        p.name AS patient_name, p.patient_code, p.date_of_birth, p.gender, p.department,
                        d.name AS doctor_name, d.specialty, d.department AS doctor_department
                        FROM appointments a
                        JOIN patients p ON p.id = a.patient_id
                        JOIN doctors d ON d.id = a.doctor_id
                        WHERE a.patient_id = ? AND a.doctor_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('ii', $patientId, $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$appointment) {
    header('Location: ' . $profileBackLink);
    exit();
}

function calculate_age(string $dob): string
{
    if ($dob === '' || $dob === null) {
        return 'N/A';
    }

    $date = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$date) {
        return 'N/A';
    }

    return (string)((new DateTime('today'))->diff($date)->y);
}

$displayPatientName = $appointment['patient_name'];
$displayDoctorName = $appointment['doctor_name'];
$patientCode = $appointment['patient_code'] ?? ('P-' . str_pad((string)$patientId, 4, '0', STR_PAD_LEFT));
$patientAge = calculate_age((string)($appointment['date_of_birth'] ?? ''));
$patientGender = $appointment['gender'] ?? 'Not specified';
$appointmentDate = $appointment['appointment_date'] ?? 'N/A';
$appointmentTime = substr((string)($appointment['appointment_time'] ?? '00:00:00'), 0, 5);
$appointmentStatus = $appointment['status'] ?? 'Pending';
$patientDepartment = $appointment['department'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Doctor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; color: #1f2937; font-family: Arial, sans-serif; }
        .container { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
        .header-card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06); padding: 20px 24px; margin-bottom: 18px; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .meta-item { background: #f8fafc; border-radius: 12px; padding: 12px 14px; }
        .meta-item strong { display: block; margin-bottom: 4px; color: #475569; }
        .chat-card { background: white; border-radius: 18px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06); padding: 18px; }
        .messages { max-height: 420px; overflow-y: auto; padding: 10px; background: #f8fafc; border-radius: 14px; }
        .message { max-width: 75%; margin: 8px 0; padding: 12px 14px; border-radius: 14px; line-height: 1.5; }
        .message.me { margin-left: auto; background: #2563eb; color: white; }
        .message.other { background: #e2e8f0; color: #1f2937; }
        .message .time { display: block; font-size: 11px; opacity: 0.8; margin-top: 6px; }
        .send-form { display: flex; gap: 12px; margin-top: 18px; }
        .send-form textarea { flex: 1; min-height: 72px; resize: vertical; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .badges { display: flex; gap: 10px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="mb-1">Patient Name: <?php echo htmlspecialchars($displayPatientName); ?></h2>
                    <div class="muted">Conversation for <?php echo htmlspecialchars($currentRole === 'Doctor' ? 'doctor view' : 'patient view'); ?></div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo $profileBackLink; ?>" class="btn btn-outline-secondary">Back</a>
                    <?php if ($currentRole === 'Doctor'): ?>
                        <a href="patient_profile.php?id=<?php echo (int)$patientId; ?>" class="btn btn-primary">View Patient Profile</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-item"><strong>Patient ID</strong><?php echo htmlspecialchars($patientCode); ?></div>
                <div class="meta-item"><strong>Age</strong><?php echo htmlspecialchars($patientAge); ?></div>
                <div class="meta-item"><strong>Gender</strong><?php echo htmlspecialchars($patientGender); ?></div>
                <div class="meta-item"><strong>Department</strong><?php echo htmlspecialchars($patientDepartment); ?></div>
                <div class="meta-item"><strong>Appointment</strong><?php echo htmlspecialchars($appointmentDate); ?></div>
                <div class="meta-item"><strong>Time</strong><?php echo htmlspecialchars($appointmentTime); ?></div>
                <div class="meta-item"><strong>Status</strong>
                    <?php
                        $statusClass = strtolower($appointmentStatus) === 'approved' ? 'badge-approved' : (strtolower($appointmentStatus) === 'cancelled' ? 'badge-cancelled' : 'badge-pending');
                    ?>
                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($appointmentStatus); ?></span>
                </div>
            </div>
        </div>

        <div class="chat-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h4 class="mb-0"><?php echo htmlspecialchars($currentRole === 'Doctor' ? $displayPatientName : $displayDoctorName); ?></h4>
                <div class="muted">Patient & doctor chat is isolated to this appointment.</div>
            </div>
            <div id="messages" class="messages"></div>
            <form id="chatForm" class="send-form">
                <textarea id="messageInput" placeholder="Type your message..." required></textarea>
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        </div>
    </div>

    <script>
        const patientId = <?php echo (int)$patientId; ?>;
        const doctorId = <?php echo (int)$doctorId; ?>;
        const currentRole = '<?php echo htmlspecialchars($currentRole); ?>';
        const messagesContainer = document.getElementById('messages');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');

        function formatTime(value) {
            if (!value) return '';
            try {
                const date = new Date(value.replace(' ', 'T'));
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (error) {
                return value.substr(11, 5);
            }
        }

        function renderMessages(messages) {
            if (!Array.isArray(messages)) {
                messages = [];
            }

            messagesContainer.innerHTML = '';
            if (messages.length === 0) {
                messagesContainer.innerHTML = '<div class="text-muted text-center py-4">No messages yet. Start the conversation.</div>';
                return;
            }

            messages.forEach((msg) => {
                const isMine = currentRole === msg.sender_role;
                const messageEl = document.createElement('div');
                messageEl.className = 'message ' + (isMine ? 'me' : 'other');
                messageEl.innerHTML = '<div>' + escapeHtml(msg.message) + '</div><span class="time">' + escapeHtml(msg.sender_role) + ' • ' + formatTime(msg.created_at) + '</span>';
                messagesContainer.appendChild(messageEl);
            });

            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function loadMessages() {
            const params = new URLSearchParams({ action: 'messages', patient_id: patientId, doctor_id: doctorId });
            const response = await fetch('chat-api.php?' + params.toString());
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            renderMessages(data.messages || []);
        }

        chatForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const message = messageInput.value.trim();
            if (!message) {
                return;
            }

            const form = new URLSearchParams();
            form.append('action', 'send');
            form.append('patient_id', patientId);
            form.append('doctor_id', doctorId);
            form.append('message', message);

            const response = await fetch('chat-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: form.toString()
            });

            if (response.ok) {
                messageInput.value = '';
                await loadMessages();
            }
        });

        loadMessages();
        setInterval(loadMessages, 5000);
    </script>
</body>
</html>
