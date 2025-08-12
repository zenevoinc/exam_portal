<?php
require_once '../config.php';
require_once '../includes/db.php';

// Security: Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

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
    <p>Your upcoming exams will be listed below.</p>

    <div class="card">
        <div class="card-header">
            Upcoming Exams
        </div>
        <div class="card-body">
            <p>No exams scheduled for you at the moment.</p>
            </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>