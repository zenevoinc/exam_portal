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

$message = '';
$error = '';

function importQuestionsFromArray(PDO $pdo, int $examId, string $setCode, array $rows): int {
    $insert = $pdo->prepare('INSERT INTO questions (exam_id, set_code, question_text, question_image, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $count = 0;
    foreach ($rows as $row) {
        // Expected columns: Question, A, B, C, D, Answer, [Marks]
        $question = trim($row[0] ?? '');
        $a = trim($row[1] ?? '');
        $b = trim($row[2] ?? '');
        $c = trim($row[3] ?? '');
        $d = trim($row[4] ?? '');
        $answer = strtolower(trim($row[5] ?? ''));
        $marks = isset($row[6]) && is_numeric($row[6]) ? (float) $row[6] : 1.0;
        if ($question === '' || !in_array($answer, ['a','b','c','d'], true)) {
            continue;
        }
        $insert->execute([$examId, $setCode, $question, null, $a, $b, $c, $d, $answer, $marks]);
        $count++;
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_code'])) {
    $set_code = $_POST['set_code'];
    if (!in_array($set_code, ['A','B','C'], true)) {
        $error = 'Invalid set code.';
    } elseif (!isset($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid file.';
    } else {
        $tmp = $_FILES['questions_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['questions_file']['name'], PATHINFO_EXTENSION));
        $rows = [];
        if ($ext === 'csv') {
            if (($handle = fopen($tmp, 'r')) !== false) {
                // skip header if present when detected
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } elseif (in_array($ext, ['xlsx','xls'], true)) {
            // Try PhpSpreadsheet if available
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                try {
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
                    $spreadsheet = $reader->load($tmp);
                    $sheet = $spreadsheet->getActiveSheet();
                    foreach ($sheet->toArray() as $row) {
                        $rows[] = $row;
                    }
                } catch (Throwable $t) {
                    $error = 'Failed to parse spreadsheet: ' . $t->getMessage();
                }
            } else {
                $error = 'XLSX parsing requires PhpSpreadsheet. Upload CSV or install dependency.';
            }
        } else {
            $error = 'Unsupported file type.';
        }

        if (!$error && $rows) {
            // heuristic: check if first row looks like header containing 'Question'
            if (isset($rows[0][0]) && stripos((string)$rows[0][0], 'question') !== false) {
                array_shift($rows);
            }
            $inserted = importQuestionsFromArray($pdo, $exam_id, $set_code, $rows);
            $message = "$inserted questions imported for Set $set_code.";
        } elseif (!$error) {
            $error = 'No rows detected in file.';
        }
    }
}

$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();
if (!$exam) { die('Exam not found'); }

$counts = $pdo->prepare('SELECT set_code, COUNT(*) as total FROM questions WHERE exam_id = ? GROUP BY set_code');
$counts->execute([$exam_id]);
$setCounts = ['A'=>0,'B'=>0,'C'=>0];
foreach ($counts as $r) { $setCounts[$r['set_code']] = (int)$r['total']; }

define('PAGE_TITLE', 'Upload Questions');
include '../includes/header.php';
include 'partials/navbar.php';
?>
<div class="container mt-4">
    <h3 class="mb-3">Upload Questions for: <?php echo htmlspecialchars($exam['title']); ?></h3>

    <?php if ($message && !$error): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="row g-4">
        <?php foreach (['A','B','C'] as $code): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Set <?php echo $code; ?></strong>
                    <span class="badge bg-secondary"><?php echo $setCounts[$code]; ?> uploaded</span>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="set_code" value="<?php echo $code; ?>">
                        <div class="mb-2">
                            <input class="form-control" type="file" name="questions_file" accept=".csv,.xlsx,.xls" required>
                        </div>
                        <p class="small text-muted mb-2">Columns: Question, A, B, C, D, Answer (a/b/c/d), [Marks]</p>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Upload Set <?php echo $code; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>