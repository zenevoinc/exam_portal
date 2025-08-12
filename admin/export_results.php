<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['exam_id'])) {
    $exams = $pdo->query('SELECT id,title FROM exams ORDER BY created_at DESC')->fetchAll();
    define('PAGE_TITLE','Export Results');
    include '../includes/header.php';
    include 'partials/navbar.php';
    echo '<div class="container mt-4"><div class="card"><div class="card-body">';
    echo '<form method="get"><div class="mb-3"><label class="form-label">Select Exam</label><select name="exam_id" class="form-select">';
    foreach ($exams as $e) echo '<option value="'.$e['id'].'">'.htmlspecialchars($e['title']).'</option>';
    echo '</select></div><button class="btn btn-primary">Download CSV</button></form>';
    echo '</div></div></div>';
    include '../includes/footer.php';
    exit();
}

$exam_id = (int) $_GET['exam_id'];

// Auto-submit any expired exams before exporting
if ($exam_id > 0) {
    // Check if this exam has ended
    $examCheck = $pdo->prepare('SELECT end_time FROM exams WHERE id = ?');
    $examCheck->execute([$exam_id]);
    $exam = $examCheck->fetch();
    
    if ($exam && strtotime($exam['end_time']) < time()) {
        // Exam has ended, auto-submit any remaining in-progress exams
        $expiredStmt = $pdo->prepare('
            SELECT se.id as student_exam_id, se.exam_id 
            FROM student_exam se 
            WHERE se.exam_id = ? AND se.status = "in_progress"
        ');
        $expiredStmt->execute([$exam_id]);
        $expiredStudentExams = $expiredStmt->fetchAll();
        
        foreach ($expiredStudentExams as $se) {
            $student_exam_id = $se['student_exam_id'];
            
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
            
            // Update student_exam to completed
            $updateStmt = $pdo->prepare('UPDATE student_exam SET status = "completed", end_time = NOW(), score = ? WHERE id = ?');
            $updateStmt->execute([$score, $student_exam_id]);
        }
    }
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="results.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Seat Number','Name','Email','Status','Score']);
if ($exam_id > 0) {
    $stmt = $pdo->prepare('SELECT u.seat_number, u.name, u.email, se.status, se.score FROM student_exam se JOIN users u ON u.id = se.student_id WHERE se.exam_id = ?');
    $stmt->execute([$exam_id]);
} else {
    $stmt = $pdo->query('SELECT u.seat_number, u.name, u.email, se.status, se.score FROM student_exam se JOIN users u ON u.id = se.student_id');
}
foreach ($stmt as $r) { fputcsv($out, [$r['seat_number'],$r['name'],$r['email'],$r['status'],$r['score']]); }
fclose($out);