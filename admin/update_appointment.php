<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'moderator')) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $appointment_id]);
}

header("Location: dashboard.php");
exit();
?>