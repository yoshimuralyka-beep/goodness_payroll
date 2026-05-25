<?php
/**
 * Activity Logging Function
 * Records user actions for audit trail
 */

function logActivity($db, $userId, $action, $module, $details = null) {
    // Get username from session if available
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';
    
    // Get IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, action_name, module_name, details_text, ip_address) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $username, $action, $module, $details, $ip]);
        return true;
    } catch(Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}
?>