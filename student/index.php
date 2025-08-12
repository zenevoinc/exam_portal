<?php
require_once '../config.php';
require_once '../includes/db.php';

// Security: Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$now = date('Y-m-d H:i:s');
$exams = $pdo->query("SELECT * FROM exams ORDER BY start_time ASC")->fetchAll();

define('PAGE_TITLE', 'Student Dashboard');
include '../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="#"><?php echo SITE_TITLE; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-light" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h1 class="mb-4">Student Dashboard</h1>

    <div class="row g-3">
        <?php if (!$exams): ?>
            <div class="col-12"><div class="alert alert-info">No exams scheduled.</div></div>
        <?php else: foreach ($exams as $exam):
            $startTs = strtotime($exam['start_time']);
            $endTs = strtotime($exam['end_time']);
            $open = time() >= $startTs && time() <= $endTs; ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h5>
                        <p class="card-text text-muted mb-1">Starts: <?php echo date('d M Y h:i A', $startTs); ?></p>
                        <p class="card-text text-muted">Ends: <?php echo date('d M Y h:i A', $endTs); ?> | Duration: <?php echo (int)$exam['duration_minutes']; ?> min</p>
                        <?php if ($open): ?>
                            <a class="btn btn-primary" href="exam.php?exam_id=<?php echo $exam['id']; ?>">Start / Resume</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>Opens Soon</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>