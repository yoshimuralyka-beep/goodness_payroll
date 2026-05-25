<?php
require_once '../helpers/auth_helper.php';
requireAuth();

$totalEmp   = $db->query("SELECT COUNT(*) FROM employees WHERE emp_status='active'")->fetchColumn();
$totalPay   = $db->query("SELECT COUNT(*) FROM payroll_records")->fetchColumn();
$totalGross = $db->query("SELECT COALESCE(SUM(gross_pay),0) FROM payroll_records")->fetchColumn();
$recentPay  = $db->query("SELECT pr.payroll_id, CONCAT(e.first_name,' ',e.last_name) as name,
                           pr.gross_pay, pr.pay_date
                           FROM payroll_records pr
                           JOIN employees e ON pr.employee_id=e.employee_id
                           ORDER BY pr.pay_date DESC LIMIT 5")->fetchAll();

echo json_encode([
    "success" => true,
    "data" => [
        "total_employees"  => (int)$totalEmp,
        "total_payrolls"   => (int)$totalPay,
        "total_gross_paid" => (float)$totalGross,
        "recent_payrolls"  => $recentPay
    ]
]);