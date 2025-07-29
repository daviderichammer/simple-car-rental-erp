<div class="page-header">
    <h2>Dashboard</h2>
    <p>Overview of your car rental business</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Vehicles</h3>
        <div class="number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM vehicles");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    <div class="stat-card">
        <h3>Available Vehicles</h3>
        <div class="number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    <div class="stat-card">
        <h3>Active Reservations</h3>
        <div class="number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('confirmed', 'pending')");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
    <div class="stat-card">
        <h3>Pending Maintenance</h3>
        <div class="number">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_schedules WHERE status = 'pending'");
            echo $stmt->fetchColumn();
            ?>
        </div>
    </div>
</div>

<div class="data-table">
    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Recent Activity</h3>
    <table>
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
                SELECT r.start_date, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       CONCAT(v.make, ' ', v.model) as vehicle_name, r.status, r.total_amount
                FROM reservations r
                JOIN customers c ON r.customer_id = c.id
                JOIN vehicles v ON r.vehicle_id = v.id
                ORDER BY r.start_date DESC
                LIMIT 5
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . date('M j, Y', strtotime($row['start_date'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>$" . number_format($row['total_amount'], 2) . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

