<?php
require_once '../config.php';
require_once '../includes/db.php';

// Security: Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Fetch stats from the database
$stmt_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt_students->fetchColumn();

$stmt_exams = $pdo->query("SELECT COUNT(*) FROM exams");
$total_exams = $stmt_exams->fetchColumn();

define('PAGE_TITLE', 'Admin Dashboard');
include '../includes/header.php';
include 'partials/navbar.php'; // Include the new navbar
?>

<div class="container mt-4">
    <h1 class="mb-4">Admin Dashboard</h1>
    <p>Welcome to the admin panel. From here you can manage students, exams, and view results.</p>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Total Students</div>
                <div class="card-body">
                    <h5 class="card-title display-4"><?php echo $total_students; ?></h5>
                    <a href="manage_students.php" class="text-white">View Details &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Total Exams</div>
                <div class="card-body">
                    <h5 class="card-title display-4"><?php echo $total_exams; ?></h5>
                     <a href="#" class="text-white">View Details &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-dark bg-warning mb-3">
                <div class="card-header">Reports</div>
                <div class="card-body">
                    <h5 class="card-title display-4"><small>N/A</small></h5>
                     <a href="#" class="text-dark">View Reports &rarr;</a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
include '../includes/footer.php';
?>