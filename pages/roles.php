<?php
// Roles Page Content
?>

<div class="page-header">
    <h2>Role Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['roles'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['roles'] ?? [])): ?>
<div class="form-section">
    <h3>Create New Role</h3>
    <form method="POST" class="role-form">
        <input type="hidden" name="action" value="add_role">
        
        <div class="form-row">
            <div class="form-group">
                <label for="name">Role Name:</label>
                <input type="text" id="name" name="name" required placeholder="e.g., manager, staff">
            </div>
            <div class="form-group">
                <label for="display_name">Display Name:</label>
                <input type="text" id="display_name" name="display_name" required placeholder="e.g., Manager, Staff Member">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="3" placeholder="Describe the role and its responsibilities"></textarea>
        </div>
        
        <div class="permissions-section">
            <h4>Assign Permissions</h4>
            <div class="permissions-grid">
                <?php
                $screens = ['dashboard', 'vehicles', 'customers', 'reservations', 'maintenance', 'users', 'roles'];
                $permission_types = ['view', 'create', 'edit', 'delete'];
                
                foreach ($screens as $screen) {
                    echo "<div class='screen-permissions'>";
                    echo "<h5>" . ucfirst($screen) . "</h5>";
                    
                    foreach ($permission_types as $perm) {
                        echo "<label class='permission-label'>";
                        echo "<input type='checkbox' name='permissions[{$screen}][]' value='{$perm}'>";
                        echo " " . ucfirst($perm);
                        echo "</label>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Create Role</button>
    </form>
</div>
<?php endif; ?>

<div class="roles-section">
    <h3>System Roles</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Role Name</th>
                <th>Display Name</th>
                <th>Description</th>
                <th>Users Count</th>
                <th>Permissions</th>
                <?php if (in_array('Edit', $permissions['roles'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.*, 
                       COUNT(ur.user_id) as user_count,
                       GROUP_CONCAT(CONCAT(rp.screen_name, ':', rp.permission_type) SEPARATOR ', ') as role_permissions
                FROM roles r
                LEFT JOIN user_roles ur ON r.id = ur.role_id
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                GROUP BY r.id
                ORDER BY r.name
            ");
            
            while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($role['name']) . "</td>";
                echo "<td>" . htmlspecialchars($role['display_name'] ?: ucfirst($role['name'])) . "</td>";
                echo "<td>" . htmlspecialchars($role['description'] ?: 'No description') . "</td>";
                echo "<td>" . $role['user_count'] . "</td>";
                echo "<td class='permissions-cell'>" . htmlspecialchars($role['role_permissions'] ?: 'No permissions') . "</td>";
                
                if (in_array('Edit', $permissions['roles'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editRole(" . $role['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['roles'] ?? []) && $role['name'] !== 'super_admin') {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteRole(" . $role['id'] . ")'>Delete</button>";
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<div class="permissions-matrix">
    <h3>Permissions Matrix</h3>
    <table class="matrix-table">
        <thead>
            <tr>
                <th>Role</th>
                <?php
                $screens = ['dashboard', 'vehicles', 'customers', 'reservations', 'maintenance', 'users', 'roles'];
                foreach ($screens as $screen) {
                    echo "<th>" . ucfirst($screen) . "</th>";
                }
                ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
            while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($role['display_name'] ?: ucfirst($role['name'])) . "</strong></td>";
                
                foreach ($screens as $screen) {
                    $perms_stmt = $pdo->prepare("
                        SELECT GROUP_CONCAT(permission_type ORDER BY permission_type SEPARATOR ', ') as perms
                        FROM role_permissions 
                        WHERE role_id = ? AND screen_name = ?
                    ");
                    $perms_stmt->execute([$role['id'], $screen]);
                    $perms = $perms_stmt->fetchColumn();
                    
                    echo "<td class='matrix-cell'>" . ($perms ?: '-') . "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<style>
.role-form {
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
.form-group textarea {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.permissions-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.permissions-section h4 {
    margin-bottom: 15px;
    color: #333;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.screen-permissions {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
}

.screen-permissions h5 {
    margin: 0 0 10px 0;
    color: #495057;
    font-size: 14px;
    text-transform: uppercase;
}

.permission-label {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
    font-weight: normal;
    font-size: 13px;
}

.permission-label input[type="checkbox"] {
    margin-right: 8px;
    width: auto;
}

.roles-section,
.permissions-matrix {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.permissions-cell {
    font-size: 12px;
    max-width: 200px;
    word-wrap: break-word;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.matrix-table th,
.matrix-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: center;
    font-size: 12px;
}

.matrix-table th {
    background: #f8f9fa;
    font-weight: bold;
}

.matrix-cell {
    background: #f8f9fa;
    font-size: 11px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function editRole(id) {
    // Edit functionality to be implemented
    alert('Edit role functionality - ID: ' + id);
}

function deleteRole(id) {
    if (confirm('Are you sure you want to delete this role? Users with this role will lose their permissions.')) {
        // Delete functionality to be implemented
        alert('Delete role functionality - ID: ' + id);
    }
}
</script>

