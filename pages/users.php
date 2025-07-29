<div class="page-header">
    <h2>User Management</h2>
    <p>Manage system users and accounts</p>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">System Users</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT u.*, GROUP_CONCAT(r.display_name SEPARATOR ', ') as role_names
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id
                ORDER BY u.last_name, u.first_name
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['role_names'] ?: 'No roles assigned') . "</td>";
                echo "<td>" . ($row['is_active'] ? 'Active' : 'Inactive') . "</td>";
                echo "<td>" . ($row['last_login'] ? date('M j, Y', strtotime($row['last_login'])) : 'Never') . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editUser(" . $row['id'] . ")'>Edit</button>";
                if ($row['id'] != $_SESSION['user_id']) {
                    echo "<button class='btn-delete' onclick='deleteUser(" . $row['id'] . ")'>Delete</button>";
                }
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <span class="close" onclick="closeModal('editUserModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editUserForm">
                <input type="hidden" id="edit_user_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_user_first_name">First Name</label>
                        <input type="text" id="edit_user_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_last_name">Last Name</label>
                        <input type="text" id="edit_user_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_email">Email</label>
                        <input type="email" id="edit_user_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_is_active">Status</label>
                        <select id="edit_user_is_active" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveUser()">Save Changes</button>
            <button type="button" onclick="closeModal('editUserModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>
<?php
