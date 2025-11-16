<div class="page-header">
    <h2>Fleet Analytics Dashboard</h2>
    <p>Comprehensive analytics and insights for your rental fleet</p>
</div>

<?php
// Get fleet statistics
$fleetStats = $pdo->query("
    SELECT 
        COUNT(*) as total_vehicles,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN airport = 'TPA' THEN 1 ELSE 0 END) as tpa,
        SUM(CASE WHEN airport = 'FLL' THEN 1 ELSE 0 END) as fll,
        SUM(CASE WHEN airport = 'MIA' THEN 1 ELSE 0 END) as mia,
        SUM(CASE WHEN year >= 2025 THEN 1 ELSE 0 END) as new_vehicles,
        SUM(CASE WHEN bouncie_id IS NOT NULL AND bouncie_id != '' THEN 1 ELSE 0 END) as tracked_vehicles
    FROM vehicles
")->fetch();

// Get vehicle distribution by make
$makeDistribution = $pdo->query("
    SELECT make, COUNT(*) as count 
    FROM vehicles 
    GROUP BY make 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();

// Get work orders statistics
$workOrderStats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN location = 'TPA' THEN 1 ELSE 0 END) as tpa_orders,
        SUM(CASE WHEN location = 'FLL' THEN 1 ELSE 0 END) as fll_orders,
        SUM(CASE WHEN location = 'MIA' THEN 1 ELSE 0 END) as mia_orders
    FROM work_orders
")->fetch();

// Get expense statistics
$expenseStats = $pdo->query("
    SELECT 
        COUNT(*) as total_refunds,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount,
        SUM(CASE WHEN refund_type = 'Parking' THEN amount ELSE 0 END) as parking_total,
        SUM(CASE WHEN refund_type = 'Car Wash' THEN amount ELSE 0 END) as carwash_total,
        SUM(CASE WHEN location = 'TPA' THEN amount ELSE 0 END) as tpa_expenses,
        SUM(CASE WHEN location = 'FLL' THEN amount ELSE 0 END) as fll_expenses,
        SUM(CASE WHEN location = 'MIA' THEN amount ELSE 0 END) as mia_expenses
    FROM expense_refunds
")->fetch();

// Get repair statistics
$repairStats = $pdo->query("
    SELECT 
        COUNT(*) as total_repairs,
        SUM(cost) as total_cost,
        AVG(cost) as avg_cost
    FROM repair_history
")->fetch();

// Get work orders by location for chart
$workOrdersByLocation = [
    ['location' => 'TPA', 'count' => $workOrderStats['tpa_orders']],
    ['location' => 'FLL', 'count' => $workOrderStats['fll_orders']],
    ['location' => 'MIA', 'count' => $workOrderStats['mia_orders']]
];

// Get expenses by location for chart
$expensesByLocation = [
    ['location' => 'TPA', 'amount' => $expenseStats['tpa_expenses']],
    ['location' => 'FLL', 'amount' => $expenseStats['fll_expenses']],
    ['location' => 'MIA', 'amount' => $expenseStats['mia_expenses']]
];
?>

<!-- Key Metrics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Fleet</div>
        <div style="font-size: 2.5rem; font-weight: bold;"><?php echo number_format($fleetStats['total_vehicles']); ?></div>
        <div style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;">Vehicles across all locations</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Utilization Rate</div>
        <div style="font-size: 2.5rem; font-weight: bold;">
            <?php 
            $utilization = $fleetStats['total_vehicles'] > 0 ? 
                round(($fleetStats['rented'] / $fleetStats['total_vehicles']) * 100, 1) : 0;
            echo $utilization;
            ?>%
        </div>
        <div style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;"><?php echo $fleetStats['rented']; ?> vehicles currently rented</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Work Orders</div>
        <div style="font-size: 2.5rem; font-weight: bold;"><?php echo number_format($workOrderStats['total_orders']); ?></div>
        <div style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;"><?php echo number_format($workOrderStats['completed']); ?> completed</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Refunds</div>
        <div style="font-size: 2.5rem; font-weight: bold;">$<?php echo number_format($expenseStats['total_amount'], 0); ?></div>
        <div style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;"><?php echo number_format($expenseStats['total_refunds']); ?> transactions</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Fleet Status Chart -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Fleet Status Distribution</h3>
        <canvas id="fleetStatusChart" style="max-height: 300px;"></canvas>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem; font-size: 0.875rem;">
            <div style="padding: 0.5rem; background: #e8f5e9; border-radius: 4px;">
                <strong style="color: #28a745;">Available:</strong> <?php echo $fleetStats['available']; ?> (<?php echo $fleetStats['total_vehicles'] > 0 ? round(($fleetStats['available'] / $fleetStats['total_vehicles']) * 100, 1) : 0; ?>%)
            </div>
            <div style="padding: 0.5rem; background: #ffebee; border-radius: 4px;">
                <strong style="color: #dc3545;">Rented:</strong> <?php echo $fleetStats['rented']; ?> (<?php echo $fleetStats['total_vehicles'] > 0 ? round(($fleetStats['rented'] / $fleetStats['total_vehicles']) * 100, 1) : 0; ?>%)
            </div>
            <div style="padding: 0.5rem; background: #fff3e0; border-radius: 4px;">
                <strong style="color: #ffc107;">Maintenance:</strong> <?php echo $fleetStats['maintenance']; ?> (<?php echo $fleetStats['total_vehicles'] > 0 ? round(($fleetStats['maintenance'] / $fleetStats['total_vehicles']) * 100, 1) : 0; ?>%)
            </div>
            <div style="padding: 0.5rem; background: #f3e5f5; border-radius: 4px;">
                <strong style="color: #6f42c1;">Tracked:</strong> <?php echo $fleetStats['tracked_vehicles']; ?> (<?php echo $fleetStats['total_vehicles'] > 0 ? round(($fleetStats['tracked_vehicles'] / $fleetStats['total_vehicles']) * 100, 1) : 0; ?>%)
            </div>
        </div>
    </div>

    <!-- Location Distribution Chart -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Vehicles by Location</h3>
        <canvas id="locationChart" style="max-height: 300px;"></canvas>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; font-size: 0.875rem;">
            <div style="padding: 0.5rem; background: #e3f2fd; border-radius: 4px; text-align: center;">
                <strong style="color: #007bff;">TPA</strong><br><?php echo $fleetStats['tpa']; ?> vehicles
            </div>
            <div style="padding: 0.5rem; background: #e8f5e9; border-radius: 4px; text-align: center;">
                <strong style="color: #28a745;">FLL</strong><br><?php echo $fleetStats['fll']; ?> vehicles
            </div>
            <div style="padding: 0.5rem; background: #fff3e0; border-radius: 4px; text-align: center;">
                <strong style="color: #ffc107;">MIA</strong><br><?php echo $fleetStats['mia']; ?> vehicles
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Vehicle Make Distribution -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Top 10 Vehicle Makes</h3>
        <canvas id="makeChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Work Orders by Location -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Work Orders by Location</h3>
        <canvas id="workOrdersChart" style="max-height: 300px;"></canvas>
        <div style="margin-top: 1rem; font-size: 0.875rem; color: #666;">
            Total: <?php echo number_format($workOrderStats['total_orders']); ?> work orders | 
            <?php echo number_format($workOrderStats['completed']); ?> completed (<?php echo $workOrderStats['total_orders'] > 0 ? round(($workOrderStats['completed'] / $workOrderStats['total_orders']) * 100, 1) : 0; ?>%)
        </div>
    </div>
</div>

<!-- Charts Row 3 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Expense Breakdown -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Expense Breakdown</h3>
        <canvas id="expenseChart" style="max-height: 300px;"></canvas>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem; font-size: 0.875rem;">
            <div style="padding: 0.75rem; background: #e3f2fd; border-radius: 4px;">
                <div style="color: #666; margin-bottom: 0.25rem;">Parking Refunds</div>
                <div style="font-size: 1.25rem; font-weight: bold; color: #17a2b8;">$<?php echo number_format($expenseStats['parking_total'], 2); ?></div>
            </div>
            <div style="padding: 0.75rem; background: #e8f5e9; border-radius: 4px;">
                <div style="color: #666; margin-bottom: 0.25rem;">Car Wash Refunds</div>
                <div style="font-size: 1.25rem; font-weight: bold; color: #28a745;">$<?php echo number_format($expenseStats['carwash_total'], 2); ?></div>
            </div>
        </div>
    </div>

    <!-- Expenses by Location -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Expenses by Location</h3>
        <canvas id="expenseLocationChart" style="max-height: 300px;"></canvas>
        <div style="margin-top: 1rem; font-size: 0.875rem; color: #666;">
            Average refund: $<?php echo number_format($expenseStats['avg_amount'], 2); ?> | 
            Total transactions: <?php echo number_format($expenseStats['total_refunds']); ?>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #007bff;">
        <h4 style="margin-top: 0; color: #007bff;">Fleet Insights</h4>
        <ul style="list-style: none; padding: 0; margin: 0; line-height: 2;">
            <li>✓ <?php echo number_format($fleetStats['new_vehicles']); ?> vehicles are 2025 models</li>
            <li>✓ <?php echo number_format($fleetStats['tracked_vehicles']); ?> vehicles have Bouncie tracking</li>
            <li>✓ <?php echo number_format($fleetStats['available']); ?> vehicles available for rent</li>
            <li>✓ <?php echo number_format($fleetStats['maintenance']); ?> vehicles in maintenance</li>
        </ul>
    </div>

    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
        <h4 style="margin-top: 0; color: #28a745;">Operations Summary</h4>
        <ul style="list-style: none; padding: 0; margin: 0; line-height: 2;">
            <li>✓ <?php echo number_format($workOrderStats['total_orders']); ?> total work orders</li>
            <li>✓ <?php echo number_format($workOrderStats['completed']); ?> work orders completed</li>
            <li>✓ <?php echo number_format($repairStats['total_repairs']); ?> repair records</li>
            <li>✓ $<?php echo number_format($repairStats['total_cost'], 2); ?> in repair costs</li>
        </ul>
    </div>

    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
        <h4 style="margin-top: 0; color: #ffc107;">Financial Overview</h4>
        <ul style="list-style: none; padding: 0; margin: 0; line-height: 2;">
            <li>✓ $<?php echo number_format($expenseStats['total_amount'], 2); ?> in refunds</li>
            <li>✓ <?php echo number_format($expenseStats['total_refunds']); ?> refund transactions</li>
            <li>✓ $<?php echo number_format($expenseStats['avg_amount'], 2); ?> average refund</li>
            <li>✓ $<?php echo number_format($repairStats['avg_cost'], 2); ?> average repair cost</li>
        </ul>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Fleet Status Chart
const fleetStatusCtx = document.getElementById('fleetStatusChart').getContext('2d');
new Chart(fleetStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Available', 'Rented', 'Maintenance', 'Out of Service'],
        datasets: [{
            data: [
                <?php echo $fleetStats['available']; ?>,
                <?php echo $fleetStats['rented']; ?>,
                <?php echo $fleetStats['maintenance']; ?>,
                <?php echo $fleetStats['total_vehicles'] - $fleetStats['available'] - $fleetStats['rented'] - $fleetStats['maintenance']; ?>
            ],
            backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Location Distribution Chart
const locationCtx = document.getElementById('locationChart').getContext('2d');
new Chart(locationCtx, {
    type: 'pie',
    data: {
        labels: ['TPA (Tampa)', 'FLL (Fort Lauderdale)', 'MIA (Miami)'],
        datasets: [{
            data: [
                <?php echo $fleetStats['tpa']; ?>,
                <?php echo $fleetStats['fll']; ?>,
                <?php echo $fleetStats['mia']; ?>
            ],
            backgroundColor: ['#007bff', '#28a745', '#ffc107'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Vehicle Make Distribution Chart
const makeCtx = document.getElementById('makeChart').getContext('2d');
new Chart(makeCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($m) { return "'" . addslashes($m['make']) . "'"; }, $makeDistribution)); ?>],
        datasets: [{
            label: 'Number of Vehicles',
            data: [<?php echo implode(',', array_column($makeDistribution, 'count')); ?>],
            backgroundColor: '#007bff',
            borderColor: '#0056b3',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Work Orders by Location Chart
const workOrdersCtx = document.getElementById('workOrdersChart').getContext('2d');
new Chart(workOrdersCtx, {
    type: 'bar',
    data: {
        labels: ['TPA', 'FLL', 'MIA'],
        datasets: [{
            label: 'Work Orders',
            data: [
                <?php echo $workOrderStats['tpa_orders']; ?>,
                <?php echo $workOrderStats['fll_orders']; ?>,
                <?php echo $workOrderStats['mia_orders']; ?>
            ],
            backgroundColor: ['#007bff', '#28a745', '#ffc107'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Expense Breakdown Chart
const expenseCtx = document.getElementById('expenseChart').getContext('2d');
new Chart(expenseCtx, {
    type: 'doughnut',
    data: {
        labels: ['Parking Refunds', 'Car Wash Refunds'],
        datasets: [{
            data: [
                <?php echo $expenseStats['parking_total']; ?>,
                <?php echo $expenseStats['carwash_total']; ?>
            ],
            backgroundColor: ['#17a2b8', '#28a745'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Expenses by Location Chart
const expenseLocationCtx = document.getElementById('expenseLocationChart').getContext('2d');
new Chart(expenseLocationCtx, {
    type: 'bar',
    data: {
        labels: ['TPA', 'FLL', 'MIA'],
        datasets: [{
            label: 'Total Expenses ($)',
            data: [
                <?php echo $expenseStats['tpa_expenses']; ?>,
                <?php echo $expenseStats['fll_expenses']; ?>,
                <?php echo $expenseStats['mia_expenses']; ?>
            ],
            backgroundColor: ['#007bff', '#28a745', '#ffc107'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '$' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
