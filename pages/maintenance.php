<div class="page-header">
    <h2>Maintenance Management</h2>
    <p>Schedule and track vehicle maintenance</p>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Maintenance Schedule</h3>
    <table>
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Maintenance Type</th>
                <th>Scheduled Date</th>
                <th>Status</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT m.*, CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_name
                FROM maintenance_schedules m
                JOIN vehicles v ON m.vehicle_id = v.id
                ORDER BY m.scheduled_date
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['maintenance_type']) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($row['scheduled_date'])) . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                echo "<td>";
                echo "<button class='btn-edit' onclick='editMaintenance(" . $row['id'] . ")'>Edit</button>";
                echo "<button class='btn-delete' onclick='deleteMaintenance(" . $row['id'] . ")'>Delete</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Maintenance Modal -->
<div id="editMaintenanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Maintenance</h3>
            <span class="close" onclick="closeModal('editMaintenanceModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editMaintenanceForm">
                <input type="hidden" id="edit_maintenance_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_maintenance_vehicle_id">Vehicle</label>
                        <select id="edit_maintenance_vehicle_id" name="vehicle_id" required>
                            <?php
                            $stmt = $pdo->query("SELECT id, make, model, license_plate FROM vehicles ORDER BY make, model");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')') . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_type">Maintenance Type</label>
                        <input type="text" id="edit_maintenance_type" name="maintenance_type" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_scheduled_date">Scheduled Date</label>
                        <input type="date" id="edit_scheduled_date" name="scheduled_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_status">Status</label>
                        <select id="edit_maintenance_status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_description">Description</label>
                        <textarea id="edit_maintenance_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="saveMaintenance()">Save Changes</button>
            <button type="button" onclick="closeModal('editMaintenanceModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>
<?php
