<?php
/**
 * Turo Sync Logs API - Local file reading
 * Reads logs directly from local filesystem
 */

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Log file paths
$email_log_file = '/var/www/admin.infiniteautorentals.com/turo_logs/email_ingestion.log';
$csv_log_file = '/var/www/admin.infiniteautorentals.com/turo_logs/batch_scraping.log';

// Handle different actions
switch ($action) {
    case 'get_email_log':
        getLogContent($email_log_file);
        break;
        
    case 'get_csv_log':
        getLogContent($csv_log_file);
        break;
        
    case 'get_service_status':
        getServiceStatus();
        break;
        
    case 'download_log':
        downloadLog();
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getLogContent($log_file) {
    $lines = intval($_GET['lines'] ?? 100);
    $filter = $_GET['filter'] ?? 'all';
    
    if (!file_exists($log_file)) {
        echo json_encode([
            'error' => 'Log file not found',
            'log' => ["Log file does not exist: $log_file"]
        ]);
        return;
    }
    
    if (!is_readable($log_file)) {
        echo json_encode([
            'error' => 'Log file not readable',
            'log' => ["Cannot read log file (permission denied): $log_file"]
        ]);
        return;
    }
    
    // Read last N lines using tail command
    $command = "tail -n $lines " . escapeshellarg($log_file) . " 2>&1";
    exec($command, $output, $return_code);
    
    if ($return_code !== 0) {
        echo json_encode([
            'error' => 'Failed to read log',
            'log' => ["Error reading log file: " . implode("\n", $output)]
        ]);
        return;
    }
    
    // Filter logs if requested
    if ($filter !== 'all') {
        $filtered = [];
        foreach ($output as $line) {
            if (stripos($line, $filter) !== false) {
                $filtered[] = $line;
            }
        }
        $output = $filtered;
    }
    
    echo json_encode([
        'success' => true,
        'log' => $output,
        'total_lines' => count($output)
    ]);
}

function getServiceStatus() {
    // Check if service is running
    $command = "systemctl is-active turo-email-ingestion.service 2>&1";
    exec($command, $output, $return_code);
    
    $is_running = ($return_code === 0 && trim($output[0]) === 'active');
    
    // Get uptime if running
    $uptime = 'Unknown';
    if ($is_running) {
        $command = "systemctl show turo-email-ingestion.service --property=ActiveEnterTimestamp --value 2>&1";
        exec($command, $uptime_output);
        if (!empty($uptime_output[0])) {
            $start_time = strtotime($uptime_output[0]);
            if ($start_time) {
                $seconds = time() - $start_time;
                $uptime = formatUptime($seconds);
            }
        }
    }
    
    echo json_encode([
        'running' => $is_running,
        'uptime' => $uptime,
        'status' => $is_running ? 'active' : 'inactive'
    ]);
}

function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";
    
    return !empty($parts) ? implode(' ', $parts) : '< 1m';
}

function downloadLog() {
    $type = $_GET['type'] ?? 'email';
    
    if ($type === 'email') {
        $log_file = '/var/www/admin.infiniteautorentals.com/turo_logs/email_ingestion.log';
        $filename = 'email_ingestion_' . date('Y-m-d_H-i-s') . '.log';
    } else {
        $log_file = '/var/www/admin.infiniteautorentals.com/turo_logs/batch_scraping.log';
        $filename = 'batch_scraping_' . date('Y-m-d_H-i-s') . '.log';
    }
    
    if (!file_exists($log_file)) {
        header('Content-Type: text/plain');
        echo "Error: Log file not found";
        exit;
    }
    
    // Set headers for file download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($log_file));
    
    // Output file content
    readfile($log_file);
    exit;
}
