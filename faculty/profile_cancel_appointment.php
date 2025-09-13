<?php
// profile_cancel_appointment.php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($_SESSION)) session_start();
$student_id = $_SESSION['student_row_id'] ?? null;
$date = $data['date'] ?? null;
$time = $data['time'] ?? null;
$reason = $data['reason'] ?? null;

if (!$student_id || !$date || !$time || !$reason) {
    echo json_encode(['success' => false, 'error' => 'Missing data.']);
    exit;
}

try {
    // Update the appointment status to cancelled (only if currently pending)
    $stmt = $db->prepare('UPDATE appointments SET status = "cancelled" WHERE student_id = ? AND date = ? AND time = ? AND reason = ? AND status = "pending" LIMIT 1');
    $stmt->execute([$student_id, $date, $time, $reason]);
    $success = $stmt->rowCount() > 0;
    echo json_encode(['success' => $success]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB error.']);
}
