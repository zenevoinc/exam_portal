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
$question_id = (int)($input['question_id'] ?? 0);
$selected = $input['selected_option'] ?? '';
if (!$student_exam_id || !$question_id || !in_array($selected, ['a','b','c','d'], true)) {
    echo json_encode(['ok'=>false]);
    exit();
}
// Ensure ownership
$own = $pdo->prepare('SELECT id FROM student_exam WHERE id = ? AND student_id = ?');
$own->execute([$student_exam_id, $_SESSION['user_id']]);
if (!$own->fetch()) { echo json_encode(['ok'=>false]); exit(); }

$pdo->beginTransaction();
$up = $pdo->prepare('INSERT INTO answers (student_exam_id, question_id, selected_option) VALUES (?,?,?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), saved_at = CURRENT_TIMESTAMP');
$up->execute([$student_exam_id, $question_id, $selected]);
$cnt = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE student_exam_id = ?');
$cnt->execute([$student_exam_id]);
$answered = (int)$cnt->fetchColumn();
$pdo->prepare('UPDATE student_exam SET answered_count = ?, last_seen_at = NOW(), current_question_id = ? WHERE id = ?')->execute([$answered, $question_id, $student_exam_id]);
$pdo->commit();

echo json_encode(['ok'=>true,'answered_count'=>$answered]);