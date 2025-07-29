<div class="page-header">
    <h2>Reservation Management</h2>
    <p>Manage vehicle reservations and bookings</p>
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
