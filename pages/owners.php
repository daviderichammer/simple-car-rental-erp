<?php
// Owners Management Page
if (!isset($pdo)) {
    die('Database connection not available');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && isset($_POST['vin']) && isset($_POST['owner_name'])) {
            $stmt = $pdo->prepare("INSERT INTO vehicle_owners (vin, owner_name, owner_type) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['vin'], $_POST['owner_name'], $_POST['owner_type'] ?? null]);
            header("Location: ?page=owners");
            exit;
        } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE vehicle_owners SET vin = ?, owner_name = ?, owner_type = ? WHERE id = ?");
            $stmt->execute([$_POST['vin'], $_POST['owner_name'], $_POST['owner_type'] ?? null, $_POST['id']]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM vehicle_owners WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$owner_filter = isset($_GET['owner']) ? $_GET['owner'] : '';

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(vo.vin LIKE ? OR vo.owner_name LIKE ? OR v.make LIKE ? OR v.model LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($owner_filter)) {
    $where[] = "vo.owner_name = ?";
    $params[] = $owner_filter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT owner_name) as unique_owners,
        COUNT(DISTINCT owner_type) as owner_types
    FROM vehicle_owners
")->fetch(PDO::FETCH_ASSOC);

// Get owner breakdown
$ownerBreakdown = $pdo->query("
    SELECT owner_name, COUNT(*) as count
    FROM vehicle_owners
    GROUP BY owner_name
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all owners with vehicle info
$stmt = $pdo->prepare("
    SELECT 
        vo.*,
        v.make,
        v.model,
        v.year,
        v.color,
        v.license_plate,
        v.airport
    FROM vehicle_owners vo
    LEFT JOIN vehicles v ON vo.vin = v.vin
    $whereClause
    ORDER BY vo.owner_name, v.year DESC, v.make, v.model
");
$stmt->execute($params);
$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all VINs for dropdown
$allVins = $pdo->query("SELECT vin, make, model, year FROM vehicles ORDER BY year DESC, make, model")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>Vehicle Owners</h2>
            <p>Manage vehicle ownership information</p>
        </div>
        <div>
            <button class="btn btn-primary btn-lg" onclick="showAddOwnerModal()">
                <i class="bi bi-plus-circle"></i> + Add New Owner
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <div style="background-color: #6c63ff; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h5 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 500;">Total Ownership Records</h5>
                <h2 style="margin: 0; font-size: 36px; font-weight: bold;"><?php echo number_format($stats['total']); ?></h2>
            </div>
        </div>
        <div style="flex: 1; min-width: 250px;">
            <div style="background-color: #28a745; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h5 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 500;">Unique Owners</h5>
                <h2 style="margin: 0; font-size: 36px; font-weight: bold;"><?php echo number_format($stats['unique_owners']); ?></h2>
            </div>
        </div>
        <div style="flex: 1; min-width: 250px;">
            <div style="background-color: #17a2b8; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h5 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 500;">Owner Types</h5>
                <h2 style="margin: 0; font-size: 36px; font-weight: bold;"><?php echo number_format($stats['owner_types']); ?></h2>
            </div>
        </div>
    </div>

    <!-- Owner Breakdown -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Vehicles by Owner</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($ownerBreakdown as $owner): ?>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($owner['owner_name']); ?></span>
                                <span class="badge bg-primary"><?php echo $owner['count']; ?> vehicles</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Owners</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <input type="hidden" name="page" value="owners">
                <div class="row">
                    <div class="col-md-5">
                        <label>Owner</label>
                        <select name="owner" class="form-control">
                            <option value="">All Owners</option>
                            <?php foreach ($ownerBreakdown as $owner): ?>
                            <option value="<?php echo htmlspecialchars($owner['owner_name']); ?>" <?php echo $owner_filter === $owner['owner_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($owner['owner_name']); ?> (<?php echo $owner['count']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search VIN, owner, make, model..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?page=owners" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Owners Table -->
    <div class="card">
        <div class="card-header">
            <h5>All Ownership Records (<?php echo count($owners); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>VIN</th>
                            <th>Vehicle</th>
                            <th>License Plate</th>
                            <th>Location</th>
                            <th>Owner Name</th>
                            <th>Owner Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($owners as $owner): ?>
                        <tr>
                            <td><small><?php echo htmlspecialchars($owner['vin']); ?></small></td>
                            <td><?php echo htmlspecialchars($owner['year'] . ' ' . $owner['make'] . ' ' . $owner['model']); ?></td>
                            <td><?php echo htmlspecialchars($owner['license_plate'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($owner['airport']): ?>
                                <span class="badge bg-<?php echo $owner['airport'] === 'TPA' ? 'primary' : ($owner['airport'] === 'FLL' ? 'success' : 'warning'); ?>">
                                    <?php echo htmlspecialchars($owner['airport']); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($owner['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($owner['owner_type'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editOwner(<?php echo htmlspecialchars(json_encode($owner)); ?>)">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteOwner(<?php echo $owner['id']; ?>, '<?php echo htmlspecialchars($owner['owner_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($owner['vin'], ENT_QUOTES); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Owner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label>VIN *</label>
                        <input type="text" name="vin" id="edit_vin" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Owner Name *</label>
                        <input type="text" name="owner_name" id="edit_owner_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Owner Type</label>
                        <input type="text" name="owner_type" id="edit_owner_type" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveOwner()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Owner Modal -->
<div class="modal fade" id="addOwnerModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Add New Owner Record</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddOwnerModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addOwnerForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Vehicle (VIN) <span class="text-danger">*</span></label>
                            <select name="vin" id="add_vin" class="form-control" required>
                                <option value="">Select a vehicle...</option>
                                <?php foreach ($allVins as $v): ?>
                                <option value="<?php echo htmlspecialchars($v['vin']); ?>">
                                    <?php echo htmlspecialchars($v['year'] . ' ' . $v['make'] . ' ' . $v['model'] . ' - ' . $v['vin']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" id="add_owner_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Owner Type</label>
                            <input type="text" name="owner_type" id="add_owner_type" class="form-control" placeholder="e.g., Individual, Company">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddOwnerModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddOwner()">Add Owner</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteOwnerModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Owner</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteOwnerModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this owner record? This action cannot be undone.</p>
                <p><strong id="deleteOwnerInfo"></strong></p>
                <input type="hidden" id="delete_owner_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteOwnerModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteOwner()">Delete Owner</button>
            </div>
        </div>
    </div>
</div>

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
</style>

<script>
// Show Add Owner Modal
function showAddOwnerModal() {
    document.getElementById('addOwnerForm').reset();
    document.getElementById('addOwnerModal').style.display = 'block';
    document.getElementById('addOwnerModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Owner Modal
function closeAddOwnerModal() {
    document.getElementById('addOwnerModal').style.display = 'none';
    document.getElementById('addOwnerModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Owner
function submitAddOwner() {
    const form = document.getElementById('addOwnerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_owner');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddOwnerModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add owner'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the owner');
    });
}

// Edit Owner (existing function, keep as is)
function editOwner(owner) {
    document.getElementById('edit_id').value = owner.id;
    document.getElementById('edit_vin').value = owner.vin;
    document.getElementById('edit_owner_name').value = owner.owner_name;
    document.getElementById('edit_owner_type').value = owner.owner_type || '';
    
    // Show edit modal using vanilla JS
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.style.display = 'block';
        editModal.classList.add('show');
        document.getElementById('modalBackdrop').style.display = 'block';
        document.getElementById('modalBackdrop').classList.add('show');
    }
}

// Save Owner (existing function, keep as is)
function saveOwner() {
    const formData = new FormData(document.getElementById('editForm'));
    fetch('?page=owners', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Show Delete Confirmation Modal
function deleteOwner(id, ownerName, vin) {
    document.getElementById('delete_owner_id').value = id;
    document.getElementById('deleteOwnerInfo').textContent = ownerName + ' - ' + vin;
    document.getElementById('deleteOwnerModal').style.display = 'block';
    document.getElementById('deleteOwnerModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Owner Modal
function closeDeleteOwnerModal() {
    document.getElementById('deleteOwnerModal').style.display = 'none';
    document.getElementById('deleteOwnerModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Owner
function confirmDeleteOwner() {
    const id = document.getElementById('delete_owner_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_owner');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteOwnerModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete owner'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the owner');
    });
}
</script>
