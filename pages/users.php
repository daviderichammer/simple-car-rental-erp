<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>User Management</h2>
    <p>Manage system users and accounts</p>
    </div>
    <button onclick="showAddUserModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New User</button>
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
?>
<!-- Modal Backdrop -->
<div class="modal-backdrop fade" id="modalBackdrop" style="display: none;"></div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1050;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-dialog.modal-lg {
    max-width: 800px;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: 0.3rem;
    outline: 0;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: 0.3rem;
    border-top-right-radius: 0.3rem;
}

.modal-title {
    margin: 0;
    line-height: 1.5;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
}

.modal-backdrop.show {
    opacity: 0.5;
    display: block !important;
}

.btn-close {
    background: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    opacity: .5;
    cursor: pointer;
}

.btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -0.75rem;
    margin-left: -0.75rem;
}

.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding-right: 0.75rem;
    padding-left: 0.75rem;
}

.col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
    padding-right: 0.75rem;
    padding-left: 0.75rem;
}

.mb-3 {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    color: #495057;
    background-color: #fff;
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.text-danger {
    color: #dc3545;
}

.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    cursor: pointer;
}

.btn-primary {
    color: #fff;
    background-color: #667eea;
    border-color: #667eea;
}

.btn-primary:hover {
    background-color: #5568d3;
    border-color: #5568d3;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #545b62;
}

.btn-danger {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}
</style>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">👤 Add New User</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddUserModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="add_username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="add_password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="add_role_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Status </label>
                            <select name="status" id="add_status" class="form-control" >
                                <option value="">Select...</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddUser()">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete User</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteUserModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <p><strong id="deleteUserInfo"></strong></p>
                <input type="hidden" id="delete_user_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteUserModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteUser()">Delete User</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add User Modal
function showAddUserModal() {
    document.getElementById('addUserForm').reset();
    document.getElementById('addUserModal').style.display = 'block';
    document.getElementById('addUserModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add User Modal
function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.getElementById('addUserModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add User
function submitAddUser() {
    const form = document.getElementById('addUserForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_user');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddUserModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add user'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the user');
    });
}

// Show Delete Confirmation Modal
function deleteUser(id, name) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('deleteUserInfo').textContent = name;
    document.getElementById('deleteUserModal').style.display = 'block';
    document.getElementById('deleteUserModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete User Modal
function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
    document.getElementById('deleteUserModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete User
function confirmDeleteUser() {
    const id = document.getElementById('delete_user_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_user');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteUserModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete user'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the user');
    });
}
</script>