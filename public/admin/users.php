<?php
require_once '../../includes/init.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'activate':
        case 'deactivate':
            $user_id = (int)$_GET['id'];
            $is_active = $_GET['action'] === 'activate' ? 1 : 0;
            $sql = "UPDATE users SET is_active = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $is_active, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $action = $is_active ? 'activated' : 'deactivated';
                log_activity($_SESSION['user_id'], 'user_update', "User ID $user_id $action");
                flash_message('success', "User $action successfully.");
            } else {
                flash_message('error', 'Failed to update user.');
            }
            header('Location: users.php');
            exit();
            
        case 'delete':
            $user_id = (int)$_GET['id'];
            // Check if user has land records
            $check_sql = "SELECT COUNT(*) as count FROM land_records WHERE owner_id = ?";
            $stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                flash_message('error', 'Cannot delete user with land records. Transfer or delete records first.');
            } else {
                $sql = "DELETE FROM users WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($_SESSION['user_id'], 'user_delete', "User ID $user_id deleted");
                    flash_message('success', 'User deleted successfully.');
                } else {
                    flash_message('error', 'Failed to delete user.');
                }
            }
            header('Location: users.php');
            exit();
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['user_ids'])) {
    $user_ids = implode(',', array_map('intval', $_POST['user_ids']));
    
    switch ($_POST['bulk_action']) {
        case 'activate':
            $sql = "UPDATE users SET is_active = 1 WHERE user_id IN ($user_ids)";
            $action = 'activated';
            break;
        case 'deactivate':
            $sql = "UPDATE users SET is_active = 0 WHERE user_id IN ($user_ids)";
            $action = 'deactivated';
            break;
        case 'delete':
            // Check for land records first
            $check_sql = "SELECT COUNT(*) as count FROM land_records WHERE owner_id IN ($user_ids)";
            $result = mysqli_query($conn, $check_sql);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                flash_message('error', 'Some users have land records and cannot be deleted.');
                header('Location: users.php');
                exit();
            }
            
            $sql = "DELETE FROM users WHERE user_id IN ($user_ids)";
            $action = 'deleted';
            break;
        default:
            header('Location: users.php');
            exit();
    }
    
    if (mysqli_query($conn, $sql)) {
        log_activity($_SESSION['user_id'], 'user_bulk', count($_POST['user_ids']) . " users $action");
        flash_message('success', "Selected users $action successfully.");
    }
    header('Location: users.php');
    exit();
}

// Build query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($role_filter)) {
    $where[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($status_filter === 'active') {
    $where[] = "is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where[] = "is_active = 0";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total users
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_users = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$items_per_page = get_setting('items_per_page', 20);
$pagination = paginate($page, $items_per_page, $total_users);

// Get users
$sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT ?, ?";
$types .= 'ii';
$params[] = $pagination['offset'];
$params[] = $pagination['limit'];

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt);

// Get user statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'officer' THEN 1 ELSE 0 END) as officers,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .bulk-select {
            margin-right: 10px;
        }
        
        .bulk-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Users Management</h1>
                    <p>Manage system users and their roles</p>
                </div>
                <div class="header-right">
                    <a href="users.php?action=add" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                </div>
            </header>

            <div class="admin-content">
                <?php if (isset($_SESSION['flash'])): ?>
                    <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                        <div class="alert alert-<?php echo $type; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="stats-cards">
                    <div class="stat-mini">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p>Total Users</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['admins']; ?></h4>
                        <p>Admins</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['officers']; ?></h4>
                        <p>Officers</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['users']; ?></h4>
                        <p>Regular Users</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['active']; ?></h4>
                        <p>Active</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['inactive']; ?></h4>
                        <p>Inactive</p>
                    </div>
                </div>

                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email or phone" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="officer" <?php echo $role_filter === 'officer' ? 'selected' : ''; ?>>Officer</option>
                                <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="users.php" class="btn" style="margin-top: 5px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <form method="POST" id="bulk-form">
                    <div class="bulk-actions">
                        <select name="bulk_action" class="form-control" style="width: auto;">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>

                    <div class="table-card">
                        <div class="table-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="select-all">
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Last Login</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($users) > 0): ?>
                                        <?php while ($user = mysqli_fetch_assoc($users)): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="user_ids[]" value="<?php echo $user['user_id']; ?>" class="user-checkbox">
                                                </td>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo format_date($user['created_at']); ?></td>
                                                <td><?php echo $user['last_login'] ? format_date($user['last_login']) : 'Never'; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-dropdown">
                                                        <button class="btn-small">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-content">
                                                            <a href="users.php?action=view&id=<?php echo $user['user_id']; ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <?php if ($user['is_active']): ?>
                                                                <a href="users.php?action=deactivate&id=<?php echo $user['user_id']; ?>" 
                                                                   onclick="return confirm('Deactivate this user?')">
                                                                    <i class="fas fa-ban"></i> Deactivate
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="users.php?action=activate&id=<?php echo $user['user_id']; ?>">
                                                                    <i class="fas fa-check"></i> Activate
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" 
                                                               onclick="return confirm('Delete this user? This action cannot be undone.')"
                                                               style="color: var(--danger);">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-users"></i>
                                                    <p>No users found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . $role_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . $role_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . $role_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all checkboxes
            document.getElementById('select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Bulk form submission
            document.getElementById('bulk-form').addEventListener('submit', function(e) {
                const action = document.querySelector('select[name="bulk_action"]').value;
                const checkboxes = document.querySelectorAll('.user-checkbox:checked');
                
                if (!action) {
                    e.preventDefault();
                    alert('Please select a bulk action.');
                    return;
                }
                
                if (checkboxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one user.');
                    return;
                }
                
                if (action === 'delete') {
                    if (!confirm(`Delete ${checkboxes.length} user(s)? This action cannot be undone.`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>