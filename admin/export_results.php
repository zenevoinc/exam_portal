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