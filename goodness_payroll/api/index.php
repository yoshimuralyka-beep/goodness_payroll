<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$request = $_GET['request'] ?? '';
$parts = explode('/', trim($request, '/'));
$endpoint = $parts[0] ?? '';

switch($endpoint) {
    case 'login':       require 'endpoints/login.php'; break;
    case 'employees':   require 'endpoints/employees.php'; break;
    case 'payroll':     require 'endpoints/payroll.php'; break;
    case 'dashboard':   require 'endpoints/dashboard.php'; break;
    case 'profile':     require 'endpoints/profile.php'; break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
}