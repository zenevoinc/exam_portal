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
    http_response_code(400);
    echo json_encode([
        'ok' => false, 
        'error' => 'Invalid parameters',
        'details' => [
            'student_exam_id' => $student_exam_id ? 'valid' : 'missing',
            'question_id' => $question_id ? 'valid' : 'missing',
            'selected_option' => in_array($selected, ['a','b','c','d'], true) ? 'valid' : 'invalid'
        ]
    ]);
    exit();
}
// Ensure ownership
$own = $pdo->prepare('SELECT id FROM student_exam WHERE id = ? AND student_id = ?');
$own->execute([$student_exam_id, $_SESSION['user_id']]);
if (!$own->fetch()) { 
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'Access denied - you can only save answers for your own exam']); 
    exit(); 
}

try {
    $pdo->beginTransaction();
    
    // Save the answer
    $up = $pdo->prepare('INSERT INTO answers (student_exam_id, question_id, selected_option) VALUES (?,?,?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), saved_at = CURRENT_TIMESTAMP');
    $up->execute([$student_exam_id, $question_id, $selected]);
    
    // Update answer count and progress
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE student_exam_id = ?');
    $cnt->execute([$student_exam_id]);
    $answered = (int)$cnt->fetchColumn();
    
    $updateStmt = $pdo->prepare('UPDATE student_exam SET answered_count = ?, last_seen_at = NOW(), current_question_id = ? WHERE id = ?');
    $updateStmt->execute([$answered, $question_id, $student_exam_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'ok' => true,
        'answered_count' => $answered,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error occurred',
        'message' => 'Failed to save answer. Please try again.'
    ]);
    error_log('Save answer error: ' . $e->getMessage());
}