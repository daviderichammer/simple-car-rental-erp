<?php
// Maintenance Page Content
?>

<div class="page-header">
    <h2>Maintenance Management</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['maintenance'] ?? ['View']); ?></p>
</div>

<?php if (in_array('Create', $permissions['maintenance'] ?? [])): ?>
<div class="form-section">
    <h3>Schedule Maintenance</h3>
    <form method="POST" class="maintenance-form">
        <input type="hidden" name="action" value="add_maintenance">
        
        <div class="form-row">
            <div class="form-group">
                <label for="vehicle_id">Vehicle:</label>
                <select id="vehicle_id" name="vehicle_id" required>
                    <option value="">Select Vehicle</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, make, model, license_plate FROM vehicles ORDER BY make, model");
                    while ($vehicle = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='" . $vehicle['id'] . "'>" . 
                             htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')') . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="maintenance_type">Maintenance Type:</label>
                <select id="maintenance_type" name="maintenance_type" required>
                    <option value="">Select Type</option>
                    <option value="oil_change">Oil Change</option>
                    <option value="tire_rotation">Tire Rotation</option>
                    <option value="brake_service">Brake Service</option>
                    <option value="transmission_service">Transmission Service</option>
                    <option value="engine_service">Engine Service</option>
                    <option value="inspection">Inspection</option>
                    <option value="repair">Repair</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="scheduled_date">Scheduled Date:</label>
                <input type="date" id="scheduled_date" name="scheduled_date" required>
            </div>
            <div class="form-group">
                <label for="estimated_cost">Estimated Cost ($):</label>
                <input type="number" id="estimated_cost" name="estimated_cost" step="0.01">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="3" required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority:</label>
                <select id="priority" name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Schedule Maintenance</button>
    </form>
</div>
<?php endif; ?>

<div class="maintenance-section">
    <h3>Maintenance Schedule</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Type</th>
                <th>Scheduled Date</th>
                <th>Description</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Estimated Cost</th>
                <?php if (in_array('Edit', $permissions['maintenance'] ?? [])): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT m.*, 
                       CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_info
                FROM maintenance_schedules m
                JOIN vehicles v ON m.vehicle_id = v.id
                ORDER BY m.scheduled_date DESC
            ");
            
            while ($maintenance = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($maintenance['vehicle_info']) . "</td>";
                echo "<td>" . ucwords(str_replace('_', ' ', $maintenance['maintenance_type'])) . "</td>";
                echo "<td>" . date('M j, Y', strtotime($maintenance['scheduled_date'])) . "</td>";
                echo "<td>" . htmlspecialchars(substr($maintenance['description'], 0, 50)) . "...</td>";
                echo "<td><span class='status-" . $maintenance['status'] . "'>" . ucwords(str_replace('_', ' ', $maintenance['status'])) . "</span></td>";
                echo "<td><span class='priority-" . $maintenance['priority'] . "'>" . ucfirst($maintenance['priority']) . "</span></td>";
                echo "<td>" . ($maintenance['estimated_cost'] ? '$' . number_format($maintenance['estimated_cost'], 2) : 'N/A') . "</td>";
                
                if (in_array('Edit', $permissions['maintenance'] ?? [])) {
                    echo "<td>";
                    echo "<button class='btn btn-sm btn-edit' onclick='editMaintenance(" . $maintenance['id'] . ")'>Edit</button>";
                    if (in_array('Delete', $permissions['maintenance'] ?? [])) {
                        echo " <button class='btn btn-sm btn-delete' onclick='deleteMaintenance(" . $maintenance['id'] . ")'>Delete</button>";
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
.maintenance-form {
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

.maintenance-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-scheduled {
    background: #e2e3e5;
    color: #383d41;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-in_progress {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-completed {
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

.priority-low {
    background: #d1ecf1;
    color: #0c5460;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.priority-medium {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.priority-high {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.priority-urgent {
    background: #721c24;
    color: white;
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
function editMaintenance(id) {
    // Edit functionality to be implemented
    alert('Edit maintenance functionality - ID: ' + id);
}

function deleteMaintenance(id) {
    if (confirm('Are you sure you want to delete this maintenance record?')) {
        // Delete functionality to be implemented
        alert('Delete maintenance functionality - ID: ' + id);
    }
}
</script>

