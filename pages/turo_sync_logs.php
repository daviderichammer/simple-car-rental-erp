<?php
/**
 * Turo Sync Logs - Real-time monitoring dashboard
 * Displays logs from Turo integration services
 */

// Server credentials
$server_host = getenv('SLICIE_IP_ADDRESS');
$server_user = 'root';
$server_password = getenv('SLICIE_PASSWORD');
$mysql_password = getenv('SLICIE_MYSQL_ROOT_PASSWORD');

// Function to execute MySQL query via SSH
function executeRemoteQuery($query) {
    global $server_host, $server_user, $server_password, $mysql_password;
    
    $escaped_query = str_replace('"', '\\"', $query);
    $command = "mysql -u root -p'$mysql_password' car_rental_erp -e \"$escaped_query\" 2>/dev/null";
    $ssh_command = "sshpass -p '$server_password' ssh -o StrictHostKeyChecking=no $server_user@$server_host \"$command\" 2>&1";
    
    exec($ssh_command, $output, $return_code);
    
    if ($return_code !== 0) {
        return [];
    }
    
    // Parse tab-separated output
    $result = [];
    if (count($output) > 1) {
        $headers = explode("\t", $output[0]);
        for ($i = 1; $i < count($output); $i++) {
            $values = explode("\t", $output[$i]);
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }
            $result[] = $row;
        }
    }
    
    return $result;
}

// Get statistics
$stats = [];

// Today's reservations from Turo email
$result = executeRemoteQuery("SELECT COUNT(*) as count FROM reservations WHERE data_source = 'turo_email' AND DATE(updated_at) = CURDATE()");
$stats['reservations_today'] = $result[0]['count'] ?? 0;

// Pending queue tasks
$result = executeRemoteQuery("SELECT task_type, COUNT(*) as count FROM turo_sync_queue WHERE status = 'pending' GROUP BY task_type");
$stats['pending_tasks'] = $result;

// Recent CSV imports
$result = executeRemoteQuery("SELECT * FROM turo_csv_imports ORDER BY import_date DESC LIMIT 5");
$stats['recent_imports'] = $result;

// Total emails processed today
$result = executeRemoteQuery("SELECT COUNT(*) as count FROM reservations WHERE data_source = 'turo_email' AND DATE(created_at) = CURDATE()");
$stats['emails_today'] = $result[0]['count'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turo Sync Logs - Real-time Monitoring</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #718096;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #718096;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .stat-label {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 5px;
        }
        
        .service-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.running {
            background: #48bb78;
            box-shadow: 0 0 10px rgba(72, 187, 120, 0.5);
        }
        
        .status-indicator.stopped {
            background: #f56565;
            box-shadow: 0 0 10px rgba(245, 101, 101, 0.5);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .log-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .log-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .log-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .log-container {
            background: #1a202c;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .log-line {
            margin-bottom: 5px;
        }
        
        .log-line.error {
            color: #fc8181;
            font-weight: 500;
        }
        
        .log-line.warning {
            color: #f6ad55;
        }
        
        .log-line.info {
            color: #63b3ed;
        }
        
        .log-line.success {
            color: #68d391;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #f7fafc;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .auto-refresh label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #48bb78;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .refresh-countdown {
            font-size: 12px;
            color: #718096;
        }
        
        .pending-tasks {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .task-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f7fafc;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .task-type {
            color: #4a5568;
            font-weight: 500;
        }
        
        .task-count {
            color: #667eea;
            font-weight: 600;
        }
        
        .recent-imports {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .import-item {
            padding: 10px;
            background: #f7fafc;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .import-date {
            color: #718096;
            font-size: 11px;
        }
        
        .import-stats {
            margin-top: 5px;
            color: #4a5568;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚗 Turo Sync Logs - Real-time Monitoring</h1>
            <p>Monitor Turo integration services, email processing, and CSV imports in real-time</p>
        </div>
        
        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📧 Emails Processed Today</h3>
                <div class="stat-value"><?= number_format($stats['emails_today']) ?></div>
                <div class="stat-label">New reservations created</div>
            </div>
            
            <div class="stat-card">
                <h3>📅 Reservations Updated</h3>
                <div class="stat-value"><?= number_format($stats['reservations_today']) ?></div>
                <div class="stat-label">From Turo emails today</div>
            </div>
            
            <div class="stat-card">
                <h3>⏳ Pending Tasks</h3>
                <div class="stat-value"><?= array_sum(array_column($stats['pending_tasks'], 'count')) ?></div>
                <div class="stat-label">In sync queue</div>
            </div>
            
            <div class="stat-card">
                <h3>🟢 Service Status</h3>
                <div class="service-status">
                    <div class="status-indicator running" id="serviceStatus"></div>
                    <span id="serviceStatusText">Checking...</span>
                </div>
                <div class="stat-label" id="serviceUptime">Loading uptime...</div>
            </div>
        </div>
        
        <!-- Pending Tasks Detail -->
        <?php if (!empty($stats['pending_tasks'])): ?>
        <div class="log-section">
            <div class="log-header">
                <div class="log-title">⏳ Pending Queue Tasks</div>
            </div>
            <div class="pending-tasks">
                <?php foreach ($stats['pending_tasks'] as $task): ?>
                <div class="task-item">
                    <span class="task-type"><?= htmlspecialchars($task['task_type']) ?></span>
                    <span class="task-count"><?= $task['count'] ?> pending</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent CSV Imports -->
        <?php if (!empty($stats['recent_imports'])): ?>
        <div class="log-section">
            <div class="log-header">
                <div class="log-title">📊 Recent CSV Imports</div>
            </div>
            <div class="recent-imports">
                <?php foreach ($stats['recent_imports'] as $import): ?>
                <div class="import-item">
                    <div class="import-date"><?= htmlspecialchars($import['import_date'] ?? 'N/A') ?></div>
                    <div class="import-stats">
                        <?= htmlspecialchars($import['records_imported'] ?? '0') ?> records imported
                        <?php if (!empty($import['errors'])): ?>
                        | <span style="color: #f56565;"><?= htmlspecialchars($import['errors']) ?> errors</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Auto-refresh Control -->
        <div class="auto-refresh">
            <label>Auto-refresh:</label>
            <label class="toggle-switch">
                <input type="checkbox" id="autoRefreshToggle" checked>
                <span class="slider"></span>
            </label>
            <select id="refreshInterval">
                <option value="10">Every 10 seconds</option>
                <option value="30" selected>Every 30 seconds</option>
                <option value="60">Every 60 seconds</option>
            </select>
            <span class="refresh-countdown">Next refresh in <span id="countdown">30</span>s</span>
            <div class="loading" id="loadingIndicator" style="display: none;"></div>
        </div>
        
        <!-- Email Ingestion Log -->
        <div class="log-section">
            <div class="log-header">
                <div class="log-title">📧 Email Ingestion Service Log</div>
                <div class="log-controls">
                    <select id="emailLogFilter">
                        <option value="all">All Levels</option>
                        <option value="error">Errors Only</option>
                        <option value="warning">Warnings Only</option>
                        <option value="info">Info Only</option>
                    </select>
                    <select id="emailLogLines">
                        <option value="50">Last 50 lines</option>
                        <option value="100" selected>Last 100 lines</option>
                        <option value="200">Last 200 lines</option>
                    </select>
                    <button class="btn btn-primary" onclick="refreshEmailLog()">🔄 Refresh</button>
                    <button class="btn btn-secondary" onclick="downloadLog('email')">💾 Download Full Log</button>
                </div>
            </div>
            <div class="log-container" id="emailLogContainer">
                <div class="log-line">Loading email ingestion logs...</div>
            </div>
        </div>
        
        <!-- CSV Import Log -->
        <div class="log-section">
            <div class="log-header">
                <div class="log-title">📊 CSV Import Log</div>
                <div class="log-controls">
                    <select id="csvLogFilter">
                        <option value="all">All Levels</option>
                        <option value="error">Errors Only</option>
                        <option value="warning">Warnings Only</option>
                        <option value="info">Info Only</option>
                    </select>
                    <select id="csvLogLines">
                        <option value="50">Last 50 lines</option>
                        <option value="100" selected>Last 100 lines</option>
                        <option value="200">Last 200 lines</option>
                    </select>
                    <button class="btn btn-primary" onclick="refreshCsvLog()">🔄 Refresh</button>
                    <button class="btn btn-secondary" onclick="downloadLog('csv')">💾 Download Full Log</button>
                </div>
            </div>
            <div class="log-container" id="csvLogContainer">
                <div class="log-line">Loading CSV import logs...</div>
            </div>
        </div>
    </div>
    
    <script>
        let autoRefreshEnabled = true;
        let refreshInterval = 30;
        let countdownTimer;
        let countdownSeconds = refreshInterval;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            refreshAllLogs();
            checkServiceStatus();
            startCountdown();
            
            // Auto-refresh toggle
            document.getElementById('autoRefreshToggle').addEventListener('change', function() {
                autoRefreshEnabled = this.checked;
                if (autoRefreshEnabled) {
                    startCountdown();
                } else {
                    stopCountdown();
                }
            });
            
            // Refresh interval change
            document.getElementById('refreshInterval').addEventListener('change', function() {
                refreshInterval = parseInt(this.value);
                countdownSeconds = refreshInterval;
                if (autoRefreshEnabled) {
                    stopCountdown();
                    startCountdown();
                }
            });
        });
        
        function startCountdown() {
            countdownSeconds = refreshInterval;
            countdownTimer = setInterval(function() {
                countdownSeconds--;
                document.getElementById('countdown').textContent = countdownSeconds;
                
                if (countdownSeconds <= 0) {
                    location.reload(); // Reload page to refresh statistics
                    countdownSeconds = refreshInterval;
                }
            }, 1000);
        }
        
        function stopCountdown() {
            if (countdownTimer) {
                clearInterval(countdownTimer);
            }
        }
        
        function refreshAllLogs() {
            if (!autoRefreshEnabled) return;
            
            document.getElementById('loadingIndicator').style.display = 'inline-block';
            refreshEmailLog();
            refreshCsvLog();
            checkServiceStatus();
            
            setTimeout(() => {
                document.getElementById('loadingIndicator').style.display = 'none';
            }, 1000);
        }
        
        function refreshEmailLog() {
            const lines = document.getElementById('emailLogLines').value;
            const filter = document.getElementById('emailLogFilter').value;
            
            fetch(`pages/turo_sync_logs_api.php?action=get_email_log&lines=${lines}&filter=${filter}`)
                .then(response => response.json())
                .then(data => {
                    displayLog('emailLogContainer', data.log);
                })
                .catch(error => {
                    document.getElementById('emailLogContainer').innerHTML = 
                        '<div class="log-line error">Error loading log: ' + error.message + '</div>';
                });
        }
        
        function refreshCsvLog() {
            const lines = document.getElementById('csvLogLines').value;
            const filter = document.getElementById('csvLogFilter').value;
            
            fetch(`pages/turo_sync_logs_api.php?action=get_csv_log&lines=${lines}&filter=${filter}`)
                .then(response => response.json())
                .then(data => {
                    displayLog('csvLogContainer', data.log);
                })
                .catch(error => {
                    document.getElementById('csvLogContainer').innerHTML = 
                        '<div class="log-line error">Error loading log: ' + error.message + '</div>';
                });
        }
        
        function checkServiceStatus() {
            fetch('pages/turo_sync_logs_api.php?action=get_service_status')
                .then(response => response.json())
                .then(data => {
                    const indicator = document.getElementById('serviceStatus');
                    const statusText = document.getElementById('serviceStatusText');
                    const uptime = document.getElementById('serviceUptime');
                    
                    if (data.running) {
                        indicator.className = 'status-indicator running';
                        statusText.textContent = 'Running';
                        uptime.textContent = 'Uptime: ' + data.uptime;
                    } else {
                        indicator.className = 'status-indicator stopped';
                        statusText.textContent = 'Stopped';
                        uptime.textContent = 'Service is not running';
                    }
                })
                .catch(error => {
                    console.error('Error checking service status:', error);
                });
        }
        
        function displayLog(containerId, logLines) {
            const container = document.getElementById(containerId);
            if (!logLines || logLines.length === 0) {
                container.innerHTML = '<div class="log-line">No log entries found</div>';
                return;
            }
            
            let html = '';
            logLines.forEach(line => {
                const className = getLogLineClass(line);
                html += `<div class="log-line ${className}">${escapeHtml(line)}</div>`;
            });
            container.innerHTML = html;
            
            // Auto-scroll to bottom
            container.scrollTop = container.scrollHeight;
        }
        
        function getLogLineClass(line) {
            if (line.includes('ERROR') || line.includes('Error') || line.includes('CRITICAL')) {
                return 'error';
            } else if (line.includes('WARNING') || line.includes('Warning')) {
                return 'warning';
            } else if (line.includes('INFO') || line.includes('Info')) {
                return 'info';
            } else if (line.includes('SUCCESS') || line.includes('Success') || line.includes('Created')) {
                return 'success';
            }
            return '';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function downloadLog(type) {
            window.location.href = `pages/turo_sync_logs_api.php?action=download_log&type=${type}`;
        }
    </script>
</body>
</html>
