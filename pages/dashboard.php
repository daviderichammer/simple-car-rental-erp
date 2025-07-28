<?php
// Dashboard Page Content
?>

<div class="page-header">
    <h2>Dashboard</h2>
    <p>Your permissions for this page: <?php echo implode(', ', $permissions['dashboard'] ?? ['View']); ?></p>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <h3>Total Vehicles</h3>
        <div class="stat-number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM vehicles");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <h3>Available Vehicles</h3>
        <div class="stat-number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <h3>Active Reservations</h3>
        <div class="stat-number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed'");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <h3>Pending Maintenance</h3>
        <div class="stat-number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_schedules WHERE status = 'scheduled'");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
</div>

<div class="recent-activity">
    <h3>Recent Activity</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Status</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT r.start_date, 
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       CONCAT(v.make, ' ', v.model) as vehicle_name,
                       r.status,
                       r.total_amount
                FROM reservations r
                JOIN customers c ON r.customer_id = c.id
                JOIN vehicles v ON r.vehicle_id = v.id
                ORDER BY r.start_date DESC
                LIMIT 5
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . date('M j, Y', strtotime($row['start_date'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td><span class='status-" . $row['status'] . "'>" . ucfirst($row['status']) . "</span></td>";
                echo "<td>$" . number_format($row['total_amount'], 2) . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.recent-activity {
    margin-top: 30px;
}

.recent-activity h3 {
    margin-bottom: 15px;
    color: #333;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}
</style>

