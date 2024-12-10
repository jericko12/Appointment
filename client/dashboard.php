<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: ../login.php");
    exit();
}

// Get appointment counts for this client
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
    FROM appointments
    WHERE client_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$counts = $stmt->fetch();

// Get filter status from URL parameter
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get appointments with filter
$query = "
    SELECT 
        a.*, 
        m.username as moderator_name,
        a.updated_at
    FROM appointments a 
    LEFT JOIN users m ON a.updated_by = m.id 
    WHERE a.client_id = ?
";

if ($filter_status !== 'all') {
    $query .= " AND status = ?";
}
$query .= " ORDER BY appointment_date DESC, appointment_time DESC";

$stmt = $pdo->prepare($query);
if ($filter_status !== 'all') {
    $stmt->execute([$_SESSION['user_id'], $filter_status]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Client Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="layout">
        <!-- Sidebar Navigation -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <h2>Menu</h2>
                <div class="nav-links">
                    <a href="?status=all" class="nav-link <?= $filter_status === 'all' ? 'active' : '' ?>">
                        All Appointments
                        <span class="badge"><?= ($counts['pending_count'] + $counts['approved_count'] + $counts['rejected_count']) ?></span>
                    </a>
                    <a href="?status=pending" class="nav-link <?= $filter_status === 'pending' ? 'active' : '' ?>">
                        Pending
                        <span class="badge"><?= $counts['pending_count'] ?? 0 ?></span>
                    </a>
                    <a href="?status=approved" class="nav-link <?= $filter_status === 'approved' ? 'active' : '' ?>">
                        Approved
                        <span class="badge"><?= $counts['approved_count'] ?? 0 ?></span>
                    </a>
                    <a href="?status=rejected" class="nav-link <?= $filter_status === 'rejected' ? 'active' : '' ?>">
                        Rejected
                        <span class="badge"><?= $counts['rejected_count'] ?? 0 ?></span>
                    </a>
                </div>
            </div>
            <div class="logout-container">
                <a href="../logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="container">
                <h1>Client Dashboard</h1>

                <!-- Stats Boxes -->
                <div class="stats-container">
                    <div class="stat-box pending-box">
                        <h3>Pending</h3>
                        <div class="stat-number"><?= $counts['pending_count'] ?? 0 ?></div>
                        <p>Appointments</p>
                    </div>
                    <div class="stat-box approved-box">
                        <h3>Approved</h3>
                        <div class="stat-number"><?= $counts['approved_count'] ?? 0 ?></div>
                        <p>Appointments</p>
                    </div>
                    <div class="stat-box rejected-box">
                        <h3>Rejected</h3>
                        <div class="stat-number"><?= $counts['rejected_count'] ?? 0 ?></div>
                        <p>Appointments</p>
                    </div>
                </div>

                <h2>Book Appointment</h2>
                <form method="POST" class="appointment-form">
                    <input type="text" name="title" placeholder="Appointment Title" required>
                    <textarea name="description" placeholder="Description"></textarea>
                    <input type="date" name="date" required>
                    <input type="time" name="time" required>
                    <button type="submit" class="auth-btn">Book Appointment</button>
                </form>

                <h2>My Appointments</h2>
                <div class="appointments">
                    <?php if (empty($appointments)): ?>
                        <p class="empty-state">No appointments found for the selected filter.</p>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card">
                                <h3><?= htmlspecialchars($appointment['title']) ?></h3>
                                <p><?= htmlspecialchars($appointment['description']) ?></p>
                                <p>Date: <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></p>
                                <p>Time: <?= date('h:i A', strtotime($appointment['appointment_time'])) ?></p>
                                <p>Status: <span class="status-badge status-<?= $appointment['status'] ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span></p>
                                <?php if ($appointment['moderator_name']): ?>
                                    <p class="updated-by">
                                        Updated by: <?= htmlspecialchars($appointment['moderator_name']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            if (!sidebar.classList.contains('active')) {
                sidebar.classList.add('active');
                mainContent.classList.add('sidebar-active');
                toggleBtn.classList.add('active');
            } else {
                sidebar.classList.remove('active');
                mainContent.classList.remove('sidebar-active');
                toggleBtn.classList.remove('active');
            }
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-btn');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });

        function setInitialState() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            toggleBtn.style.transition = 'none';
            sidebar.style.transition = 'none';
            mainContent.style.transition = 'none';
            
            sidebar.classList.remove('active');
            mainContent.classList.remove('sidebar-active');
            toggleBtn.classList.remove('active');
            
            sidebar.offsetHeight;
            
            setTimeout(() => {
                toggleBtn.style.transition = 'transform 0.3s ease';
                sidebar.style.transition = 'transform 0.3s ease';
                mainContent.style.transition = 'margin-left 0.3s ease';
            }, 50);
        }

        document.addEventListener('DOMContentLoaded', setInitialState);
    </script>
</body>
</html> 