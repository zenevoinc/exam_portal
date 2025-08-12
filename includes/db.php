<?php
// Include the configuration file
require_once __DIR__ . '/../config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If connection fails, stop the script and show an error
    die("Database connection failed: " . $e->getMessage());
}

// Idle session timeout (30 minutes)
if (isset($_SESSION['user_id'])) {
    $idleLimit = 30 * 60;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
    $_SESSION['last_activity'] = time();
}
?>