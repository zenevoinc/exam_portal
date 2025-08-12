<?php
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP username
define('DB_PASS', '');     // Default XAMPP password
define('DB_NAME', 'sagar');

// Site URL and Title
define('SITE_URL', 'http://localhost/exam_portal');
define('SITE_TITLE', 'Online MCQ Exam Portal');

?>