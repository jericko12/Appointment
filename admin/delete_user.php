<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    
    // First delete all appointments for this user
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE client_id = ?");
    $stmt->execute([$user_id]);
    
    // Then delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
}

header("Location: dashboard.php"); 