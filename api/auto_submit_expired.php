<?php
require_once '../config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// This endpoint can be called by cron job or ajax to auto-submit expired exams
// For security, we might want to add some authentication later

try {
    $pdo->beginTransaction();
    
    // Find all exams that have ended but still have students with 'in_progress' status
    $expiredExamsStmt = $pdo->prepare('
        SELECT DISTINCT se.id as student_exam_id, se.exam_id, se.student_id
        FROM student_exam se 
        JOIN exams e ON e.id = se.exam_id 
        WHERE se.status = "in_progress" 
        AND NOW() > e.end_time
    ');
    $expiredExamsStmt->execute();
    $expiredStudentExams = $expiredExamsStmt->fetchAll();
    
    $submittedCount = 0;
    
    foreach ($expiredStudentExams as $se) {
        $student_exam_id = $se['student_exam_id'];
        $exam_id = $se['exam_id'];
        
        // Auto-grade this student's exam
        $answers = $pdo->prepare('SELECT a.question_id, a.selected_option FROM answers a WHERE a.student_exam_id = ?');
        $answers->execute([$student_exam_id]);
        $ans = $answers->fetchAll();
        $map = [];
        foreach ($ans as $r) { 
            $map[(int)$r['question_id']] = $r['selected_option']; 
        }
        
        $q = $pdo->prepare('SELECT id, correct_option, marks FROM questions WHERE exam_id = ?');
        $q->execute([$exam_id]);
        $score = 0.0;
        foreach ($q as $qr) {
            $qid = (int)$qr['id'];
            if (isset($map[$qid]) && $map[$qid] === $qr['correct_option']) {
                $score += (float)$qr['marks'];
            }
        }
        
        // Update student_exam to completed with calculated score
        $updateStmt = $pdo->prepare('UPDATE student_exam SET status = "completed", end_time = NOW(), score = ? WHERE id = ?');
        $updateStmt->execute([$score, $student_exam_id]);
        
        $submittedCount++;
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'submitted_count' => $submittedCount,
        'message' => "Auto-submitted $submittedCount expired exams"
    ]);
    
} catch (Exception $e) {
    $pdo->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>