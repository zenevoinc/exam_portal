<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// Create exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $duration_minutes = (int) $_POST['duration_minutes'];

    if ($title === '' || $start_time === '' || $end_time === '' || $duration_minutes <= 0) {
        $error = 'Please fill all required fields with valid values.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO exams (title, description, start_time, end_time, duration_minutes, created_by) VALUES (?,?,?,?,?,?)');
        if ($stmt->execute([$title, $description, $start_time, $end_time, $duration_minutes, $_SESSION['user_id']])) {
            $message = 'Exam created successfully.';
        } else {
            $error = 'Failed to create exam.';
        }
    }
}

// Toggle result view
if (isset($_GET['toggle_result']) && isset($_GET['id'])) {
    $exam_id = (int) $_GET['id'];
    $pdo->prepare('UPDATE exams SET allow_result_view = 1 - allow_result_view WHERE id = ?')->execute([$exam_id]);
    header('Location: manage_exams.php');
    exit();
}

// Delete exam and all related data
if (isset($_GET['delete_exam']) && isset($_GET['id'])) {
    $exam_id = (int) $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete in correct order to maintain referential integrity
        
        // First, get all student_exam IDs to delete related answers
        $stmt = $pdo->prepare('SELECT id FROM student_exam WHERE exam_id = ?');
        $stmt->execute([$exam_id]);
        $studentExamIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete answers for this exam
        if (!empty($studentExamIds)) {
            $placeholders = str_repeat('?,', count($studentExamIds) - 1) . '?';
            $pdo->prepare("DELETE FROM answers WHERE student_exam_id IN ($placeholders)")->execute($studentExamIds);
        }
        
        // Delete student exam records
        $pdo->prepare('DELETE FROM student_exam WHERE exam_id = ?')->execute([$exam_id]);
        
        // Delete questions for this exam
        $pdo->prepare('DELETE FROM questions WHERE exam_id = ?')->execute([$exam_id]);
        
        // Finally, delete the exam itself
        $pdo->prepare('DELETE FROM exams WHERE id = ?')->execute([$exam_id]);
        
        $pdo->commit();
        $message = 'Exam and all related data deleted successfully.';
    } catch (Exception $e) {
        $pdo->rollback();
        $error = 'Failed to delete exam: ' . $e->getMessage();
    }
}

$exams = $pdo->query('SELECT * FROM exams ORDER BY created_at DESC')->fetchAll();

define('PAGE_TITLE', 'Manage Exams');
include '../includes/header.php';
include 'partials/navbar.php';
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4>Create Exam</h4></div>
                <div class="card-body">
                    <?php if ($message && !$error): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="datetime-local" class="form-control" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Time</label>
                            <input type="datetime-local" class="form-control" name="end_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" name="duration_minutes" min="1" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="create_exam" class="btn btn-primary">Create Exam</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Exams</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Schedule</th>
                                    <th>Duration</th>
                                    <th>Results</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$exams): ?>
                                    <tr><td colspan="5" class="text-center">No exams created yet.</td></tr>
                                <?php else: foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td>
                                            <?php echo date('d M Y h:i A', strtotime($exam['start_time'])); ?>
                                            &ndash;
                                            <?php echo date('d M Y h:i A', strtotime($exam['end_time'])); ?>
                                        </td>
                                        <td><?php echo (int)$exam['duration_minutes']; ?> min</td>
                                        <td>
                                            <span class="badge bg-<?php echo $exam['allow_result_view'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $exam['allow_result_view'] ? 'Visible' : 'Hidden'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="upload_questions.php?exam_id=<?php echo $exam['id']; ?>">Upload Questions</a>
                                            <a class="btn btn-sm btn-outline-warning" href="?toggle_result=1&id=<?php echo $exam['id']; ?>">Toggle Results</a>
                                            <a class="btn btn-sm btn-outline-success" href="live_monitor.php?exam_id=<?php echo $exam['id']; ?>">Monitor</a>
                                            <a class="btn btn-sm btn-outline-info" href="export_results.php?exam_id=<?php echo $exam['id']; ?>">Export</a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars($exam['title'], ENT_QUOTES); ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(examId, examTitle) {
    if (confirm(`Are you sure you want to delete the exam "${examTitle}"?\n\nThis will permanently delete:\n- The exam\n- All questions\n- All student results\n- All student answers\n\nThis action cannot be undone!`)) {
        window.location.href = `?delete_exam=1&id=${examId}`;
    }
}
</script>

<?php include '../includes/footer.php'; ?>