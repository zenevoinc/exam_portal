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
      <div>
        <span class="badge bg-dark p-2">Time Left: <span id="timeLeft"></span></span>
        <button class="btn btn-sm btn-outline-secondary ms-2" id="fullscreenBtn">Fullscreen</button>
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
  if (stored) {
    const parsed = JSON.parse(stored);
    existing = Object.assign(existing, parsed);
  }
} catch(e) {}

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
    // Local backup
    try { localStorage.setItem('exam_answers_'+studentExamId, JSON.stringify(existing)); } catch(e) {}
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

renderQuestion();
</script>
<?php include '../includes/footer.php'; ?>