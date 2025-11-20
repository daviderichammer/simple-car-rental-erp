<?php
// Turo Import / Google Sheets Sync page
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get last sync info
$stmt = $pdo->query("SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 1");
$last_sync = $stmt->fetch(PDO::FETCH_ASSOC);

// Get sync history
$stmt = $pdo->query("SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 10");
$sync_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle manual sync request
if (isset($_POST['action']) && $_POST['action'] === 'sync_now' && isset($permissions) && $permissions->hasPermission('turo_import', 'create')) {
    // Execute sync script
    $output = shell_exec('cd /var/www/admin.infiniteautorentals.com && php scripts/sync_google_sheets.php 2>&1');
    
    // Refresh last sync info
    $stmt = $pdo->query("SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 1");
    $last_sync = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo '<div class="alert alert-success">Sync completed! Check the log below for details.</div>';
    echo '<div class="alert alert-info"><pre>' . htmlspecialchars($output) . '</pre></div>';
}
?>

<div class="container-fluid mt-4">
    <h2>Google Sheets Sync</h2>
    <p>Synchronize data from Google Sheets to the RAVEN database</p>
    
    <!-- Last Sync Info -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card <?php echo $last_sync ? ($last_sync['status'] === 'success' ? 'bg-success' : 'bg-warning') : 'bg-secondary'; ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">Last Sync Status</h5>
                    <h2><?php echo $last_sync ? ucfirst($last_sync['status']) : 'Never'; ?></h2>
                    <small><?php echo $last_sync ? date('M d, Y H:i', strtotime($last_sync['created_at'])) : 'No sync yet'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Records Synced</h5>
                    <h2><?php echo $last_sync ? number_format($last_sync['rows_synced']) : '0'; ?></h2>
                    <small>Last sync operation</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Sync Frequency</h5>
                    <h2>Hourly</h2>
                    <small>Automated via cron job</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h5 class="card-title">Next Scheduled Sync</h5>
                    <h2><?php 
                        if ($last_sync) {
                            $next_sync = strtotime($last_sync['created_at']) + 3600; // +1 hour
                            echo date('H:i', $next_sync);
                        } else {
                            echo 'Soon';
                        }
                    ?></h2>
                    <small><?php echo $last_sync ? date('M d', strtotime($last_sync['created_at']) + 3600) : 'Pending'; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Sync Button -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Manual Sync</h5>
        </div>
        <div class="card-body">
            <p>Click the button below to manually trigger a sync from Google Sheets. This will:</p>
            <ul>
                <li>Update existing vehicles with new data</li>
                <li>Add new vehicles, work orders, expenses, and rentals</li>
                <li>Skip duplicate records to prevent data duplication</li>
                <li>Log all changes to the sync history</li>
            </ul>
            
            <?php if (isset($permissions) && $permissions->hasPermission('turo_import', 'create')): ?>
            <form method="POST" action="" onsubmit="return confirm('Start sync now? This may take a few moments.');">
                <input type="hidden" name="action" value="sync_now">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sync"></i> Sync Now
                </button>
            </form>
            <?php else: ?>
            <p class="text-muted">You don't have permission to trigger manual sync.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Data Sources -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Connected Data Sources</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>IAM Master Workbook</td>
                            <td>Vehicles, Owners, Team</td>
                            <td><span class="badge badge-success">Connected</span></td>
                            <td>165 vehicles</td>
                        </tr>
                        <tr>
                            <td>TPA Operations</td>
                            <td>Work Orders, Rentals, Expenses</td>
                            <td><span class="badge badge-success">Connected</span></td>
                            <td>2,621 rentals</td>
                        </tr>
                        <tr>
                            <td>FLL Operations</td>
                            <td>Work Orders, Rentals, Expenses</td>
                            <td><span class="badge badge-success">Connected</span></td>
                            <td>614 rentals</td>
                        </tr>
                        <tr>
                            <td>MIA Operations</td>
                            <td>Work Orders, Rentals, Expenses</td>
                            <td><span class="badge badge-success">Connected</span></td>
                            <td>169 rentals</td>
                        </tr>
                        <tr>
                            <td>Customer Service & Accounting</td>
                            <td>Expense Refunds</td>
                            <td><span class="badge badge-success">Connected</span></td>
                            <td>608 refunds</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sync History -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Sync History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($sync_history)): ?>
            <p class="text-muted">No sync history yet. Click "Sync Now" to perform the first sync.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Records Synced</th>
                            <th>Status</th>
                            <th>Errors</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sync_history as $sync): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($sync['created_at'])); ?></td>
                            <td><?php echo number_format($sync['rows_synced']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $sync['status'] === 'success' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($sync['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sync['error_message']): ?>
                                <button class="btn btn-sm btn-warning" onclick="showErrors(<?php echo $sync['id']; ?>)">
                                    View Errors
                                </button>
                                <?php else: ?>
                                <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewSyncDetails(<?php echo $sync['id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Configuration -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Sync Configuration</h5>
        </div>
        <div class="card-body">
            <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;" style="display: flex; flex-wrap: wrap; gap: 20px;">
                <div class="col-md-6" style="flex: 1; min-width: 200px;">
                    <h6>Automated Sync</h6>
                    <p><strong>Schedule:</strong> Every hour (at :00)</p>
                    <p><strong>Method:</strong> Cron job</p>
                    <p><strong>Script:</strong> <code>/var/www/admin.infiniteautorentals.com/scripts/sync_google_sheets.php</code></p>
                </div>
                <div class="col-md-6" style="flex: 1; min-width: 200px;">
                    <h6>Authentication</h6>
                    <p><strong>Method:</strong> Google Service Account</p>
                    <p><strong>Account:</strong> djini-sheets@cryptodjinni.iam.gserviceaccount.com</p>
                    <p><strong>Permissions:</strong> Read-only access to shared spreadsheets</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sync Errors</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="errorContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
function showErrors(syncId) {
    $('#errorModal').modal('show');
    $('#errorContent').html('Loading...');
    
    $.get('?page=turo_import&action=get_errors&id=' + syncId, function(data) {
        $('#errorContent').html('<pre>' + data + '</pre>');
    });
}

function viewSyncDetails(syncId) {
    alert('Sync details view - to be implemented');
}
</script>

<?php
// Handle AJAX request for error log
if (isset($_GET['action']) && $_GET['action'] == 'get_errors' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT error_message FROM sync_log WHERE id = ?");
    $stmt->execute([$id]);
    $sync = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sync && $sync['error_message']) {
        echo htmlspecialchars($sync['error_message']);
    } else {
        echo 'No errors found.';
    }
    exit;
}
?>
