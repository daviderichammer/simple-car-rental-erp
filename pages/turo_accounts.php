<?php
// Fetch all Turo accounts with vehicle counts
$stmt = $pdo->query("
    SELECT 
        ta.id,
        ta.account_name,
        ta.email,
        ta.password,
        ta.location,
        ta.is_active,
        ta.last_used,
        COUNT(v.id) as vehicle_count
    FROM turo_accounts ta
    LEFT JOIN vehicles v ON v.turo_account_id = ta.id
    GROUP BY ta.id
    ORDER BY ta.account_name
");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin: 0; font-size: 2rem;">🔐 Turo Accounts Management</h1>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Manage Turo account credentials and vehicle assignments</p>
        </div>
        <button onclick="showAddAccountModal()" style="background: white; color: #667eea; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 1rem;">
            + Add New Account
        </button>
    </div>
</div>

<!-- Accounts List -->
<div style="display: grid; gap: 1.5rem;">
    <?php foreach ($accounts as $account): ?>
    <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <h2 style="margin: 0; font-size: 1.5rem; color: #667eea;"><?php echo htmlspecialchars($account['account_name']); ?></h2>
                    <?php if ($account['is_active']): ?>
                        <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">● Active</span>
                    <?php else: ?>
                        <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">● Inactive</span>
                    <?php endif; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Email</div>
                        <div style="font-weight: 500;"><?php echo htmlspecialchars($account['email']); ?></div>
                    </div>
                    <div>
                        <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Location</div>
                        <div style="font-weight: 500;"><?php echo htmlspecialchars($account['location']); ?></div>
                    </div>
                    <div>
                        <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Vehicles Assigned</div>
                        <div style="font-weight: 500; color: #667eea;"><?php echo $account['vehicle_count']; ?> vehicles</div>
                    </div>
                    <div>
                        <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Last Used</div>
                        <div style="font-weight: 500;">
                            <?php 
                            if ($account['last_used']) {
                                $lastUsed = new DateTime($account['last_used']);
                                $now = new DateTime();
                                $diff = $now->diff($lastUsed);
                                if ($diff->days == 0 && $diff->h == 0) {
                                    echo $diff->i . ' min ago';
                                } elseif ($diff->days == 0) {
                                    echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                } else {
                                    echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                }
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="editAccount(<?php echo $account['id']; ?>)" style="background: #667eea; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                    ✏️ Edit
                </button>
                <?php if ($account['is_active']): ?>
                    <button onclick="deactivateAccount(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['account_name']); ?>')" style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                        🚫 Deactivate
                    </button>
                <?php else: ?>
                    <button onclick="activateAccount(<?php echo $account['id']; ?>)" style="background: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                        ✓ Activate
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Account Modal -->
<div id="addAccountModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3>🔐 Add Turo Account</h3>
            <span class="close" onclick="closeAddAccountModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addAccountForm">
                <div class="form-group">
                    <label for="add_account_name">Account Name *</label>
                    <input type="text" id="add_account_name" name="account_name" placeholder="e.g., TPA, FLL, MIA" required>
                </div>
                <div class="form-group">
                    <label for="add_email">Email Address *</label>
                    <input type="email" id="add_email" name="email" placeholder="info@drive-example.com" required>
                </div>
                <div class="form-group">
                    <label for="add_password">Password *</label>
                    <div style="position: relative;">
                        <input type="password" id="add_password" name="password" placeholder="Enter password" required>
                        <button type="button" onclick="togglePassword('add_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #667eea; font-size: 0.85rem;">
                            Show
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="add_location">Location/Airport *</label>
                    <input type="text" id="add_location" name="location" placeholder="e.g., Tampa, Fort Lauderdale" required>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="add_is_active" name="is_active" checked value="1">
                        <span>Active</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="submitAddAccount()">Save Account</button>
            <button type="button" onclick="closeAddAccountModal()" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div id="editAccountModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3>✏️ Edit Turo Account</h3>
            <span class="close" onclick="closeEditAccountModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editAccountForm">
                <input type="hidden" id="edit_account_id" name="id">
                <div class="form-group">
                    <label for="edit_account_name">Account Name *</label>
                    <input type="text" id="edit_account_name" name="account_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email Address *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="change_password" onchange="togglePasswordField()">
                        <span>Change Password</span>
                    </label>
                </div>
                <div class="form-group" id="password_field" style="display: none;">
                    <label for="edit_password">New Password</label>
                    <div style="position: relative;">
                        <input type="password" id="edit_password" name="password" placeholder="Enter new password">
                        <button type="button" onclick="togglePassword('edit_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #667eea; font-size: 0.85rem;">
                            Show
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_location">Location/Airport *</label>
                    <input type="text" id="edit_location" name="location" required>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <span>Active</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="submitEditAccount()">Save Changes</button>
            <button type="button" onclick="closeEditAccountModal()" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<!-- Modal Backdrop -->
<div id="modalBackdrop" class="modal-backdrop"></div>

<script>
// Show Add Account Modal
function showAddAccountModal() {
    document.getElementById('addAccountModal').style.display = 'block';
    document.getElementById('addAccountModal').classList.add('show');
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalBackdrop').classList.add('show');
}

// Close Add Account Modal
function closeAddAccountModal() {
    document.getElementById('addAccountModal').style.display = 'none';
    document.getElementById('addAccountModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
    document.getElementById('addAccountForm').reset();
}

// Submit Add Account
function submitAddAccount() {
    const formData = new FormData(document.getElementById('addAccountForm'));
    formData.append('ajax', '1');
    formData.append('action', 'add_turo_account');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddAccountModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to add account'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the account');
    });
}

// Edit Account
function editAccount(id) {
    fetch('index.php?ajax=1&action=get_turo_account&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const account = data.account;
                document.getElementById('edit_account_id').value = account.id;
                document.getElementById('edit_account_name').value = account.account_name;
                document.getElementById('edit_email').value = account.email;
                document.getElementById('edit_location').value = account.location;
                document.getElementById('edit_is_active').checked = account.is_active == 1;
                
                document.getElementById('editAccountModal').style.display = 'block';
                document.getElementById('editAccountModal').classList.add('show');
                document.getElementById('modalBackdrop').style.display = 'block';
                document.getElementById('modalBackdrop').classList.add('show');
            } else {
                alert('Error: ' + (data.message || 'Failed to load account data'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading the account data');
        });
}

// Close Edit Account Modal
function closeEditAccountModal() {
    document.getElementById('editAccountModal').style.display = 'none';
    document.getElementById('editAccountModal').classList.remove('show');
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalBackdrop').classList.remove('show');
    document.getElementById('editAccountForm').reset();
    document.getElementById('password_field').style.display = 'none';
    document.getElementById('change_password').checked = false;
}

// Submit Edit Account
function submitEditAccount() {
    const formData = new FormData(document.getElementById('editAccountForm'));
    formData.append('ajax', '1');
    formData.append('action', 'edit_turo_account');
    
    // Only include password if change_password is checked
    if (!document.getElementById('change_password').checked) {
        formData.delete('password');
    }
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditAccountModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update account'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the account');
    });
}

// Deactivate Account
function deactivateAccount(id, name) {
    if (!confirm(`Deactivate account "${name}"?\n\nThis account will no longer be used for scraping, but existing data will be preserved.\nYou can reactivate it later if needed.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'deactivate_turo_account');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to deactivate account'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deactivating the account');
    });
}

// Activate Account
function activateAccount(id) {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'activate_turo_account');
    formData.append('id', id);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to activate account'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while activating the account');
    });
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        button.textContent = 'Hide';
    } else {
        field.type = 'password';
        button.textContent = 'Show';
    }
}

// Toggle password field visibility
function togglePasswordField() {
    const checkbox = document.getElementById('change_password');
    const field = document.getElementById('password_field');
    field.style.display = checkbox.checked ? 'block' : 'none';
    
    if (!checkbox.checked) {
        document.getElementById('edit_password').value = '';
    }
}
</script>
