?>
<div class="page-header">
    <h2>Role Management</h2>
    <p>Manage user roles and permissions</p>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">System Roles</h3>
    <table>
        <thead>
            <tr>
                <th>Role Name</th>
                <th>Display Name</th>
                <th>Description</th>
                <th>Users</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.*, COUNT(ur.user_id) as user_count
                FROM roles r
                LEFT JOIN user_roles ur ON r.id = ur.role_id
                GROUP BY r.id
                ORDER BY r.name
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['display_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                echo "<td>" . $row['user_count'] . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editRole(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteRole(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Role</h3>
            <span class="close" onclick="closeModal('editRoleModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editRoleForm">
                <input type="hidden" id="edit_role_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_role_name">Role Name</label>
                        <input type="text" id="edit_role_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role_display_name">Display Name</label>
                        <input type="text" id="edit_role_display_name" name="display_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role_description">Description</label>
                        <textarea id="edit_role_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveRole()">Save Changes</button>
            <button type="button" onclick="closeModal('editRoleModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>
<?php
