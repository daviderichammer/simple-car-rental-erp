<?php
// Reservations Page Content
?>

<div class="page-header">
    <h2>Reservation Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['reservations'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['reservations'] ?? [])): ?>
<div class="form-section">
    <h3>Create New Reservation</h3>
    <form method="POST" class="reservation-form">
        <input type="hidden" name="action" value="add_reservation">
        
        <div class="form-row">
            <div class="form-group">
                <label for="customer_id">Customer:</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">Select Customer</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
                    while ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='" . $customer['id'] . "'>" . 
                             htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="vehicle_id">Vehicle:</label>
                <select id="vehicle_id" name="vehicle_id" required>
                    <option value="">Select Vehicle</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, make, model, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make, model");
                    while ($vehicle = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='" . $vehicle['id'] . "' data-rate='" . $vehicle['daily_rate'] . "'>" . 
                             htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) . " - $" . $vehicle['daily_rate'] . "/day</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="pickup_location">Pickup Location:</label>
                <input type="text" id="pickup_location" name="pickup_location" required>
            </div>
            <div class="form-group">
                <label for="dropoff_location">Dropoff Location:</label>
                <input type="text" id="dropoff_location" name="dropoff_location" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="total_amount">Total Amount ($):</label>
                <input type="number" id="total_amount" name="total_amount" step="0.01" required readonly>
            </div>
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="notes">Notes:</label>
            <textarea id="notes" name="notes" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Create Reservation</button>
    </form>
</div>
<?php endif; ?>

<div class="reservations-section">
    <h3>Current Reservations</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Pickup Location</th>
                <th>Total Amount</th>
                <th>Status</th>
                <?php if (in_array('Edit', $permissions['reservations'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.*, 
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       CONCAT(v.make, ' ', v.model) as vehicle_name
                FROM reservations r
                JOIN customers c ON r.customer_id = c.id
                JOIN vehicles v ON r.vehicle_id = v.id
                ORDER BY r.start_date DESC
            ");
            
            while ($reservation = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($reservation['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($reservation['vehicle_name']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($reservation['start_date'])) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($reservation['end_date'])) . "</td>";
                echo "<td>" . htmlspecialchars($reservation['pickup_location']) . "</td>";
                echo "<td>$" . number_format($reservation['total_amount'], 2) . "</td>";
                echo "<td><span class='status-" . $reservation['status'] . "'>" . ucfirst($reservation['status']) . "</span></td>";
                
                if (in_array('Edit', $permissions['reservations'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editReservation(" . $reservation['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['reservations'] ?? [])) {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteReservation(" . $reservation['id'] . ")'>Delete</button>";
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<style>
.reservation-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.reservations-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Calculate total amount based on dates and vehicle rate
document.getElementById('start_date').addEventListener('change', calculateTotal);
document.getElementById('end_date').addEventListener('change', calculateTotal);
document.getElementById('vehicle_id').addEventListener('change', calculateTotal);

function calculateTotal() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const vehicleSelect = document.getElementById('vehicle_id');
    const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
    
    if (startDate && endDate && selectedOption.dataset.rate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        const rate = parseFloat(selectedOption.dataset.rate);
        const total = days * rate;
        
        document.getElementById('total_amount').value = total.toFixed(2);
    }
}

function editReservation(id) {
    // Edit functionality to be implemented
    alert('Edit reservation functionality - ID: ' + id);
}

function deleteReservation(id) {
    if (confirm('Are you sure you want to delete this reservation?')) {
        // Delete functionality to be implemented
        alert('Delete reservation functionality - ID: ' + id);
    }
}
</script>

