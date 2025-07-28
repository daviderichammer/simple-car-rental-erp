<?php
// Users Page Content
?>

<div class="page-header">
    <h2>User Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['users'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['users'] ?? [])): ?>
<div class="form-section">
    <h3>Add New User</h3>
    <form method="POST" class="user-form">
        <input type="hidden" name="action" value="add_user">
        
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
        </div>
        
        <div class="form-group">
            <label for="role_ids">Assign Roles:</label>
            <div class="checkbox-group">
                <?php
                $stmt = $pdo->query("SELECT id, name, display_name FROM roles ORDER BY name");
                while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<label class='checkbox-label'>";
                    echo "<input type='checkbox' name='role_ids[]' value='" . $role['id'] . "'>";
                    echo " " . htmlspecialchars($role['display_name'] ?: ucfirst($role['name']));
                    echo "</label>";
                }
                ?>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="form-group">
                <label for="require_password_change">Require Password Change:</label>
                <select id="require_password_change" name="require_password_change">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Add User</button>
    </form>
</div>
<?php endif; ?>

<div class="users-section">
    <h3>System Users</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created</th>
                <?php if (in_array('Edit', $permissions['users'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT u.*, 
                       GROUP_CONCAT(r.display_name SEPARATOR ', ') as role_names
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id
                ORDER BY u.last_name, u.first_name
            ");
            
            while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role_names'] ?: 'No roles assigned') . "</td>";
                echo "<td><span class='status-" . $user['status'] . "'>" . ucfirst($user['status']) . "</span></td>";
                echo "<td>" . ($user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never') . "</td>";
                echo "<td>" . date('M j, Y', strtotime($user['created_at'])) . "</td>";
                
                if (in_array('Edit', $permissions['users'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editUser(" . $user['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['users'] ?? []) && $user['id'] != $_SESSION['user_id']) {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteUser(" . $user['id'] . ")'>Delete</button>";
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<style>
.user-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 5px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    font-weight: normal;
    margin-bottom: 0;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 8px;
    width: auto;
}

.users-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-active {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-inactive {
    background: #e2e3e5;
    color: #383d41;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-suspended {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .checkbox-group {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function editUser(id) {
    // Edit functionality to be implemented
    alert('Edit user functionality - ID: ' + id);
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        // Delete functionality to be implemented
        alert('Delete user functionality - ID: ' + id);
    }
}
</script>

