<?php
require_once 'config/database.php';
session_start();

// Admin only check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?page=dashboard");
    exit();
}

$message = '';
$messageType = '';

// Get all users
$stmt = $db->prepare("SELECT * FROM users ORDER BY user_id ASC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get colors for avatars
$avatarColors = [
    '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'
];
?>

<div>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold" style="color: white;">System Users</h5>
                        <p class="text-muted small mb-0">Manage user accounts and roles</p>
                    </div>
                    <button class="btn-green" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-0">
        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Avatar</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): 
                        $initial = strtoupper(substr($user['full_name'], 0, 1));
                        $avatarBg = $user['avatar_color'] ?? '#10b981';
                    ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td>
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $avatarBg; ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo $initial; ?>
                            </div>
                        </td>
                        <td style="color: white;"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span style="background: <?php echo $user['user_role'] == 'admin' ? '#10b981' : '#3b82f6'; ?>; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: white;">
                                <?php echo ucfirst($user['user_role']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="background: <?php echo $user['user_status'] == 'active' ? '#10b981' : '#ef4444'; ?>; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: white;">
                                <?php echo ucfirst($user['user_status']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="color: white;">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-modern" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-modern" value="admin123" required>
                        <small class="text-muted">Default: admin123</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-modern" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-modern" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-modern">
                            <option value="staff">Staff (Limited Access)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avatar Color</label>
                        <select name="avatar_color" class="form-modern">
                            <option value="#10b981">Green</option>
                            <option value="#3b82f6">Blue</option>
                            <option value="#f59e0b">Orange</option>
                            <option value="#ef4444">Red</option>
                            <option value="#8b5cf6">Purple</option>
                            <option value="#ec4899">Pink</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn-green">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>