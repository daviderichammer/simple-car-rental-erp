<?php
// Turo Daily Metrics page
if (!isset($pdo)) {
    die("Database connection not available");
}

// Get statistics
$stats = $pdo->query("SELECT 
    COUNT(*) as total_days,
    AVG(CAST(REPLACE(cancellation_rate, '%', '') AS DECIMAL(5,2))) as avg_cancellation,
    AVG(CAST(REPLACE(five_star_rate, '%', '') AS DECIMAL(5,2))) as avg_five_star,
    AVG(CAST(REPLACE(maintenance_rate, '%', '') AS DECIMAL(5,2))) as avg_maintenance,
    AVG(CAST(REPLACE(cleanliness_rate, '%', '') AS DECIMAL(5,2))) as avg_cleanliness,
    SUM(CAST(completed_trips AS UNSIGNED)) as total_trips
    FROM turo_daily_metrics")->fetch();

// Get location breakdown
$locations = $pdo->query("SELECT location, COUNT(*) as days,
    AVG(CAST(REPLACE(five_star_rate, '%', '') AS DECIMAL(5,2))) as avg_rating,
    SUM(CAST(completed_trips AS UNSIGNED)) as trips
    FROM turo_daily_metrics GROUP BY location ORDER BY days DESC")->fetchAll();

// Build WHERE clause
$where = [];
$params = [];

if (!empty($_GET['location'])) {
    $where[] = "location = ?";
    $params[] = $_GET['location'];
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get metrics
$stmt = $pdo->prepare("SELECT * FROM turo_daily_metrics $whereClause ORDER BY date DESC LIMIT 100");
$stmt->execute($params);
$metrics = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h2>Turo Daily Metrics</h2>
    
    <!-- Statistics -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Days</h6>
                    <h3><?= number_format($stats['total_days']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Avg 5-Star</h6>
                    <h3><?= number_format($stats['avg_five_star'] ?? 0, 1) ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Avg Cancel</h6>
                    <h3><?= number_format($stats['avg_cancellation'] ?? 0, 1) ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Avg Maint</h6>
                    <h3><?= number_format($stats['avg_maintenance'] ?? 0, 1) ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Avg Clean</h6>
                    <h3><?= number_format($stats['avg_cleanliness'] ?? 0, 1) ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Trips</h6>
                    <h3><?= number_format($stats['total_trips'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Location Breakdown -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <?php foreach ($locations as $loc): ?>
        <div class="col-md-4" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <span class="badge bg-<?= $loc['location'] === 'TPA' ? 'primary' : ($loc['location'] === 'FLL' ? 'success' : 'warning') ?>">
                            <?= htmlspecialchars($loc['location']) ?>
                        </span>
                    </h5>
                    <p class="mb-1"><strong><?= number_format($loc['days']) ?></strong> days tracked</p>
                    <p class="mb-1"><strong><?= number_format($loc['trips']) ?></strong> completed trips</p>
                    <p class="mb-0"><strong><?= number_format($loc['avg_rating'], 1) ?>%</strong> avg 5-star rating</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="turo_metrics">
                <div class="col-md-3" style="flex: 1; min-width: 200px;">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Locations</option>
                        <option value="TPA" <?= ($_GET['location'] ?? '') === 'TPA' ? 'selected' : '' ?>>TPA</option>
                        <option value="FLL" <?= ($_GET['location'] ?? '') === 'FLL' ? 'selected' : '' ?>>FLL</option>
                        <option value="MIA" <?= ($_GET['location'] ?? '') === 'MIA' ? 'selected' : '' ?>>MIA</option>
                    </select>
                </div>
                <div class="col-md-3" style="flex: 1; min-width: 200px;">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?page=turo_metrics" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Metrics Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Location</th>
                            <th>5-Star Rate</th>
                            <th>Cancellation Rate</th>
                            <th>Maintenance Rate</th>
                            <th>Cleanliness Rate</th>
                            <th>Completed Trips</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics as $metric): ?>
                        <tr>
                            <td><?= htmlspecialchars($metric['date']) ?></td>
                            <td><span class="badge bg-<?= $metric['location'] === 'TPA' ? 'primary' : ($metric['location'] === 'FLL' ? 'success' : 'warning') ?>"><?= htmlspecialchars($metric['location']) ?></span></td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($metric['five_star_rate']) ?></span></td>
                            <td><span class="badge bg-<?= floatval(str_replace('%', '', $metric['cancellation_rate'])) > 5 ? 'danger' : 'info' ?>"><?= htmlspecialchars($metric['cancellation_rate']) ?></span></td>
                            <td><span class="badge bg-warning"><?= htmlspecialchars($metric['maintenance_rate']) ?></span></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($metric['cleanliness_rate']) ?></span></td>
                            <td><?= htmlspecialchars($metric['completed_trips']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($metrics)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No metrics found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
