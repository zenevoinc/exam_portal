<?php
require_once '../config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error'=>'forbidden']);
    exit();
}
$input = json_decode(file_get_contents('php://input'), true);
$student_exam_id = (int)($input['student_exam_id'] ?? 0);
$current_question_id = (int)($input['current_question_id'] ?? 0);
$answered_count = (int)($input['answered_count'] ?? 0);
if ($student_exam_id) {
    $pdo->prepare('UPDATE student_exam SET current_question_id = ?, answered_count = ?, last_seen_at = NOW(), status = IF(status=\'not_started\',\'in_progress\',status) WHERE id = ? AND student_id = ?')->execute([$current_question_id, $answered_count, $student_exam_id, $_SESSION['user_id']]);
}
echo json_encode(['ok'=>true]);