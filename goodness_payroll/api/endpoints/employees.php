<?php
require_once '../helpers/auth_helper.php';
$authUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET all employees
    $stmt = $db->query("SELECT employee_id, employee_code, first_name, last_name, 
                        email, department, emp_position, basic_salary, 
                        hire_date, emp_status FROM employees ORDER BY last_name");
    echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    // Search employees
    $data = json_decode(file_get_contents("php://input"), true);
    $search = '%' . ($data['search'] ?? '') . '%';
    $stmt = $db->prepare("SELECT * FROM employees 
                          WHERE first_name LIKE ? OR last_name LIKE ? OR department LIKE ?");
    $stmt->execute([$search, $search, $search]);
    echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
}