<?php
require_once 'config/db_connect.php';

$username = 'admin';
$password = 'admin123';
$role = 'admin';

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// Delete existing admin if any
$stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
$stmt->execute([$username]);

// Insert new admin
$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->execute([$username, $hash, $role]);

// Verify the inserted data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

echo "<pre>";
echo "Admin Creation Debug Info:\n";
echo "------------------------\n";
echo "Generated Hash: " . $hash . "\n";
echo "Stored Hash: " . $user['password'] . "\n";
echo "Verification Test: " . (password_verify($password, $user['password']) ? "Success" : "Failed") . "\n";
echo "</pre>";
?> 