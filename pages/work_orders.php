<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>Work Orders Management</h2>
    <p>Manage and track work orders across all locations</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="showAddWorkOrderModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Work Order</button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteWorkOrders()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>
<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_work_order' && $permissions->hasPermission('work_orders', 'create')) {
        $stmt = $pdo->prepare("INSERT INTO work_orders (job_number, job_datetime, location, assigned_to, cost, status, details_url, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['job_number'],
            $_POST['job_datetime'],
            $_POST['location'],
            $_POST['assigned_to'],
            $_POST['cost'],
            $_POST['status'],
            $_POST['details_url'],
            $_POST['notes']
        ]);
        echo "<div class='alert alert-success'>Work order added successfully!</div>";
    } elseif ($_POST['action'] === 'update_work_order' && $permissions->hasPermission('work_orders', 'update')) {
        $stmt = $pdo->prepare("UPDATE work_orders SET job_number = ?, job_datetime = ?, location = ?, assigned_to = ?, cost = ?, status = ?, details_url = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $_POST['job_number'],
            $_POST['job_datetime'],
            $_POST['location'],
            $_POST['assigned_to'],
            $_POST['cost'],
            $_POST['status'],
            $_POST['details_url'],
            $_POST['notes'],
            $_POST['id']
        ]);
        echo "<div class='alert alert-success'>Work order updated successfully!</div>";
    } elseif ($_POST['action'] === 'delete_work_order' && $permissions->hasPermission('work_orders', 'delete')) {
        $stmt = $pdo->prepare("DELETE FROM work_orders WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo "<div class='alert alert-success'>Work order deleted successfully!</div>";
    }
}

// Get filter parameters
$filterLocation = $_GET['location'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterAssignee = $_GET['assignee'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$sql = "SELECT * FROM work_orders WHERE 1=1";
$params = [];

if ($filterLocation) {
    $sql .= " AND location = ?";
    $params[] = $filterLocation;
}

if ($filterStatus) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

if ($filterAssignee) {
    $sql .= " AND assigned_to LIKE ?";
    $params[] = "%$filterAssignee%";
}

if ($searchQuery) {
    $sql .= " AND (job_number LIKE ? OR notes LIKE ? OR assigned_to LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY job_datetime DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$workOrders = $stmt->fetchAll();

// Get unique locations and assignees for filters
$locations = $pdo->query("SELECT DISTINCT location FROM work_orders WHERE location IS NOT NULL ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$assignees = $pdo->query("SELECT DISTINCT assigned_to FROM work_orders WHERE assigned_to IS NOT NULL ORDER BY assigned_to")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(cost) as total_cost
    FROM work_orders
")->fetch();
?>

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Work Orders</div>
        <div style="font-size: 2rem; font-weight: bold; color: #007bff;"><?php echo number_format($stats['total']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Pending</div>
        <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['pending']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">In Progress</div>
        <div style="font-size: 2rem; font-weight: bold; color: #17a2b8;"><?php echo number_format($stats['in_progress']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Completed</div>
        <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo number_format($stats['completed']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Cost</div>
        <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">$<?php echo number_format($stats['total_cost'] ?? 0, 2); ?></div>
    </div>
</div>

<?php if ($permissions->hasPermission('work_orders', 'create')): ?>
<div class="form-section">
    <h3>Add New Work Order</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_work_order">
        <div class="form-grid">
            <div class="form-group">
                <label for="job_number">Job Number</label>
                <input type="number" id="job_number" name="job_number" required>
            </div>
            <div class="form-group">
                <label for="job_datetime">Date/Time</label>
                <input type="datetime-local" id="job_datetime" name="job_datetime" required>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <select id="location" name="location" required>
                    <option value="">Select Location</option>
                    <option value="TPA">TPA (Tampa)</option>
                    <option value="FLL">FLL (Fort Lauderdale)</option>
                    <option value="MIA">MIA (Miami)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="assigned_to">Assigned To</label>
                <input type="text" id="assigned_to" name="assigned_to">
            </div>
            <div class="form-group">
                <label for="cost">Cost ($)</label>
                <input type="number" id="cost" name="cost" step="0.01">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="details_url">Details URL</label>
                <input type="url" id="details_url" name="details_url">
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"></textarea>
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Work Order</button>
    </form>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Filter Work Orders</h3>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label for="filter_location">Location</label>
            <select id="filter_location" name="location" onchange="this.form.submit()">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $filterLocation === $loc ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="filter_status">Status</label>
            <select id="filter_status" name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="filter_assignee">Assigned To</label>
            <select id="filter_assignee" name="assignee" onchange="this.form.submit()">
                <option value="">All Assignees</option>
                <?php foreach ($assignees as $assignee): ?>
                    <option value="<?php echo htmlspecialchars($assignee); ?>" <?php echo $filterAssignee === $assignee ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($assignee); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" placeholder="Job #, notes, assignee..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-primary" style="margin: 0;">Apply Filters</button>
            <a href="?page=work_orders" class="btn-secondary" style="margin: 0; text-decoration: none; display: inline-block; padding: 0.5rem 1rem;">Clear</a>
        </div>
    </form>
</div>

<!-- Work Orders Table -->
<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        Work Orders (<?php echo count($workOrders); ?> records)
    </h3>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Job #</th>
                <th>Date/Time</th>
                <th>Location</th>
                <th>Assigned To</th>
                <th>Cost</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($workOrders) > 0): ?>
                <?php foreach ($workOrders as $wo): ?>
                <tr>
                    <td><input type='checkbox' class='row-checkbox' value='<?php echo $wo['id']; ?>' onchange='updateBulkDeleteButton()'></td>
                <tr>
                    <td><?php echo htmlspecialchars($wo['job_number']); ?></td>
                    <td><?php echo $wo['job_datetime'] ? date('M d, Y H:i', strtotime($wo['job_datetime'])) : '-'; ?></td>
                    <td>
                        <span class="badge" style="background: <?php 
                            echo $wo['location'] === 'TPA' ? '#007bff' : ($wo['location'] === 'FLL' ? '#28a745' : '#ffc107'); 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo htmlspecialchars($wo['location']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($wo['assigned_to'] ?? '-'); ?></td>
                    <td><?php echo $wo['cost'] ? '$' . number_format($wo['cost'], 2) : '-'; ?></td>
                    <td>
                        <span class="badge" style="background: <?php 
                            echo $wo['status'] === 'completed' ? '#28a745' : ($wo['status'] === 'in_progress' ? '#17a2b8' : '#ffc107'); 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $wo['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($wo['details_url']): ?>
                            <a href="<?php echo htmlspecialchars($wo['details_url']); ?>" target="_blank" class="btn-view" style="margin-right: 0.5rem;">View</a>
                        <?php endif; ?>
                        <?php if ($permissions->hasPermission('work_orders', 'update')): ?>
                            <button class="btn-edit" onclick="editWorkOrder(<?php echo $wo['id']; ?>)">Edit</button>
                        <?php endif; ?>
                        <?php if ($permissions->hasPermission('work_orders', 'delete')): ?>
                            <button class="btn-delete" onclick="deleteWorkOrder(<?php echo $wo['id']; ?>)">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #6c757d;">
                        No work orders found. Try adjusting your filters or add a new work order.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Edit Work Order Modal -->
<div id="editWorkOrderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Work Order</h3>
            <span class="close" onclick="closeModal('editWorkOrderModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editWorkOrderForm">
                <input type="hidden" id="edit_wo_id" name="id">
                <input type="hidden" name="action" value="update_work_order">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_job_number">Job Number</label>
                        <input type="number" id="edit_job_number" name="job_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_job_datetime">Date/Time</label>
                        <input type="datetime-local" id="edit_job_datetime" name="job_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <select id="edit_location" name="location" required>
                            <option value="TPA">TPA (Tampa)</option>
                            <option value="FLL">FLL (Fort Lauderdale)</option>
                            <option value="MIA">MIA (Miami)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_assigned_to">Assigned To</label>
                        <input type="text" id="edit_assigned_to" name="assigned_to">
                    </div>
                    <div class="form-group">
                        <label for="edit_cost">Cost ($)</label>
                        <input type="number" id="edit_cost" name="cost" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_details_url">Details URL</label>
                        <input type="url" id="edit_details_url" name="details_url">
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Notes</label>
                        <textarea id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Update Work Order</button>
            </form>
        </div>
    </div>
</div>

<script>
function editWorkOrder(id) {
    fetch(`?page=work_orders&action=get_work_order&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_wo_id').value = data.id;
            document.getElementById('edit_job_number').value = data.job_number;
            document.getElementById('edit_job_datetime').value = data.job_datetime ? data.job_datetime.replace(' ', 'T') : '';
            document.getElementById('edit_location').value = data.location;
            document.getElementById('edit_assigned_to').value = data.assigned_to || '';
            document.getElementById('edit_cost').value = data.cost || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_details_url').value = data.details_url || '';
            document.getElementById('edit_notes').value = data.notes || '';
            document.getElementById('editWorkOrderModal').style.display = 'block';
        });
}

function deleteWorkOrder(id) {
    if (confirm('Are you sure you want to delete this work order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_work_order">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Handle edit form submission
document.getElementById('editWorkOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(() => {
        window.location.reload();
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editWorkOrderModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
// Handle AJAX request for getting work order data
if (isset($_GET['action']) && $_GET['action'] === 'get_work_order' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $wo = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($wo);
    exit;
}
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

<!-- Add Work Order Modal -->
<div class="modal fade" id="addWorkOrderModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">📝 Add New Work Order</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddWorkOrderModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addWorkOrderForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="add_vehicle_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, CONCAT(make, \" \", model) as label FROM vehicles ORDER BY make");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["vehicle_id"] . "'>" . htmlspecialchars($row["label"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="add_title" class="form-control" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="add_description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="add_priority" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="add_status" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assigned To </label>
                            <select name="assigned_to" id="add_assigned_to" class="form-control" >
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM team_members ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Due Date </label>
                            <input type="date" name="due_date" id="add_due_date" class="form-control" >
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddWorkOrderModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddWorkOrder()">Add Work Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWorkOrderModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Work Order</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteWorkOrderModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this work order? This action cannot be undone.</p>
                <p><strong id="deleteWorkOrderInfo"></strong></p>
                <input type="hidden" id="delete_work_order_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteWorkOrderModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteWorkOrder()">Delete Work Order</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Work Order Modal
function showAddWorkOrderModal() {
    document.getElementById('addWorkOrderForm').reset();
    document.getElementById('addWorkOrderModal').style.display = 'block';
    document.getElementById('addWorkOrderModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Work Order Modal
function closeAddWorkOrderModal() {
    document.getElementById('addWorkOrderModal').style.display = 'none';
    document.getElementById('addWorkOrderModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Work Order
function submitAddWorkOrder() {
    const form = document.getElementById('addWorkOrderForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_work_order');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddWorkOrderModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add work order'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the work order');
    });
}

// Show Delete Confirmation Modal
function deleteWorkOrder(id, name) {
    document.getElementById('delete_work_order_id').value = id;
    document.getElementById('deleteWorkOrderInfo').textContent = name;
    document.getElementById('deleteWorkOrderModal').style.display = 'block';
    document.getElementById('deleteWorkOrderModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Work Order Modal
function closeDeleteWorkOrderModal() {
    document.getElementById('deleteWorkOrderModal').style.display = 'none';
    document.getElementById('deleteWorkOrderModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Work Order
function confirmDeleteWorkOrder() {
    const id = document.getElementById('delete_work_order_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_work_order');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteWorkOrderModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete work order'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the work order');
    });
}
</script><!-- Add Work Order Modal -->
<div class="modal fade" id="addWorkOrderModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">📝 Add New Work Order</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddWorkOrderModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addWorkOrderForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="add_vehicle_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, CONCAT(make, \" \", model) as label FROM vehicles ORDER BY make");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["vehicle_id"] . "'>" . htmlspecialchars($row["label"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="add_title" class="form-control" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="add_description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="add_priority" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="add_status" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assigned To </label>
                            <select name="assigned_to" id="add_assigned_to" class="form-control" >
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM team_members ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Due Date </label>
                            <input type="date" name="due_date" id="add_due_date" class="form-control" >
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddWorkOrderModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddWorkOrder()">Add Work Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWorkOrderModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Work Order</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteWorkOrderModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this work order? This action cannot be undone.</p>
                <p><strong id="deleteWorkOrderInfo"></strong></p>
                <input type="hidden" id="delete_work_order_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteWorkOrderModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteWorkOrder()">Delete Work Order</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Work Order Modal
function showAddWorkOrderModal() {
    document.getElementById('addWorkOrderForm').reset();
    document.getElementById('addWorkOrderModal').style.display = 'block';
    document.getElementById('addWorkOrderModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Work Order Modal
function closeAddWorkOrderModal() {
    document.getElementById('addWorkOrderModal').style.display = 'none';
    document.getElementById('addWorkOrderModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Work Order
function submitAddWorkOrder() {
    const form = document.getElementById('addWorkOrderForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_work_order');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddWorkOrderModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add work order'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the work order');
    });
}

// Show Delete Confirmation Modal
function deleteWorkOrder(id, name) {
    document.getElementById('delete_work_order_id').value = id;
    document.getElementById('deleteWorkOrderInfo').textContent = name;
    document.getElementById('deleteWorkOrderModal').style.display = 'block';
    document.getElementById('deleteWorkOrderModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Work Order Modal
function closeDeleteWorkOrderModal() {
    document.getElementById('deleteWorkOrderModal').style.display = 'none';
    document.getElementById('deleteWorkOrderModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Work Order
function confirmDeleteWorkOrder() {
    const id = document.getElementById('delete_work_order_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_work_order');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteWorkOrderModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete work order'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the work order');
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

function bulkDeleteWorkOrders() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one work order to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} work order(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_work_orders&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} work order(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete work orders'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting work orders');
    });
}
</script>
