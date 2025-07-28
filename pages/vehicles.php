<?php
// Vehicles Page Content
?>

<div class="page-header">
    <h2>Vehicle Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['vehicles'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['vehicles'] ?? [])): ?>
<div class="form-section">
    <h3>Add New Vehicle</h3>
    <form method="POST" class="vehicle-form">
        <input type="hidden" name="action" value="add_vehicle">
        
        <div class="form-row">
            <div class="form-group">
                <label for="make">Make:</label>
                <input type="text" id="make" name="make" required>
            </div>
            <div class="form-group">
                <label for="model">Model:</label>
                <input type="text" id="model" name="model" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="year">Year:</label>
                <input type="number" id="year" name="year" min="1900" max="2030" required>
            </div>
            <div class="form-group">
                <label for="vin">VIN:</label>
                <input type="text" id="vin" name="vin" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="license_plate">License Plate:</label>
                <input type="text" id="license_plate" name="license_plate" required>
            </div>
            <div class="form-group">
                <label for="color">Color:</label>
                <input type="text" id="color" name="color" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="mileage">Mileage:</label>
                <input type="number" id="mileage" name="mileage" required>
            </div>
            <div class="form-group">
                <label for="daily_rate">Daily Rate ($):</label>
                <input type="number" id="daily_rate" name="daily_rate" step="0.01" required>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Add Vehicle</button>
    </form>
</div>
<?php endif; ?>

<div class="inventory-section">
    <h3>Vehicle Inventory</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Make/Model</th>
                <th>Year</th>
                <th>License Plate</th>
                <th>Color</th>
                <th>Mileage</th>
                <th>Daily Rate</th>
                <th>Status</th>
                <?php if (in_array('Edit', $permissions['vehicles'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
            while ($vehicle = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['year']) . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['license_plate']) . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['color']) . "</td>";
                echo "<td>" . number_format($vehicle['mileage']) . "</td>";
                echo "<td>$" . number_format($vehicle['daily_rate'], 2) . "</td>";
                echo "<td><span class='status-" . $vehicle['status'] . "'>" . ucfirst($vehicle['status']) . "</span></td>";
                
                if (in_array('Edit', $permissions['vehicles'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editVehicle(" . $vehicle['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['vehicles'] ?? [])) {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteVehicle(" . $vehicle['id'] . ")'>Delete</button>";
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
.vehicle-form {
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

.form-group input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.inventory-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-available {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-rented {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-maintenance {
    background: #fff3cd;
    color: #856404;
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
function editVehicle(id) {
    // Edit functionality to be implemented
    alert('Edit vehicle functionality - ID: ' + id);
}

function deleteVehicle(id) {
    if (confirm('Are you sure you want to delete this vehicle?')) {
        // Delete functionality to be implemented
        alert('Delete vehicle functionality - ID: ' + id);
    }
}
</script>

