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

<!-- Edit Vehicle Modal -->
<div id="editVehicleModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Vehicle</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editVehicleForm" method="POST">
            <input type="hidden" name="action" value="edit_vehicle">
            <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_make">Make:</label>
                    <input type="text" id="edit_make" name="make" required>
                </div>
                <div class="form-group">
                    <label for="edit_model">Model:</label>
                    <input type="text" id="edit_model" name="model" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_year">Year:</label>
                    <input type="number" id="edit_year" name="year" min="1900" max="2030" required>
                </div>
                <div class="form-group">
                    <label for="edit_vin">VIN:</label>
                    <input type="text" id="edit_vin" name="vin" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_license_plate">License Plate:</label>
                    <input type="text" id="edit_license_plate" name="license_plate" required>
                </div>
                <div class="form-group">
                    <label for="edit_color">Color:</label>
                    <input type="text" id="edit_color" name="color" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_mileage">Mileage:</label>
                    <input type="number" id="edit_mileage" name="mileage" required>
                </div>
                <div class="form-group">
                    <label for="edit_daily_rate">Daily Rate ($):</label>
                    <input type="number" id="edit_daily_rate" name="daily_rate" step="0.01" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select id="edit_status" name="status" required>
                        <option value="available">Available</option>
                        <option value="rented">Rented</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Vehicle</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    opacity: 0.7;
}

.modal form {
    padding: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
}
</style>

<script>
function editVehicle(id) {
    // Fetch vehicle data and populate the edit form
    fetch('?page=vehicles&action=get_vehicle&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const vehicle = data.vehicle;
                document.getElementById('edit_vehicle_id').value = vehicle.id;
                document.getElementById('edit_make').value = vehicle.make;
                document.getElementById('edit_model').value = vehicle.model;
                document.getElementById('edit_year').value = vehicle.year;
                document.getElementById('edit_vin').value = vehicle.vin;
                document.getElementById('edit_license_plate').value = vehicle.license_plate;
                document.getElementById('edit_color').value = vehicle.color;
                document.getElementById('edit_mileage').value = vehicle.mileage;
                document.getElementById('edit_daily_rate').value = vehicle.daily_rate;
                document.getElementById('edit_status').value = vehicle.status;
                
                document.getElementById('editVehicleModal').style.display = 'flex';
            } else {
                alert('Error loading vehicle data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading vehicle data');
        });
}

function closeEditModal() {
    document.getElementById('editVehicleModal').style.display = 'none';
}

function deleteVehicle(id) {
    if (confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) {
        fetch('?page=vehicles&action=delete_vehicle&id=' + id, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Vehicle deleted successfully!');
                location.reload();
            } else {
                alert('Error deleting vehicle: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting vehicle');
        });
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('editVehicleModal');
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>

