<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$created = [];
$error = '';

function generateSeatNumber(PDO $pdo): string {
    // SN-YYYY-XXXXX
    $year = date('Y');
    do {
        $rand = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $seat = "SN-$year-$rand";
        $exists = $pdo->prepare('SELECT id FROM users WHERE seat_number = ?');
        $exists->execute([$seat]);
    } while ($exists->fetch());
    return $seat;
}

function generatePassword(int $length = 8): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$#!';
    $password = '';
    for ($i=0; $i<$length; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    return $password;
}

function readRowsFromUpload(array $file): array {
    $tmp = $file['tmp_name'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $rows = [];
    
    if ($ext !== 'csv') {
        throw new Exception('Only CSV files are supported. Please convert your Excel file to CSV format.');
    }
    
    if (($h = fopen($tmp, 'r')) !== false) {
        $rowCount = 0;
        while (($data = fgetcsv($h)) !== false) {
            $rowCount++;
            // Skip empty rows
            if (count(array_filter($data, function($v) { return trim($v) !== ''; })) > 0) {
                $rows[] = $data;
            }
        }
        fclose($h);
        
        if ($rowCount === 0) {
            throw new Exception('The CSV file appears to be empty.');
        }
    } else {
        throw new Exception('Failed to read the uploaded CSV file. Please check the file format.');
    }
    
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['students_file']) || $_FILES['students_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: No temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Server error: Cannot write file',
            UPLOAD_ERR_EXTENSION => 'Server error: File upload stopped by extension'
        ];
        $errorCode = $_FILES['students_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $uploadErrors[$errorCode] ?? 'Unknown upload error occurred';
    } else {
        try {
            $rows = readRowsFromUpload($_FILES['students_file']);
            if (!$rows) { 
                $error = 'No valid data found in the uploaded file.'; 
            } else {
                // Drop header row if it contains 'name' or 'email'
                if (isset($rows[0][0]) && (stripos((string)$rows[0][0], 'name') !== false || stripos((string)($rows[0][1] ?? ''), 'email') !== false)) {
                    array_shift($rows);
                }
                
                if (empty($rows)) {
                    $error = 'No valid data rows found after removing header. Please check your CSV format.';
                } else {
                    $insert = $pdo->prepare('INSERT INTO users (seat_number, name, email, password, role) VALUES (?,?,?,?,\'student\')');
                    $skipped = [];
                    $rowNum = 1; // Start from 1 since we may have removed header
                    
                    foreach ($rows as $r) {
                        $rowNum++;
                        $name = trim($r[0] ?? '');
                        $email = trim($r[1] ?? '');
                        
                        // Validate data
                        if ($name === '') {
                            $skipped[] = "Row $rowNum: Name is empty";
                            continue;
                        }
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $skipped[] = "Row $rowNum: Invalid email format '$email'";
                            continue;
                        }
                        
                        // Check existing
                        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                        $exists->execute([$email]);
                        if ($exists->fetch()) { 
                            $skipped[] = "Row $rowNum: Email '$email' already exists";
                            continue; 
                        }
                        
                        try {
                            $seat = generateSeatNumber($pdo);
                            $plain = generatePassword(8);
                            $hash = password_hash($plain, PASSWORD_BCRYPT);
                            $insert->execute([$seat, $name, $email, $hash]);
                            $created[] = ['seat_number'=>$seat,'name'=>$name,'email'=>$email,'password'=>$plain];
                        } catch (Exception $e) {
                            $skipped[] = "Row $rowNum: Database error - " . $e->getMessage();
                        }
                    }
                    
                    // Show summary
                    if (!empty($skipped) && !empty($created)) {
                        $error = count($skipped) . " rows were skipped:\n" . implode("\n", array_slice($skipped, 0, 5));
                        if (count($skipped) > 5) {
                            $error .= "\n... and " . (count($skipped) - 5) . " more.";
                        }
                    } elseif (!empty($skipped) && empty($created)) {
                        $error = "No students were created. All rows had errors:\n" . implode("\n", array_slice($skipped, 0, 10));
                        if (count($skipped) > 10) {
                            $error .= "\n... and " . (count($skipped) - 10) . " more.";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

define('PAGE_TITLE', 'Import Students');
include '../includes/header.php';
include 'partials/navbar.php';
?>
<div class="container mt-4">
    <h3 class="mb-3">Import Students</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-8">
                    <input class="form-control" type="file" name="students_file" accept=".csv" required>
                </div>
                <div class="col-md-4 d-grid">
                    <button class="btn btn-primary">Upload Students</button>
                </div>
                <p class="small text-muted">
                    <strong>CSV Format:</strong> Name, Email<br>
                    <small>Excel files are no longer supported. Please save as CSV format. The system generates Seat Number and Password automatically.</small>
                </p>
            </form>
        </div>
    </div>

    <?php if ($created): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Created Accounts (<?php echo count($created); ?>)</strong>
            <a href="#" class="btn btn-sm btn-outline-secondary" onclick="downloadCsv();return false;">Download CSV</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="createdTable">
                    <thead>
                        <tr><th>Seat Number</th><th>Name</th><th>Email</th><th>Password</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($created as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['seat_number']); ?></td>
                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><?php echo htmlspecialchars($c['email']); ?></td>
                            <td><?php echo htmlspecialchars($c['password']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    function downloadCsv(){
        const rows = [['Seat Number','Name','Email','Password']];
        document.querySelectorAll('#createdTable tbody tr').forEach(tr=>{
            const cols = Array.from(tr.children).map(td=>td.textContent);
            rows.push(cols);
        });
        const csv = rows.map(r=>r.map(v=>`"${v.replaceAll('"','""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], {type:'text/csv'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'created_students.csv';
        a.click();
    }
    </script>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>