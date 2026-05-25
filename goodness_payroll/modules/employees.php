<?php
require_once 'config/database.php';
require_once 'includes/log_activity.php';

$message = '';
$messageType = '';
$isAdmin = ($_SESSION['role'] ?? 'staff') === 'admin';
$userId = $_SESSION['user_id'];

// CREATE - Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $employee_code = trim($_POST['employee_code']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $basic_salary = floatval($_POST['basic_salary']);
    $hire_date = $_POST['hire_date'];
    
    $errors = [];
    if (empty($employee_code)) $errors[] = "Employee code required";
    if (empty($first_name)) $errors[] = "First name required";
    if (empty($last_name)) $errors[] = "Last name required";
    if (empty($email)) $errors[] = "Email required";
    if (empty($department)) $errors[] = "Department required";
    if (empty($position)) $errors[] = "Position required";
    if ($basic_salary <= 0) $errors[] = "Valid salary required";
    if (empty($hire_date)) $errors[] = "Hire date required";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    if (empty($errors)) {
        $checkCode = $db->prepare("SELECT COUNT(*) FROM employees WHERE employee_code = ?");
        $checkCode->execute([$employee_code]);
        if ($checkCode->fetchColumn() > 0) {
            $message = "Error: Employee code '$employee_code' already exists!";
            $messageType = "danger";
        } else {
            $checkEmail = $db->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetchColumn() > 0) {
                $message = "Error: Email '$email' is already registered!";
                $messageType = "danger";
            } else {
                $stmt = $db->prepare("INSERT INTO employees (employee_code, first_name, last_name, email, department, emp_position, basic_salary, hire_date, emp_status, version_number) VALUES (?,?,?,?,?,?,?,?,'active', 1)");
                try {
                    $stmt->execute([$employee_code, $first_name, $last_name, $email, $department, $position, $basic_salary, $hire_date]);
                    
                    logActivity($db, $userId, 'ADD_EMPLOYEE', 'Employees', "Added employee: $employee_code - $first_name $last_name");
                    
                    $message = "Employee added successfully!";
                    $messageType = "success";
                    echo "<script>setTimeout(function() { window.location.href = '?page=employees'; }, 1500);</script>";
                } catch(PDOException $e) {
                    $message = "Database Error: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
        }
    } else {
        $message = implode(", ", $errors);
        $messageType = "danger";
    }
}

// UPDATE - Edit Employee with CONCURRENCY CONTROL (Version Checking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $employee_id = $_POST['employee_id'];
    $current_version = $_POST['current_version'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $basic_salary = floatval($_POST['basic_salary']);
    $emp_status = $_POST['emp_status'];
    
    // First, check if version still matches (Concurrency Control)
    $checkStmt = $db->prepare("SELECT version_number, employee_code, first_name, last_name FROM employees WHERE employee_id = ?");
    $checkStmt->execute([$employee_id]);
    $currentData = $checkStmt->fetch();
    
    if (!$currentData) {
        $message = "Employee not found!";
        $messageType = "danger";
    } elseif ($currentData['version_number'] != $current_version) {
        // CONCURRENCY CONFLICT - someone else modified this record
        $message = "⚠️ CONCURRENCY CONFLICT: This record was modified by another user. Current version: " . $currentData['version_number'] . ", Your version: " . $current_version . ". Please refresh and try again.";
        $messageType = "danger";
    } else {
        $errors = [];
        if (empty($first_name)) $errors[] = "First name required";
        if (empty($last_name)) $errors[] = "Last name required";
        if (empty($email)) $errors[] = "Email required";
        if (empty($department)) $errors[] = "Department required";
        if (empty($position)) $errors[] = "Position required";
        if ($basic_salary <= 0) $errors[] = "Valid salary required";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        if (empty($errors)) {
            $checkEmail = $db->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND employee_id != ?");
            $checkEmail->execute([$email, $employee_id]);
            if ($checkEmail->fetchColumn() > 0) {
                $message = "Error: Email '$email' is already registered to another employee!";
                $messageType = "danger";
            } else {
                // UPDATE with version increment - only if version still matches (Optimistic Locking)
                $stmt = $db->prepare("UPDATE employees SET first_name=?, last_name=?, email=?, department=?, emp_position=?, basic_salary=?, emp_status=?, version_number = version_number + 1 WHERE employee_id=? AND version_number=?");
                $stmt->execute([$first_name, $last_name, $email, $department, $position, $basic_salary, $emp_status, $employee_id, $current_version]);
                
                if ($stmt->rowCount() == 0) {
                    // No rows updated - version mismatch (Another user modified it)
                    $message = "⚠️ CONCURRENCY CONFLICT: Record was modified by another user while you were editing. Please refresh and try again.";
                    $messageType = "danger";
                } else {
                    logActivity($db, $userId, 'EDIT_EMPLOYEE', 'Employees', "Edited employee: {$currentData['employee_code']} - $first_name $last_name (Version: " . ($current_version + 1) . ")");
                    $message = "Employee updated successfully! (Version: " . ($current_version + 1) . ")";
                    $messageType = "success";
                    echo "<script>setTimeout(function() { window.location.href = '?page=employees'; }, 1500);</script>";
                }
            }
        } else {
            $message = implode(", ", $errors);
            $messageType = "danger";
        }
    }
}

// DELETE - Remove Employee
if (isset($_GET['delete']) && $isAdmin) {
    $id = intval($_GET['delete']);
    
    $getEmp = $db->prepare("SELECT employee_code, first_name, last_name FROM employees WHERE employee_id = ?");
    $getEmp->execute([$id]);
    $emp = $getEmp->fetch();
    
    $check = $db->prepare("SELECT COUNT(*) FROM payroll_records WHERE employee_id = ?");
    $check->execute([$id]);
    $payrollCount = $check->fetchColumn();
    if ($payrollCount > 0) {
        $message = "Cannot delete - has $payrollCount payroll record(s). Use 'Inactive' instead.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->execute([$id]);
            
            logActivity($db, $userId, 'DELETE_EMPLOYEE', 'Employees', "Deleted employee: {$emp['employee_code']} - {$emp['first_name']} {$emp['last_name']}");
            
            $message = "Employee deleted successfully!";
            $messageType = "success";
            echo "<script>setTimeout(function() { window.location.href = '?page=employees'; }, 1500);</script>";
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// SET INACTIVE
if (isset($_GET['deactivate']) && $isAdmin) {
    $id = intval($_GET['deactivate']);
    try {
        $stmt = $db->prepare("UPDATE employees SET emp_status = 'inactive', version_number = version_number + 1 WHERE employee_id = ?");
        $stmt->execute([$id]);
        $message = "Employee set to Inactive.";
        $messageType = "warning";
        echo "<script>setTimeout(function() { window.location.href = '?page=employees'; }, 1500);</script>";
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// READ - Get All Employees
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$query = "SELECT * FROM employees WHERE 1=1";
$params = [];
if ($search) { 
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ? OR email LIKE ?)"; 
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; 
}
if ($department_filter) { 
    $query .= " AND department = ?"; 
    $params[] = $department_filter; 
}
$query .= " ORDER BY employee_id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll();
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE emp_status = 'active'")->fetchColumn();
$totalSalary = $db->query("SELECT SUM(basic_salary) FROM employees")->fetchColumn();
$avgSalary = $totalEmployees > 0 ? $totalSalary / $totalEmployees : 0;
?>

<style>
.employee-stat-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 18px;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}
.employee-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    border-color: #ff4d6d;
}
.stat-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-primary);
}
.stat-label {
    color: var(--text-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-sub {
    color: var(--text-muted);
    font-size: 11px;
    margin-top: 5px;
}
.stat-icon {
    background: var(--hover-bg);
    padding: 12px;
    border-radius: 16px;
}
.search-wrapper { position: relative; }
.search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #e6395c; }
.search-input {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 12px;
    padding: 12px 16px 12px 45px;
    color: var(--text-primary);
    width: 100%;
}
.search-input:focus {
    border-color: #e6395c;
    outline: none;
    box-shadow: 0 0 0 3px rgba(230,57,92,0.1);
}
.filter-select {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    width: 100%;
}
.filter-icon-btn {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border: none;
    border-radius: 10px;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
}
.btn-add {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.btn-edit {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.btn-inactive {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}
.btn-delete {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}
.employee-table-container {
    background: var(--card-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}
.employee-table {
    width: 100%;
    border-collapse: collapse;
}
.employee-table th {
    background: var(--table-header-bg);
    color: #e6395c;
    padding: 12px;
    font-weight: 600;
    font-size: 11px;
}
.employee-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
}
.employee-table tr:hover td {
    background: var(--hover-bg);
}
.employee-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
}
.code-badge {
    background: var(--hover-bg);
    color: #e6395c;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-family: monospace;
}
.version-badge {
    background: #e0e7ff;
    color: #4f46e5;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-family: monospace;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}
.status-active { background: #d1fae5; color: #059669; }
.status-inactive { background: #fed7aa; color: #ea580c; }
.action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.modal-content-custom { background: var(--card-bg); border-radius: 20px; }
.modal-header-custom { background: linear-gradient(135deg, #ff4d6d, #e6395c); border: none; padding: 20px; border-radius: 20px 20px 0 0; }
.modal-title-custom { color: white; font-weight: 700; }
.form-control-custom {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 12px;
    padding: 10px 14px;
    color: var(--text-primary);
    width: 100%;
}
.form-label-custom {
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
}
.info-alert {
    background: var(--hover-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
}
.info-alert i { color: #e6395c; }
.info-alert small { color: var(--text-muted); }
@media (max-width: 768px) { .action-buttons { flex-direction: column; } }
</style>

<div>
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="employee-stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-number"><?php echo $totalEmployees; ?></div><div class="stat-label">Total Employees</div><div class="stat-sub"><?php echo $activeEmployees; ?> active</div></div>
                    <div class="stat-icon"><i class="fas fa-users fa-xl" style="color: #e6395c;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="employee-stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-number"><?php echo count($departments); ?></div><div class="stat-label">Departments</div><div class="stat-sub">Across company</div></div>
                    <div class="stat-icon"><i class="fas fa-building fa-xl" style="color: #4f46e5;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="employee-stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-number">₱<?php echo number_format($totalSalary, 2); ?></div><div class="stat-label">Monthly Payroll</div><div class="stat-sub">Total salary</div></div>
                    <div class="stat-icon"><i class="fas fa-chart-line fa-xl" style="color: #d97706;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="employee-stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-number">₱<?php echo number_format($avgSalary, 2); ?></div><div class="stat-label">Average Salary</div><div class="stat-sub">Per employee</div></div>
                    <div class="stat-icon"><i class="fas fa-chart-simple fa-xl" style="color: #0ea5e9;"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="employee-table-container mb-4" style="padding: 20px;">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="employees">
            <div class="col-md-5">
                <label class="form-label-custom">Search Employee</label>
                <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" name="search" class="search-input" placeholder="Name, code or email..." value="<?php echo htmlspecialchars($search); ?>"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Filter by Department</label>
                <select name="department" class="filter-select">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="filter-icon-btn"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>
    
    <!-- Add Button -->
    <?php if($isAdmin): ?>
    <div class="mb-4 text-end"><button class="btn-add" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fas fa-plus-circle"></i> Add New Employee</button></div>
    <?php endif; ?>
    
    <!-- Employees Table -->
    <div class="employee-table-container">
        <div class="table-responsive">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Version</th>
                        <?php if($isAdmin): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNum = 1; foreach($employees as $emp): $initial = strtoupper(substr($emp['first_name'], 0, 1)); $colors = ['#e6395c','#ff4d6d','#f4a261','#2a9d8f','#9b5de5']; $color = $colors[array_rand($colors)]; ?>
                    <tr>
                        <td><?php echo $rowNum++; ?></td>
                        <td><span class="code-badge"><?php echo htmlspecialchars($emp['employee_code']); ?></span></td>
                        <td><div class="d-flex align-items-center gap-2"><div class="employee-avatar" style="background:<?php echo $color; ?>;"><?php echo $initial; ?></div><div><strong><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></strong></div></div></td>
                        <td><small><?php echo htmlspecialchars($emp['email']); ?></small></td>
                        <td><span class="code-badge" style="background:var(--hover-bg);"><?php echo htmlspecialchars($emp['department']); ?></span></td>
                        <td><?php echo htmlspecialchars($emp['emp_position']); ?></td>
                        <td class="text-success fw-bold">₱<?php echo number_format($emp['basic_salary'],2); ?></td>
                        <td><?php if($emp['emp_status']=='active'): ?><span class="status-badge status-active"><i class="fas fa-circle" style="font-size:8px;"></i> Active</span><?php elseif($emp['emp_status']=='inactive'): ?><span class="status-badge status-inactive"><i class="fas fa-circle" style="font-size:8px;"></i> Inactive</span><?php else: ?><span class="status-badge status-inactive"><i class="fas fa-circle" style="font-size:8px;"></i> Terminated</span><?php endif; ?></td>
                        <td><span class="version-badge"><i class="fas fa-code-branch"></i> v<?php echo $emp['version_number']; ?></span></td>
                        <?php if($isAdmin): ?>
                        <td><div class="action-buttons"><button class="btn-edit" onclick='editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)'><i class="fas fa-edit"></i> Edit</button><?php if($emp['emp_status']=='active'): ?><a href="?page=employees&deactivate=<?php echo $emp['employee_id']; ?>" class="btn-inactive" onclick="return confirm('Set to Inactive?')"><i class="fas fa-user-slash"></i> Inactive</a><?php endif; ?><a href="?page=employees&delete=<?php echo $emp['employee_id']; ?>" class="btn-delete" onclick="return confirm('Delete permanently? This will also delete all payroll records for this employee!')"><i class="fas fa-trash-alt"></i> Delete</a></div></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if(empty($employees)): ?><div class="text-center py-5"><i class="fas fa-user-slash fa-3x mb-3" style="color:var(--border-color);"></i><h5 style="color:var(--text-primary);">No Employees Found</h5><p style="color:var(--text-muted);">Click "Add Employee" to get started.</p><?php if($isAdmin): ?><button class="btn-add mt-2" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fas fa-plus-circle"></i> Add Employee</button><?php endif; ?></div><?php endif; ?>
    </div>
    
    <div class="info-alert">
        <i class="fas fa-info-circle"></i>
        <small><strong>Concurrency Control Active:</strong> Each employee record has a version number. If two users edit the same employee simultaneously, the second user will see a conflict warning and must refresh before editing.</small>
    </div>
    <div class="info-alert mt-2">
        <i class="fas fa-info-circle"></i>
        <small>Employees with existing payroll records cannot be deleted. Use the "Inactive" button instead to preserve payroll history.</small>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom"><h5 class="modal-title modal-title-custom"><i class="fas fa-user-plus me-2"></i> Add New Employee</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body" style="padding:25px;">
                    <div class="row">
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Employee Code *</label><input type="text" name="employee_code" class="form-control-custom" placeholder="EMP011" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Email *</label><input type="email" name="email" class="form-control-custom" placeholder="employee@goodness.com" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">First Name *</label><input type="text" name="first_name" class="form-control-custom" placeholder="First Name" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Last Name *</label><input type="text" name="last_name" class="form-control-custom" placeholder="Last Name" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Department *</label><input type="text" name="department" class="form-control-custom" placeholder="Department" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Position *</label><input type="text" name="position" class="form-control-custom" placeholder="Position" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Basic Salary (₱) *</label><input type="number" step="0.01" name="basic_salary" class="form-control-custom" placeholder="0.00" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label class="form-label-custom">Hire Date *</label><input type="date" name="hire_date" class="form-control-custom" required></div></div>
                    </div>
                </div>
                <div class="modal-footer" style="padding:15px 25px; border-top:1px solid var(--border-color);"><button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:40px;">Cancel</button><button type="submit" name="add_employee" class="btn-add">Add Employee</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function editEmployee(emp) {
    const modalHtml = `<div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title modal-title-custom"><i class="fas fa-user-edit me-2"></i> Edit Employee</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="padding:25px;">
                        <input type="hidden" name="employee_id" value="${emp.employee_id}">
                        <input type="hidden" name="current_version" value="${emp.version_number}">
                        <div class="alert alert-info" style="font-size: 12px; margin-bottom: 15px; background: #e0e7ff; border: none; border-radius: 12px;">
                            <i class="fas fa-code-branch"></i> <strong>Concurrency Control Active</strong> - Version: ${emp.version_number}
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">First Name *</label>
                                    <input type="text" name="first_name" class="form-control-custom" value="${emp.first_name.replace(/"/g, '&quot;')}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control-custom" value="${emp.last_name.replace(/"/g, '&quot;')}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label-custom">Email *</label>
                                    <input type="email" name="email" class="form-control-custom" value="${emp.email.replace(/"/g, '&quot;')}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Department *</label>
                                    <input type="text" name="department" class="form-control-custom" value="${emp.department.replace(/"/g, '&quot;')}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Position *</label>
                                    <input type="text" name="position" class="form-control-custom" value="${emp.emp_position.replace(/"/g, '&quot;')}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Basic Salary *</label>
                                    <input type="number" step="0.01" name="basic_salary" class="form-control-custom" value="${emp.basic_salary}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Status</label>
                                    <select name="emp_status" class="form-control-custom">
                                        <option value="active" ${emp.emp_status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${emp.emp_status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        <option value="terminated" ${emp.emp_status === 'terminated' ? 'selected' : ''}>Terminated</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding:15px 25px; border-top:1px solid var(--border-color);">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:40px;">Cancel</button>
                        <button type="submit" name="edit_employee" class="btn-add">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
    
    $('body').append(modalHtml);
    $('#editModal').modal('show');
    $('#editModal').on('hidden.bs.modal', function() { $(this).remove(); });
}
</script>

<?php if($message): ?>
<div class="toast-custom">
    <div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="5000">
        <div class="toast-header" style="background: <?php echo $messageType == 'success' ? '#10b981' : ($messageType == 'warning' ? '#f59e0b' : '#e6395c'); ?>; color: white;">
            <i class="fas fa-bell me-2"></i>
            <strong class="me-auto">Employee Management</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" style="background: var(--card-bg); color: var(--text-primary);"><?php echo $message; ?></div>
    </div>
</div>
<?php endif; ?>