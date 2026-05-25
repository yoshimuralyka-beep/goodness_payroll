<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/log_activity.php';

$auth = new Auth($db);
$auth->requireLogin();

$message = '';
$messageType = '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'staff';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $old_name = $_SESSION['full_name'];
        $stmt = $db->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $userId]);
        $_SESSION['full_name'] = $full_name;
        logActivity($db, $userId, 'UPDATE_PROFILE', 'Profile', "Changed name from '$old_name' to '$full_name'");
        $message = "Profile updated successfully!";
        $messageType = "success";
    }
    
    if (isset($_POST['update_avatar']) && isset($_FILES['avatar_image'])) {
        $target_dir = "uploads/avatars/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = strtolower(pathinfo($_FILES['avatar_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_extension, $allowed)) {
            $new_filename = "user_" . $userId . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES['avatar_image']['tmp_name'], $target_file)) {
                $stmt = $db->prepare("UPDATE users SET avatar_image = ?, avatar_type = 'image' WHERE user_id = ?");
                $stmt->execute([$target_file, $userId]);
                $_SESSION['avatar_image'] = $target_file;
                logActivity($db, $userId, 'UPLOAD_AVATAR', 'Profile', "Uploaded new profile picture");
                $message = "Avatar updated successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to upload image.";
                $messageType = "danger";
            }
        } else {
            $message = "Only JPG, PNG, GIF, WEBP files are allowed.";
            $messageType = "danger";
        }
    }
    
    if (isset($_POST['remove_avatar'])) {
        $stmt = $db->prepare("UPDATE users SET avatar_image = NULL, avatar_type = 'initial' WHERE user_id = ?");
        $stmt->execute([$userId]);
        $_SESSION['avatar_image'] = null;
        logActivity($db, $userId, 'REMOVE_AVATAR', 'Profile', "Removed profile picture");
        $message = "Avatar removed.";
        $messageType = "success";
    }
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (password_verify($current, $user['password'])) {
            if ($new === $confirm) {
                if (strlen($new) >= 6) {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed, $userId]);
                    logActivity($db, $userId, 'CHANGE_PASSWORD', 'Profile', "Changed password");
                    $message = "Password changed successfully!";
                    $messageType = "success";
                } else {
                    $message = "Password must be at least 6 characters.";
                    $messageType = "danger";
                }
            } else {
                $message = "New passwords do not match.";
                $messageType = "danger";
            }
        } else {
            $message = "Current password is incorrect.";
            $messageType = "danger";
        }
    }
}

// Get current user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$avatarImage = $user['avatar_image'] ?? null;
$avatarType = $user['avatar_type'] ?? 'initial';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Goodness Gracious Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style id="theme-styles">
        /* Light Theme (Default) */
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #4a4a5a;
            --text-muted: #6c757d;
            --border-color: #e0e0e8;
            --input-bg: #f8f9fa;
            --input-border: #e0e0e8;
            --hover-bg: #f5f5f5;
        }
        
        /* Dark Theme */
        body.dark-mode {
            --bg-color: #1a1a2e;
            --card-bg: #1e1e2e;
            --text-primary: #ffffff;
            --text-secondary: #c0c0d0;
            --text-muted: #a0a0b0;
            --border-color: #2a2a3a;
            --input-bg: #2a2a3a;
            --input-border: #3a3a4a;
            --hover-bg: #2a2a3a;
        }
        
        * { font-family: 'Inter', sans-serif; }
        body {
            background: var(--bg-color);
            min-height: 100vh;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .theme-toggle:hover {
            background: #e6395c;
        }
        .theme-toggle:hover i,
        .theme-toggle:hover span {
            color: white;
        }
        .theme-toggle i {
            color: var(--text-primary);
            font-size: 14px;
        }
        .theme-toggle span {
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 500;
        }
        
        /* Profile Card - Smaller */
        .profile-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            max-width: 550px;
            margin: 0 auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color);
        }
        
        /* Avatar - Smaller */
        .avatar-preview {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            background: #e6395c;
            border: 3px solid var(--card-bg);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .role-admin { background: #e6395c; color: white; }
        .role-staff { background: #3b82f6; color: white; }
        
        .btn-back {
            background: #e0e0e8;
            color: #4a4a5a;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover { background: #d0d0d8; }
        body.dark-mode .btn-back {
            background: #2a2a3a;
            color: #c0c0d0;
        }
        
        .btn-pink {
            background: linear-gradient(135deg, #ff4d6d, #e6395c);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .btn-outline-pink {
            border: 1px solid #e6395c;
            background: transparent;
            color: #e6395c;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-outline-pink:hover {
            background: #e6395c;
            color: white;
        }
        
        .btn-outline-danger {
            border: 1px solid #dc2626;
            background: transparent;
            color: #dc2626;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-outline-danger:hover {
            background: #dc2626;
            color: white;
        }
        
        .form-control-custom {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--text-primary);
            width: 100%;
            font-size: 13px;
        }
        .form-control-custom:focus {
            border-color: #e6395c;
            outline: none;
            box-shadow: 0 0 0 2px rgba(230,57,92,0.2);
        }
        
        .form-label {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
        }
        
        .password-strength {
            height: 3px;
            border-radius: 3px;
            margin-top: 6px;
            width: 100%;
        }
        .strength-weak { background: #dc2626; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #059669; width: 100%; }
        
        hr {
            border-color: var(--border-color);
            margin: 15px 0;
        }
        
        h5 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .text-muted-custom {
            color: var(--text-muted);
            font-size: 10px;
            margin-top: 4px;
            display: block;
        }
        
        .alert-custom {
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 15px;
        }
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border: none;
        }
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Dark/Light Mode Toggle -->
    <div class="theme-toggle" id="themeToggle">
        <i class="fas fa-sun" id="sunIcon"></i>
        <i class="fas fa-moon" id="moonIcon" style="display: none;"></i>
        <span id="themeText">Light</span>
    </div>

    <div class="profile-card">
        <!-- Header -->
        <div class="text-center">
            <?php if($avatarType == 'image' && $avatarImage && file_exists($avatarImage)): ?>
            <img src="<?php echo $avatarImage; ?>?t=<?php echo time(); ?>" class="avatar-preview" style="object-fit: cover;">
            <?php else: ?>
            <div class="avatar-preview"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <?php endif; ?>
            
            <div class="mt-1">
                <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display: inline;">
                    <label class="btn-outline-pink" style="cursor: pointer;">
                        <i class="fas fa-camera me-1"></i> Upload
                        <input type="file" name="avatar_image" accept="image/*" style="display: none;" onchange="document.getElementById('avatarForm').submit();">
                    </label>
                    <input type="hidden" name="update_avatar" value="1">
                </form>
                <?php if($avatarType == 'image'): ?>
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="remove_avatar" class="btn-outline-danger" onclick="return confirm('Remove picture?')">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="mt-2">
                <h5 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <span class="role-badge role-<?php echo $userRole; ?> mt-1">
                    <i class="fas fa-<?php echo $userRole == 'admin' ? 'crown' : 'user'; ?> me-1"></i>
                    <?php echo ucfirst($userRole); ?>
                </span>
            </div>
            <div class="text-muted-custom mt-1">
                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?>
            </div>
        </div>
        
        <?php if($message): ?>
        <div class="alert-custom alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?> mt-3">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-1"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Edit Profile Form -->
        <form method="POST" class="mt-3">
            <div class="mb-2">
                <label class="form-label">Username</label>
                <input type="text" class="form-control-custom" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <div class="text-muted-custom">Cannot be changed</div>
            </div>
            
            <div class="mb-2">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control-custom" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="mb-2">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control-custom" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
            </div>
            
            <div class="mb-2">
                <label class="form-label">Role</label>
                <input type="text" class="form-control-custom" value="<?php echo ucfirst($user['user_role']); ?>" disabled>
            </div>
            
            <button type="submit" name="update_profile" class="btn-pink w-100 mt-2">
                <i class="fas fa-save me-2"></i> Save Changes
            </button>
        </form>
        
        <hr>
        
        <!-- Change Password Form -->
        <form method="POST">
            <h5 class="mb-3"><i class="fas fa-key me-2" style="color: #e6395c;"></i> Change Password</h5>
            
            <div class="mb-2">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control-custom" required>
            </div>
            
            <div class="mb-2">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-control-custom" required>
                <div class="password-strength" id="strengthIndicator"></div>
                <div class="text-muted-custom">Minimum 6 characters</div>
            </div>
            
            <div class="mb-2">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control-custom" required>
                <div id="passwordMatch" class="text-muted-custom"></div>
            </div>
            
            <button type="submit" name="change_password" class="btn-pink w-100 mt-2">
                <i class="fas fa-lock me-2"></i> Change Password
            </button>
        </form>
        
        <!-- Back Button -->
        <div class="text-center mt-3">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>
    </div>

    <script>
    // Dark/Light Mode Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        const themeText = document.getElementById('themeText');
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'inline-block';
            themeText.innerText = 'Dark';
        }
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('theme', 'dark');
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'inline-block';
                    themeText.innerText = 'Dark';
                } else {
                    localStorage.setItem('theme', 'light');
                    sunIcon.style.display = 'inline-block';
                    moonIcon.style.display = 'none';
                    themeText.innerText = 'Light';
                }
            });
        }
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('new_password');
    const strengthIndicator = document.getElementById('strengthIndicator');
    const confirmInput = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        strengthIndicator.className = 'password-strength';
        if (password.length === 0) {
            strengthIndicator.style.width = '0';
        } else if (strength <= 2) {
            strengthIndicator.classList.add('strength-weak');
        } else if (strength === 3) {
            strengthIndicator.classList.add('strength-fair');
        } else if (strength === 4) {
            strengthIndicator.classList.add('strength-good');
        } else {
            strengthIndicator.classList.add('strength-strong');
        }
    }
    
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });
    
    function checkPasswordMatch() {
        if (confirmInput.value.length > 0) {
            if (passwordInput.value === confirmInput.value) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle me-1"></i> Passwords match';
                passwordMatch.style.color = '#10b981';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Passwords do not match';
                passwordMatch.style.color = '#dc2626';
            }
        } else {
            passwordMatch.innerHTML = '';
        }
    }
    
    confirmInput.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>