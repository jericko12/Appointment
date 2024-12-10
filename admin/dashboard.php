<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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

// Get role filter from URL
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

// Modify the users query to include filter
if ($role_filter === 'all') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role != 'admin'");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? AND role != 'admin'");
    $stmt->execute([$role_filter]);
}
$users = $stmt->fetchAll();

// Get count of users by role
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN role = 'client' THEN 1 END) as client_count,
        COUNT(CASE WHEN role = 'moderator' THEN 1 END) as moderator_count
    FROM users WHERE role != 'admin'
");
$user_counts = $stmt->fetch();

// Add this query at the top
$stmt = $pdo->query("
    SELECT u.username, u.work_count 
    FROM users u 
    WHERE u.role = 'moderator' 
    ORDER BY u.work_count DESC
");
$moderator_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
        .tabs {
            margin-bottom: 20px;
        }
        .tab-button {
            padding: 10px 20px;
            margin-right: 10px;
            cursor: pointer;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .status-pending { color: #f0ad4e; }
        .status-approved { color: #5cb85c; }
        .status-rejected { color: #d9534f; }
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

        .nav-item {
            position: relative;
        }

        .submenu {
            display: none;
            padding-left: 20px;
            margin-top: 5px;
        }

        .submenu.active {
            display: block;
        }

        .submenu-link {
            display: block;
            padding: 10px 15px;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 5px;
            transition: background-color 0.3s;
        }

        .submenu-link:hover {
            background-color: #34495e;
        }

        .submenu-link.active {
            background-color: #3498db;
        }

        .fa-chevron-down {
            transition: transform 0.3s;
        }

        .nav-link.expanded .fa-chevron-down {
            transform: rotate(180deg);
        }

        /* Moderator Stats */
        .moderator-stats {
            margin-top: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .mod-stat-card {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .mod-stat-card h3 {
            color: #2c3e50;
            margin: 0 0 10px 0;
        }

        .mod-stat-card p {
            color: #34495e;
            font-size: 1.2em;
            font-weight: bold;
            margin: 0;
        }
    </style>
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
                    
                    <!-- User Management with submenu -->
                    <div class="nav-item">
                        <a href="#" class="nav-link" onclick="toggleSubmenu(event)">
                            User Management
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="submenu">
                            <a href="#" onclick="showTab('users'); filterUsers('all'); return false;" 
                               class="submenu-link <?= $role_filter === 'all' ? 'active' : '' ?>">
                                All Users (<?= ($user_counts['client_count'] + $user_counts['moderator_count']) ?>)
                            </a>
                            <a href="#" onclick="showTab('users'); filterUsers('client'); return false;" 
                               class="submenu-link <?= $role_filter === 'client' ? 'active' : '' ?>">
                                Clients (<?= $user_counts['client_count'] ?>)
                            </a>
                            <a href="#" onclick="showTab('users'); filterUsers('moderator'); return false;" 
                               class="submenu-link <?= $role_filter === 'moderator' ? 'active' : '' ?>">
                                Moderators (<?= $user_counts['moderator_count'] ?>)
                            </a>
                        </div>
                    </div>
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
                <h1>Admin Dashboard</h1>

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

                <!-- Moderator Stats -->
                <div class="moderator-stats">
                    <h2>Moderator Statistics</h2>
                    <div class="stats-grid">
                        <?php foreach ($moderator_stats as $mod): ?>
                            <div class="mod-stat-card">
                                <h3><?= htmlspecialchars($mod['username']) ?></h3>
                                <p>Total Updates: <?= $mod['work_count'] ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Appointments Section -->
                <div class="appointments" style="display: block;">
                    <?php if (empty($appointments)): ?>
                        <p>No appointments found for the selected filter.</p>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card">
                                <h3><?= htmlspecialchars($appointment['title']) ?></h3>
                                <p>Client: <?= htmlspecialchars($appointment['username']) ?></p>
                                <p><?= htmlspecialchars($appointment['description']) ?></p>
                                <p>Date: <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></p>
                                <p>Time: <?= date('h:i A', strtotime($appointment['appointment_time'])) ?></p>
                                <p>Status: <span class="status-<?= $appointment['status'] ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span></p>
                                
                                <?php if ($appointment['moderator_name']): ?>
                                    <p>Updated by: 
                                        <?= ($appointment['updated_by'] == $_SESSION['user_id']) ? 'Me' : htmlspecialchars($appointment['moderator_name']) ?>
                                    </p>
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
                                
                                <form method="POST" action="delete_appointment.php" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                    <button type="submit" style="background-color: #d9534f;">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Users Section -->
                <div class="users" style="display: none;">
                    <?php if (empty($users)): ?>
                        <p>No users found for the selected role.</p>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <div class="appointment-card">
                                <h3><?= htmlspecialchars($user['username']) ?></h3>
                                <p>Role: <?= ucfirst($user['role']) ?></p>
                                <?php if ($user['role'] == 'moderator'): ?>
                                    <p>Total Work Done: <?= $user['work_count'] ?> appointments</p>
                                <?php endif; ?>
                                
                                <form method="POST" action="update_user.php">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="role">
                                        <option value="client" <?= $user['role'] == 'client' ? 'selected' : '' ?>>Client</option>
                                        <option value="moderator" <?= $user['role'] == 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                    </select>
                                    <button type="submit">Update Role</button>
                                </form>
                                
                                <form method="POST" action="delete_user.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" style="background-color: #d9534f;">Delete User</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            const appointmentsSection = document.querySelector('.appointments');
            const usersSection = document.querySelector('.users');
            
            if (tabId === 'users') {
                appointmentsSection.style.display = 'none';
                usersSection.style.display = 'block';
            } else {
                appointmentsSection.style.display = 'block';
                usersSection.style.display = 'none';
            }
        }

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

        function checkInitialView() {
            const urlParams = new URLSearchParams(window.location.search);
            const roleParam = urlParams.get('role');
            
            if (roleParam) {
                const appointmentsSection = document.querySelector('.appointments');
                const usersSection = document.querySelector('.users');
                appointmentsSection.style.display = 'none';
                usersSection.style.display = 'block';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            setInitialState();
            checkInitialView();
        });

        function toggleSubmenu(event) {
            event.preventDefault();
            const navLink = event.currentTarget;
            const submenu = navLink.nextElementSibling;
            submenu.classList.toggle('active');
            navLink.classList.toggle('expanded');
        }

        function filterUsers(role) {
            const appointmentsSection = document.querySelector('.appointments');
            const usersSection = document.querySelector('.users');
            appointmentsSection.style.display = 'none';
            usersSection.style.display = 'block';

            window.location.href = `?role=${role}`;
        }
    </script>
</body>
</html> 