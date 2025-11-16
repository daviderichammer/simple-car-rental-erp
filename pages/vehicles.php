<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Vehicle Management</h2>
        <p>Manage fleet vehicles and inventory with tracking devices and specifications</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="openModal('addVehicleModal')" style="background: #007bff; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.2rem;">+</span> Add New Vehicle
        </button>
        <button id="bulkDeleteBtn" onclick="bulkDeleteVehicles()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; display: none;">🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
    </div>
</div>

<?php
// Get filter parameters
$filterAirport = $_GET['airport'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterMake = $_GET['make'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$sql = "SELECT * FROM vehicles WHERE 1=1";
$params = [];

if ($filterAirport) {
    $sql .= " AND airport = ?";
    $params[] = $filterAirport;
}

if ($filterStatus) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

if ($filterMake) {
    $sql .= " AND make = ?";
    $params[] = $filterMake;
}

if ($searchQuery) {
    $sql .= " AND (make LIKE ? OR model LIKE ? OR vin LIKE ? OR license_plate LIKE ? OR bouncie_id LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY airport, make, model";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN status = 'out_of_service' THEN 1 ELSE 0 END) as out_of_service,
        SUM(CASE WHEN airport = 'TPA' THEN 1 ELSE 0 END) as tpa_count,
        SUM(CASE WHEN airport = 'FLL' THEN 1 ELSE 0 END) as fll_count,
        SUM(CASE WHEN airport = 'MIA' THEN 1 ELSE 0 END) as mia_count,
        SUM(CASE WHEN bouncie_id IS NOT NULL AND bouncie_id != '' THEN 1 ELSE 0 END) as with_bouncie
    FROM vehicles
")->fetch();

// Get unique makes for filter
$makes = $pdo->query("SELECT DISTINCT make FROM vehicles ORDER BY make")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Vehicles</div>
        <div style="font-size: 2rem; font-weight: bold; color: #007bff;"><?php echo number_format($stats['total']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Available</div>
        <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo number_format($stats['available']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Rented</div>
        <div style="font-size: 2rem; font-weight: bold; color: #dc3545;"><?php echo number_format($stats['rented']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">Maintenance</div>
        <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['maintenance']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">TPA</div>
        <div style="font-size: 2rem; font-weight: bold; color: #007bff;"><?php echo number_format($stats['tpa_count']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">FLL</div>
        <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo number_format($stats['fll_count']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">MIA</div>
        <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['mia_count']); ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #6c757d; font-size: 0.875rem; margin-bottom: 0.5rem;">With Bouncie</div>
        <div style="font-size: 2rem; font-weight: bold; color: #6f42c1;"><?php echo number_format($stats['with_bouncie']); ?></div>
    </div>
</div>

<!-- Filters -->
<div class="filters-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Filter Vehicles</h3>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="vehicles">
        <div class="form-group" style="margin: 0;">
            <label for="filter_airport">Location</label>
            <select id="filter_airport" name="airport" onchange="this.form.submit()">
                <option value="">All Locations</option>
                <option value="TPA" <?php echo $filterAirport === 'TPA' ? 'selected' : ''; ?>>TPA (Tampa)</option>
                <option value="FLL" <?php echo $filterAirport === 'FLL' ? 'selected' : ''; ?>>FLL (Fort Lauderdale)</option>
                <option value="MIA" <?php echo $filterAirport === 'MIA' ? 'selected' : ''; ?>>MIA (Miami)</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="filter_status">Status</label>
            <select id="filter_status" name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="rented" <?php echo $filterStatus === 'rented' ? 'selected' : ''; ?>>Rented</option>
                <option value="maintenance" <?php echo $filterStatus === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                <option value="out_of_service" <?php echo $filterStatus === 'out_of_service' ? 'selected' : ''; ?>>Out of Service</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="filter_make">Make</label>
            <select id="filter_make" name="make" onchange="this.form.submit()">
                <option value="">All Makes</option>
                <?php foreach ($makes as $make): ?>
                    <option value="<?php echo htmlspecialchars($make); ?>" <?php echo $filterMake === $make ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($make); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" placeholder="Make, model, VIN, plate..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-primary" style="margin: 0;">Apply Filters</button>
            <a href="?page=vehicles" class="btn-secondary" style="margin: 0; text-decoration: none; display: inline-block; padding: 0.5rem 1rem;">Clear</a>
        </div>
    </form>
</div>

<!-- Vehicles Table -->
<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        Vehicle Inventory (<?php echo count($vehicles); ?> vehicles)
    </h3>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Vehicle</th>
                <th>VIN / Plate</th>
                <th>Location</th>
                <th>Status</th>
                <th>Tracking</th>
                <th>Specs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($vehicles) > 0): ?>
                <?php foreach ($vehicles as $vehicle): ?>
                <tr>
                    <td><input type='checkbox' class='row-checkbox' value='<?php echo $vehicle['id']; ?>' onchange='updateBulkDeleteButton()'></td>
                    <td>
                        <strong><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($vehicle['color']); ?></small>
                    </td>
                    <td>
                        <div style="font-size: 0.875rem;">
                            <strong>VIN:</strong> <?php echo htmlspecialchars(substr($vehicle['vin'], -8)); ?><br>
                            <strong>Plate:</strong> <?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($vehicle['airport']): ?>
                        <span class="badge" style="background: <?php 
                            echo $vehicle['airport'] === 'TPA' ? '#007bff' : ($vehicle['airport'] === 'FLL' ? '#28a745' : '#ffc107'); 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo htmlspecialchars($vehicle['airport']); ?>
                        </span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background: <?php 
                            echo $vehicle['status'] === 'available' ? '#28a745' : 
                                ($vehicle['status'] === 'rented' ? '#dc3545' : 
                                ($vehicle['status'] === 'maintenance' ? '#ffc107' : '#6c757d')); 
                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $vehicle['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size: 0.75rem; line-height: 1.6;">
                            <?php if ($vehicle['bouncie_id']): ?>
                                <div>🚗 <strong>Bouncie:</strong> <?php echo htmlspecialchars($vehicle['bouncie_id']); ?></div>
                            <?php endif; ?>
                            <?php if ($vehicle['sunpass_id']): ?>
                                <div>🛣️ <strong>SunPass:</strong> <?php echo htmlspecialchars($vehicle['sunpass_id']); ?></div>
                            <?php endif; ?>
                            <?php if ($vehicle['ezpass_id']): ?>
                                <div>🛣️ <strong>EZPass:</strong> <?php echo htmlspecialchars($vehicle['ezpass_id']); ?></div>
                            <?php endif; ?>
                            <?php if ($vehicle['lockbox_code']): ?>
                                <div>🔒 <strong>Lockbox:</strong> <?php echo htmlspecialchars($vehicle['lockbox_code']); ?></div>
                            <?php endif; ?>
                            <?php if (!$vehicle['bouncie_id'] && !$vehicle['sunpass_id'] && !$vehicle['ezpass_id'] && !$vehicle['lockbox_code']): ?>
                                <span style="color: #999;">No tracking</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 0.75rem; line-height: 1.6;">
                            <?php if ($vehicle['fuel_type']): ?>
                                <div>⛽ <?php echo htmlspecialchars($vehicle['fuel_type']); ?></div>
                            <?php endif; ?>
                            <?php if ($vehicle['oil_type']): ?>
                                <div>🛢️ <?php echo htmlspecialchars($vehicle['oil_type']); ?></div>
                            <?php endif; ?>
                            <?php if ($vehicle['tire_front_size']): ?>
                                <div>🛞 <?php echo htmlspecialchars($vehicle['tire_front_size']); ?></div>
                            <?php endif; ?>
                            <?php if (!$vehicle['fuel_type'] && !$vehicle['oil_type'] && !$vehicle['tire_front_size']): ?>
                                <span style="color: #999;">No specs</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <button class="btn-view" onclick="showVehicleDetails('<?php echo $vehicle['vin']; ?>')" style="margin-bottom: 0.25rem;">Details</button><br>
                        <?php if ($permissions->hasPermission('vehicles', 'update')): ?>
                            <button class="btn-edit" onclick="editVehicle(<?php echo $vehicle['id']; ?>)" style="margin-bottom: 0.25rem;">Edit</button><br>
                        <?php endif; ?>
                        <?php if ($permissions->hasPermission('vehicles', 'delete')): ?>
                            <button class="btn-delete" onclick="prepareDeleteVehicle(<?php echo $vehicle['id']; ?>, '<?php echo addslashes($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>')" style="background: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #6c757d;">
                        No vehicles found. Try adjusting your filters.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- View Vehicle Details Modal -->
<div id="viewVehicleModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Vehicle Details</h3>
            <span class="close" onclick="closeModal('viewVehicleModal')">&times;</span>
        </div>
        <div class="modal-body" id="vehicleDetailsContent">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div id="editVehicleModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3>Edit Vehicle</h3>
            <span class="close" onclick="closeModal('editVehicleModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editVehicleForm">
                <input type="hidden" id="edit_vehicle_id" name="id">
                <input type="hidden" name="action" value="update_vehicle">
                
                <h4 style="margin-top: 0; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem;">Basic Information</h4>
                <div class="form-grid" style="grid-template-columns: repeat(3, 1fr);">
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
                        <input type="text" id="edit_license_plate" name="license_plate">
                    </div>
                    <div class="form-group">
                        <label for="edit_color">Color</label>
                        <input type="text" id="edit_color" name="color">
                    </div>
                    <div class="form-group">
                        <label for="edit_airport">Location</label>
                        <select id="edit_airport" name="airport">
                            <option value="">Select Location</option>
                            <option value="TPA">TPA (Tampa)</option>
                            <option value="FLL">FLL (Fort Lauderdale)</option>
                            <option value="MIA">MIA (Miami)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status">
                            <option value="available">Available</option>
                            <option value="rented">Rented</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="out_of_service">Out of Service</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_mileage">Mileage</label>
                        <input type="number" id="edit_mileage" name="mileage">
                    </div>
                </div>

                <h4 style="margin-top: 1.5rem; border-bottom: 2px solid #28a745; padding-bottom: 0.5rem;">Tracking Devices</h4>
                <div class="form-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="form-group">
                        <label for="edit_bouncie_id">Bouncie ID</label>
                        <input type="text" id="edit_bouncie_id" name="bouncie_id">
                    </div>
                    <div class="form-group">
                        <label for="edit_sunpass_id">SunPass ID</label>
                        <input type="text" id="edit_sunpass_id" name="sunpass_id">
                    </div>
                    <div class="form-group">
                        <label for="edit_ezpass_id">EZPass ID</label>
                        <input type="text" id="edit_ezpass_id" name="ezpass_id">
                    </div>
                    <div class="form-group">
                        <label for="edit_lockbox_code">Lockbox Code</label>
                        <input type="text" id="edit_lockbox_code" name="lockbox_code">
                    </div>
                    <div class="form-group">
                        <label for="edit_mister_carwash_rfid">MisterCarWash RFID</label>
                        <input type="text" id="edit_mister_carwash_rfid" name="mister_carwash_rfid">
                    </div>
                </div>

                <h4 style="margin-top: 1.5rem; border-bottom: 2px solid #ffc107; padding-bottom: 0.5rem;">Vehicle Specifications</h4>
                <div class="form-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="form-group">
                        <label for="edit_fuel_type">Fuel Type</label>
                        <input type="text" id="edit_fuel_type" name="fuel_type" placeholder="e.g., Regular Unleaded">
                    </div>
                    <div class="form-group">
                        <label for="edit_fuel_capacity">Fuel Capacity (gal)</label>
                        <input type="number" id="edit_fuel_capacity" name="fuel_capacity" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="edit_oil_type">Oil Type</label>
                        <input type="text" id="edit_oil_type" name="oil_type" placeholder="e.g., 5W-30">
                    </div>
                    <div class="form-group">
                        <label for="edit_oil_change_interval">Oil Change Interval</label>
                        <input type="text" id="edit_oil_change_interval" name="oil_change_interval" placeholder="e.g., 5000 miles">
                    </div>
                    <div class="form-group">
                        <label for="edit_tire_front_size">Front Tire Size</label>
                        <input type="text" id="edit_tire_front_size" name="tire_front_size" placeholder="e.g., 225/65R17">
                    </div>
                    <div class="form-group">
                        <label for="edit_tire_rear_size">Rear Tire Size</label>
                        <input type="text" id="edit_tire_rear_size" name="tire_rear_size" placeholder="e.g., 225/65R17">
                    </div>
                    <div class="form-group">
                        <label for="edit_registration_expiry">Registration Expiry</label>
                        <input type="text" id="edit_registration_expiry" name="registration_expiry" placeholder="MM/YYYY">
                    </div>
                    <div class="form-group">
                        <label for="edit_date_added">Date Added to Fleet</label>
                        <input type="date" id="edit_date_added" name="date_added">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 1rem;">Update Vehicle</button>
            </form>
        </div>
    </div>
</div>

<script>
function viewVehicle(id) {
    fetch(`?page=vehicles&action=get_vehicle&id=${id}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                    <div>
                        <h4 style="margin-top: 0; color: #007bff;">Basic Information</h4>
                        <table style="width: 100%; font-size: 0.875rem;">
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Vehicle:</td><td>${data.year} ${data.make} ${data.model}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">VIN:</td><td>${data.vin}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">License Plate:</td><td>${data.license_plate || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Color:</td><td>${data.color || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Location:</td><td>${data.airport || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Status:</td><td>${data.status}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Mileage:</td><td>${data.mileage ? parseInt(data.mileage).toLocaleString() : 'N/A'}</td></tr>
                        </table>
                    </div>
                    <div>
                        <h4 style="margin-top: 0; color: #28a745;">Tracking Devices</h4>
                        <table style="width: 100%; font-size: 0.875rem;">
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Bouncie ID:</td><td>${data.bouncie_id || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">SunPass ID:</td><td>${data.sunpass_id || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">EZPass ID:</td><td>${data.ezpass_id || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">Lockbox Code:</td><td>${data.lockbox_code || 'N/A'}</td></tr>
                            <tr><td style="padding: 0.5rem 0; font-weight: bold;">MisterCarWash:</td><td>${data.mister_carwash_rfid || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                <div style="margin-top: 1.5rem;">
                    <h4 style="color: #ffc107;">Vehicle Specifications</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; font-size: 0.875rem;">
                        <div><strong>Fuel Type:</strong> ${data.fuel_type || 'N/A'}</div>
                        <div><strong>Fuel Capacity:</strong> ${data.fuel_capacity ? data.fuel_capacity + ' gal' : 'N/A'}</div>
                        <div><strong>Oil Type:</strong> ${data.oil_type || 'N/A'}</div>
                        <div><strong>Oil Change Interval:</strong> ${data.oil_change_interval || 'N/A'}</div>
                        <div><strong>Front Tire Size:</strong> ${data.tire_front_size || 'N/A'}</div>
                        <div><strong>Rear Tire Size:</strong> ${data.tire_rear_size || 'N/A'}</div>
                        <div><strong>Registration Expiry:</strong> ${data.registration_expiry || 'N/A'}</div>
                        <div><strong>Date Added:</strong> ${data.date_added || 'N/A'}</div>
                    </div>
                </div>
            `;
            document.getElementById('vehicleDetailsContent').innerHTML = content;
            document.getElementById('viewVehicleModal').style.display = 'block';
        });
}

function editVehicle(id) {
    fetch(`?page=vehicles&action=get_vehicle&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_vehicle_id').value = data.id;
            document.getElementById('edit_make').value = data.make || '';
            document.getElementById('edit_model').value = data.model || '';
            document.getElementById('edit_year').value = data.year || '';
            document.getElementById('edit_vin').value = data.vin || '';
            document.getElementById('edit_license_plate').value = data.license_plate || '';
            document.getElementById('edit_color').value = data.color || '';
            document.getElementById('edit_airport').value = data.airport || '';
            document.getElementById('edit_status').value = data.status || 'available';
            document.getElementById('edit_mileage').value = data.mileage || '';
            document.getElementById('edit_bouncie_id').value = data.bouncie_id || '';
            document.getElementById('edit_sunpass_id').value = data.sunpass_id || '';
            document.getElementById('edit_ezpass_id').value = data.ezpass_id || '';
            document.getElementById('edit_lockbox_code').value = data.lockbox_code || '';
            document.getElementById('edit_mister_carwash_rfid').value = data.mister_carwash_rfid || '';
            document.getElementById('edit_fuel_type').value = data.fuel_type || '';
            document.getElementById('edit_fuel_capacity').value = data.fuel_capacity || '';
            document.getElementById('edit_oil_type').value = data.oil_type || '';
            document.getElementById('edit_oil_change_interval').value = data.oil_change_interval || '';
            document.getElementById('edit_tire_front_size').value = data.tire_front_size || '';
            document.getElementById('edit_tire_rear_size').value = data.tire_rear_size || '';
            document.getElementById('edit_registration_expiry').value = data.registration_expiry || '';
            document.getElementById('edit_date_added').value = data.date_added || '';
            document.getElementById('editVehicleModal').style.display = 'block';
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Handle edit form submission
document.getElementById('editVehicleForm').addEventListener('submit', function(e) {
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
    const viewModal = document.getElementById('viewVehicleModal');
    const editModal = document.getElementById('editVehicleModal');
    if (event.target === viewModal) {
        viewModal.style.display = 'none';
    }
    if (event.target === editModal) {
        editModal.style.display = 'none';
    }
}
</script>

<?php
// Handle AJAX request for getting vehicle data
if (isset($_GET['action']) && $_GET['action'] === 'get_vehicle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $vehicle = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($vehicle);
    exit;
}

// Handle vehicle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vehicle' && $permissions->hasPermission('vehicles', 'update')) {
    $stmt = $pdo->prepare("UPDATE vehicles SET 
        make = ?, model = ?, year = ?, vin = ?, license_plate = ?, color = ?, mileage = ?, status = ?,
        airport = ?, bouncie_id = ?, sunpass_id = ?, ezpass_id = ?, lockbox_code = ?, mister_carwash_rfid = ?,
        fuel_type = ?, fuel_capacity = ?, oil_type = ?, oil_change_interval = ?,
        tire_front_size = ?, tire_rear_size = ?, registration_expiry = ?, date_added = ?
        WHERE id = ?");
    $stmt->execute([
        $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], $_POST['license_plate'], 
        $_POST['color'], $_POST['mileage'], $_POST['status'],
        $_POST['airport'], $_POST['bouncie_id'], $_POST['sunpass_id'], $_POST['ezpass_id'], 
        $_POST['lockbox_code'], $_POST['mister_carwash_rfid'],
        $_POST['fuel_type'], $_POST['fuel_capacity'], $_POST['oil_type'], $_POST['oil_change_interval'],
        $_POST['tire_front_size'], $_POST['tire_rear_size'], $_POST['registration_expiry'], $_POST['date_added'],
        $_POST['id']
    ]);
    echo "<div class='alert alert-success'>Vehicle updated successfully!</div>";
}
?>

<!-- Add Vehicle Modal -->
<div id="addVehicleModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
    <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 2rem; border-radius: 8px; max-width: 900px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem;">
            <h3 style="margin: 0; color: #333;">Add New Vehicle</h3>
            <button onclick="closeModal('addVehicleModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <form id="addVehicleForm" onsubmit="handleAddVehicle(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- Basic Information -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Make *</label>
                    <input type="text" name="make" required style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Model *</label>
                    <input type="text" name="model" required style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Year *</label>
                    <input type="number" name="year" required min="1900" max="2099" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Color</label>
                    <input type="text" name="color" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">VIN *</label>
                    <input type="text" name="vin" required maxlength="17" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">License Plate</label>
                    <input type="text" name="license_plate" maxlength="20" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Mileage</label>
                    <input type="number" name="mileage" min="0" value="0" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status *</label>
                    <select name="status" required style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="available">Available</option>
                        <option value="rented">Rented</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="out_of_service">Out of Service</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Daily Rate *</label>
                    <input type="number" name="daily_rate" required min="0" step="0.01" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Airport</label>
                    <select name="airport" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="">Select Airport</option>
                        <option value="TPA">TPA - Tampa</option>
                        <option value="FLL">FLL - Fort Lauderdale</option>
                        <option value="MIA">MIA - Miami</option>
                    </select>
                </div>
                
                <!-- Tracking & IDs -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Bouncie ID</label>
                    <input type="text" name="bouncie_id" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">SunPass ID</label>
                    <input type="text" name="sunpass_id" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">EZ Pass ID</label>
                    <input type="text" name="ezpass_id" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Lockbox Code</label>
                    <input type="text" name="lockbox_code" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Mister Carwash RFID</label>
                    <input type="text" name="mister_carwash_rfid" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <!-- Vehicle Specifications -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Fuel Type</label>
                    <input type="text" name="fuel_type" maxlength="100" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Fuel Capacity (gallons)</label>
                    <input type="number" name="fuel_capacity" step="0.01" min="0" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Oil Type</label>
                    <input type="text" name="oil_type" maxlength="50" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Oil Change Interval</label>
                    <input type="text" name="oil_change_interval" maxlength="50" placeholder="e.g., 5000 miles" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Front Tire Size</label>
                    <input type="text" name="tire_front_size" maxlength="100" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Rear Tire Size</label>
                    <input type="text" name="tire_rear_size" maxlength="100" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Registration Expiry</label>
                    <input type="text" name="registration_expiry" maxlength="20" placeholder="MM/YYYY" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Date Added</label>
                    <input type="date" name="date_added" style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                <button type="button" onclick="closeModal('addVehicleModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #007bff; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Add Vehicle</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Vehicle Confirmation Modal -->
<div id="deleteVehicleModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: white; margin: 15% auto; padding: 2rem; border-radius: 8px; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: #dc3545;">⚠️ Confirm Delete Vehicle</h3>
        </div>
        
        <div class="modal-body" style="margin-bottom: 1.5rem;">
            <p style="margin: 0; color: #666;">Are you sure you want to delete this vehicle? This action cannot be undone.</p>
            <p id="deleteVehicleName" style="margin-top: 1rem; font-weight: 500; color: #333;"></p>
        </div>
        
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
            <button onclick="closeModal('deleteVehicleModal')" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
            <button id="confirmDeleteVehicleBtn" onclick="confirmDeleteVehicle()" style="background: #dc3545; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Delete Vehicle</button>
        </div>
    </div>
</div>

<script>
// Global variable for delete operation
let deleteVehicleId = null;

// Open modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    
    if (modalId === 'addVehicleModal') {
        document.getElementById('addVehicleForm').reset();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Handle add vehicle form submission
function handleAddVehicle(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('ajax', '1');
    formData.append('action', 'create_vehicle');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('addVehicleModal');
            showMessage('success', data.message || 'Vehicle added successfully!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('error', data.message || 'Failed to add vehicle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('error', 'An error occurred. Please try again.');
    });
}

// Prepare delete confirmation
function prepareDeleteVehicle(id, name) {
    deleteVehicleId = id;
    document.getElementById('deleteVehicleName').textContent = name;
    openModal('deleteVehicleModal');
}

// Confirm and execute delete
function confirmDeleteVehicle() {
    if (!deleteVehicleId) return;
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_vehicle');
    formData.append('id', deleteVehicleId);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('deleteVehicleModal');
            showMessage('success', data.message || 'Vehicle deleted successfully!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('error', data.message || 'Failed to delete vehicle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('error', 'An error occurred. Please try again.');
    });
}

// Show success/error message
function showMessage(type, message) {
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 2000;
        animation: slideIn 0.3s ease-out;
        background-color: ${type === 'success' ? '#28a745' : '#dc3545'};
    `;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => messageDiv.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
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

function bulkDeleteVehicles() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one vehicle to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${ids.length} vehicle(s)?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=bulk_delete_vehicles&ids=${ids.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully deleted ${ids.length} vehicle(s)`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete vehicles'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting vehicles');
    });
}
</script>
