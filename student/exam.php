<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if ($exam_id <= 0) { die('Invalid exam'); }

// Ensure student_exam row exists
$pdo->beginTransaction();
$sel = $pdo->prepare('SELECT * FROM student_exam WHERE student_id = ? AND exam_id = ? FOR UPDATE');
$sel->execute([$_SESSION['user_id'], $exam_id]);
$se = $sel->fetch();
if (!$se) {
    $pdo->prepare('INSERT INTO student_exam (student_id, exam_id, status, start_time, last_seen_at) VALUES (?,?,?,?,NOW())')
        ->execute([$_SESSION['user_id'], $exam_id, 'in_progress', date('Y-m-d H:i:s')]);
    $sel->execute([$_SESSION['user_id'], $exam_id]);
    $se = $sel->fetch();
}
$pdo->commit();

$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();
if (!$exam) { die('Exam not found'); }
// Enforce schedule window
$now = time();
if ($now < strtotime($exam['start_time']) || $now > strtotime($exam['end_time'])) {
    die('Exam is not available at this time.');
}

// Load questions for a randomly assigned set stored in session per exam
$sessionKey = 'exam_set_' . $exam_id;
if (!isset($_SESSION[$sessionKey])) {
    $sets = ['A','B','C'];
    $_SESSION[$sessionKey] = $sets[array_rand($sets)];
}
$setCode = $_SESSION[$sessionKey];

$qStmt = $pdo->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE exam_id = ? AND set_code = ? ORDER BY id ASC');
$qStmt->execute([$exam_id, $setCode]);
$questions = $qStmt->fetchAll();

// Validate questions exist
if (empty($questions)) {
    define('PAGE_TITLE', 'No Questions Available');
    include '../includes/header.php';
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h4><i class="fas fa-exclamation-triangle"></i> No Questions Available</h4>
                    </div>
                    <div class="card-body text-center">
                        <h5><?php echo htmlspecialchars($exam['title']); ?></h5>
                        <div class="alert alert-warning">
                            <p>No questions are available for Set <?php echo htmlspecialchars($setCode); ?> of this exam.</p>
                            <p>Please contact your administrator or try again later.</p>
                        </div>
                        <a href="../student/index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; exit(); ?>
<?php }

// Existing answers for resuming
$aStmt = $pdo->prepare('SELECT question_id, selected_option FROM answers WHERE student_exam_id = ?');
$aStmt->execute([$se['id']]);
$existing = [];
foreach ($aStmt as $row) { $existing[(int)$row['question_id']] = $row['selected_option']; }

define('PAGE_TITLE', 'Exam');
include '../includes/header.php';
?>
<style>
.exam-container {max-width: 1000px; margin: 0 auto;}
.question-card {min-height: 260px;}
</style>
<div class="container-fluid bg-light py-3">
  <div class="exam-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?> <small class="text-muted">(Set <?php echo $setCode; ?>)</small></h4>
        <small class="text-muted">Duration: <?php echo (int)$exam['duration_minutes']; ?> minutes</small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-dark p-2">Time Left: <span id="timeLeft"></span></span>
        <span id="saveStatus" class="badge bg-secondary p-2 d-none">Saved</span>
        <button class="btn btn-sm btn-outline-secondary" id="fullscreenBtn">Fullscreen</button>
      </div>
    </div>

    <div class="card question-card shadow-sm">
      <div class="card-body">
        <div id="questionText" class="mb-3"></div>
        <form id="optionsForm" class="mb-2">
          <div class="form-check"><input class="form-check-input" type="radio" name="option" id="optA" value="a"><label class="form-check-label" for="optA" id="labelA"></label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="option" id="optB" value="b"><label class="form-check-label" for="optB" id="labelB"></label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="option" id="optC" value="c"><label class="form-check-label" for="optC" id="labelC"></label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="option" id="optD" value="d"><label class="form-check-label" for="optD" id="labelD"></label></div>
        </form>
        <div class="d-flex justify-content-between">
          <button class="btn btn-outline-secondary" id="prevBtn">Previous</button>
          <div><span id="progressText"></span></div>
          <button class="btn btn-primary" id="nextBtn">Next</button>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <div class="d-flex flex-wrap gap-2" id="navButtons"></div>
      <button class="btn btn-success mt-3" id="finishBtn">Finish Exam</button>
    </div>
  </div>
</div>
<script>
// Disable right-click
window.addEventListener('contextmenu', e=>e.preventDefault());

document.body.setAttribute('data-in-exam','1');

const questions = <?php echo json_encode($questions, JSON_UNESCAPED_UNICODE); ?>;
let existing = <?php echo json_encode($existing); ?>;
const studentExamId = <?php echo (int)$se['id']; ?>;
const durationMinutes = <?php echo (int)$exam['duration_minutes']; ?>;
let currentIndex = 0;
let remainingSeconds = durationMinutes * 60;

// Load from localStorage backup
try {
  const stored = localStorage.getItem('exam_answers_'+studentExamId);
  const progress = localStorage.getItem('exam_progress_'+studentExamId);
  
  if (stored) {
    const parsed = JSON.parse(stored);
    existing = Object.assign(existing, parsed);
    console.log('Restored', Object.keys(parsed).length, 'saved answers from local backup');
  }
  
  if (progress) {
    const progressData = JSON.parse(progress);
    // Restore to last position if it was recent (within last hour)
    if (progressData.lastSaved && (Date.now() - progressData.lastSaved) < 3600000) {
      currentIndex = progressData.currentIndex || 0;
      console.log('Restored exam position to question', currentIndex + 1);
    }
  }
} catch(e) {
  console.error('Error restoring from localStorage:', e);
}

function renderQuestion(){
  const q = questions[currentIndex];
  document.getElementById('questionText').textContent = (currentIndex+1)+'. '+q.question_text;
  document.getElementById('labelA').textContent = q.option_a;
  document.getElementById('labelB').textContent = q.option_b;
  document.getElementById('labelC').textContent = q.option_c;
  document.getElementById('labelD').textContent = q.option_d;
  document.getElementById('optA').checked = existing[q.id]==='a';
  document.getElementById('optB').checked = existing[q.id]==='b';
  document.getElementById('optC').checked = existing[q.id]==='c';
  document.getElementById('optD').checked = existing[q.id]==='d';
  document.getElementById('progressText').textContent = `${currentIndex+1} / ${questions.length}`;
  updateNavButtons();
  heartbeat();
}

function updateNavButtons(){
  const container = document.getElementById('navButtons');
  container.innerHTML='';
  questions.forEach((q,idx)=>{
    const btn = document.createElement('button');
    btn.type='button';
    const answered = !!existing[q.id];
    btn.className = `btn btn-sm ${idx===currentIndex?'btn-primary':'btn-outline-secondary'} ${answered?'border-success':''}`;
    btn.textContent = idx+1;
    btn.onclick = ()=>{saveCurrent(); currentIndex=idx; renderQuestion();};
    container.appendChild(btn);
  });
}

async function saveAnswer(questionId, option){
  try{
    await fetch('../api/save_answer.php',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({student_exam_id:studentExamId, question_id:questionId, selected_option:option})});
  }catch(e){console.error(e)}
}

function saveCurrent(){
  const q = questions[currentIndex];
  const selected = document.querySelector('input[name="option"]:checked');
  if (selected){
    existing[q.id] = selected.value;
    // Local backup - save immediately
    try { 
      localStorage.setItem('exam_answers_'+studentExamId, JSON.stringify(existing));
      localStorage.setItem('exam_progress_'+studentExamId, JSON.stringify({
        currentIndex: currentIndex,
        lastSaved: Date.now(),
        totalQuestions: questions.length
      }));
    } catch(e) {
      console.error('LocalStorage error:', e);
    }
    
    // Save to server
    showSaveStatus('saving');
    saveAnswer(q.id, selected.value);
  }
}

document.getElementById('nextBtn').onclick = ()=>{ saveCurrent(); if (currentIndex < questions.length-1){ currentIndex++; renderQuestion(); } };
document.getElementById('prevBtn').onclick = ()=>{ saveCurrent(); if (currentIndex > 0){ currentIndex--; renderQuestion(); } };
document.getElementById('optionsForm').addEventListener('change', saveCurrent);

function formatTime(s){ const m = Math.floor(s/60), r = s%60; return `${String(m).padStart(2,'0')}:${String(r).padStart(2,'0')}`; }
function tick(){ remainingSeconds--; if (remainingSeconds<=0){ finishExam(); } document.getElementById('timeLeft').textContent = formatTime(remainingSeconds); }
setInterval(tick, 1000);

document.getElementById('finishBtn').onclick = finishExam;
async function finishExam(){
  saveCurrent();
  try{ await fetch('../api/finish_exam.php',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({student_exam_id:studentExamId})}); }catch(e){}
  window.location.href='thankyou.php?exam_id=<?php echo $exam_id; ?>';
}

// Fullscreen handling
const fsBtn = document.getElementById('fullscreenBtn');
fsBtn.onclick = ()=>{ if (!document.fullscreenElement){ document.documentElement.requestFullscreen?.(); } else { document.exitFullscreen?.(); } };

// Heartbeat for live monitor
let hbTimer = null;
async function heartbeat(){
  try{ await fetch('../api/heartbeat.php',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({student_exam_id:studentExamId, current_question_id:questions[currentIndex].id, answered_count:Object.keys(existing).length})}); }catch(e){}
  if (hbTimer) clearTimeout(hbTimer);
  hbTimer = setTimeout(heartbeat, 10000);
}

// Save status indicator function
function showSaveStatus(status) {
  const saveStatus = document.getElementById('saveStatus');
  saveStatus.classList.remove('d-none', 'bg-secondary', 'bg-success', 'bg-warning', 'bg-danger');
  
  switch(status) {
    case 'saving':
      saveStatus.classList.add('bg-warning');
      saveStatus.textContent = 'Saving...';
      break;
    case 'saved':
      saveStatus.classList.add('bg-success');
      saveStatus.textContent = 'Saved';
      setTimeout(() => {
        saveStatus.classList.add('d-none');
      }, 2000);
      break;
    case 'error':
      saveStatus.classList.add('bg-danger');
      saveStatus.textContent = 'Save Failed';
      setTimeout(() => {
        saveStatus.classList.add('d-none');
      }, 5000);
      break;
  }
}

// Auto-save functionality
let autoSaveTimer;
function startAutoSave() {
  if (autoSaveTimer) clearInterval(autoSaveTimer);
  autoSaveTimer = setInterval(() => {
    const q = questions[currentIndex];
    const selected = document.querySelector('input[name="option"]:checked');
    if (selected && existing[q.id] !== selected.value) {
      saveCurrent();
    }
  }, 30000); // Auto-save every 30 seconds
}

// Network status monitoring
let isOnline = navigator.onLine;
function updateNetworkStatus() {
  const saveStatus = document.getElementById('saveStatus');
  if (!isOnline) {
    saveStatus.classList.remove('d-none', 'bg-secondary', 'bg-success', 'bg-warning');
    saveStatus.classList.add('bg-danger');
    saveStatus.textContent = 'Offline - Answers saved locally';
  }
}

window.addEventListener('online', () => {
  isOnline = true;
  console.log('Connection restored - syncing answers...');
  // Resave current answer when connection is restored
  saveCurrent();
});

window.addEventListener('offline', () => {
  isOnline = false;
  updateNetworkStatus();
});

// Warn before leaving page
window.addEventListener('beforeunload', (e) => {
  e.preventDefault();
  e.returnValue = 'Are you sure you want to leave? Your progress will be saved but you may lose time.';
  return e.returnValue;
});

renderQuestion();
startAutoSave();
</script>
<?php include '../includes/footer.php'; ?>