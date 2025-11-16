<?php
// EV Charging Sessions page
if (!isset($pdo)) {
    die("Database connection not available");
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'delete' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM ev_charging_sessions WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            try {
                $stmt = $pdo->prepare("INSERT INTO ev_charging_sessions 
                    (date, model, tag, guest, trip_start, trip_end, begin_charge, end_charge, 
                     post_trip_charge, total_charging_inv, grand_total_bill, date_paid, location) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $_POST['date'], $_POST['model'], $_POST['tag'], $_POST['guest'],
                    $_POST['trip_start'], $_POST['trip_end'], $_POST['begin_charge'],
                    $_POST['end_charge'], $_POST['post_trip_charge'], $_POST['total_charging_inv'],
                    $_POST['grand_total_bill'], $_POST['date_paid'], $_POST['location']
                ]);
                
                $success = "EV charging session added successfully!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'edit') {
            try {
                $stmt = $pdo->prepare("UPDATE ev_charging_sessions SET 
                    date=?, model=?, tag=?, guest=?, trip_start=?, trip_end=?, begin_charge=?, 
                    end_charge=?, post_trip_charge=?, total_charging_inv=?, grand_total_bill=?, 
                    date_paid=?, location=? WHERE id=?");
                
                $stmt->execute([
                    $_POST['date'], $_POST['model'], $_POST['tag'], $_POST['guest'],
                    $_POST['trip_start'], $_POST['trip_end'], $_POST['begin_charge'],
                    $_POST['end_charge'], $_POST['post_trip_charge'], $_POST['total_charging_inv'],
                    $_POST['grand_total_bill'], $_POST['date_paid'], $_POST['location'],
                    $_POST['id']
                ]);
                
                $success = "EV charging session updated successfully!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get statistics
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CAST(REPLACE(REPLACE(grand_total_bill, '$', ''), ',', '') AS DECIMAL(10,2))) as total_cost,
    COUNT(DISTINCT location) as locations,
    COUNT(DISTINCT tag) as vehicles
    FROM ev_charging_sessions")->fetch();

// Get location breakdown
$locations = $pdo->query("SELECT location, COUNT(*) as count,
    SUM(CAST(REPLACE(REPLACE(grand_total_bill, '$', ''), ',', '') AS DECIMAL(10,2))) as total
    FROM ev_charging_sessions GROUP BY location")->fetchAll();

// Build WHERE clause
$where = [];
$params = [];

if (!empty($_GET['location'])) {
    $where[] = "location = ?";
    $params[] = $_GET['location'];
}

if (!empty($_GET['search'])) {
    $where[] = "(tag LIKE ? OR guest LIKE ? OR model LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get sessions
$stmt = $pdo->prepare("SELECT * FROM ev_charging_sessions $whereClause ORDER BY date DESC LIMIT 50");
$stmt->execute($params);
$sessions = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h2>EV Charging Sessions</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Sessions</h5>
                    <h3><?= number_format($stats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Cost</h5>
                    <h3>$<?= number_format($stats['total_cost'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Locations</h5>
                    <h3><?= $stats['locations'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Vehicles</h5>
                    <h3><?= $stats['vehicles'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Location Breakdown -->
    <div class="row mb-4">
        <?php foreach ($locations as $loc): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($loc['location']) ?></h5>
                    <p class="mb-1"><?= number_format($loc['count']) ?> sessions</p>
                    <p class="mb-0">$<?= number_format($loc['total'] ?? 0, 2) ?> total</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="ev_charging">
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Locations</option>
                        <option value="TPA" <?= ($_GET['location'] ?? '') === 'TPA' ? 'selected' : '' ?>>TPA</option>
                        <option value="FLL" <?= ($_GET['location'] ?? '') === 'FLL' ? 'selected' : '' ?>>FLL</option>
                        <option value="MIA" <?= ($_GET['location'] ?? '') === 'MIA' ? 'selected' : '' ?>>MIA</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Tag, Guest, or Model" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?page=ev_charging" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Sessions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Model</th>
                            <th>Tag</th>
                            <th>Guest</th>
                            <th>Trip Start</th>
                            <th>Trip End</th>
                            <th>Begin Charge</th>
                            <th>End Charge</th>
                            <th>Total Bill</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= htmlspecialchars($session['date']) ?></td>
                            <td><?= htmlspecialchars($session['model']) ?></td>
                            <td><?= htmlspecialchars($session['tag']) ?></td>
                            <td><?= htmlspecialchars($session['guest']) ?></td>
                            <td><?= htmlspecialchars($session['trip_start']) ?></td>
                            <td><?= htmlspecialchars($session['trip_end']) ?></td>
                            <td><?= htmlspecialchars($session['begin_charge']) ?></td>
                            <td><?= htmlspecialchars($session['end_charge']) ?></td>
                            <td><?= htmlspecialchars($session['grand_total_bill']) ?></td>
                            <td><span class="badge bg-<?= $session['location'] === 'TPA' ? 'primary' : ($session['location'] === 'FLL' ? 'success' : 'warning') ?>"><?= htmlspecialchars($session['location']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No EV charging sessions found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
