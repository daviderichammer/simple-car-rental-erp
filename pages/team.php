<?php
// Team Management Page
if (!isset($pdo)) {
    die('Database connection not available');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && isset($_POST['first_name']) && isset($_POST['last_name'])) {
            $stmt = $pdo->prepare("INSERT INTO team_members (first_name, last_name, team_name, location, date_started) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['first_name'], 
                $_POST['last_name'], 
                $_POST['team_name'] ?? null, 
                $_POST['location'] ?? null,
                $_POST['date_started'] ?? null
            ]);
            header("Location: ?page=team");
            exit;
        } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE team_members SET first_name = ?, last_name = ?, team_name = ?, location = ?, date_started = ? WHERE id = ?");
            $stmt->execute([
                $_POST['first_name'], 
                $_POST['last_name'], 
                $_POST['team_name'] ?? null, 
                $_POST['location'] ?? null,
                $_POST['date_started'] ?? null,
                $_POST['id']
            ]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$team_filter = isset($_GET['team']) ? $_GET['team'] : '';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR team_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($team_filter)) {
    $where[] = "team_name = ?";
    $params[] = $team_filter;
}

if (!empty($location_filter)) {
    $where[] = "location = ?";
    $params[] = $location_filter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT team_name) as teams,
        COUNT(DISTINCT location) as locations
    FROM team_members
")->fetch(PDO::FETCH_ASSOC);

// Get team breakdown
$teamBreakdown = $pdo->query("
    SELECT team_name, COUNT(*) as count
    FROM team_members
    WHERE team_name IS NOT NULL
    GROUP BY team_name
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get location breakdown
$locationBreakdown = $pdo->query("
    SELECT location, COUNT(*) as count
    FROM team_members
    WHERE location IS NOT NULL
    GROUP BY location
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all team members
$stmt = $pdo->prepare("
    SELECT *
    FROM team_members
    $whereClause
    ORDER BY team_name, last_name, first_name
");
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <h2>Team Members</h2>
    <p>Manage team member information</p>

    <!-- Statistics Cards -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-4" style="flex: 1; min-width: 200px;">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Team Members</h5>
                    <h2><?php echo number_format($stats['total']); ?></h2>
                    </div>
    <button onclick="showAddTeamMemberModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Team Member</button>
</div>
            </div>
        </div>
        <div class="col-md-4" style="flex: 1; min-width: 200px;">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Teams</h5>
                    <h2><?php echo number_format($stats['teams']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4" style="flex: 1; min-width: 200px;">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Locations</h5>
                    <h2><?php echo number_format($stats['locations']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Team & Location Breakdown -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-6" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-header">
                    <h5>Members by Team</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($teamBreakdown as $team): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo htmlspecialchars($team['team_name']); ?></span>
                        <span class="badge bg-primary"><?php echo $team['count']; ?> members</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-header">
                    <h5>Members by Location</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($locationBreakdown as $loc): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo htmlspecialchars($loc['location']); ?></span>
                        <span class="badge bg-success"><?php echo $loc['count']; ?> members</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Team Member -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Team Member</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;" style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div class="col-md-2" style="flex: 1; min-width: 200px;">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="col-md-2" style="flex: 1; min-width: 200px;">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="col-md-3" style="flex: 1; min-width: 200px;">
                        <label>Team</label>
                        <input type="text" name="team_name" class="form-control" placeholder="e.g., CUSTOMER SVC">
                    </div>
                    <div class="col-md-2" style="flex: 1; min-width: 200px;">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., TPA">
                    </div>
                    <div class="col-md-2" style="flex: 1; min-width: 200px;">
                        <label>Date Started</label>
                        <input type="date" name="date_started" class="form-control">
                    </div>
                    <div class="col-md-1" style="flex: 1; min-width: 200px;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Team Members</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <input type="hidden" name="page" value="team">
                <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;" style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div class="col-md-3" style="flex: 1; min-width: 200px;">
                        <label>Team</label>
                        <select name="team" class="form-control">
                            <option value="">All Teams</option>
                            <?php foreach ($teamBreakdown as $team): ?>
                            <option value="<?php echo htmlspecialchars($team['team_name']); ?>" <?php echo $team_filter === $team['team_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['team_name']); ?> (<?php echo $team['count']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3" style="flex: 1; min-width: 200px;">
                        <label>Location</label>
                        <select name="location" class="form-control">
                            <option value="">All Locations</option>
                            <?php foreach ($locationBreakdown as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location_filter === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?> (<?php echo $loc['count']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4" style="flex: 1; min-width: 200px;">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search name or team..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2" style="flex: 1; min-width: 200px;">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?page=team" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Team Members Table -->
    <div class="card">
        <div class="card-header">
            <h5>All Team Members (<?php echo count($members); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Team</th>
                            <th>Location</th>
                            <th>Date Started</th>
                            <th>Tenure</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <?php
                        $tenure = '';
                        if ($member['date_started']) {
                            $start = new DateTime($member['date_started']);
                            $now = new DateTime();
                            $diff = $start->diff($now);
                            if ($diff->y > 0) {
                                $tenure = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                            } elseif ($diff->m > 0) {
                                $tenure = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                            } else {
                                $tenure = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong></td>
                            <td>
                                <?php if ($member['team_name']): ?>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($member['team_name']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($member['location']): ?>
                                <span class="badge bg-success"><?php echo htmlspecialchars($member['location']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $member['date_started'] ? date('M d, Y', strtotime($member['date_started'])) : 'N/A'; ?></td>
                            <td><?php echo $tenure ?: 'N/A'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteMember(<?php echo $member['id']; ?>)">Delete</button>
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
                <h5 class="modal-title">Edit Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Team</label>
                        <input type="text" name="team_name" id="edit_team_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Date Started</label>
                        <input type="date" name="date_started" id="edit_date_started" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveMember()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function editMember(member) {
    document.getElementById('edit_id').value = member.id;
    document.getElementById('edit_first_name').value = member.first_name;
    document.getElementById('edit_last_name').value = member.last_name;
    document.getElementById('edit_team_name').value = member.team_name || '';
    document.getElementById('edit_location').value = member.location || '';
    document.getElementById('edit_date_started').value = member.date_started || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function saveMember() {
    const formData = new FormData(document.getElementById('editForm'));
    fetch('?page=team', {
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

function deleteMember(id) {
    if (confirm('Are you sure you want to delete this team member?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        fetch('?page=team', {
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
}
</script>

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

<!-- Add Team Member Modal -->
<div class="modal fade" id="addTeamMemberModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">👨‍💼 Add New Team Member</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddTeamMemberModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addTeamMemberForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="add_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone </label>
                            <input type="tel" name="phone" id="add_phone" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <input type="text" name="role" id="add_role" class="form-control" placeholder="e.g., Manager, Technician" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department </label>
                            <input type="text" name="department" id="add_department" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hire Date </label>
                            <input type="date" name="hire_date" id="add_hire_date" class="form-control" >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status </label>
                            <select name="status" id="add_status" class="form-control" >
                                <option value="">Select...</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
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
                <button type="button" class="btn btn-secondary" onclick="closeAddTeamMemberModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddTeamMember()">Add Team Member</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTeamMemberModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Team Member</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteTeamMemberModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this team member? This action cannot be undone.</p>
                <p><strong id="deleteTeamMemberInfo"></strong></p>
                <input type="hidden" id="delete_team_member_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteTeamMemberModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteTeamMember()">Delete Team Member</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Team Member Modal
function showAddTeamMemberModal() {
    document.getElementById('addTeamMemberForm').reset();
    document.getElementById('addTeamMemberModal').style.display = 'block';
    document.getElementById('addTeamMemberModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Team Member Modal
function closeAddTeamMemberModal() {
    document.getElementById('addTeamMemberModal').style.display = 'none';
    document.getElementById('addTeamMemberModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Team Member
function submitAddTeamMember() {
    const form = document.getElementById('addTeamMemberForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_team_member');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddTeamMemberModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add team member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the team member');
    });
}

// Show Delete Confirmation Modal
function deleteTeamMember(id, name) {
    document.getElementById('delete_team_member_id').value = id;
    document.getElementById('deleteTeamMemberInfo').textContent = name;
    document.getElementById('deleteTeamMemberModal').style.display = 'block';
    document.getElementById('deleteTeamMemberModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Team Member Modal
function closeDeleteTeamMemberModal() {
    document.getElementById('deleteTeamMemberModal').style.display = 'none';
    document.getElementById('deleteTeamMemberModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Team Member
function confirmDeleteTeamMember() {
    const id = document.getElementById('delete_team_member_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_team_member');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteTeamMemberModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete team member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the team member');
    });
}
</script>
