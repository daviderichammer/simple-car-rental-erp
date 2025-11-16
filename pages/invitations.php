<?php
// Check permissions
if (!$permissions->hasPermission('invitations', 'read')) {
    echo "<div class='alert alert-danger'>You don't have permission to view invitations.</div>";
    exit;
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2>System Invitations</h2>
        <p>Manage user invitations and access requests</p>
    </div>
    <button onclick="showAddInvitationModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">+ Add New Invitation</button>
</div>

<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_invitation' && $permissions->hasPermission('invitations', 'create')) {
        $stmt = $pdo->prepare("INSERT INTO invitations (email, role, invited_by, status, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['email'],
            $_POST['role'],
            $_SESSION['user_id'],
            'pending',
            date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);
        echo "<div class='alert alert-success'>Invitation sent successfully!</div>";
    } elseif ($_POST['action'] === 'update_invitation' && $permissions->hasPermission('invitations', 'update')) {
        $stmt = $pdo->prepare("UPDATE invitations SET email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['email'],
            $_POST['role'],
            $_POST['status'],
            $_POST['id']
        ]);
        echo "<div class='alert alert-success'>Invitation updated successfully!</div>";
    } elseif ($_POST['action'] === 'delete_invitation' && $permissions->hasPermission('invitations', 'delete')) {
        $stmt = $pdo->prepare("DELETE FROM invitations WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo "<div class='alert alert-success'>Invitation deleted successfully!</div>";
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$sql = "SELECT i.*, u.username as invited_by_name 
        FROM invitations i 
        LEFT JOIN users u ON i.invited_by = u.id 
        WHERE 1=1";
$params = [];

if ($filterStatus) {
    $sql .= " AND i.status = ?";
    $params[] = $filterStatus;
}

if ($searchQuery) {
    $sql .= " AND (i.email LIKE ? OR i.role LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invitations = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM invitations
")->fetch();
?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Invitations</div>
        <div style="font-size: 2rem; font-weight: bold; color: #333;"><?= $stats['total'] ?></div>
    </div>
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.5rem;">Pending</div>
        <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;"><?= $stats['pending'] ?></div>
    </div>
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.5rem;">Accepted</div>
        <div style="font-size: 2rem; font-weight: bold; color: #10b981;"><?= $stats['accepted'] ?></div>
    </div>
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.5rem;">Expired</div>
        <div style="font-size: 2rem; font-weight: bold; color: #ef4444;"><?= $stats['expired'] ?></div>
    </div>
</div>

<!-- Filters -->
<div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="invitations">
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
            <select name="status" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">All Statuses</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="accepted" <?= $filterStatus === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Email or role..." style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" style="padding: 0.5rem 1rem; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">Filter</button>
            <a href="?page=invitations" style="padding: 0.5rem 1rem; background: #6b7280; color: white; border: none; border-radius: 4px; text-decoration: none; display: inline-block;">Reset</a>
        </div>
    </form>
</div>

<!-- Invitations Table -->
<div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 1rem;">All Invitations (<?= count($invitations) ?>)</h3>
    
    <?php if (empty($invitations)): ?>
        <p style="text-align: center; color: #666; padding: 2rem;">No invitations found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Email</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Role</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Status</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Invited By</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Created</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Expires</th>
                        <th style="padding: 0.75rem; text-align: left; font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invitations as $invitation): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 0.75rem;"><?= htmlspecialchars($invitation['email']) ?></td>
                            <td style="padding: 0.75rem;"><?= htmlspecialchars($invitation['role']) ?></td>
                            <td style="padding: 0.75rem;">
                                <?php
                                $statusColors = [
                                    'pending' => '#f59e0b',
                                    'accepted' => '#10b981',
                                    'expired' => '#ef4444',
                                    'cancelled' => '#6b7280'
                                ];
                                $color = $statusColors[$invitation['status']] ?? '#6b7280';
                                ?>
                                <span style="background: <?= $color ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem;">
                                    <?= ucfirst($invitation['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem;"><?= htmlspecialchars($invitation['invited_by_name'] ?? 'Unknown') ?></td>
                            <td style="padding: 0.75rem;"><?= date('M d, Y', strtotime($invitation['created_at'])) ?></td>
                            <td style="padding: 0.75rem;"><?= date('M d, Y', strtotime($invitation['expires_at'])) ?></td>
                            <td style="padding: 0.75rem;">
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php if ($permissions->hasPermission('invitations', 'update')): ?>
                                        <button onclick="editInvitation(<?= $invitation['id'] ?>)" style="padding: 0.25rem 0.75rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.875rem;">Edit</button>
                                    <?php endif; ?>
                                    <?php if ($permissions->hasPermission('invitations', 'delete')): ?>
                                        <button onclick="showDeleteInvitationModal(<?= $invitation['id'] ?>, '<?= htmlspecialchars($invitation['email']) ?>')" style="padding: 0.25rem 0.75rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.875rem;">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function editInvitation(id) {
    // TODO: Implement edit modal
    alert('Edit functionality coming soon!');
}
</script>
<!-- Add Invitation Modal -->
<div class="modal fade" id="addInvitationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">✉️ Add New Invitation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddInvitationModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addInvitationForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="add_role_id" class="form-control" required>
                                <option value="">Select...</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . $row["id"] . "'>" . htmlspecialchars($row["name"]) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddInvitationModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddInvitation()">Add Invitation</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteInvitationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">⚠️ Confirm Delete Invitation</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeDeleteInvitationModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this invitation? This action cannot be undone.</p>
                <p><strong id="deleteInvitationInfo"></strong></p>
                <input type="hidden" id="delete_invitation_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteInvitationModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteInvitation()">Delete Invitation</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show Add Invitation Modal
function showAddInvitationModal() {
    document.getElementById('addInvitationForm').reset();
    document.getElementById('addInvitationModal').style.display = 'block';
    document.getElementById('addInvitationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Invitation Modal
function closeAddInvitationModal() {
    document.getElementById('addInvitationModal').style.display = 'none';
    document.getElementById('addInvitationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Submit Add Invitation
function submitAddInvitation() {
    const form = document.getElementById('addInvitationForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'create_invitation');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddInvitationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add invitation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the invitation');
    });
}

// Show Delete Confirmation Modal
function deleteInvitation(id, name) {
    document.getElementById('delete_invitation_id').value = id;
    document.getElementById('deleteInvitationInfo').textContent = name;
    document.getElementById('deleteInvitationModal').style.display = 'block';
    document.getElementById('deleteInvitationModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Delete Invitation Modal
function closeDeleteInvitationModal() {
    document.getElementById('deleteInvitationModal').style.display = 'none';
    document.getElementById('deleteInvitationModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
}

// Confirm Delete Invitation
function confirmDeleteInvitation() {
    const id = document.getElementById('delete_invitation_id').value;
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'delete_invitation');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteInvitationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete invitation'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the invitation');
    });
}
</script>
