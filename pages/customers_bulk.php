<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>Customer Management</h2>
    <p>Manage customer information and profiles</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="showAddCustomerModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Customer</button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteCustomers()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>
<?php if ($permissions->hasPermission('customers', 'create')): ?>
<div class="form-section">
    <h3>Add New Customer</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_customer">
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="driver_license">Driver License</label>
                <input type="text" id="driver_license" name="driver_license" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Customer</button>
    </form>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Filter Customers</h3>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="customers">
        <div class="form-group" style="margin: 0;">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" placeholder="Name, email, phone, license..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-primary" style="margin: 0;">Apply Filters</button>
            <a href="?page=customers" class="btn-secondary" style="margin: 0; text-decoration: none; display: inline-block; padding: 0.5rem 1rem;">Clear</a>
        </div>
    </form>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Customer List</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Driver License</th>
                <th>Date of Birth</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get filter parameters
            $searchQuery = $_GET['search'] ?? '';
            
            // Build query with filters
            $sql = "SELECT * FROM customers WHERE 1=1";
            $params = [];
            
            if ($searchQuery) {
                $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR driver_license LIKE ?)";
                $params[] = "%$searchQuery%";
                $params[] = "%$searchQuery%";
                $params[] = "%$searchQuery%";
                $params[] = "%$searchQuery%";
                $params[] = "%$searchQuery%";
            }
            
            $sql .= " ORDER BY last_name, first_name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td><input type='checkbox' class='row-checkbox' value='" . $row['id'] . "' onchange='updateBulkDeleteButton()'></td>";
                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td>" . htmlspecialchars($row['driver_license']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['date_of_birth'])) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editCustomer(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteCustomer(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Customer</h3>
            <span class="close" onclick="closeModal('editCustomerModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editCustomerForm">
                <input type="hidden" id="edit_customer_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_first_name">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_driver_license">Driver License</label>
                        <input type="text" id="edit_driver_license" name="driver_license" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_date_of_birth">Date of Birth</label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveCustomer()">Save Changes</button>
            <button type="button" onclick="closeModal('editCustomerModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">👥 Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddCustomerModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addCustomerForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="add_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="add_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address </label>
                            <input type="text" name="address" id="add_address" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City </label>
                            <input type="text" name="city" id="add_city" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State </label>
                            <input type="text" name="state" id="add_state" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ZIP Code </label>
                            <input type="text" name="zip" id="add_zip" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">License Number </label>
                            <input type="text" name="license_number" id="add_license_number" class="form-control" >
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes </label>
                            <textarea name="notes" id="add_notes" class="form-control" rows="3" ></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddCustomerModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddCustomer()">Add Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Customer</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteCustomerModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this customer? This action cannot be undone.</p>
                <p><strong id="deleteCustomerInfo"></strong></p>
                <input type="hidden" id="delete_customer_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteCustomerModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteCustomer()">Delete Customer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Customer Modal
function showAddCustomerModal() {
    document.getElementById('addCustomerForm').reset();
    document.getElementById('addCustomerModal').style.display = 'block';
    document.getElementById('addCustomerModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Customer Modal
function closeAddCustomerModal() {
    document.getElementById('addCustomerModal').style.display = 'none';
    document.getElementById('addCustomerModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Customer
function submitAddCustomer() {
    const form = document.getElementById('addCustomerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_customer');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddCustomerModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add customer'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the customer');
    });
}

// Show Delete Confirmation Modal
function deleteCustomer(id, name) {
    document.getElementById('delete_customer_id').value = id;
    document.getElementById('deleteCustomerInfo').textContent = name;
    document.getElementById('deleteCustomerModal').style.display = 'block';
    document.getElementById('deleteCustomerModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Customer Modal
function closeDeleteCustomerModal() {
    document.getElementById('deleteCustomerModal').style.display = 'none';
    document.getElementById('deleteCustomerModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Customer
function confirmDeleteCustomer() {
    const id = document.getElementById('delete_customer_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_customer');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteCustomerModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete customer'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the customer');
    });
}
</script>
<script>
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

function bulkDeleteCustomers() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one customer to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} customer(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_customers&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} customer(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete customers'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting customers');
    });
}
</script>
