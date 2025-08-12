<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();

define('PAGE_TITLE', 'Exam Completed');
include '../includes/header.php';
?>
<div class="container mt-5">
  <div class="text-center">
    <?php if (isset($_GET['auto_submitted']) && $_GET['auto_submitted'] == '1'): ?>
      <div class="alert alert-warning mb-4">
        <h5><i class="fas fa-clock"></i> Exam Auto-Submitted</h5>
        <p class="mb-0">Your exam was automatically submitted because the time limit was reached.</p>
      </div>
    <?php endif; ?>
    <h3>Thank you! Your exam has been submitted.</h3>
    <?php if ($exam && $exam['allow_result_view']): ?>
      <p class="mt-3">Your score:</p>
      <?php
        $se = $pdo->prepare('SELECT score FROM student_exam WHERE student_id = ? AND exam_id = ?');
        $se->execute([$_SESSION['user_id'], $exam_id]);
        $score = $se->fetchColumn();
      ?>
      <div class="display-6"><?php echo $score !== false ? $score : 'Pending'; ?></div>
    <?php else: ?>
      <p class="text-muted mt-3">Results will be published later.</p>
    <?php endif; ?>
    <a class="btn btn-primary mt-4" href="index.php">Go to Dashboard</a>
  </div>
</div>
<script>try{ localStorage.removeItem('exam_answers_'+<?php echo (int)$exam_id; ?>); }catch(e){}</script>
<?php include '../includes/footer.php'; ?>