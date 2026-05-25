<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/log_activity.php';

$auth = new Auth($db);

if (isset($_SESSION['user_id'])) {
    logActivity($db, $_SESSION['user_id'], 'LOGOUT', 'Authentication', 'User logged out');
}

$auth->logout();
header("Location: login.php");
exit();
?>