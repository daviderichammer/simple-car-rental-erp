<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>Role Management</h2>
    <p>Manage user roles and permissions</p>
    </div>
    <button onclick="showAddRoleModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Role</button>
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

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">🔐 Add New Role</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddRoleModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addRoleForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="add_name" class="form-control" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description </label>
                            <textarea name="description" id="add_description" class="form-control" rows="3" ></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddRoleModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddRole()">Add Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Role</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteRoleModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this role? This action cannot be undone.</p>
                <p><strong id="deleteRoleInfo"></strong></p>
                <input type="hidden" id="delete_role_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteRoleModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteRole()">Delete Role</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Role Modal
function showAddRoleModal() {
    document.getElementById('addRoleForm').reset();
    document.getElementById('addRoleModal').style.display = 'block';
    document.getElementById('addRoleModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Role Modal
function closeAddRoleModal() {
    document.getElementById('addRoleModal').style.display = 'none';
    document.getElementById('addRoleModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Role
function submitAddRole() {
    const form = document.getElementById('addRoleForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_role');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddRoleModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add role'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the role');
    });
}

// Show Delete Confirmation Modal
function deleteRole(id, name) {
    document.getElementById('delete_role_id').value = id;
    document.getElementById('deleteRoleInfo').textContent = name;
    document.getElementById('deleteRoleModal').style.display = 'block';
    document.getElementById('deleteRoleModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Role Modal
function closeDeleteRoleModal() {
    document.getElementById('deleteRoleModal').style.display = 'none';
    document.getElementById('deleteRoleModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Role
function confirmDeleteRole() {
    const id = document.getElementById('delete_role_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_role');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteRoleModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete role'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the role');
    });
}
</script>