<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

// Check authentication and admin role
if (!isset($_SESSION['authenticated']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$errorMsg = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    
    if ($userId) {
        switch ($action) {
            case 'approve':
                $db->updateUserStatus($userId, 'approved');
                $message = 'User approved successfully!';
                Logger::info("User $userId approved by admin");
                break;
            case 'reject':
                $db->updateUserStatus($userId, 'rejected');
                $message = 'User rejected!';
                Logger::info("User $userId rejected by admin");
                break;
            case 'activate':
                $db->updateUserStatus($userId, 'approved');
                $message = 'User activated!';
                Logger::info("User $userId activated by admin");
                break;
            case 'deactivate':
                $db->updateUserStatus($userId, 'pending');
                $message = 'User deactivated!';
                Logger::info("User $userId deactivated by admin");
                break;
            case 'delete':
                $db->deleteUser($userId);
                $message = 'User deleted!';
                Logger::info("User $userId deleted by admin");
                break;
        }
    }
}

$users = $db->getAllUsers();
$pendingCount = count(array_filter($users, function($u) { return $u['status'] === 'pending'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .page-header h1 {
            color: #0066cc;
            margin: 0;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card.pending h3 {
            color: #f57c00;
        }
        
        .stat-card.pending .number {
            color: #f57c00;
        }
        
        .stat-card.approved h3 {
            color: #388e3c;
        }
        
        .stat-card.approved .number {
            color: #388e3c;
        }
        
        .stat-card.rejected h3 {
            color: #d32f2f;
        }
        
        .stat-card.rejected .number {
            color: #d32f2f;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .users-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .users-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        .users-table tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-badge.approved {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-badge.rejected {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-badge.admin {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .role-badge.user {
            background: #f5f5f5;
            color: #666;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 10px;
            font-size: 11px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-approve {
            background: #4caf50;
            color: white;
        }
        
        .btn-approve:hover {
            background: #388e3c;
        }
        
        .btn-reject {
            background: #ff9800;
            color: white;
        }
        
        .btn-reject:hover {
            background: #f57c00;
        }
        
        .btn-activate {
            background: #2196f3;
            color: white;
        }
        
        .btn-activate:hover {
            background: #1976d2;
        }
        
        .btn-deactivate {
            background: #9e9e9e;
            color: white;
        }
        
        .btn-deactivate:hover {
            background: #757575;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        
        <div class="admin-container">
            <div class="page-header">
                <h1>👥 User Management</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <div class="stats-cards">
                <div class="stat-card pending">
                    <h3>⏳ Pending Approval</h3>
                    <div class="number"><?php echo $pendingCount; ?></div>
                </div>
                <div class="stat-card approved">
                    <h3>✅ Active Users</h3>
                    <div class="number"><?php echo count(array_filter($users, function($u) { return $u['status'] === 'approved'; })); ?></div>
                </div>
                <div class="stat-card rejected">
                    <h3>❌ Rejected</h3>
                    <div class="number"><?php echo count(array_filter($users, function($u) { return $u['status'] === 'rejected'; })); ?></div>
                </div>
                <div class="stat-card">
                    <h3>👥 Total Users</h3>
                    <div class="number"><?php echo count($users); ?></div>
                </div>
            </div>

            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>AI Provider</th>
                            <th>AI Model</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['ai_provider']); ?></td>
                                <td><?php echo htmlspecialchars($user['ai_model']); ?></td>
                                <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                <td><span class="status-badge <?php echo $user['status']; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <?php if ($user['status'] === 'pending'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-approve" onclick="return confirm('Approve this user?')">Approve</button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-reject" onclick="return confirm('Reject this user?')">Reject</button>
                                                </form>
                                            <?php elseif ($user['status'] === 'approved'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-deactivate" onclick="return confirm('Deactivate this user?')">Deactivate</button>
                                                </form>
                                            <?php elseif ($user['status'] === 'rejected'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-activate" onclick="return confirm('Reactivate this user?')">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="action-btn btn-delete">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 11px;">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                                    No users found. Users will appear here after registration.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <footer>
    </div>

        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>

