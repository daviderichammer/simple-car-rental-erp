<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>Expense Refunds Management</h2>
    <p>Track and manage parking and car wash refunds</p>
    </div>
    <button onclick="showAddExpenseModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Expense</button>
</div>
<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_expense' && $permissions->hasPermission('expenses', 'create')) {
        $stmt = $pdo->prepare("INSERT INTO expense_refunds (refund_type, refund_date, guest_name, amount, trip_start_date, trip_end_date, vehicle_info, reservation_link, location, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['refund_type'],
            $_POST['refund_date'],
            $_POST['guest_name'],
            $_POST['amount'],
            $_POST['trip_start_date'],
            $_POST['trip_end_date'],
            $_POST['vehicle_info'],
            $_POST['reservation_link'],
            $_POST['location'],
            $_POST['status'],
            $_POST['notes']
        ]);
        echo "<div class='alert alert-success'>Expense refund added successfully!</div>";
    } elseif ($_POST['action'] === 'update_expense' && $permissions->hasPermission('expenses', 'update')) {
        $stmt = $pdo->prepare("UPDATE expense_refunds SET refund_type = ?, refund_date = ?, guest_name = ?, amount = ?, trip_start_date = ?, trip_end_date = ?, vehicle_info = ?, reservation_link = ?, location = ?, status = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $_POST['refund_type'],
            $_POST['refund_date'],
            $_POST['guest_name'],
            $_POST['amount'],
            $_POST['trip_start_date'],
            $_POST['trip_end_date'],
            $_POST['vehicle_info'],
            $_POST['reservation_link'],
            $_POST['location'],
            $_POST['status'],
            $_POST['notes'],
            $_POST['id']
        ]);
        echo "<div class='alert alert-success'>Expense refund updated successfully!</div>";
    } elseif ($_POST['action'] === 'delete_expense' && $permissions->hasPermission('expenses', 'delete')) {
        $stmt = $pdo->prepare("DELETE FROM expense_refunds WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo "<div class='alert alert-success'>Expense refund deleted successfully!</div>";
    }
}

// Get filter parameters
$filterType = $_GET['type'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query with filters
$sql = "SELECT * FROM expense_refunds WHERE 1=1";
$params = [];

if ($filterType) {
    $sql .= " AND refund_type = ?";
    $params[] = $filterType;
}

if ($filterLocation) {
    $sql .= " AND location = ?";
    $params[] = $filterLocation;
}

if ($filterStatus) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

if ($dateFrom) {
    $sql .= " AND refund_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND refund_date <= ?";
    $params[] = $dateTo;
}

if ($searchQuery) {
    $sql .= " AND (guest_name LIKE ? OR vehicle_info LIKE ? OR notes LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY refund_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Get unique locations for filters
$locations = $pdo->query("SELECT DISTINCT location FROM expense_refunds WHERE location IS NOT NULL ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN refund_type = 'Parking' THEN 1 ELSE 0 END) as parking_count,
        SUM(CASE WHEN refund_type = 'Car Wash' THEN 1 ELSE 0 END) as carwash_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
        SUM(amount) as total_amount,
        SUM(CASE WHEN refund_type = 'Parking' THEN amount ELSE 0 END) as parking_amount,
        SUM(CASE WHEN refund_type = 'Car Wash' THEN amount ELSE 0 END) as carwash_amount
    FROM expense_refunds
")->fetch();
?>

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Refunds</div>
        <div style="font-size: 2rem; font-weight: bold; color: #007bff;"><?php echo number_format($stats['total']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Parking Refunds</div>
        <div style="font-size: 2rem; font-weight: bold; color: #17a2b8;"><?php echo number_format($stats['parking_count']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Car Wash Refunds</div>
        <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo number_format($stats['carwash_count']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Pending</div>
        <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['pending']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Processed</div>
        <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo number_format($stats['processed']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Amount</div>
        <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">$<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
    </div>
</div>

<!-- Amount Breakdown -->
<div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Refund Breakdown</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <div style="padding: 1rem; background: #e7f3ff; border-radius: 4px;">
            <div style="font-size: 0.875rem; color: #666; margin-bottom: 0.5rem;">Parking Refunds</div>
            <div style="font-size: 1.5rem; font-weight: bold; color: #17a2b8;">$<?php echo number_format($stats['parking_amount'] ?? 0, 2); ?></div>
            <div style="font-size: 0.75rem; color: #666; margin-top: 0.25rem;"><?php echo number_format($stats['parking_count']); ?> refunds</div>
        </div>
        <div style="padding: 1rem; background: #e8f5e9; border-radius: 4px;">
            <div style="font-size: 0.875rem; color: #666; margin-bottom: 0.5rem;">Car Wash Refunds</div>
            <div style="font-size: 1.5rem; font-weight: bold; color: #28a745;">$<?php echo number_format($stats['carwash_amount'] ?? 0, 2); ?></div>
            <div style="font-size: 0.75rem; color: #666; margin-top: 0.25rem;"><?php echo number_format($stats['carwash_count']); ?> refunds</div>
        </div>
        <div style="padding: 1rem; background: #fff3e0; border-radius: 4px;">
            <div style="font-size: 0.875rem; color: #666; margin-bottom: 0.5rem;">Average Refund</div>
            <div style="font-size: 1.5rem; font-weight: bold; color: #ff9800;">$<?php echo $stats['total'] > 0 ? number_format($stats['total_amount'] / $stats['total'], 2) : '0.00'; ?></div>
            <div style="font-size: 0.75rem; color: #666; margin-top: 0.25rem;">per transaction</div>
        </div>
    </div>
</div>

<?php if ($permissions->hasPermission('expenses', 'create')): ?>
<div class="form-section">
    <h3>Add New Expense Refund</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_expense">
        <div class="form-grid">
            <div class="form-group">
                <label for="refund_type">Refund Type</label>
                <select id="refund_type" name="refund_type" required>
                    <option value="">Select Type</option>
                    <option value="Parking">Parking</option>
                    <option value="Car Wash">Car Wash</option>
                </select>
            </div>
            <div class="form-group">
                <label for="refund_date">Refund Date</label>
                <input type="date" id="refund_date" name="refund_date" required>
            </div>
            <div class="form-group">
                <label for="guest_name">Guest Name</label>
                <input type="text" id="guest_name" name="guest_name" required>
            </div>
            <div class="form-group">
                <label for="amount">Amount ($)</label>
                <input type="number" id="amount" name="amount" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="trip_start_date">Trip Start Date</label>
                <input type="date" id="trip_start_date" name="trip_start_date">
            </div>
            <div class="form-group">
                <label for="trip_end_date">Trip End Date</label>
                <input type="date" id="trip_end_date" name="trip_end_date">
            </div>
            <div class="form-group">
                <label for="vehicle_info">Vehicle Info</label>
                <input type="text" id="vehicle_info" name="vehicle_info">
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <select id="location" name="location">
                    <option value="">Select Location</option>
                    <option value="TPA">TPA (Tampa)</option>
                    <option value="FLL">FLL (Fort Lauderdale)</option>
                    <option value="MIA">MIA (Miami)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="reservation_link">Reservation Link</label>
                <input type="url" id="reservation_link" name="reservation_link">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="processed">Processed</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Expense Refund</button>
    </form>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Filter Expense Refunds</h3>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="expenses">
        <div class="form-group" style="margin: 0;">
            <label for="filter_type">Refund Type</label>
            <select id="filter_type" name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="Parking" <?php echo $filterType === 'Parking' ? 'selected' : ''; ?>>Parking</option>
                <option value="Car Wash" <?php echo $filterType === 'Car Wash' ? 'selected' : ''; ?>>Car Wash</option>
            </select>
        </div>
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
                <option value="processed" <?php echo $filterStatus === 'processed' ? 'selected' : ''; ?>>Processed</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="date_from">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="date_to">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" placeholder="Guest, vehicle, notes..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-primary" style="margin: 0;">Apply Filters</button>
            <a href="?page=expenses" class="btn-secondary" style="margin: 0; text-decoration: none; display: inline-block; padding: 0.5rem 1rem;">Clear</a>
        </div>
    </form>
</div>

<!-- Expense Refunds Table -->
<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        Expense Refunds (<?php echo count($expenses); ?> records)
    </h3>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Guest Name</th>
                <th>Amount</th>
                <th>Vehicle</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($expenses) > 0): ?>
                <?php foreach ($expenses as $exp): ?>
                <tr>
                    <td><?php echo $exp['refund_date'] ? date('M d, Y', strtotime($exp['refund_date'])) : '-'; ?></td>
                    <td>
                        <span class="badge" style="background: <?php 
                            echo $exp['refund_type'] === 'Parking' ? '#17a2b8' : '#28a745'; 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo htmlspecialchars($exp['refund_type']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($exp['guest_name'] ?? '-'); ?></td>
                    <td style="font-weight: bold; color: #dc3545;">$<?php echo number_format($exp['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($exp['vehicle_info'] ?? '-'); ?></td>
                    <td>
                        <?php if ($exp['location']): ?>
                        <span class="badge" style="background: <?php 
                            echo $exp['location'] === 'TPA' ? '#007bff' : ($exp['location'] === 'FLL' ? '#28a745' : '#ffc107'); 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo htmlspecialchars($exp['location']); ?>
                        </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background: <?php 
                            echo $exp['status'] === 'processed' ? '#28a745' : '#ffc107'; 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo ucfirst($exp['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($exp['reservation_link']): ?>
                            <a href="<?php echo htmlspecialchars($exp['reservation_link']); ?>" target="_blank" class="btn-view" style="margin-right: 0.5rem;">View</a>
                        <?php endif; ?>
                        <?php if ($permissions->hasPermission('expenses', 'update')): ?>
                            <button class="btn-edit" onclick="editExpense(<?php echo $exp['id']; ?>)">Edit</button>
                        <?php endif; ?>
                        <?php if ($permissions->hasPermission('expenses', 'delete')): ?>
                            <button class="btn-delete" onclick="deleteExpense(<?php echo $exp['id']; ?>)">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2rem; color: #6c757d;">
                        No expense refunds found. Try adjusting your filters or add a new refund.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="editExpenseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Expense Refund</h3>
            <span class="close" onclick="closeModal('editExpenseModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editExpenseForm">
                <input type="hidden" id="edit_exp_id" name="id">
                <input type="hidden" name="action" value="update_expense">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_refund_type">Refund Type</label>
                        <select id="edit_refund_type" name="refund_type" required>
                            <option value="Parking">Parking</option>
                            <option value="Car Wash">Car Wash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_refund_date">Refund Date</label>
                        <input type="date" id="edit_refund_date" name="refund_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_guest_name">Guest Name</label>
                        <input type="text" id="edit_guest_name" name="guest_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_amount">Amount ($)</label>
                        <input type="number" id="edit_amount" name="amount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_trip_start_date">Trip Start Date</label>
                        <input type="date" id="edit_trip_start_date" name="trip_start_date">
                    </div>
                    <div class="form-group">
                        <label for="edit_trip_end_date">Trip End Date</label>
                        <input type="date" id="edit_trip_end_date" name="trip_end_date">
                    </div>
                    <div class="form-group">
                        <label for="edit_vehicle_info">Vehicle Info</label>
                        <input type="text" id="edit_vehicle_info" name="vehicle_info">
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <select id="edit_location" name="location">
                            <option value="">Select Location</option>
                            <option value="TPA">TPA (Tampa)</option>
                            <option value="FLL">FLL (Fort Lauderdale)</option>
                            <option value="MIA">MIA (Miami)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_reservation_link">Reservation Link</label>
                        <input type="url" id="edit_reservation_link" name="reservation_link">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="processed">Processed</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_notes">Notes</label>
                        <textarea id="edit_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Update Expense Refund</button>
            </form>
        </div>
    </div>
</div>

<script>
function editExpense(id) {
    fetch(`?page=expenses&action=get_expense&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_exp_id').value = data.id;
            document.getElementById('edit_refund_type').value = data.refund_type;
            document.getElementById('edit_refund_date').value = data.refund_date || '';
            document.getElementById('edit_guest_name').value = data.guest_name || '';
            document.getElementById('edit_amount').value = data.amount || '';
            document.getElementById('edit_trip_start_date').value = data.trip_start_date || '';
            document.getElementById('edit_trip_end_date').value = data.trip_end_date || '';
            document.getElementById('edit_vehicle_info').value = data.vehicle_info || '';
            document.getElementById('edit_location').value = data.location || '';
            document.getElementById('edit_reservation_link').value = data.reservation_link || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_notes').value = data.notes || '';
            document.getElementById('editExpenseModal').style.display = 'block';
        });
}

function deleteExpense(id) {
    if (confirm('Are you sure you want to delete this expense refund?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_expense">
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
document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
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
    const modal = document.getElementById('editExpenseModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
// Handle AJAX request for getting expense data
if (isset($_GET['action']) && $_GET['action'] === 'get_expense' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM expense_refunds WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $exp = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($exp);
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">💰 Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddExpenseModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addExpenseForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle (Optional) </label>
                            <select name="vehicle_id" id="add_vehicle_id" class="form-control" >
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
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="add_category" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="fuel">Fuel</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="insurance">Insurance</option>
                                <option value="registration">Registration</option>
                                <option value="parking">Parking</option>
                                <option value="tolls">Tolls</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="add_description" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="add_amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" id="add_expense_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor </label>
                            <input type="text" name="vendor" id="add_vendor" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method </label>
                            <select name="payment_method" id="add_payment_method" class="form-control" >
                                <option value="">Select...</option>
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="check">Check</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes </label>
                            <textarea name="notes" id="add_notes" class="form-control" rows="3" ></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddExpenseModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddExpense()">Add Expense</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Expense</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteExpenseModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this expense? This action cannot be undone.</p>
                <p><strong id="deleteExpenseInfo"></strong></p>
                <input type="hidden" id="delete_expense_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteExpenseModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteExpense()">Delete Expense</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Expense Modal
function showAddExpenseModal() {
    document.getElementById('addExpenseForm').reset();
    document.getElementById('addExpenseModal').style.display = 'block';
    document.getElementById('addExpenseModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Expense Modal
function closeAddExpenseModal() {
    document.getElementById('addExpenseModal').style.display = 'none';
    document.getElementById('addExpenseModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Expense
function submitAddExpense() {
    const form = document.getElementById('addExpenseForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_expense');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddExpenseModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add expense'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the expense');
    });
}

// Show Delete Confirmation Modal
function deleteExpense(id, name) {
    document.getElementById('delete_expense_id').value = id;
    document.getElementById('deleteExpenseInfo').textContent = name;
    document.getElementById('deleteExpenseModal').style.display = 'block';
    document.getElementById('deleteExpenseModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Expense Modal
function closeDeleteExpenseModal() {
    document.getElementById('deleteExpenseModal').style.display = 'none';
    document.getElementById('deleteExpenseModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Expense
function confirmDeleteExpense() {
    const id = document.getElementById('delete_expense_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_expense');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteExpenseModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete expense'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the expense');
    });
}
</script><!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">💰 Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddExpenseModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addExpenseForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle (Optional) </label>
                            <select name="vehicle_id" id="add_vehicle_id" class="form-control" >
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
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="add_category" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="fuel">Fuel</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="insurance">Insurance</option>
                                <option value="registration">Registration</option>
                                <option value="parking">Parking</option>
                                <option value="tolls">Tolls</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="add_description" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="add_amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" id="add_expense_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor </label>
                            <input type="text" name="vendor" id="add_vendor" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method </label>
                            <select name="payment_method" id="add_payment_method" class="form-control" >
                                <option value="">Select...</option>
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="check">Check</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes </label>
                            <textarea name="notes" id="add_notes" class="form-control" rows="3" ></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddExpenseModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddExpense()">Add Expense</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Expense</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteExpenseModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this expense? This action cannot be undone.</p>
                <p><strong id="deleteExpenseInfo"></strong></p>
                <input type="hidden" id="delete_expense_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteExpenseModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteExpense()">Delete Expense</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Expense Modal
function showAddExpenseModal() {
    document.getElementById('addExpenseForm').reset();
    document.getElementById('addExpenseModal').style.display = 'block';
    document.getElementById('addExpenseModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Expense Modal
function closeAddExpenseModal() {
    document.getElementById('addExpenseModal').style.display = 'none';
    document.getElementById('addExpenseModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Expense
function submitAddExpense() {
    const form = document.getElementById('addExpenseForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_expense');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddExpenseModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add expense'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the expense');
    });
}

// Show Delete Confirmation Modal
function deleteExpense(id, name) {
    document.getElementById('delete_expense_id').value = id;
    document.getElementById('deleteExpenseInfo').textContent = name;
    document.getElementById('deleteExpenseModal').style.display = 'block';
    document.getElementById('deleteExpenseModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Expense Modal
function closeDeleteExpenseModal() {
    document.getElementById('deleteExpenseModal').style.display = 'none';
    document.getElementById('deleteExpenseModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Expense
function confirmDeleteExpense() {
    const id = document.getElementById('delete_expense_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_expense');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteExpenseModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete expense'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the expense');
    });
}
</script>
