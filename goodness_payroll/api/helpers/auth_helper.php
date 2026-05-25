<?php
function generateToken($user_id, $username) {
    // Simple token — for school project this is fine
    return base64_encode($user_id . ':' . $username . ':' . time());
}

function validateToken($token) {
    if (!$token) return false;
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    return count($parts) === 3 ? $parts : false;
}

function getAuthHeader() {
    $headers = getallheaders();
    return $headers['Authorization'] ?? null;
}

function requireAuth() {
    $auth = getAuthHeader();
    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
    $token = substr($auth, 7);
    $data = validateToken($token);
    if (!$data) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid token"]);
        exit();
    }
    return $data; // [user_id, username, timestamp]
}