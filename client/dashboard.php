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

// Handle new appointment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    $stmt = $pdo->prepare("INSERT INTO appointments (client_id, title, description, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $description, $date, $time]);
    
    // Redirect to refresh the page
    header("Location: dashboard.php");
    exit();
}

// Get appointments with filter
$query = "SELECT * FROM appointments WHERE client_id = ?";
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
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100%;
            left: 0;
            top: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .toggle-btn {
            position: fixed;
            left: 10px;
            top: 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            z-index: 1001;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn.active {
            transform: translateX(260px);
        }

        .toggle-btn i {
            font-size: 18px;
            width: 20px;
            height: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            padding-left: 60px;
            padding-top: 60px;
            transition: margin-left 0.3s ease;
            width: 100%;
        }

        .main-content.sidebar-active {
            margin-left: 250px;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }

        .sidebar h2 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #34495e;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-link {
            padding: 12px 15px;
            text-decoration: none;
            color: #ecf0f1;
            border-radius: 4px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .nav-link:hover {
            background-color: #34495e;
        }

        .nav-link.active {
            background-color: #3498db;
        }

        .count-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.9em;
        }

        .logout-container {
            padding: 20px;
            padding-top: 0;
            margin-top: auto;
            width: 100%;
            box-sizing: border-box;
        }

        .logout-link {
            display: block;
            padding: 12px;
            background-color: #e74c3c;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .logout-link:hover {
            background-color: #c0392b;
        }

        /* Keep your existing styles */
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 15px;
        }

        .stat-box {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }

        .pending-box { background-color: #f0ad4e; }
        .approved-box { background-color: #5cb85c; }
        .rejected-box { background-color: #d9534f; }

        .appointment-card {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            transition: transform 0.2s;
            padding: 15px;
            border-radius: 4px;
        }

        .appointment-card:hover {
            transform: translateY(-2px);
        }

        .status-pending { color: #f0ad4e; }
        .status-approved { color: #5cb85c; }
        .status-rejected { color: #d9534f; }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar Navigation -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <h2>Navigation</h2>
                <div class="nav-links">
                    <a href="?status=all" class="nav-link <?= $filter_status === 'all' ? 'active' : '' ?>">
                        All Appointments
                        <span class="count-badge"><?= ($counts['pending_count'] + $counts['approved_count'] + $counts['rejected_count']) ?></span>
                    </a>
                    <a href="?status=pending" class="nav-link <?= $filter_status === 'pending' ? 'active' : '' ?>">
                        Pending
                        <span class="count-badge"><?= $counts['pending_count'] ?? 0 ?></span>
                    </a>
                    <a href="?status=approved" class="nav-link <?= $filter_status === 'approved' ? 'active' : '' ?>">
                        Approved
                        <span class="count-badge"><?= $counts['approved_count'] ?? 0 ?></span>
                    </a>
                    <a href="?status=rejected" class="nav-link <?= $filter_status === 'rejected' ? 'active' : '' ?>">
                        Rejected
                        <span class="count-badge"><?= $counts['rejected_count'] ?? 0 ?></span>
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
            <h1 style="margin-top: 0;">Client Dashboard</h1>

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
            <form method="POST">
                <input type="text" name="title" placeholder="Appointment Title" required>
                <textarea name="description" placeholder="Description"></textarea>
                <input type="date" name="date" required>
                <input type="time" name="time" required>
                <button type="submit">Book Appointment</button>
            </form>

            <h2>My Appointments</h2>
            <div class="appointments">
                <?php if (empty($appointments)): ?>
                    <p>No appointments found for the selected filter.</p>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <h3><?= htmlspecialchars($appointment['title']) ?></h3>
                            <p><?= htmlspecialchars($appointment['description']) ?></p>
                            <p>Date: <?= $appointment['appointment_date'] ?></p>
                            <p>Time: <?= $appointment['appointment_time'] ?></p>
                            <p>Status: <span class="status-<?= $appointment['status'] ?>">
                                <?= ucfirst($appointment['status']) ?>
                            </span></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            if (!sidebar.classList.contains('active')) {
                // Opening sidebar
                sidebar.classList.add('active');
                mainContent.classList.add('sidebar-active');
                toggleBtn.classList.add('active');
            } else {
                // Closing sidebar
                sidebar.classList.remove('active');
                mainContent.classList.remove('sidebar-active');
                toggleBtn.classList.remove('active');
            }
        }

        // Update click outside handler
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-btn');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && sidebar.classList.contains('active')) {
                toggleSidebar(); // Use the same toggle function for consistency
            }
        });

        // Update the setInitialState function
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
            
            // Force a reflow
            sidebar.offsetHeight;
            
            // Re-enable transitions
            setTimeout(() => {
                toggleBtn.style.transition = 'all 0.3s ease';
                sidebar.style.transition = 'all 0.3s ease';
                mainContent.style.transition = 'all 0.3s ease';
            }, 50);
        }

        document.addEventListener('DOMContentLoaded', setInitialState);
    </script>
</body>
</html> 