<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/log_activity.php';

$auth = new Auth($db);
$error = '';

if ($auth->isLoggedIn()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        logActivity($db, $_SESSION['user_id'], 'LOGIN', 'Authentication', 'User logged in successfully');
        header("Location: index.php");
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Goodness Gracious Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container { width: 100%; max-width: 450px; padding: 20px; }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            border: 1px solid rgba(230,57,92,0.2);
        }
        
        /* LOGO SECTION */
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            display: block;
            box-shadow: 0 10px 25px rgba(230,57,92,0.3);
            border: 3px solid white;
        }
        .logo-fallback {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ff4d6d, #e6395c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 25px rgba(230,57,92,0.3);
        }
        .logo-fallback i {
            font-size: 50px;
            color: white;
        }
        .logo-title {
            color: #1a1a2e;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .logo-subtitle {
            color: #e6395c;
            font-size: 11px;
            margin-top: 5px;
            letter-spacing: 2px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { color: #4a4a5a; font-size: 14px; font-weight: 500; margin-bottom: 8px; display: block; }
        .form-control {
            background: #f8f9fa;
            border: 1px solid #e0e0e8;
            border-radius: 12px;
            padding: 12px 16px;
            width: 100%;
        }
        .form-control:focus { border-color: #e6395c; outline: none; box-shadow: 0 0 0 3px rgba(230,57,92,0.1); }
        .btn-login {
            background: linear-gradient(135deg, #ff4d6d, #e6395c);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            color: white;
            cursor: pointer;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(230,57,92,0.4); }
        .alert {
            background: rgba(230,57,92,0.1);
            border: 1px solid rgba(230,57,92,0.3);
            color: #e6395c;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .credentials {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            margin-top: 20px;
            font-size: 12px;
            color: #6a6a7a;
            text-align: center;
        }
        .credentials code { background: white; padding: 2px 6px; border-radius: 6px; color: #e6395c; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <!-- Logo Image -->
                <img src="assets/images/logo.png" alt="Goodness Gracious Logo" class="logo-img" 
                     onerror="this.style.display='none'; document.getElementById('fallbackLogo').style.display='flex';">
                <!-- Fallback Icon -->
                <div id="fallbackLogo" class="logo-fallback" style="display: none;">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="logo-title">GOODNESS GRACIOUS</div>
                <div class="logo-subtitle">PAYROLL MANAGEMENT SYSTEM</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var img = document.querySelector('.logo-img');
            if (img && img.naturalWidth === 0) {
                img.style.display = 'none';
                var fallback = document.getElementById('fallbackLogo');
                if (fallback) fallback.style.display = 'flex';
            }
        });
    </script>
</body>
</html