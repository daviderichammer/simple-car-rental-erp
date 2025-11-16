<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Maintenance Management</h2>
        <p>Schedule and track vehicle maintenance</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="showAddMaintenanceModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Maintenance</button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteMaintenance()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Maintenance Schedule</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Vehicle</th>
                <th>Maintenance Type</th>
                <th>Scheduled Date</th>
                <th>Status</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT m.*, CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_name
                FROM maintenance_schedules m
                JOIN vehicles v ON m.vehicle_id = v.id
                ORDER BY m.scheduled_date
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td><input type='checkbox' class='row-checkbox' value='" . $row['id'] . "' onchange='updateBulkDeleteButton()'></td>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['maintenance_type']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['scheduled_date'])) . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editMaintenance(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteMaintenance(" . $row['id'] . ", \"" . htmlspecialchars($row['vehicle_name']) . "\", \"" . htmlspecialchars($row['maintenance_type']) . "\")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Maintenance Modal -->
<div id="editMaintenanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Maintenance</h3>
            <span class="close" onclick="closeModal('editMaintenanceModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editMaintenanceForm">
                <input type="hidden" id="edit_maintenance_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_maintenance_vehicle_id">Vehicle</label>
                        <select id="edit_vehicle_id" name="vehicle_id" required>
                            <?php
                            $stmt = $pdo->query("SELECT id, make, model, license_plate FROM vehicles ORDER BY make, model");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')') . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_type">Maintenance Type</label>
                        <input type="text" id="edit_maintenance_type" name="maintenance_type" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_scheduled_date">Scheduled Date</label>
                        <input type="date" id="edit_scheduled_date" name="scheduled_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="submitEditMaintenance()">Save Changes</button>
            <button type="button" onclick="closeEditMaintenanceModal()" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<!-- Add Maintenance Modal -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚙️ Add New Maintenance</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddMaintenanceModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addMaintenanceForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="add_vehicle_id" class="form-control" required>
                                <option value="">Select a vehicle...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, make, model, license_plate FROM vehicles ORDER BY make, model");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')') . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                            <input type="text" name="maintenance_type" id="add_maintenance_type" class="form-control" placeholder="e.g., Oil Change, Tire Rotation" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" id="add_scheduled_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="add_status" class="form-control" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="add_description" class="form-control" rows="3" placeholder="Enter maintenance details..." required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddMaintenanceModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddMaintenance()">Add Maintenance</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMaintenanceModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Maintenance</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteMaintenanceModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this maintenance record? This action cannot be undone.</p>
                <p><strong id="deleteMaintenanceInfo"></strong></p>
                <input type="hidden" id="delete_maintenance_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteMaintenanceModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteMaintenance()">Delete Maintenance</button>
            </div>
        </div>
    </div>
</div>

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

<script>
// Show Add Maintenance Modal
function showAddMaintenanceModal() {
    document.getElementById('addMaintenanceForm').reset();
    document.getElementById('addMaintenanceModal').style.display = 'block';
    document.getElementById('addMaintenanceModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Maintenance Modal
function closeAddMaintenanceModal() {
    document.getElementById('addMaintenanceModal').style.display = 'none';
    document.getElementById('addMaintenanceModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Maintenance
function submitAddMaintenance() {
    const form = document.getElementById('addMaintenanceForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_maintenance');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddMaintenanceModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add maintenance'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the maintenance');
    });
}

// Show Delete Confirmation Modal
// Edit Maintenance
function editMaintenance(id) {
    // Fetch maintenance data
    fetch('index.php?ajax=1&action=get_maintenance&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const maintenance = data.maintenance;
                document.getElementById('edit_maintenance_id').value = maintenance.id;
                document.getElementById('edit_vehicle_id').value = maintenance.vehicle_id;
                document.getElementById('edit_maintenance_type').value = maintenance.maintenance_type;
                document.getElementById('edit_scheduled_date').value = maintenance.scheduled_date;
                document.getElementById('edit_status').value = maintenance.status;
                document.getElementById('edit_description').value = maintenance.description || '';
                
                document.getElementById('editMaintenanceModal').style.display = 'block';
                document.getElementById('editMaintenanceModal').classList.add('show');
                document.getElementById('modalBackdrop').style.display = 'block';
                document.getElementById('modalBackdrop').classList.add('show');
            } else {
                alert('Error: ' + (data.message || 'Failed to load maintenance data'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading the maintenance data');
        });
}

// Close Edit Maintenance Modal
function closeEditMaintenanceModal() {
    document.getElementById('editMaintenanceModal').style.display = 'none';
    document.getElementById('editMaintenanceModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Edit Maintenance
function submitEditMaintenance() {
    const formData = new FormData(document.getElementById('editMaintenanceForm'));
    formData.append('ajax', '1');
    formData.append('action', 'edit_maintenance');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditMaintenanceModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update maintenance'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the maintenance');
    });
}

function deleteMaintenance(id, vehicleName, maintenanceType) {
    document.getElementById('delete_maintenance_id').value = id;
    document.getElementById('deleteMaintenanceInfo').textContent = vehicleName + ' - ' + maintenanceType;
    document.getElementById('deleteMaintenanceModal').style.display = 'block';
    document.getElementById('deleteMaintenanceModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Maintenance Modal
function closeDeleteMaintenanceModal() {
    document.getElementById('deleteMaintenanceModal').style.display = 'none';
    document.getElementById('deleteMaintenanceModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Maintenance
function confirmDeleteMaintenance() {
    const id = document.getElementById('delete_maintenance_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_maintenance');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteMaintenanceModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete maintenance'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the maintenance');
    });
}

// Bulk Operations Functions
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectedCount = document.getElementById('selectedCount');
    
    if (count > 0) {
        bulkDeleteBtn.style.display = 'block';
        selectedCount.textContent = count;
    } else {
        bulkDeleteBtn.style.display = 'none';
    }
    
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
}

function bulkDeleteMaintenance() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one maintenance record to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} maintenance record(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_maintenance&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} maintenance record(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete maintenance records'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting maintenance records');
    });
}
</script>
