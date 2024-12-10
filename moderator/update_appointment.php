<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    // Start transaction
    $pdo->beginTransaction();
    try {
        // Update appointment
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_by = ? WHERE id = ?");
        $stmt->execute([$status, $_SESSION['user_id'], $appointment_id]);
        
        // Increment work count for the moderator
        $stmt = $pdo->prepare("UPDATE users SET work_count = work_count + 1 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

header("Location: dashboard.php");
exit();
?> 