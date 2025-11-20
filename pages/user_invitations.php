<?php
// User Invitations Management Page
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle invitation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_invitation' && isset($permissions) && $permissions->hasPermission('users', 'create')) {
        $email = trim($_POST['email']);
        $role_id = (int)$_POST['role_id'];
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration (7 days from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        try {
            // Insert invitation
            $stmt = $pdo->prepare("INSERT INTO user_invitations (email, token, role_id, invited_by, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $token, $role_id, $_SESSION['user_id'], $expires_at]);
            
            // Generate invitation URL
            $invite_url = "https://admin.infiniteautorentals.com/accept_invite.php?token=" . $token;
            
            // Send email (using mail() function - you may want to use a proper email service)
            $subject = "Invitation to RAVEN System";
            $message = "Hello,\n\n";
            $message .= "You have been invited to join the RAVEN System.\n\n";
            $message .= "Click the link below to accept your invitation and set up your account:\n";
            $message .= $invite_url . "\n\n";
            $message .= "This invitation will expire in 7 days.\n\n";
            $message .= "Best regards,\nRAVEN Team";
            $headers = "From: noreply@infiniteautorentals.com\r\n";
            $headers .= "Reply-To: support@infiniteautorentals.com\r\n";
            
            mail($email, $subject, $message, $headers);
            
            $success_message = "Invitation sent successfully to " . htmlspecialchars($email) . "!";
            $invite_link = $invite_url;
        } catch (Exception $e) {
            $error_message = "Failed to send invitation: " . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_invitation' && isset($permissions) && $permissions->hasPermission('users', 'delete')) {
        $invitation_id = (int)$_POST['invitation_id'];
        $stmt = $pdo->prepare("UPDATE user_invitations SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$invitation_id]);
        $success_message = "Invitation cancelled successfully!";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'resend_invitation' && isset($permissions) && $permissions->hasPermission('users', 'create')) {
        $invitation_id = (int)$_POST['invitation_id'];
        
        // Get invitation details
        $stmt = $pdo->prepare("SELECT * FROM user_invitations WHERE id = ?");
        $stmt->execute([$invitation_id]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invitation) {
            // Generate new token and expiration
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Update invitation
            $stmt = $pdo->prepare("UPDATE user_invitations SET token = ?, expires_at = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$token, $expires_at, $invitation_id]);
            
            // Generate invitation URL
            $invite_url = "https://admin.infiniteautorentals.com/accept_invite.php?token=" . $token;
            
            // Send email
            $subject = "Invitation to RAVEN System (Resent)";
            $message = "Hello,\n\n";
            $message .= "Your invitation to join the RAVEN System has been resent.\n\n";
            $message .= "Click the link below to accept your invitation and set up your account:\n";
            $message .= $invite_url . "\n\n";
            $message .= "This invitation will expire in 7 days.\n\n";
            $message .= "Best regards,\nRAVEN Team";
            $headers = "From: noreply@infiniteautorentals.com\r\n";
            $headers .= "Reply-To: support@infiniteautorentals.com\r\n";
            
            mail($invitation['email'], $subject, $message, $headers);
            
            $success_message = "Invitation resent successfully to " . htmlspecialchars($invitation['email']) . "!";
            $invite_link = $invite_url;
        }
    }
}

// Get all invitations
$stmt = $pdo->query("
    SELECT ui.*, r.name as role_name, u.first_name, u.last_name 
    FROM user_invitations ui
    LEFT JOIN roles r ON ui.role_id = r.id
    LEFT JOIN users u ON ui.invited_by = u.id
    ORDER BY ui.created_at DESC
");
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles for the invitation form
$stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update expired invitations
$pdo->query("UPDATE user_invitations SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
?>

<div class="container-fluid mt-4">
    <h2>User Invitations</h2>
    <p>Send invitations to new users to join the RAVEN system</p>
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $success_message; ?>
        <?php if (isset($invite_link)): ?>
        <hr>
        <p class="mb-0"><strong>Invitation Link:</strong> <a href="<?php echo $invite_link; ?>" target="_blank"><?php echo $invite_link; ?></a></p>
        <small class="text-muted">You can also copy this link and send it manually.</small>
        <?php endif; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>
    
    <!-- Send New Invitation -->
    <?php if (isset($permissions) && $permissions->hasPermission('users', 'create')): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Send New Invitation</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="send_invitation">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <small class="form-text text-muted">The user will receive an invitation email at this address</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="role_id">Role *</label>
                            <select class="form-control" id="role_id" name="role_id" required>
                                <option value="">Select a role...</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane"></i> Send Invitation
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Invitations List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Sent Invitations</h5>
        </div>
        <div class="card-body">
            <?php if (empty($invitations)): ?>
            <p class="text-muted">No invitations sent yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Invited By</th>
                            <th>Sent Date</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['email']); ?></td>
                            <td><?php echo htmlspecialchars($inv['role_name']); ?></td>
                            <td>
                                <?php
                                $badge_class = 'secondary';
                                if ($inv['status'] === 'pending') $badge_class = 'warning';
                                elseif ($inv['status'] === 'accepted') $badge_class = 'success';
                                elseif ($inv['status'] === 'expired') $badge_class = 'danger';
                                elseif ($inv['status'] === 'cancelled') $badge_class = 'dark';
                                ?>
                                <span class="badge badge-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst($inv['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($inv['created_at'])); ?></td>
                            <td>
                                <?php 
                                $expires = strtotime($inv['expires_at']);
                                $now = time();
                                if ($expires < $now) {
                                    echo '<span class="text-danger">Expired</span>';
                                } else {
                                    $days_left = ceil(($expires - $now) / 86400);
                                    echo date('M d, Y', $expires) . ' <small class="text-muted">(' . $days_left . ' days)</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($inv['status'] === 'pending' && isset($permissions) && $permissions->hasPermission('users', 'create')): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="resend_invitation">
                                    <input type="hidden" name="invitation_id" value="<?php echo $inv['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-info" title="Resend">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($inv['status'] === 'pending' && isset($permissions) && $permissions->hasPermission('users', 'delete')): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this invitation?');">
                                    <input type="hidden" name="action" value="cancel_invitation">
                                    <input type="hidden" name="invitation_id" value="<?php echo $inv['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($inv['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-secondary" onclick="copyInviteLink('<?php echo $inv['token']; ?>')" title="Copy Link">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyInviteLink(token) {
    const url = 'https://admin.infiniteautorentals.com/accept_invite.php?token=' + token;
    navigator.clipboard.writeText(url).then(function() {
        alert('Invitation link copied to clipboard!');
    }, function(err) {
        alert('Failed to copy link: ' + err);
    });
}
</script>
