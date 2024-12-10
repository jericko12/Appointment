<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator') {
    header("Location: ../login.php");
    exit();
}

// Get appointment counts
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
    FROM appointments
");
$counts = $stmt->fetch();

// Get filter status from URL parameter
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Modify the query based on filter
$query = "
    SELECT 
        a.*, 
        u.username,
        m.username as moderator_name 
    FROM appointments a 
    JOIN users u ON a.client_id = u.id 
    LEFT JOIN users m ON a.updated_by = m.id 
";

if ($filter_status !== 'all') {
    $query .= " WHERE a.status = :status";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($query);
if ($filter_status !== 'all') {
    $stmt->bindParam(':status', $filter_status);
}
$stmt->execute();
$appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Moderator Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .stat-box {
            flex: 1;
            margin: 0 10px;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }
        .pending-box {
            background-color: #f0ad4e;
        }
        .approved-box {
            background-color: #5cb85c;
        }
        .rejected-box {
            background-color: #d9534f;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .filter-buttons {
            margin: 20px 0;
        }
        .filter-button {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .filter-button.all {
            background-color: #6c757d;
            color: white;
        }
        .filter-button.pending {
            background-color: #f0ad4e;
            color: white;
        }
        .filter-button.approved {
            background-color: #5cb85c;
            color: white;
        }
        .filter-button.rejected {
            background-color: #d9534f;
            color: white;
        }
        .filter-button.active {
            outline: 3px solid #0275d8;
        }
        .status-pending { color: #f0ad4e; }
        .status-approved { color: #5cb85c; }
        .status-rejected { color: #d9534f; }

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

        .toggle-btn:hover {
            background-color: #34495e;
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

        .nav-link {
            background-color: #34495e;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-link:hover {
            background-color: #2c3e50;
        }

        .nav-link.active {
            background-color: #3498db;
        }

        .badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.9em;
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
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }
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
                <h1>Moderator Dashboard</h1>

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

                <h2>Manage Appointments</h2>
                
                <div class="appointments">
                    <?php if (empty($appointments)): ?>
                        <p>No appointments found for the selected filter.</p>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card">
                                <h3><?= htmlspecialchars($appointment['title']) ?></h3>
                                <p>Client: <?= htmlspecialchars($appointment['username']) ?></p>
                                <p><?= htmlspecialchars($appointment['description']) ?></p>
                                <p>Date: <?= $appointment['appointment_date'] ?></p>
                                <p>Time: <?= $appointment['appointment_time'] ?></p>
                                <p>Status: <span class="status-<?= $appointment['status'] ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span></p>
                                
                                <?php if ($appointment['moderator_name']): ?>
                                    <p>Updated by: <?= htmlspecialchars($appointment['moderator_name']) ?></p>
                                <?php endif; ?>
                                
                                <form method="POST" action="update_appointment.php">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                    <select name="status">
                                        <option value="pending" <?= $appointment['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $appointment['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= $appointment['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    <button type="submit">Update Status</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add the JavaScript -->
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
                sidebar.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                mainContent.style.transition = 'margin-left 0.3s ease';
            }, 50);
        }

        document.addEventListener('DOMContentLoaded', setInitialState);
    </script>
</body>
</html> 