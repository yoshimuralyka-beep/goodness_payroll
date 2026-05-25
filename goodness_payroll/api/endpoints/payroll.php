<?php
require_once '../helpers/auth_helper.php';
$authUser = requireAuth();

$stmt = $db->prepare("SELECT pr.*, 
                      CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                      e.employee_code, e.department
                      FROM payroll_records pr
                      JOIN employees e ON pr.employee_id = e.employee_id
                      ORDER BY pr.pay_date DESC LIMIT 50");
$stmt->execute();
echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);