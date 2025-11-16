<div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
    <h1 style="margin: 0; font-size: 2rem;">📊 Turo Sync Monitoring Dashboard</h1>
    <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Real-time monitoring of Turo data synchronization service</p>
    <div style="margin-top: 1rem; font-size: 0.9rem;">
        <span id="last-refresh">Last refreshed: <strong>--:--:--</strong></span>
        <span style="margin-left: 2rem;">Auto-refresh: <strong id="auto-refresh-status">ON</strong></span>
    </div>
</div>

<!-- Service Status Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Service Status -->
    <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Service Status</div>
                <div style="font-size: 1.5rem; font-weight: bold;" id="service-status">
                    <span style="color: #10b981;">● Running</span>
                </div>
            </div>
            <div style="font-size: 2.5rem;">⚡</div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.85rem; color: #666;" id="service-uptime">Uptime: --</div>
    </div>

    <!-- Queue Progress -->
    <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: between;">
            <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Queue Progress</div>
                <div style="font-size: 1.5rem; font-weight: bold;" id="queue-progress">0 / 0</div>
            </div>
            <div style="font-size: 2.5rem;">📋</div>
        </div>
        <div style="margin-top: 1rem;">
            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div id="queue-progress-bar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s;"></div>
            </div>
        </div>
    </div>

    <!-- Success Rate -->
    <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Success Rate (24h)</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #10b981;" id="success-rate">--%</div>
            </div>
            <div style="font-size: 2.5rem;">✅</div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.85rem; color: #666;" id="success-count">-- successful / -- total</div>
    </div>

    <!-- Last Sync -->
    <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Last Successful Sync</div>
                <div style="font-size: 1.2rem; font-weight: bold;" id="last-sync-time">--</div>
            </div>
            <div style="font-size: 2.5rem;">🕐</div>
        </div>
        <div style="margin-top: 1rem; font-size: 0.85rem; color: #666;" id="last-sync-details">--</div>
    </div>
</div>

<!-- Data Quality Metrics -->
<div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <h2 style="margin: 0 0 1.5rem 0; font-size: 1.3rem;">📈 Data Quality Metrics</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div>
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Reservations Synced</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #667eea;" id="reservations-synced">--</div>
            <div style="font-size: 0.85rem; color: #10b981; margin-top: 0.25rem;" id="reservations-change">+-- today</div>
        </div>
        <div>
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Vehicles Tracked</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #667eea;" id="vehicles-tracked">--</div>
            <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;" id="vehicles-active">-- active</div>
        </div>
        <div>
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Data Completeness</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #10b981;" id="data-completeness">--%</div>
            <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">All required fields</div>
        </div>
        <div>
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Failed Tasks</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #ef4444;" id="failed-tasks-count">--</div>
            <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;" id="failed-tasks-time">Last 24 hours</div>
        </div>
    </div>
</div>

<!-- Recent Scrapes Table -->
<div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0; font-size: 1.3rem;">📝 Recent Scraping Operations</h2>
        <button onclick="refreshData()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
            🔄 Refresh Now
        </button>
    </div>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Timestamp</th>
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Operation</th>
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Vehicle</th>
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Status</th>
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Duration</th>
                    <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151;">Details</th>
                </tr>
            </thead>
            <tbody id="recent-scrapes-tbody">
                <tr>
                    <td colspan="6" style="padding: 2rem; text-align: center; color: #9ca3af;">
                        Loading recent scraping operations...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Failed Tasks Section -->
<div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h2 style="margin: 0 0 1.5rem 0; font-size: 1.3rem; color: #ef4444;">⚠️ Failed Tasks</h2>
    <div id="failed-tasks-container">
        <div style="padding: 2rem; text-align: center; color: #9ca3af;">
            Loading failed tasks...
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;
let autoRefreshEnabled = true;

// Format timestamp
function formatTimestamp(timestamp) {
    if (!timestamp) return '--';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds
    
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleString();
}

// Format duration
function formatDuration(seconds) {
    if (!seconds) return '--';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
}

// Refresh dashboard data
function refreshData() {
    const now = new Date();
    document.getElementById('last-refresh').innerHTML = `Last refreshed: <strong>${now.toLocaleTimeString()}</strong>`;
    
    // Fetch dashboard data from API
    fetch('index.php?ajax=1&action=get_turo_sync_status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboard(data);
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
        });
}

// Update dashboard with data
function updateDashboard(data) {
    // Service Status
    if (data.service_status) {
        const status = data.service_status.running ? 
            '<span style="color: #10b981;">● Running</span>' : 
            '<span style="color: #ef4444;">● Stopped</span>';
        document.getElementById('service-status').innerHTML = status;
        document.getElementById('service-uptime').textContent = `Uptime: ${data.service_status.uptime || '--'}`;
    }
    
    // Queue Progress
    if (data.queue) {
        const completed = data.queue.completed || 0;
        const total = data.queue.total || 0;
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        
        document.getElementById('queue-progress').textContent = `${completed} / ${total}`;
        document.getElementById('queue-progress-bar').style.width = `${percentage}%`;
    }
    
    // Success Rate
    if (data.metrics) {
        const successRate = data.metrics.success_rate || 0;
        const successful = data.metrics.successful || 0;
        const total = data.metrics.total || 0;
        
        document.getElementById('success-rate').textContent = `${successRate}%`;
        document.getElementById('success-count').textContent = `${successful} successful / ${total} total`;
    }
    
    // Last Sync
    if (data.last_sync) {
        document.getElementById('last-sync-time').textContent = formatTimestamp(data.last_sync.timestamp);
        document.getElementById('last-sync-details').textContent = data.last_sync.details || '--';
    }
    
    // Data Quality Metrics
    if (data.data_quality) {
        document.getElementById('reservations-synced').textContent = data.data_quality.reservations_synced || '--';
        document.getElementById('reservations-change').textContent = `+${data.data_quality.reservations_today || 0} today`;
        document.getElementById('vehicles-tracked').textContent = data.data_quality.vehicles_tracked || '--';
        document.getElementById('vehicles-active').textContent = `${data.data_quality.vehicles_active || 0} active`;
        document.getElementById('data-completeness').textContent = `${data.data_quality.completeness || 0}%`;
        document.getElementById('failed-tasks-count').textContent = data.data_quality.failed_tasks || '--';
    }
    
    // Recent Scrapes
    if (data.recent_scrapes && data.recent_scrapes.length > 0) {
        const tbody = document.getElementById('recent-scrapes-tbody');
        tbody.innerHTML = '';
        
        data.recent_scrapes.forEach(scrape => {
            const statusColor = scrape.status === 'success' ? '#10b981' : '#ef4444';
            const statusText = scrape.status === 'success' ? '✓ Success' : '✗ Failed';
            
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid #e5e7eb';
            row.innerHTML = `
                <td style="padding: 0.75rem;">${formatTimestamp(scrape.timestamp)}</td>
                <td style="padding: 0.75rem;">${scrape.operation || '--'}</td>
                <td style="padding: 0.75rem;">${scrape.vehicle || '--'}</td>
                <td style="padding: 0.75rem; color: ${statusColor}; font-weight: 600;">${statusText}</td>
                <td style="padding: 0.75rem;">${formatDuration(scrape.duration)}</td>
                <td style="padding: 0.75rem; font-size: 0.85rem; color: #666;">${scrape.details || '--'}</td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Failed Tasks
    if (data.failed_tasks && data.failed_tasks.length > 0) {
        const container = document.getElementById('failed-tasks-container');
        container.innerHTML = '';
        
        data.failed_tasks.forEach(task => {
            const taskDiv = document.createElement('div');
            taskDiv.style.cssText = 'border: 1px solid #fee2e2; background: #fef2f2; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;';
            taskDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div style="font-weight: 600; color: #dc2626; margin-bottom: 0.5rem;">${task.operation || 'Unknown Operation'}</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.25rem;">Vehicle: ${task.vehicle || '--'}</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.25rem;">Error: ${task.error || '--'}</div>
                        <div style="font-size: 0.85rem; color: #999;">${formatTimestamp(task.timestamp)}</div>
                    </div>
                    <button onclick="retryTask(${task.id})" style="background: #667eea; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.85rem;">
                        🔄 Retry
                    </button>
                </div>
            `;
            container.appendChild(taskDiv);
        });
    } else {
        document.getElementById('failed-tasks-container').innerHTML = '<div style="padding: 2rem; text-align: center; color: #10b981;">✓ No failed tasks</div>';
    }
}

// Retry failed task
function retryTask(taskId) {
    if (!confirm('Retry this failed task?')) return;
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax=1&action=retry_turo_sync_task&task_id=${taskId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Task queued for retry');
            refreshData();
        } else {
            alert('Error: ' + (data.message || 'Failed to retry task'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while retrying the task');
    });
}

// Toggle auto-refresh
function toggleAutoRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;
    document.getElementById('auto-refresh-status').textContent = autoRefreshEnabled ? 'ON' : 'OFF';
    
    if (autoRefreshEnabled) {
        startAutoRefresh();
    } else {
        clearInterval(autoRefreshInterval);
    }
}

// Start auto-refresh
function startAutoRefresh() {
    autoRefreshInterval = setInterval(refreshData, 30000); // 30 seconds
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    refreshData();
    startAutoRefresh();
});
</script>
