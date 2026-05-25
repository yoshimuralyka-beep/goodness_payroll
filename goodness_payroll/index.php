<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$auth = new Auth($db);
$auth->requireLogin();

$isAdmin = $auth->isAdmin();
$currentUser = $auth->getCurrentUser();
$userName = $currentUser['full_name'] ?? 'User';
$userRole = $currentUser['role'] ?? 'staff';
$userInitial = strtoupper(substr($userName, 0, 1));

// Get user avatar
$stmt = $db->prepare("SELECT avatar_image, avatar_type FROM users WHERE user_id = ?");
$stmt->execute([$currentUser['user_id']]);
$userData = $stmt->fetch();
$avatarImage = $userData['avatar_image'] ?? null;
$avatarType = $userData['avatar_type'] ?? 'initial';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goodness Gracious Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style id="theme-styles">
        :root {
            --bg-gradient-start: #fef5f7;
            --bg-gradient-end: #ffe4e9;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #4a4a5a;
            --text-muted: #6c757d;
            --border-color: #ffe4e9;
            --hover-bg: #fff0f3;
            --header-bg: #ffffff;
            --table-header-bg: #fff5f7;
            --input-bg: #f8f9fa;
            --input-border: #e0e0e8;
        }
        body.dark-mode {
            --bg-gradient-start: #0f0f1a;
            --bg-gradient-end: #1a1a2e;
            --sidebar-bg: #1a1a2e;
            --card-bg: #1e1e2e;
            --text-primary: #ffffff;
            --text-secondary: #c0c0d0;
            --text-muted: #a0a0b0;
            --border-color: #2a2a3a;
            --hover-bg: #2a2a3a;
            --header-bg: #1a1a2e;
            --table-header-bg: #2a2a3a;
            --input-bg: #2a2a3a;
            --input-border: #3a3a4a;
        }
        body { background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%); transition: all 0.3s ease; margin: 0; padding: 0; }
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            border-right: 1px solid var(--border-color);
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
        }
        .sidebar-logo { padding: 25px 20px; text-align: center; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
        .sidebar-logo-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 12px;
            display: block;
            box-shadow: 0 5px 15px rgba(230,57,92,0.3);
            border: 2px solid white;
        }
        .sidebar-logo-icon { width: 70px; height: 70px; background: linear-gradient(135deg, #ff4d6d, #e6395c); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; box-shadow: 0 5px 15px rgba(230,57,92,0.3); }
        .sidebar-logo-icon i { font-size: 35px; color: white; }
        .sidebar-logo h2 { color: var(--text-primary); font-size: 16px; font-weight: 800; margin-bottom: 4px; letter-spacing: 1px; }
        .sidebar-logo p { color: #e6395c; font-size: 10px; letter-spacing: 2px; font-weight: 600; }
        .nav-link { display: flex; align-items: center; padding: 12px 20px; margin: 4px 12px; border-radius: 12px; color: var(--text-secondary); text-decoration: none; transition: all 0.3s; }
        .nav-link:hover { background: var(--hover-bg); color: var(--text-primary); }
        .nav-link.active { background: linear-gradient(135deg, #ff4d6d, #e6395c); color: white; }
        .nav-link i { width: 24px; margin-right: 12px; }
        .nav-link.disabled { opacity: 0.5; cursor: not-allowed; }
        .main-content { margin-left: 280px; padding: 24px 32px; min-height: 100vh; }
        .top-bar {
            background: var(--header-bg);
            border-radius: 16px;
            padding: 12px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .user-profile { display: flex; align-items: center; gap: 15px; }
        .user-details { text-align: right; }
        .user-name { color: var(--text-primary); font-weight: 600; font-size: 14px; }
        .user-role { background: #fff0f3; color: #e6395c; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 18px; color: white; background: #e6395c; object-fit: cover; }
        .logout-btn { background: #ffe4e9; border: none; color: #e6395c; padding: 8px 16px; border-radius: 10px; text-decoration: none; transition: all 0.3s; }
        .logout-btn:hover { background: #e6395c; color: white; }
        .theme-toggle {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .theme-toggle:hover { background: #e6395c; color: white; }
        .theme-toggle i { color: var(--text-primary); font-size: 14px; }
        .theme-toggle span { color: var(--text-primary); font-size: 12px; font-weight: 500; }
        .stat-card { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border-color); padding: 20px; transition: all 0.3s; }
        .stat-card:hover { border-color: #ff4d6d; transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .btn-pink { background: linear-gradient(135deg, #ff4d6d, #e6395c); color: white; border: none; padding: 10px 24px; border-radius: 12px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; }
        .btn-pink:hover { transform: translateY(-2px); }
        .toast-custom { position: fixed; bottom: 20px; right: 20px; z-index: 1055; min-width: 300px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; }
            .main-content { margin-left: 0; padding: 16px; }
            .page-title { font-size: 20px; }
            .top-bar { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">
        <!-- Logo Image -->
        <img src="assets/images/logo.png" alt="Goodness Gracious Logo" class="sidebar-logo-img" 
             onerror="this.style.display='none'; document.getElementById('sidebarFallbackLogo').style.display='flex';">
        <!-- Fallback Icon -->
        <div id="sidebarFallbackLogo" class="sidebar-logo-icon" style="display: none;">
            <i class="fas fa-heart"></i>
        </div>
        <h2>GOODNESS GRACIOUS</h2>
        <p>PAYROLL SYSTEM</p>
    </div>
    
    <nav>
        <a href="?page=dashboard" class="nav-link <?php echo ($_GET['page'] ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="?page=employees" class="nav-link <?php echo ($_GET['page'] ?? '') == 'employees' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Employees
        </a>
        <?php if($isAdmin): ?>
        <a href="?page=process_payroll" class="nav-link <?php echo ($_GET['page'] ?? '') == 'process_payroll' ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i> Process Payroll
        </a>
        <?php else: ?>
        <a href="#" class="nav-link disabled" onclick="return false;">
            <i class="fas fa-calculator"></i> Process Payroll
            <span style="margin-left: auto; font-size: 10px;">Admin Only</span>
        </a>
        <?php endif; ?>
        <a href="?page=payroll_history" class="nav-link <?php echo ($_GET['page'] ?? '') == 'payroll_history' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Payroll History
        </a>
        <?php if($isAdmin): ?>
        <a href="?page=data_warehouse" class="nav-link <?php echo ($_GET['page'] ?? '') == 'data_warehouse' ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i> Data Warehouse
        </a>
        <a href="?page=audit_logs" class="nav-link <?php echo ($_GET['page'] ?? '') == 'audit_logs' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i> Audit Logs
        </a>
        <?php else: ?>
        <a href="#" class="nav-link disabled" onclick="return false;">
            <i class="fas fa-warehouse"></i> Data Warehouse
            <span style="margin-left: auto; font-size: 10px;">Admin Only</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <hr style="border-color: var(--border-color); margin: 20px;">
    
    <div style="padding: 0 20px 20px;">
        <a href="profile.php" class="nav-link">
            <i class="fas fa-user-cog"></i> Profile Settings
        </a>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1 class="page-title">
            <?php 
                $page = $_GET['page'] ?? 'dashboard';
                $titles = [
                    'dashboard' => 'Dashboard',
                    'employees' => 'Employee Management',
                    'process_payroll' => 'Process Payroll',
                    'payroll_history' => 'Payroll History',
                    'data_warehouse' => 'Data Warehouse',
                    'audit_logs' => 'Audit Logs'
                ];
                echo $titles[$page] ?? 'Dashboard';
            ?>
        </h1>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="theme-toggle" id="themeToggle">
                <i class="fas fa-sun" id="sunIcon"></i>
                <i class="fas fa-moon" id="moonIcon" style="display: none;"></i>
                <span id="themeText">Light</span>
            </div>
            <div class="user-profile">
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role"><?php echo ucfirst($userRole); ?></div>
                </div>
                <?php if($avatarType == 'image' && $avatarImage && file_exists($avatarImage)): ?>
                <img src="<?php echo $avatarImage; ?>?t=<?php echo time(); ?>" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                <div class="user-avatar"><?php echo $userInitial; ?></div>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    
    <?php
    $page = $_GET['page'] ?? 'dashboard';
    $adminOnlyPages = ['process_payroll', 'data_warehouse', 'audit_logs'];
    
    if (in_array($page, $adminOnlyPages) && !$isAdmin) {
        echo '<div class="stat-card text-center p-5">
                <i class="fas fa-lock fa-3x mb-3" style="color: #e6395c;"></i>
                <h3 style="color: var(--text-primary);">Access Denied</h3>
                <p class="text-muted">You do not have permission to access this page.</p>
                <a href="?page=dashboard" class="btn-pink mt-3 d-inline-block">Go to Dashboard</a>
              </div>';
    } elseif (in_array($page, ['dashboard', 'employees', 'process_payroll', 'payroll_history', 'data_warehouse', 'audit_logs']) && file_exists("modules/{$page}.php")) {
        include "modules/{$page}.php";
    } else {
        include "modules/dashboard.php";
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');
    const themeText = document.getElementById('themeText');
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        if (sunIcon) sunIcon.style.display = 'none';
        if (moonIcon) moonIcon.style.display = 'inline-block';
        if (themeText) themeText.innerText = 'Dark';
    }
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                if (sunIcon) sunIcon.style.display = 'none';
                if (moonIcon) moonIcon.style.display = 'inline-block';
                if (themeText) themeText.innerText = 'Dark';
            } else {
                localStorage.setItem('theme', 'light');
                if (sunIcon) sunIcon.style.display = 'inline-block';
                if (moonIcon) moonIcon.style.display = 'none';
                if (themeText) themeText.innerText = 'Light';
            }
        });
    }
});
$(document).ready(function() {
    setTimeout(function() { $('.toast-custom').fadeOut(500, function() { $(this).remove(); }); }, 5000);
});
</script>
</body>
</html>