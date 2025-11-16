<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
    <h2>📜 Audit Logs</h2>
    <p>Track all system activities and changes</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="exportToCSV()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;">📥 Export to CSV</button>
    </div>
</div>

<!-- Filters -->
<div class="filters-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h3 style="margin-top: 0;">Filter Audit Logs</h3>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="audit_logs">
        
        <div class="form-group" style="margin: 0;">
            <label for="user">User</label>
            <select id="user" name="user">
                <option value="">All Users</option>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT username FROM audit_logs ORDER BY username");
                while ($row = $stmt->fetch()) {
                    $selected = (isset($_GET['user']) && $_GET['user'] === $row['username']) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($row['username']) . "\" $selected>" . htmlspecialchars($row['username']) . "</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All Actions</option>
                <option value="CREATE" <?php echo (isset($_GET['action']) && $_GET['action'] === 'CREATE') ? 'selected' : ''; ?>>Create</option>
                <option value="UPDATE" <?php echo (isset($_GET['action']) && $_GET['action'] === 'UPDATE') ? 'selected' : ''; ?>>Update</option>
                <option value="DELETE" <?php echo (isset($_GET['action']) && $_GET['action'] === 'DELETE') ? 'selected' : ''; ?>>Delete</option>
                <option value="BULK_DELETE" <?php echo (isset($_GET['action']) && $_GET['action'] === 'BULK_DELETE') ? 'selected' : ''; ?>>Bulk Delete</option>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label for="table_name">Table</label>
            <select id="table_name" name="table_name">
                <option value="">All Tables</option>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name");
                while ($row = $stmt->fetch()) {
                    $selected = (isset($_GET['table_name']) && $_GET['table_name'] === $row['table_name']) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($row['table_name']) . "\" $selected>" . htmlspecialchars($row['table_name']) . "</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label for="date_from">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label for="date_to">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-primary" style="margin: 0;">Apply Filters</button>
            <a href="?page=audit_logs" class="btn-secondary" style="margin: 0; text-decoration: none; display: inline-block; padding: 0.5rem 1rem;">Clear</a>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <?php
    // Get statistics
    $sql = "SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        SUM(CASE WHEN action = 'CREATE' THEN 1 ELSE 0 END) as creates,
        SUM(CASE WHEN action = 'UPDATE' THEN 1 ELSE 0 END) as updates,
        SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) as deletes,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
    FROM audit_logs";
    
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch();
    ?>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; opacity: 0.9;">Total Logs</div>
        <div style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo number_format($stats['total_logs']); ?></div>
        <div style="font-size: 0.875rem; opacity: 0.9;"><?php echo number_format($stats['today_count']); ?> today</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; opacity: 0.9;">Unique Users</div>
        <div style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo number_format($stats['unique_users']); ?></div>
        <div style="font-size: 0.875rem; opacity: 0.9;">Active users</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; opacity: 0.9;">Creates</div>
        <div style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo number_format($stats['creates']); ?></div>
        <div style="font-size: 0.875rem; opacity: 0.9;">New records</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; opacity: 0.9;">Updates</div>
        <div style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo number_format($stats['updates']); ?></div>
        <div style="font-size: 0.875rem; opacity: 0.9;">Modified records</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; opacity: 0.9;">Deletes</div>
        <div style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo number_format($stats['deletes']); ?></div>
        <div style="font-size: 0.875rem; opacity: 0.9;">Removed records</div>
    </div>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        Audit Log History
        <?php
        // Count filtered results
        $sql = "SELECT COUNT(*) as count FROM audit_logs WHERE 1=1";
        $params = [];
        
        if (!empty($_GET['user'])) {
            $sql .= " AND username = ?";
            $params[] = $_GET['user'];
        }
        if (!empty($_GET['action'])) {
            $sql .= " AND action = ?";
            $params[] = $_GET['action'];
        }
        if (!empty($_GET['table_name'])) {
            $sql .= " AND table_name = ?";
            $params[] = $_GET['table_name'];
        }
        if (!empty($_GET['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetch()['count'];
        echo " (" . number_format($count) . " records)";
        ?>
    </h3>
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>IP Address</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Build query with filters
            $sql = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];
            
            if (!empty($_GET['user'])) {
                $sql .= " AND username = ?";
                $params[] = $_GET['user'];
            }
            if (!empty($_GET['action'])) {
                $sql .= " AND action = ?";
                $params[] = $_GET['action'];
            }
            if (!empty($_GET['table_name'])) {
                $sql .= " AND table_name = ?";
                $params[] = $_GET['table_name'];
            }
            if (!empty($_GET['date_from'])) {
                $sql .= " AND DATE(created_at) >= ?";
                $params[] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to'])) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $_GET['date_to'];
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT 100";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            while ($row = $stmt->fetch()) {
                $actionColor = '';
                switch($row['action']) {
                    case 'CREATE': $actionColor = '#28a745'; break;
                    case 'UPDATE': $actionColor = '#ffc107'; break;
                    case 'DELETE': $actionColor = '#dc3545'; break;
                    case 'BULK_DELETE': $actionColor = '#dc3545'; break;
                    default: $actionColor = '#6c757d';
                }
                
                echo "<tr>";
                echo "<td>" . date('Y-m-d H:i:s', strtotime($row['created_at'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td><span style='background: $actionColor; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.875rem;'>" . htmlspecialchars($row['action']) . "</span></td>";
                echo "<td>" . htmlspecialchars($row['table_name']) . "</td>";
                echo "<td>" . ($row['record_id'] ? htmlspecialchars($row['record_id']) : '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
                echo "<td><button onclick='showDetails(" . $row['id'] . ")' style='background: #007bff; color: white; border: none; padding: 0.25rem 0.75rem; border-radius: 3px; cursor: pointer;'>View</button></td>";
                echo "</tr>";
            }
            
            if ($count == 0) {
                echo "<tr><td colspan='7' style='text-align: center; padding: 2rem; color: #6c757d;'>No audit logs found</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>📋 Audit Log Details</h3>
            <button onclick="closeDetailsModal()" class="close-btn">×</button>
        </div>
        <div id="detailsContent" style="padding: 1.5rem;">
            Loading...
        </div>
        <div class="modal-footer">
            <button onclick="closeDetailsModal()" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
function showDetails(logId) {
    document.getElementById('detailsModal').style.display = 'flex';
    
    fetch('index.php?ajax=1&action=get_audit_log&id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const log = data.log;
                let html = '<div style="display: grid; gap: 1rem;">';
                
                html += '<div><strong>Timestamp:</strong> ' + log.created_at + '</div>';
                html += '<div><strong>User:</strong> ' + log.username + '</div>';
                html += '<div><strong>Action:</strong> <span style="background: ' + getActionColor(log.action) + '; color: white; padding: 0.25rem 0.5rem; border-radius: 3px;">' + log.action + '</span></div>';
                html += '<div><strong>Table:</strong> ' + log.table_name + '</div>';
                html += '<div><strong>Record ID:</strong> ' + (log.record_id || '-') + '</div>';
                html += '<div><strong>IP Address:</strong> ' + log.ip_address + '</div>';
                html += '<div><strong>User Agent:</strong> ' + log.user_agent + '</div>';
                
                if (log.old_values) {
                    html += '<div><strong>Old Values:</strong><pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow-x: auto;">' + JSON.stringify(JSON.parse(log.old_values), null, 2) + '</pre></div>';
                }
                
                if (log.new_values) {
                    html += '<div><strong>New Values:</strong><pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow-x: auto;">' + JSON.stringify(JSON.parse(log.new_values), null, 2) + '</pre></div>';
                }
                
                html += '</div>';
                document.getElementById('detailsContent').innerHTML = html;
            } else {
                document.getElementById('detailsContent').innerHTML = '<div style="color: #dc3545;">Failed to load details</div>';
            }
        })
        .catch(error => {
            document.getElementById('detailsContent').innerHTML = '<div style="color: #dc3545;">Error loading details</div>';
        });
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function getActionColor(action) {
    switch(action) {
        case 'CREATE': return '#28a745';
        case 'UPDATE': return '#ffc107';
        case 'DELETE': return '#dc3545';
        case 'BULK_DELETE': return '#dc3545';
        default: return '#6c757d';
    }
}

function exportToCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}
</script>

<?php
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'User', 'Action', 'Table', 'Record ID', 'IP Address', 'User Agent']);
    
    // Build query with filters (same as above)
    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    
    if (!empty($_GET['user'])) {
        $sql .= " AND username = ?";
        $params[] = $_GET['user'];
    }
    if (!empty($_GET['action'])) {
        $sql .= " AND action = ?";
        $params[] = $_GET['action'];
    }
    if (!empty($_GET['table_name'])) {
        $sql .= " AND table_name = ?";
        $params[] = $_GET['table_name'];
    }
    if (!empty($_GET['date_from'])) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $_GET['date_to'];
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['created_at'],
            $row['username'],
            $row['action'],
            $row['table_name'],
            $row['record_id'],
            $row['ip_address'],
            $row['user_agent']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
