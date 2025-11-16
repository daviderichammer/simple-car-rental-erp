<?php
// Rental History page
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$location_filter = isset($_GET['location']) && $_GET['location'] !== '' ? $_GET['location'] : null;
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$guest_filter = isset($_GET['guest']) && $_GET['guest'] !== '' ? $_GET['guest'] : null;
$vehicle_filter = isset($_GET['vehicle']) && $_GET['vehicle'] !== '' ? $_GET['vehicle'] : null;
$date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$search = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($location_filter) {
    $where_conditions[] = "location = ?";
    $params[] = $location_filter;
}

if ($status_filter) {
    $where_conditions[] = "trip_status = ?";
    $params[] = $status_filter;
}

if ($guest_filter) {
    $where_conditions[] = "guest_name LIKE ?";
    $params[] = "%$guest_filter%";
}

if ($vehicle_filter) {
    $where_conditions[] = "vehicle_name LIKE ?";
    $params[] = "%$vehicle_filter%";
}

if ($date_from) {
    $where_conditions[] = "trip_start >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = "trip_start <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if ($search) {
    $where_conditions[] = "(reservation_id LIKE ? OR guest_name LIKE ? OR vehicle_name LIKE ? OR license_plate LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM rental_history $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Get rentals
$sql = "SELECT * FROM rental_history $where_clause ORDER BY trip_start DESC LIMIT $records_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_rentals,
    COUNT(DISTINCT location) as locations,
    COUNT(DISTINCT guest_name) as unique_guests,
    COUNT(DISTINCT vehicle_name) as unique_vehicles,
    SUM(trip_days) as total_days,
    AVG(trip_days) as avg_days,
    SUM(total_earnings) as total_revenue,
    AVG(total_earnings) as avg_revenue,
    MIN(trip_start) as earliest_rental,
    MAX(trip_start) as latest_rental
FROM rental_history $where_clause";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get location breakdown
$location_sql = "SELECT location, COUNT(*) as count, SUM(trip_days) as days 
    FROM rental_history $where_clause GROUP BY location ORDER BY count DESC";
$location_stmt = $pdo->prepare($location_sql);
$location_stmt->execute($params);
$location_stats = $location_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <h2>Rental History</h2>
    
    <!-- Statistics Cards -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Rentals</h5>
                    <h2><?php echo number_format($stats['total_rentals']); ?></h2>
                    <small><?php echo $total_records < $stats['total_rentals'] ? "Showing " . number_format($total_records) . " filtered" : "All records"; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Rental Days</h5>
                    <h2><?php echo number_format($stats['total_days'], 0); ?></h2>
                    <small>Avg: <?php echo number_format($stats['avg_days'], 1); ?> days/rental</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Unique Guests</h5>
                    <h2><?php echo number_format($stats['unique_guests']); ?></h2>
                    <small><?php echo number_format($stats['unique_vehicles']); ?> vehicles rented</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" style="flex: 1; min-width: 200px;">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Date Range</h5>
                    <h6><?php echo $stats['earliest_rental'] ? date('M d, Y', strtotime($stats['earliest_rental'])) : 'N/A'; ?></h6>
                    <h6><?php echo $stats['latest_rental'] ? date('M d, Y', strtotime($stats['latest_rental'])) : 'N/A'; ?></h6>
                    <small><?php echo $stats['earliest_rental'] && $stats['latest_rental'] ? 
                        round((strtotime($stats['latest_rental']) - strtotime($stats['earliest_rental'])) / (60*60*24*30)) . ' months' : ''; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Breakdown -->
    <div class="row mb-4" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <?php foreach ($location_stats as $loc): ?>
        <div class="col-md-4" style="flex: 1; min-width: 200px;">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <span class="badge badge-<?php echo $loc['location'] == 'TPA' ? 'primary' : ($loc['location'] == 'FLL' ? 'success' : 'warning'); ?>">
                            <?php echo htmlspecialchars($loc['location']); ?>
                        </span>
                    </h5>
                    <p class="mb-1"><strong><?php echo number_format($loc['count']); ?></strong> rentals</p>
                    <p class="mb-0"><strong><?php echo number_format($loc['days'], 0); ?></strong> rental days</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="form-inline">
                <input type="hidden" name="page" value="rental_history">
                
                <div class="form-group mr-2 mb-2">
                    <label class="mr-2">Location:</label>
                    <select name="location" class="form-control">
                        <option value="">All Locations</option>
                        <option value="TPA" <?php echo $location_filter == 'TPA' ? 'selected' : ''; ?>>TPA</option>
                        <option value="FLL" <?php echo $location_filter == 'FLL' ? 'selected' : ''; ?>>FLL</option>
                        <option value="MIA" <?php echo $location_filter == 'MIA' ? 'selected' : ''; ?>>MIA</option>
                    </select>
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <label class="mr-2">Status:</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Canceled" <?php echo $status_filter == 'Canceled' ? 'selected' : ''; ?>>Canceled</option>
                        <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    </select>
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <label class="mr-2">From:</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <label class="mr-2">To:</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                </div>
                
                <div class="form-group mr-2 mb-2">
                    <input type="text" name="search" class="form-control" placeholder="Search..." 
                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary mr-2 mb-2">Apply Filters</button>
                <a href="?page=rental_history" class="btn btn-secondary mb-2">Clear</a>
            </form>
        </div>
    </div>

    <!-- Results Info -->
    <div class="mb-3">
        <p>Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> 
           of <?php echo number_format($total_records); ?> rentals 
           (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</p>
    </div>

    <!-- Rentals Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Reservation ID</th>
                    <th>Guest</th>
                    <th>Vehicle</th>
                    <th>Trip Start</th>
                    <th>Trip End</th>
                    <th>Days</th>
                    <th>Distance</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                <tr>
                    <td colspan="10" class="text-center">No rentals found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($rentals as $rental): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rental['reservation_id']); ?></td>
                    <td><?php echo htmlspecialchars($rental['guest_name']); ?></td>
                    <td>
                        <small><?php echo htmlspecialchars($rental['vehicle_name']); ?></small><br>
                        <?php if ($rental['license_plate']): ?>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($rental['license_plate']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $rental['trip_start'] ? date('M d, Y H:i', strtotime($rental['trip_start'])) : 'N/A'; ?></td>
                    <td><?php echo $rental['trip_end'] ? date('M d, Y H:i', strtotime($rental['trip_end'])) : 'N/A'; ?></td>
                    <td><?php echo $rental['trip_days'] ? number_format($rental['trip_days'], 1) : 'N/A'; ?></td>
                    <td>
                        <?php if ($rental['distance_traveled']): ?>
                            <?php echo number_format($rental['distance_traveled']); ?> mi
                        <?php elseif ($rental['checkin_odometer'] && $rental['checkout_odometer']): ?>
                            <?php echo number_format($rental['checkout_odometer'] - $rental['checkin_odometer']); ?> mi
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $rental['trip_status'] == 'Completed' ? 'success' : 
                                ($rental['trip_status'] == 'Canceled' ? 'danger' : 'info'); 
                        ?>">
                            <?php echo htmlspecialchars($rental['trip_status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $rental['location'] == 'TPA' ? 'primary' : 
                                ($rental['location'] == 'FLL' ? 'success' : 'warning'); 
                        ?>">
                            <?php echo htmlspecialchars($rental['location']); ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewRental(<?php echo $rental['id']; ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($rental['reservation_link']): ?>
                        <a href="<?php echo htmlspecialchars($rental['reservation_link']); ?>" 
                           target="_blank" class="btn btn-sm btn-secondary" title="View in Turo">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=rental_history&p=<?php echo $page - 1; ?><?php 
                    echo $location_filter ? '&location=' . urlencode($location_filter) : '';
                    echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                    echo $date_from ? '&date_from=' . urlencode($date_from) : '';
                    echo $date_to ? '&date_to=' . urlencode($date_to) : '';
                    echo $search ? '&search=' . urlencode($search) : '';
                ?>">Previous</a>
            </li>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=rental_history&p=<?php echo $i; ?><?php 
                    echo $location_filter ? '&location=' . urlencode($location_filter) : '';
                    echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                    echo $date_from ? '&date_from=' . urlencode($date_from) : '';
                    echo $date_to ? '&date_to=' . urlencode($date_to) : '';
                    echo $search ? '&search=' . urlencode($search) : '';
                ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=rental_history&p=<?php echo $page + 1; ?><?php 
                    echo $location_filter ? '&location=' . urlencode($location_filter) : '';
                    echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                    echo $date_from ? '&date_from=' . urlencode($date_from) : '';
                    echo $date_to ? '&date_to=' . urlencode($date_to) : '';
                    echo $search ? '&search=' . urlencode($search) : '';
                ?>">Next</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- View Rental Modal -->
<div class="modal fade" id="viewRentalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rental Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="rentalDetails">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
function viewRental(id) {
    $('#viewRentalModal').modal('show');
    $('#rentalDetails').html('Loading...');
    
    $.get('?page=rental_history&action=get_rental&id=' + id, function(data) {
        $('#rentalDetails').html(data);
    });
}
</script>

<?php
// Handle AJAX request for rental details
if (isset($_GET['action']) && $_GET['action'] == 'get_rental' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM rental_history WHERE id = ?");
    $stmt->execute([$id]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rental) {
        ?>
        <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
            <div class="col-md-6" style="flex: 1; min-width: 200px;">
                <h6>Reservation Information</h6>
                <p><strong>Reservation ID:</strong> <?php echo htmlspecialchars($rental['reservation_id']); ?></p>
                <p><strong>Guest:</strong> <?php echo htmlspecialchars($rental['guest_name']); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php 
                    echo $rental['trip_status'] == 'Completed' ? 'success' : 
                        ($rental['trip_status'] == 'Canceled' ? 'danger' : 'info'); 
                ?>"><?php echo htmlspecialchars($rental['trip_status']); ?></span></p>
                <p><strong>Location:</strong> <span class="badge badge-<?php 
                    echo $rental['location'] == 'TPA' ? 'primary' : 
                        ($rental['location'] == 'FLL' ? 'success' : 'warning'); 
                ?>"><?php echo htmlspecialchars($rental['location']); ?></span></p>
            </div>
            <div class="col-md-6" style="flex: 1; min-width: 200px;">
                <h6>Vehicle Information</h6>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($rental['vehicle_name']); ?></p>
                <p><strong>Identifier:</strong> <?php echo htmlspecialchars($rental['vehicle_identifier']); ?></p>
                <?php if ($rental['license_plate']): ?>
                <p><strong>License Plate:</strong> <?php echo htmlspecialchars($rental['license_plate']); ?></p>
                <?php endif; ?>
                <?php if ($rental['color']): ?>
                <p><strong>Color:</strong> <?php echo htmlspecialchars($rental['color']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <hr>
        <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
            <div class="col-md-6" style="flex: 1; min-width: 200px;">
                <h6>Trip Details</h6>
                <p><strong>Start:</strong> <?php echo $rental['trip_start'] ? date('M d, Y H:i', strtotime($rental['trip_start'])) : 'N/A'; ?></p>
                <p><strong>End:</strong> <?php echo $rental['trip_end'] ? date('M d, Y H:i', strtotime($rental['trip_end'])) : 'N/A'; ?></p>
                <p><strong>Duration:</strong> <?php echo $rental['trip_days'] ? number_format($rental['trip_days'], 1) . ' days' : 'N/A'; ?></p>
                <p><strong>Pickup:</strong> <?php echo htmlspecialchars($rental['pickup_location']); ?></p>
                <p><strong>Return:</strong> <?php echo htmlspecialchars($rental['return_location']); ?></p>
            </div>
            <div class="col-md-6" style="flex: 1; min-width: 200px;">
                <h6>Mileage Information</h6>
                <p><strong>Check-in Odometer:</strong> <?php echo $rental['checkin_odometer'] ? number_format($rental['checkin_odometer']) . ' mi' : 'N/A'; ?></p>
                <p><strong>Check-out Odometer:</strong> <?php echo $rental['checkout_odometer'] ? number_format($rental['checkout_odometer']) . ' mi' : 'N/A'; ?></p>
                <p><strong>Distance Traveled:</strong> 
                    <?php 
                    if ($rental['distance_traveled']) {
                        echo number_format($rental['distance_traveled']) . ' mi';
                    } elseif ($rental['checkin_odometer'] && $rental['checkout_odometer']) {
                        echo number_format($rental['checkout_odometer'] - $rental['checkin_odometer']) . ' mi';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </p>
            </div>
        </div>
        <?php if ($rental['reservation_link']): ?>
        <hr>
        <p><a href="<?php echo htmlspecialchars($rental['reservation_link']); ?>" target="_blank" class="btn btn-primary">View in Turo</a></p>
        <?php endif; ?>
        <?php
    } else {
        echo '<p>Rental not found.</p>';
    }
    exit;
}
?>
