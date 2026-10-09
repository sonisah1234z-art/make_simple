<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

function api_response(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $payload);
    exit;
}

function get_current_chat_role(): ?string
{
    if (isset($_SESSION['doctor_id'])) {
        return 'Doctor';
    }
    if (isset($_SESSION['patient_id'])) {
        return 'Patient';
    }
    return null;
}

function get_current_chat_user_id(): ?int
{
    if (isset($_SESSION['doctor_id'])) {
        return (int)$_SESSION['doctor_id'];
    }
    if (isset($_SESSION['patient_id'])) {
        return (int)$_SESSION['patient_id'];
    }
    return null;
}

function ensure_conversation_authorized(mysqli $conn, int $patientId, int $doctorId): bool
{
    $role = get_current_chat_role();
    $userId = get_current_chat_user_id();
    if ($role === null || $userId === null) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $patientId, $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        return false;
    }

    if ($role === 'Doctor') {
        return $doctorId === $userId;
    }

    return $patientId === $userId;
}

function mark_conversation_read(mysqli $conn, int $patientId, int $doctorId, string $role): void
{
    if ($role === 'Doctor') {
        $sql = 'UPDATE chat_messages SET is_read = 1 WHERE patient_id = ? AND doctor_id = ? AND sender_role = ? AND is_read = 0';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $senderRole = 'Patient';
            $stmt->bind_param('iis', $patientId, $doctorId, $senderRole);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $sql = 'UPDATE chat_messages SET is_read = 1 WHERE patient_id = ? AND doctor_id = ? AND sender_role = ? AND is_read = 0';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $senderRole = 'Doctor';
        $stmt->bind_param('iis', $patientId, $doctorId, $senderRole);
        $stmt->execute();
        $stmt->close();
    }
}

$action = $_REQUEST['action'] ?? '';

if (!$action) {
    api_response(false, ['error' => 'No action specified'], 400);
}

$patientId = isset($_REQUEST['patient_id']) ? (int)$_REQUEST['patient_id'] : 0;
$doctorId = isset($_REQUEST['doctor_id']) ? (int)$_REQUEST['doctor_id'] : 0;
$role = get_current_chat_role();
$userId = get_current_chat_user_id();

if ($role === null || $userId === null) {
    api_response(false, ['error' => 'Unauthorized'], 401);
}

if ($patientId <= 0 || $doctorId <= 0) {
    api_response(false, ['error' => 'Invalid conversation'], 400);
}

if (!ensure_conversation_authorized($conn, $patientId, $doctorId)) {
    api_response(false, ['error' => 'Conversation not available to this user'], 403);
}

if ($action === 'messages') {
    $messageSql = 'SELECT id, patient_id, doctor_id, sender_id, sender_role, message, is_read, created_at FROM chat_messages WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at ASC';
    $stmt = $conn->prepare($messageSql);
    if (!$stmt) {
        api_response(false, ['error' => $conn->error], 500);
    }
    $stmt->bind_param('ii', $patientId, $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'patient_id' => (int)$row['patient_id'],
            'doctor_id' => (int)$row['doctor_id'],
            'sender_id' => (int)$row['sender_id'],
            'sender_role' => $row['sender_role'],
            'message' => $row['message'],
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at'],
        ];
    }
    $stmt->close();

    mark_conversation_read($conn, $patientId, $doctorId, $role);

    api_response(true, ['messages' => $messages]);
}

if ($action === 'send') {
    $messageText = trim((string)($_POST['message'] ?? ''));
    if ($messageText === '') {
        api_response(false, ['error' => 'Message cannot be empty'], 400);
    }

    $senderRole = $role;
    $senderId = $userId;

    $insertSql = 'INSERT INTO chat_messages (patient_id, doctor_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        api_response(false, ['error' => $conn->error], 500);
    }
    $stmt->bind_param('iiiss', $patientId, $doctorId, $senderId, $senderRole, $messageText);
    if (!$stmt->execute()) {
        $stmt->close();
        api_response(false, ['error' => $stmt->error], 500);
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    $messageRow = [
        'id' => (int)$newId,
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'sender_id' => $senderId,
        'sender_role' => $senderRole,
        'message' => $messageText,
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    api_response(true, ['message' => $messageRow]);
}

if ($action === 'conversations') {
    if ($role === 'Doctor') {
        $sql = "SELECT a.patient_id, a.doctor_id, p.name AS patient_name, p.patient_code, p.date_of_birth, p.gender, p.department,
                a.appointment_date, a.appointment_time, a.status,
                (SELECT message FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id ORDER BY cm.created_at DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id AND cm.sender_role = 'Patient' AND cm.is_read = 0) AS unread_count
                FROM appointments a
                JOIN patients p ON p.id = a.patient_id
                WHERE a.doctor_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        api_response(true, ['conversations' => $rows]);
    }

    $sql = "SELECT a.patient_id, a.doctor_id, d.name AS doctor_name, d.specialty, d.department AS doctor_department,
            a.appointment_date, a.appointment_time, a.status,
            (SELECT message FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id ORDER BY cm.created_at DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.patient_id = a.patient_id AND cm.doctor_id = a.doctor_id AND cm.sender_role = 'Doctor' AND cm.is_read = 0) AS unread_count
            FROM appointments a
            JOIN doctors d ON d.id = a.doctor_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    api_response(true, ['conversations' => $rows]);
}

api_response(false, ['error' => 'Unsupported action'], 400);
