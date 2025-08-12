<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if ($exam_id <= 0) {
    die('Invalid exam.');
}

// Lightweight JSON endpoint
if (isset($_GET['as']) && $_GET['as'] === 'json') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('SELECT se.id, u.name, u.email, se.status, se.answered_count, se.last_seen_at, se.start_time, se.end_time, se.score FROM student_exam se JOIN users u ON u.id = se.student_id WHERE se.exam_id = ? ORDER BY u.name');
    $stmt->execute([$exam_id]);
    echo json_encode($stmt->fetchAll());
    exit();
}

$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();
if (!$exam) { die('Exam not found'); }

define('PAGE_TITLE', 'Live Monitor');
include '../includes/header.php';
include 'partials/navbar.php';
?>
<div class="container mt-4">
    <h3 class="mb-3">Live Monitor: <?php echo htmlspecialchars($exam['title']); ?></h3>
    <div class="table-responsive">
        <table class="table table-striped" id="monitorTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Answered</th>
                    <th>Last Seen</th>
                    <th>Started</th>
                    <th>Ended</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
async function loadMonitor(){
  const res = await fetch('live_monitor.php?exam_id=<?php echo $exam_id; ?>&as=json',{cache:'no-store'});
  const data = await res.json();
  const tbody = document.querySelector('#monitorTable tbody');
  tbody.innerHTML = '';
  data.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${r.name}</td><td>${r.email}</td><td><span class="badge bg-${r.status==='completed'?'success':(r.status==='in_progress'?'info':'secondary')}">${r.status.replace('_',' ')}</span></td><td>${r.answered_count}</td><td>${r.last_seen_at??''}</td><td>${r.start_time??''}</td><td>${r.end_time??''}</td><td>${r.score??''}</td>`;
    tbody.appendChild(tr);
  });
}
loadMonitor();
setInterval(loadMonitor, 5000);
</script>
<?php include '../includes/footer.php'; ?>