<?php
require_once '../helpers/auth_helper.php';
$authUser = requireAuth();
$user_id = $authUser[0];

$stmt = $db->prepare("SELECT user_id, username, full_name, email, user_role, 
                      last_login, created_at FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    echo json_encode(["success" => true, "data" => $user]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}