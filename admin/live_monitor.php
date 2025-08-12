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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Live Monitor: <?php echo htmlspecialchars($exam['title']); ?></h3>
        <div>
            <a href="live_monitor.php" class="btn btn-outline-secondary btn-sm">← Back to Exam List</a>
            <button class="btn btn-outline-primary btn-sm" onclick="toggleAutoRefresh()" id="refreshBtn">
                <span id="refreshStatus">Auto-refresh: ON</span>
            </button>
        </div>
    </div>
    
    <!-- Exam Status Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Exam Status</h6>
                    <span class="badge bg-<?php 
                        $now = time();
                        $start = strtotime($exam['start_time']);
                        $end = strtotime($exam['end_time']);
                        if ($now < $start) {
                            echo 'warning">Upcoming';
                        } elseif ($now > $end) {
                            echo 'secondary">Ended';
                        } else {
                            echo 'success">Active';
                        }
                    ?></span>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Duration</h6>
                    <span><?php echo (int)$exam['duration_minutes']; ?> minutes</span>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Start Time</h6>
                    <span><?php echo date('d M Y h:i A', strtotime($exam['start_time'])); ?></span>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">End Time</h6>
                    <span><?php echo date('d M Y h:i A', strtotime($exam['end_time'])); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Students Monitor -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Student Activity</h5>
            <small class="text-muted">Last updated: <span id="lastUpdate">Loading...</span></small>
        </div>
        <div class="card-body">
            <div id="noStudentsMessage" class="alert alert-info d-none">
                <h6><i class="fas fa-info-circle"></i> No Students Currently</h6>
                <p class="mb-0">No students have started this exam yet. Students will appear here once they begin.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="monitorTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Progress</th>
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
    </div>
</div>
<script>
let autoRefresh = true;
let refreshInterval;

async function loadMonitor(){
  try {
    const res = await fetch('live_monitor.php?exam_id=<?php echo $exam_id; ?>&as=json',{cache:'no-store'});
    const data = await res.json();
    const tbody = document.querySelector('#monitorTable tbody');
    const noStudentsMsg = document.getElementById('noStudentsMessage');
    
    tbody.innerHTML = '';
    
    if (data.length === 0) {
      noStudentsMsg.classList.remove('d-none');
      document.querySelector('#monitorTable').style.display = 'none';
    } else {
      noStudentsMsg.classList.add('d-none');
      document.querySelector('#monitorTable').style.display = 'table';
      
      data.forEach(r => {
        const tr = document.createElement('tr');
        const statusBadge = r.status === 'completed' ? 'success' : 
                           r.status === 'in_progress' ? 'info' : 'secondary';
        const statusText = r.status.replace('_', ' ');
        const progress = r.answered_count ? `${r.answered_count} questions` : 'Not started';
        const lastSeen = r.last_seen_at ? new Date(r.last_seen_at).toLocaleString() : '-';
        const startTime = r.start_time ? new Date(r.start_time).toLocaleString() : '-';
        const endTime = r.end_time ? new Date(r.end_time).toLocaleString() : '-';
        const score = r.score !== null ? r.score : '-';
        
        tr.innerHTML = `
          <td>${r.name}</td>
          <td>${r.email}</td>
          <td><span class="badge bg-${statusBadge}">${statusText}</span></td>
          <td>${progress}</td>
          <td><small>${lastSeen}</small></td>
          <td><small>${startTime}</small></td>
          <td><small>${endTime}</small></td>
          <td>${score}</td>
        `;
        tbody.appendChild(tr);
      });
    }
    
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
  } catch (error) {
    console.error('Failed to load monitor data:', error);
    document.getElementById('lastUpdate').textContent = 'Error loading data';
  }
}

function toggleAutoRefresh() {
  autoRefresh = !autoRefresh;
  const statusSpan = document.getElementById('refreshStatus');
  const btn = document.getElementById('refreshBtn');
  
  if (autoRefresh) {
    statusSpan.textContent = 'Auto-refresh: ON';
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-outline-primary');
    startAutoRefresh();
  } else {
    statusSpan.textContent = 'Auto-refresh: OFF';
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-outline-secondary');
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }
  }
}

function startAutoRefresh() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
  refreshInterval = setInterval(() => {
    if (autoRefresh) {
      loadMonitor();
    }
  }, 5000);
}

// Initial load
loadMonitor();
startAutoRefresh();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});
</script>
<?php include '../includes/footer.php'; ?>