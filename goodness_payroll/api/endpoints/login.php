<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

require_once '../helpers/auth_helper.php';
$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND user_status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $token = generateToken($user['user_id'], $user['username']);
    echo json_encode([
        "success" => true,
        "token" => $token,
        "user" => [
            "user_id"   => $user['user_id'],
            "username"  => $user['username'],
            "full_name" => $user['full_name'],
            "email"     => $user['email'],
            "role"      => $user['user_role']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Invalid credentials"]);
}