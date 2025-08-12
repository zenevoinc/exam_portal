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

function importQuestionsFromArray(PDO $pdo, int $examId, string $setCode, array $rows): array {
    $insert = $pdo->prepare('INSERT INTO questions (exam_id, set_code, question_text, question_image, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $count = 0;
    $errors = [];
    $rowNum = 1;
    
    foreach ($rows as $row) {
        $rowNum++;
        // Expected columns: Question, A, B, C, D, Answer, [Marks]
        $question = trim($row[0] ?? '');
        $a = trim($row[1] ?? '');
        $b = trim($row[2] ?? '');
        $c = trim($row[3] ?? '');
        $d = trim($row[4] ?? '');
        $answer = strtolower(trim($row[5] ?? ''));
        $marks = isset($row[6]) && is_numeric($row[6]) ? (float) $row[6] : 1.0;
        
        // Validate row data
        if ($question === '') {
            $errors[] = "Row $rowNum: Question text is empty";
            continue;
        }
        if ($a === '' || $b === '' || $c === '' || $d === '') {
            $errors[] = "Row $rowNum: One or more options are empty";
            continue;
        }
        if (!in_array($answer, ['a','b','c','d'], true)) {
            $errors[] = "Row $rowNum: Invalid answer '$answer' (must be a, b, c, or d)";
            continue;
        }
        
        try {
            $insert->execute([$examId, $setCode, $question, null, $a, $b, $c, $d, $answer, $marks]);
            $count++;
        } catch (Exception $e) {
            $errors[] = "Row $rowNum: Database error - " . $e->getMessage();
        }
    }
    
    return ['count' => $count, 'errors' => $errors];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_code'])) {
    $set_code = $_POST['set_code'];
    if (!in_array($set_code, ['A','B','C'], true)) {
        $error = 'Invalid set code.';
    } elseif (!isset($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: No temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Server error: Cannot write file',
            UPLOAD_ERR_EXTENSION => 'Server error: File upload stopped by extension'
        ];
        $errorCode = $_FILES['questions_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $uploadErrors[$errorCode] ?? 'Unknown upload error occurred';
    } else {
        $tmp = $_FILES['questions_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['questions_file']['name'], PATHINFO_EXTENSION));
        $rows = [];
        
        if ($ext !== 'csv') {
            $error = 'Only CSV files are supported. Please convert your Excel file to CSV format.';
        } else {
            // Read CSV file
            if (($handle = fopen($tmp, 'r')) !== false) {
                $rowCount = 0;
                while (($data = fgetcsv($handle)) !== false) {
                    $rowCount++;
                    // Skip empty rows
                    if (count(array_filter($data, function($v) { return trim($v) !== ''; })) > 0) {
                        $rows[] = $data;
                    }
                }
                fclose($handle);
                
                if ($rowCount === 0) {
                    $error = 'The CSV file appears to be empty.';
                }
            } else {
                $error = 'Failed to read the uploaded CSV file. Please check the file format.';
            }
        }

        if (!$error && $rows) {
            // Check if first row looks like header containing 'Question'
            if (isset($rows[0][0]) && stripos((string)$rows[0][0], 'question') !== false) {
                array_shift($rows);
            }
            
            if (empty($rows)) {
                $error = 'No valid data rows found in the CSV file. Please check your file format.';
            } else {
                $result = importQuestionsFromArray($pdo, $exam_id, $set_code, $rows);
                $inserted = $result['count'];
                $errors = $result['errors'];
                
                if ($inserted > 0) {
                    $message = "$inserted questions imported successfully for Set $set_code.";
                    if (!empty($errors)) {
                        $message .= " However, " . count($errors) . " rows had errors and were skipped.";
                    }
                }
                
                if (!empty($errors)) {
                    if ($inserted === 0) {
                        $error = "No questions were imported. Errors found:\n" . implode("\n", array_slice($errors, 0, 10));
                        if (count($errors) > 10) {
                            $error .= "\n... and " . (count($errors) - 10) . " more errors.";
                        }
                    } else {
                        // Show errors as warning
                        $error = "Some rows had errors:\n" . implode("\n", array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $error .= "\n... and " . (count($errors) - 5) . " more errors.";
                        }
                    }
                }
            }
        } elseif (!$error) {
            $error = 'No valid data found in the uploaded file.';
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
                            <input class="form-control" type="file" name="questions_file" accept=".csv" required>
                        </div>
                        <p class="small text-muted mb-2">
                            <strong>CSV Format:</strong> Question, A, B, C, D, Answer (a/b/c/d), [Marks]<br>
                            <small>Excel files are no longer supported. Please save as CSV format.</small>
                        </p>
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