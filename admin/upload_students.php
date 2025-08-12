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
    if ($ext === 'csv') {
        if (($h = fopen($tmp, 'r')) !== false) {
            while (($data = fgetcsv($h)) !== false) { $rows[] = $data; }
            fclose($h);
        }
    } else {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
            $sheet = $reader->load($tmp)->getActiveSheet();
            $rows = $sheet->toArray();
        }
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['students_file']) || $_FILES['students_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid CSV/XLSX file.';
    } else {
        $rows = readRowsFromUpload($_FILES['students_file']);
        if (!$rows) { $error = 'No rows found or unsupported format.'; }
        if (!$error) {
            // Drop header row if it contains 'email'
            if (isset($rows[0][0]) && (stripos((string)$rows[0][0], 'name') !== false || stripos((string)($rows[0][1] ?? ''), 'email') !== false)) {
                array_shift($rows);
            }
            $insert = $pdo->prepare('INSERT INTO users (seat_number, name, email, password, role) VALUES (?,?,?,?,\'student\')');
            foreach ($rows as $r) {
                $name = trim($r[0] ?? '');
                $email = trim($r[1] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
                // Check existing
                $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $exists->execute([$email]);
                if ($exists->fetch()) { continue; }
                $seat = generateSeatNumber($pdo);
                $plain = generatePassword(8);
                $hash = password_hash($plain, PASSWORD_BCRYPT);
                $insert->execute([$seat, $name, $email, $hash]);
                $created[] = ['seat_number'=>$seat,'name'=>$name,'email'=>$email,'password'=>$plain];
            }
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
                    <input class="form-control" type="file" name="students_file" accept=".csv,.xlsx,.xls" required>
                </div>
                <div class="col-md-4 d-grid">
                    <button class="btn btn-primary">Upload</button>
                </div>
                <p class="small text-muted">Expected columns: Name, Email. The system generates Seat Number and Password automatically.</p>
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