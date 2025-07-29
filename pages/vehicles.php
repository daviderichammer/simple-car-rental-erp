?>
<div class="page-header">
    <h2>Vehicle Management</h2>
    <p>Manage your fleet of rental vehicles</p>
</div>

<?php if ($permissions->hasPermission('vehicles', 'create')): ?>
<div class="form-section">
    <h3>Add New Vehicle</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_vehicle">
        <div class="form-grid">
            <div class="form-group">
                <label for="make">Make</label>
                <input type="text" id="make" name="make" required>
            </div>
            <div class="form-group">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" required>
            </div>
            <div class="form-group">
                <label for="year">Year</label>
                <input type="number" id="year" name="year" min="1900" max="2030" required>
            </div>
            <div class="form-group">
                <label for="vin">VIN</label>
                <input type="text" id="vin" name="vin" required>
            </div>
            <div class="form-group">
                <label for="license_plate">License Plate</label>
                <input type="text" id="license_plate" name="license_plate" required>
            </div>
            <div class="form-group">
                <label for="color">Color</label>
                <input type="text" id="color" name="color" required>
            </div>
            <div class="form-group">
                <label for="mileage">Mileage</label>
                <input type="number" id="mileage" name="mileage" required>
            </div>
            <div class="form-group">
                <label for="daily_rate">Daily Rate ($)</label>
                <input type="number" id="daily_rate" name="daily_rate" step="0.01" required>
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Vehicle</button>
    </form>
</div>
<?php endif; ?>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Vehicle Inventory</h3>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Make</th>
                <th>Model</th>
                <th>Year</th>
                <th>License Plate</th>
                <th>Status</th>
                <th>Daily Rate</th>
                <th>Mileage</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['make']) . "</td>";
                echo "<td>" . htmlspecialchars($row['model']) . "</td>";
                echo "<td>" . $row['year'] . "</td>";
                echo "<td>" . htmlspecialchars($row['license_plate']) . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>$" . number_format($row['daily_rate'], 2) . "</td>";
                echo "<td>" . number_format($row['mileage']) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editVehicle(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteVehicle(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div id="editVehicleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Vehicle</h3>
            <span class="close" onclick="closeModal('editVehicleModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editVehicleForm">
                <input type="hidden" id="edit_vehicle_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_make">Make</label>
                        <input type="text" id="edit_make" name="make" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_model">Model</label>
                        <input type="text" id="edit_model" name="model" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_year">Year</label>
                        <input type="number" id="edit_year" name="year" min="1900" max="2030" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_vin">VIN</label>
                        <input type="text" id="edit_vin" name="vin" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_license_plate">License Plate</label>
                        <input type="text" id="edit_license_plate" name="license_plate" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_color">Color</label>
                        <input type="text" id="edit_color" name="color" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_mileage">Mileage</label>
                        <input type="number" id="edit_mileage" name="mileage" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_daily_rate">Daily Rate ($)</label>
                        <input type="number" id="edit_daily_rate" name="daily_rate" step="0.01" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveVehicle()">Save Changes</button>
            <button type="button" onclick="closeModal('editVehicleModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>
<?php
