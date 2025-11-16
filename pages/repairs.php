<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Repair History</h2>
        <p>Track vehicle repairs and maintenance history</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="showAddRepairModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Repair</button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteRepairs()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Repair History (<?php echo $pdo->query("SELECT COUNT(*) FROM repair_history")->fetchColumn(); ?> records)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Mileage</th>
                <th>Problem</th>
                <th>Repair</th>
                <th>Cost</th>
                <th>Vendor</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.*, CONCAT(v.year, ' ', v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_name
                FROM repair_history r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                ORDER BY r.repair_date DESC, r.id DESC
                LIMIT 50
            ");
            while ($row = $stmt->fetch()) {
            ?>
            <tr>
                <td><input type="checkbox" class="row-checkbox" value="<?php echo $row['id']; ?>" onchange="updateBulkDeleteButton()"></td>
                <td><?php echo $row['repair_date'] ? date('M d, Y', strtotime($row['repair_date'])) : '-'; ?></td>
                <td><?php echo htmlspecialchars($row['vehicle_name'] ?? 'Unknown'); ?></td>
                <td><?php echo $row['mileage'] ? number_format($row['mileage']) : '-'; ?></td>
                <td><?php echo htmlspecialchars($row['problem_description'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['repair_description'] ?? '-'); ?></td>
                <td style="font-weight: bold; color: #dc3545;"><?php echo $row['cost'] ? '$' . number_format($row['cost'], 2) : '-'; ?></td>
                <td><?php echo htmlspecialchars($row['vendor'] ?? '-'); ?></td>
                <td>
                    <span style="background: <?php 
                        echo $row['status'] === 'completed' ? '#28a745' : ($row['status'] === 'in_progress' ? '#ffc107' : '#6c757d'); 
                    ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                    </span>
                </td>
                <td>
                    <button class="btn-edit" onclick="editRepair(<?php echo htmlspecialchars(json_encode($row)); ?>)" style="background: #28a745; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; margin-right: 0.5rem;">Edit</button>
                    <button class="btn-delete" onclick="showDeleteRepairModal(<?php echo $row['id']; ?>)" style="background: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Delete</button>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Modal Backdrop -->
<div class="modal-backdrop fade" id="modalBackdrop" style="display: none;"></div>

<!-- Edit Repair Modal -->
<div class="modal fade" id="editRepairModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">✏️ Edit Repair</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeEditRepairModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="editRepairForm">
                    <input type="hidden" name="action" value="edit_repair">
                    <input type="hidden" name="id" id="edit_repair_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="edit_vehicle_id" class="form-control" required>
                                <option value="">Select a vehicle...</option>
                                <?php
                                $vehicles = $pdo->query("SELECT id, CONCAT(year, ' ', make, ' ', model, ' (', license_plate, ')') as name FROM vehicles ORDER BY make, model")->fetchAll();
                                foreach ($vehicles as $v) {
                                    echo '<option value="' . $v['id'] . '">' . htmlspecialchars($v['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Repair Date</label>
                            <input type="date" name="repair_date" id="edit_repair_date" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mileage</label>
                            <input type="number" name="mileage" id="edit_mileage" class="form-control" placeholder="e.g., 45000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" step="0.01" name="cost" id="edit_cost" class="form-control" placeholder="e.g., 250.00">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor</label>
                            <input type="text" name="vendor" id="edit_vendor" class="form-control" placeholder="e.g., Joe's Auto Shop">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Problem Description</label>
                        <textarea name="problem_description" id="edit_problem_description" class="form-control" rows="3" placeholder="Describe the problem..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Repair Description</label>
                        <textarea name="repair_description" id="edit_repair_description" class="form-control" rows="3" placeholder="Describe the repair work done..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditRepairModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditRepair()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Repair Modal -->
<div class="modal fade" id="addRepairModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">🛠️ Add New Repair</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddRepairModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addRepairForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" class="form-control" required>
                                <option value="">Select a vehicle...</option>
                                <?php
                                $vehicles = $pdo->query("SELECT id, CONCAT(year, ' ', make, ' ', model, ' (', license_plate, ')') as name FROM vehicles ORDER BY make, model")->fetchAll();
                                foreach ($vehicles as $v) {
                                    echo '<option value="' . $v['id'] . '">' . htmlspecialchars($v['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Repair Date</label>
                            <input type="date" name="repair_date" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mileage</label>
                            <input type="number" name="mileage" class="form-control" placeholder="e.g., 45000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" step="0.01" name="cost" class="form-control" placeholder="e.g., 250.00">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor</label>
                            <input type="text" name="vendor" class="form-control" placeholder="e.g., Joe's Auto Shop">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Problem Description</label>
                        <textarea name="problem_description" class="form-control" rows="3" placeholder="Describe the problem..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Repair Description</label>
                        <textarea name="repair_description" class="form-control" rows="3" placeholder="Describe the repair work done..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddRepairModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddRepair()">Add Repair</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRepairModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteRepairModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this repair record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteRepairModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteRepair()">Delete</button>
            </div>
        </div>
    </div>
</div>

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
    pointer-events: none;
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
    border-radius: 0.5rem;
    outline: 0;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,.5);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 500;
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
    gap: 0.5rem;
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
    color: #fff;
    opacity: .8;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
}

.form-label {
    display: inline-block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.text-danger {
    color: #dc3545;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: -0.5rem;
}

.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding: 0.5rem;
}

.col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
    padding: 0.5rem;
}

.mb-3 {
    margin-bottom: 1rem;
}
</style>

<script>
// Show Add Repair Modal
function showAddRepairModal() {
    document.getElementById('addRepairForm').reset();
    document.getElementById('addRepairModal').style.display = 'block';
    document.getElementById('addRepairModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Repair Modal
function closeAddRepairModal() {
    document.getElementById('addRepairModal').style.display = 'none';
    document.getElementById('addRepairModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Repair Form
function submitAddRepair() {
    const form = document.getElementById('addRepairForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'add_repair');
    
    fetch('index.php?page=repairs', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddRepairModal();
            if (typeof showToast === 'function') {
                showToast(data.message || 'Repair added successfully!', 'success');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + (data.message || 'Failed to add repair'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the repair');
    });
}

// Show Delete Repair Modal
let deleteRepairId = null;
function showDeleteRepairModal(id) {
    deleteRepairId = id;
    document.getElementById('deleteRepairModal').style.display = 'block';
    document.getElementById('deleteRepairModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Repair Modal
function closeDeleteRepairModal() {
    document.getElementById('deleteRepairModal').style.display = 'none';
    document.getElementById('deleteRepairModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
    deleteRepairId = null;
}

// Confirm Delete Repair
function confirmDeleteRepair() {
    if (!deleteRepairId) return;
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_repair');
    formData.append('id', deleteRepairId);
    
    fetch('index.php?page=repairs', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteRepairModal();
            if (typeof showToast === 'function') {
                showToast(data.message || 'Repair deleted successfully!', 'success');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + (data.message || 'Failed to delete repair'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the repair');
    });
}

// Show Edit Repair Modal
function editRepair(repair) {
    // Populate form fields
    document.getElementById('edit_repair_id').value = repair.id;
    document.getElementById('edit_vehicle_id').value = repair.vehicle_id || '';
    document.getElementById('edit_repair_date').value = repair.repair_date || '';
    document.getElementById('edit_mileage').value = repair.mileage || '';
    document.getElementById('edit_cost').value = repair.cost || '';
    document.getElementById('edit_vendor').value = repair.vendor || '';
    document.getElementById('edit_status').value = repair.status || 'pending';
    document.getElementById('edit_problem_description').value = repair.problem_description || '';
    document.getElementById('edit_repair_description').value = repair.repair_description || '';
    
    // Show modal
    document.getElementById('editRepairModal').style.display = 'block';
    document.getElementById('editRepairModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Edit Repair Modal
function closeEditRepairModal() {
    document.getElementById('editRepairModal').style.display = 'none';
    document.getElementById('editRepairModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Edit Repair Form
function submitEditRepair() {
    const form = document.getElementById('editRepairForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    
    fetch('index.php?page=repairs', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditRepairModal();
            if (typeof showToast === 'function') {
                showToast(data.message || 'Repair updated successfully!', 'success');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + (data.message || 'Failed to update repair'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the repair');
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
    
    // Update "Select All" checkbox state
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
}

function bulkDeleteRepairs() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one repair to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} repair record(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_repairs&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} repair record(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete repairs'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting repairs');
    });
}
</script>
