<?php
require_once '../config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Seat Number','Name','Email','Status','Created']);
$stmt = $pdo->query('SELECT seat_number, name, email, status, created_at FROM users WHERE role = \'student\' ORDER BY created_at DESC');
foreach ($stmt as $r) { fputcsv($out, [$r['seat_number'],$r['name'],$r['email'],$r['status'],$r['created_at']]); }
fclose($out);