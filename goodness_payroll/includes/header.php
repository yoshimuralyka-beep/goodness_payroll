<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>🌸 PayrollFlow | Modern Payroll Management System</title>
    
    <!-- Bootstrap 5 + Icons + Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --pink-50: #fff5f7;
            --pink-100: #ffe4e9;
            --pink-200: #ffccd6;
            --pink-300: #ffa3b3;
            --pink-400: #ff6b85;
            --pink-500: #ff4d6d;
            --pink-600: #e6395c;
            --pink-700: #c92a4a;
            --pink-800: #a61e3a;
            --pink-900: #8a1a33;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #6b7280;
            --gray-800: #1f2937;
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--pink-50) 0%, #fff 100%);
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--pink-800) 0%, var(--pink-900) 100%);
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(4px);
        }
        
        .sidebar .nav-link.active {
            background: white;
            color: var(--pink-700);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
        }
        
        /* Card Styles */
        .stat-card {
            background: white;
            border-radius: 24px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        /* Table Styles */
        .modern-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .modern-table thead {
            background: var(--pink-50);
        }
        
        .modern-table th {
            color: var(--pink-800);
            font-weight: 600;
            padding: 16px;
            border-bottom: 2px solid var(--pink-200);
        }
        
        .modern-table td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom-color: var(--gray-100);
        }
        
        /* Button Styles */
        .btn-pink {
            background: linear-gradient(135deg, var(--pink-500), var(--pink-600));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-pink:hover {
            background: linear-gradient(135deg, var(--pink-600), var(--pink-700));
            transform: scale(1.02);
            color: white;
            box-shadow: 0 4px 15px rgba(230,57,92,0.3);
        }
        
        .btn-outline-pink {
            border: 2px solid var(--pink-500);
            color: var(--pink-600);
            background: transparent;
            border-radius: 40px;
            font-weight: 600;
        }
        
        .btn-outline-pink:hover {
            background: var(--pink-500);
            color: white;
        }
        
        /* Form Styles */
        .form-modern {
            border: 1.5px solid var(--gray-200);
            border-radius: 16px;
            padding: 12px 16px;
            transition: all 0.3s;
        }
        
        .form-modern:focus {
            border-color: var(--pink-400);
            box-shadow: 0 0 0 4px rgba(255,77,109,0.1);
            outline: none;
        }
        
        /* Badge Styles */
        .badge-success {
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 40px;
            font-weight: 500;
        }
        
        .badge-warning {
            background: #f59e0b;
            color: white;
            padding: 6px 12px;
            border-radius: 40px;
        }
        
        /* Main Content */
        .main-content {
            padding: 24px 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--pink-800);
            margin-bottom: 8px;
        }
        
        /* Alert/Toast */
        .toast-custom {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1055;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                position: sticky;
                top: 0;
                z-index: 100;
            }
            .main-content {
                padding: 20px 16px;
            }
            .page-title {
                font-size: 22px;
            }
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--pink-200);
            border-top-color: var(--pink-600);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-auto">
            <div class="sidebar p-3">
                <div class="text-center py-4 mb-3">
                    <i class="fas fa-heart fa-3x" style="color: rgba(255,255,255,0.9);"></i>
                    <h5 class="text-white mt-2 mb-0 fw-bold">PayrollFlow</h5>
                    <small class="text-white-50">v2.0 · Pink Edition</small>
                </div>
                <nav class="nav flex-column">
                    <a href="index.php?page=dashboard" class="nav-link <?php echo ($_GET['page'] ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                    <a href="index.php?page=employees" class="nav-link <?php echo ($_GET['page'] ?? '') == 'employees' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Employees
                    </a>
                    <a href="index.php?page=process_payroll" class="nav-link <?php echo ($_GET['page'] ?? '') == 'process_payroll' ? 'active' : ''; ?>">
                        <i class="fas fa-calculator"></i> Process Payroll
                    </a>
                    <a href="index.php?page=payroll_history" class="nav-link <?php echo ($_GET['page'] ?? '') == 'payroll_history' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Payroll History
                    </a>
                    <a href="index.php?page=data_warehouse" class="nav-link <?php echo ($_GET['page'] ?? '') == 'data_warehouse' ? 'active' : ''; ?>">
                        <i class="fas fa-warehouse"></i> Data Warehouse
                    </a>
                </nav>
                <hr class="bg-white-50 my-3">
                <div class="text-center text-white-50 small">
                    <i class="fas fa-database"></i> ACID Compliant<br>
                    <i class="fas fa-lock"></i> Concurrency Control
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col">
            <div class="main-content">