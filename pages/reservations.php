<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
    <h2>Reservation Management</h2>
    <p>Manage vehicle reservations and bookings</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="showAddReservationModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Reservation</button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteReservations()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>
<?php if ($permissions->hasPermission('reservations', 'create')): ?>
<div class="form-section">
    <h3>Create New Reservation</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_reservation">
        <div class="form-grid">
            <div class="form-group">
                <label for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">Select Customer</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
                    while ($row = $stmt->fetch()) {
                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="vehicle_id">Vehicle</label>
                <select id="vehicle_id" name="vehicle_id" required>
                    <option value="">Select Vehicle</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, make, model, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make, model");
                    while ($row = $stmt->fetch()) {
                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model']) . " - $" . number_format($row['daily_rate'], 2) . "/day</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" required>
            </div>
            <div class="form-group">
                <label for="pickup_location">Pickup Location</label>
                <input type="text" id="pickup_location" name="pickup_location" required>
            </div>
            <div class="form-group">
                <label for="dropoff_location">Dropoff Location</label>
                <input type="text" id="dropoff_location" name="dropoff_location" required>
            </div>
            <div class="form-group">
                <label for="total_amount">Total Amount ($)</label>
                <input type="number" id="total_amount" name="total_amount" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"></textarea>
            </div>
        </div>
        <button type="submit" class="btn-primary">Create Reservation</button>
    </form>
</div>
<?php endif; ?>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Current Reservations</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Total Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       CONCAT(v.make, ' ', v.model) as vehicle_name
                FROM reservations r
                JOIN customers c ON r.customer_id = c.id
                JOIN vehicles v ON r.vehicle_id = v.id
                ORDER BY r.start_date DESC
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td><input type='checkbox' class='row-checkbox' value='" . $row['id'] . "' onchange='updateBulkDeleteButton()'></td>";
                echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['start_date'])) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['end_date'])) . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>$" . number_format($row['total_amount'], 2) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editReservation(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteReservation(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Reservation Modal -->
<div id="editReservationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Reservation</h3>
            <span class="close" onclick="closeModal('editReservationModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editReservationForm">
                <input type="hidden" id="edit_reservation_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_customer_id">Customer</label>
                        <select id="edit_customer_id" name="customer_id" required>
                            <?php
                            $stmt = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_vehicle_id">Vehicle</label>
                        <select id="edit_vehicle_id" name="vehicle_id" required>
                            <?php
                            $stmt = $pdo->query("SELECT id, make, model, daily_rate FROM vehicles ORDER BY make, model");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model']) . " - $" . number_format($row['daily_rate'], 2) . "/day</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_start_date">Start Date</label>
                        <input type="date" id="edit_start_date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_end_date">End Date</label>
                        <input type="date" id="edit_end_date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_pickup_location">Pickup Location</label>
                        <input type="text" id="edit_pickup_location" name="pickup_location" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_dropoff_location">Dropoff Location</label>
                        <input type="text" id="edit_dropoff_location" name="dropoff_location" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_total_amount">Total Amount ($)</label>
                        <input type="number" id="edit_total_amount" name="total_amount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Notes</label>
                        <textarea id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveReservation()">Save Changes</button>
            <button type="button" onclick="closeModal('editReservationModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
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

<!-- Add Reservation Modal -->
<div class="modal fade" id="addReservationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">📅 Add New Reservation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddReservationModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addReservationForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="add_customer_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM customers ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
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
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" id="add_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="end_date" id="add_end_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pickup Location <span class="text-danger">*</span></label>
                            <input type="text" name="pickup_location" id="add_pickup_location" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dropoff Location <span class="text-danger">*</span></label>
                            <input type="text" name="dropoff_location" id="add_dropoff_location" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount <span class="text-danger">*</span></label>
                            <input type="number" name="total_amount" id="add_total_amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="add_status" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
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
                <button type="button" class="btn btn-secondary" onclick="closeAddReservationModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddReservation()">Add Reservation</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteReservationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Reservation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteReservationModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this reservation? This action cannot be undone.</p>
                <p><strong id="deleteReservationInfo"></strong></p>
                <input type="hidden" id="delete_reservation_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteReservationModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteReservation()">Delete Reservation</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Reservation Modal
function showAddReservationModal() {
    document.getElementById('addReservationForm').reset();
    document.getElementById('addReservationModal').style.display = 'block';
    document.getElementById('addReservationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Reservation Modal
function closeAddReservationModal() {
    document.getElementById('addReservationModal').style.display = 'none';
    document.getElementById('addReservationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Reservation
function submitAddReservation() {
    const form = document.getElementById('addReservationForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_reservation');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddReservationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add reservation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the reservation');
    });
}

// Show Delete Confirmation Modal
function deleteReservation(id, name) {
    document.getElementById('delete_reservation_id').value = id;
    document.getElementById('deleteReservationInfo').textContent = name;
    document.getElementById('deleteReservationModal').style.display = 'block';
    document.getElementById('deleteReservationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Reservation Modal
function closeDeleteReservationModal() {
    document.getElementById('deleteReservationModal').style.display = 'none';
    document.getElementById('deleteReservationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Reservation
function confirmDeleteReservation() {
    const id = document.getElementById('delete_reservation_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_reservation');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteReservationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete reservation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the reservation');
    });
}
</script><!-- Add Reservation Modal -->
<div class="modal fade" id="addReservationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">📅 Add New Reservation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddReservationModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addReservationForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="add_customer_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM customers ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
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
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" id="add_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="end_date" id="add_end_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pickup Location <span class="text-danger">*</span></label>
                            <input type="text" name="pickup_location" id="add_pickup_location" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dropoff Location <span class="text-danger">*</span></label>
                            <input type="text" name="dropoff_location" id="add_dropoff_location" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount <span class="text-danger">*</span></label>
                            <input type="number" name="total_amount" id="add_total_amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="add_status" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
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
                <button type="button" class="btn btn-secondary" onclick="closeAddReservationModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddReservation()">Add Reservation</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteReservationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Reservation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteReservationModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this reservation? This action cannot be undone.</p>
                <p><strong id="deleteReservationInfo"></strong></p>
                <input type="hidden" id="delete_reservation_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteReservationModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteReservation()">Delete Reservation</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Reservation Modal
function showAddReservationModal() {
    document.getElementById('addReservationForm').reset();
    document.getElementById('addReservationModal').style.display = 'block';
    document.getElementById('addReservationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Reservation Modal
function closeAddReservationModal() {
    document.getElementById('addReservationModal').style.display = 'none';
    document.getElementById('addReservationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Reservation
function submitAddReservation() {
    const form = document.getElementById('addReservationForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_reservation');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddReservationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add reservation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the reservation');
    });
}

// Show Delete Confirmation Modal
function deleteReservation(id, name) {
    document.getElementById('delete_reservation_id').value = id;
    document.getElementById('deleteReservationInfo').textContent = name;
    document.getElementById('deleteReservationModal').style.display = 'block';
    document.getElementById('deleteReservationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Reservation Modal
function closeDeleteReservationModal() {
    document.getElementById('deleteReservationModal').style.display = 'none';
    document.getElementById('deleteReservationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Reservation
function confirmDeleteReservation() {
    const id = document.getElementById('delete_reservation_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_reservation');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteReservationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete reservation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the reservation');
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

function bulkDeleteReservations() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one reservation to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} reservation(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_reservations&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} reservation(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete reservations'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting reservations');
    });
}
</script>
