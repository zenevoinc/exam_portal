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
if (!$student_exam_id) { echo json_encode(['ok'=>false]); exit(); }

$se = $pdo->prepare('SELECT se.id, se.exam_id FROM student_exam se WHERE se.id = ? AND se.student_id = ?');
$se->execute([$student_exam_id, $_SESSION['user_id']]);
$se = $se->fetch();
if (!$se) { echo json_encode(['ok'=>false]); exit(); }

// Auto-grade
$answers = $pdo->prepare('SELECT a.question_id, a.selected_option FROM answers a WHERE a.student_exam_id = ?');
$answers->execute([$student_exam_id]);
$ans = $answers->fetchAll();
$map = [];
foreach ($ans as $r) { $map[(int)$r['question_id']] = $r['selected_option']; }
$q = $pdo->prepare('SELECT id, correct_option, marks FROM questions WHERE exam_id = ?');
$q->execute([$se['exam_id']]);
$score = 0.0;
foreach ($q as $qr) {
    $qid = (int)$qr['id'];
    if (isset($map[$qid]) && $map[$qid] === $qr['correct_option']) {
        $score += (float)$qr['marks'];
    }
}
$pdo->prepare('UPDATE student_exam SET status = \"completed\", end_time = NOW(), score = ? WHERE id = ?')->execute([$score, $student_exam_id]);

echo json_encode(['ok'=>true,'score'=>$score]);