<?php
// Simple Car Rental ERP System - Complete Version with Working Edit Functionality
// All page content inline - no external includes needed
session_start();

// Database connection
$host = 'localhost';
$dbname = 'car_rental_erp';
$username = 'root';
$password = 'SecureRootPass123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Simple authentication check
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $login_error = "Invalid email or password";
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Car Rental ERP</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-container { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
            .login-header { text-align: center; margin-bottom: 2rem; }
            .login-header h1 { color: #333; margin-bottom: 0.5rem; }
            .login-header p { color: #666; }
            .form-group { margin-bottom: 1rem; }
            .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
            .form-group input { width: 100%; padding: 0.75rem; border: 2px solid #e1e5e9; border-radius: 5px; font-size: 1rem; transition: border-color 0.3s; }
            .form-group input:focus { outline: none; border-color: #667eea; }
            .btn-login { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: transform 0.2s; }
            .btn-login:hover { transform: translateY(-2px); }
            .error { color: #e74c3c; text-align: center; margin-top: 1rem; }
            .checkbox-group { display: flex; align-items: center; margin-bottom: 1rem; }
            .checkbox-group input { width: auto; margin-right: 0.5rem; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>Car Rental ERP</h1>
                <p>Sign in to your account</p>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me for 30 days</label>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
                <?php if (isset($login_error)): ?>
                    <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle AJAX requests for edit functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_vehicle') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
        $stmt->execute([$id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($vehicle);
        exit;
    }
    
    if ($_POST['action'] === 'update_vehicle') {
        $id = $_POST['id'];
        $make = $_POST['make'];
        $model = $_POST['model'];
        $year = $_POST['year'];
        $vin = $_POST['vin'];
        $license_plate = $_POST['license_plate'];
        $color = $_POST['color'];
        $mileage = $_POST['mileage'];
        $daily_rate = $_POST['daily_rate'];
        
        $stmt = $pdo->prepare("UPDATE vehicles SET make=?, model=?, year=?, vin=?, license_plate=?, color=?, mileage=?, daily_rate=? WHERE id=?");
        $result = $stmt->execute([$make, $model, $year, $vin, $license_plate, $color, $mileage, $daily_rate, $id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_vehicle') {
        $id = $_POST['id'];
        
        // Check if vehicle has active reservations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE vehicle_id = ? AND status IN ('confirmed', 'pending')");
        $stmt->execute([$id]);
        $activeReservations = $stmt->fetchColumn();
        
        if ($activeReservations > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active reservations']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_vehicle':
                $stmt = $pdo->prepare("INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')");
                $stmt->execute([$_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']]);
                break;
                
            case 'add_customer':
                $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']]);
                break;
                
            case 'add_reservation':
                $stmt = $pdo->prepare("INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']]);
                break;
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . ($_GET['page'] ?? 'dashboard'));
        exit;
    }
}

// Simple permissions class
class SimplePermissions {
    public function hasPermission($resource, $action) {
        return true; // For simplicity, allow all actions
    }
}

$permissions = new SimplePermissions();
$current_page = $_GET['page'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 1.5rem; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; }
        .btn-logout:hover { background: rgba(255,255,255,0.3); }
        
        .nav-tabs { background: white; border-bottom: 1px solid #ddd; padding: 0 2rem; display: flex; gap: 0; }
        .nav-tab { padding: 1rem 1.5rem; text-decoration: none; color: #666; border-bottom: 3px solid transparent; transition: all 0.3s; position: relative; }
        .nav-tab:hover { color: #333; background: #f8f9fa; }
        .nav-tab.active { color: #667eea; border-bottom-color: #667eea; background: #f8f9fa; }
        .nav-tab .badge { background: #e74c3c; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; position: absolute; top: 0.5rem; right: 0.5rem; }
        
        .permissions-banner { background: #d1ecf1; color: #0c5460; padding: 0.75rem 2rem; border-bottom: 1px solid #bee5eb; }
        
        .container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-header { margin-bottom: 2rem; }
        .page-header h2 { color: #333; margin-bottom: 0.5rem; }
        .page-header p { color: #666; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #667eea; }
        .stat-card h3 { color: #333; margin-bottom: 0.5rem; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #667eea; }
        
        .form-section { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .form-section h3 { color: #333; margin-bottom: 1.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 2px solid #e1e5e9; border-radius: 5px; font-size: 1rem; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        
        .data-table { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .data-table table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 1rem; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #dee2e6; }
        .data-table td { padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .data-table tr:hover { background: #f8f9fa; }
        
        .btn-edit { background: #28a745; color: white; border: none; padding: 0.5rem 1rem; border-radius: 3px; cursor: pointer; font-size: 0.9rem; }
        .btn-edit:hover { background: #218838; }
        
        .btn-delete { background: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 3px; cursor: pointer; font-size: 0.9rem; margin-left: 0.5rem; }
        .btn-delete:hover { background: #c82333; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 0; width: 90%; max-width: 600px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; }
        .close { color: white; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { opacity: 0.7; }
        .modal-body { padding: 2rem; }
        .modal-footer { padding: 1rem 2rem; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 1rem; }
        
        @media (max-width: 768px) {
            .header { padding: 1rem; }
            .nav-tabs { padding: 0 1rem; overflow-x: auto; }
            .container { padding: 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Car Rental ERP System</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </div>
    
    <div class="nav-tabs">
        <a href="?page=dashboard" class="nav-tab <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            Dashboard <span class="badge">4</span>
        </a>
        <a href="?page=vehicles" class="nav-tab <?php echo $current_page === 'vehicles' ? 'active' : ''; ?>">
            Vehicles <span class="badge">8</span>
        </a>
        <a href="?page=customers" class="nav-tab <?php echo $current_page === 'customers' ? 'active' : ''; ?>">
            Customers <span class="badge">6</span>
        </a>
        <a href="?page=reservations" class="nav-tab <?php echo $current_page === 'reservations' ? 'active' : ''; ?>">
            Reservations <span class="badge">3</span>
        </a>
        <a href="?page=maintenance" class="nav-tab <?php echo $current_page === 'maintenance' ? 'active' : ''; ?>">
            Maintenance <span class="badge">2</span>
        </a>
        <a href="?page=users" class="nav-tab <?php echo $current_page === 'users' ? 'active' : ''; ?>">
            Users <span class="badge">1</span>
        </a>
        <a href="?page=roles" class="nav-tab <?php echo $current_page === 'roles' ? 'active' : ''; ?>">
            Roles <span class="badge">4</span>
        </a>
    </div>
    
    <div class="permissions-banner">
        Your permissions for this page: View, Create, Edit, Delete
    </div>
    
    <div class="container">
        <?php
        switch ($current_page) {
            case 'dashboard':
                ?>
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
                <?php
                break;
                
            case 'vehicles':
                ?>
                <div class="page-header">
                    <h2>Vehicle Management</h2>
                    <p>Manage your fleet of rental vehicles</p>
                </div>
                
                <?php if ($permissions->hasPermission('vehicles', 'create')): ?>
                <div class="form-section">
                    <h3>Add New Vehicle</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_vehicle">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="make">Make</label>
                                <input type="text" id="make" name="make" required>
                            </div>
                            <div class="form-group">
                                <label for="model">Model</label>
                                <input type="text" id="model" name="model" required>
                            </div>
                            <div class="form-group">
                                <label for="year">Year</label>
                                <input type="number" id="year" name="year" min="1900" max="2030" required>
                            </div>
                            <div class="form-group">
                                <label for="vin">VIN</label>
                                <input type="text" id="vin" name="vin" required>
                            </div>
                            <div class="form-group">
                                <label for="license_plate">License Plate</label>
                                <input type="text" id="license_plate" name="license_plate" required>
                            </div>
                            <div class="form-group">
                                <label for="color">Color</label>
                                <input type="text" id="color" name="color" required>
                            </div>
                            <div class="form-group">
                                <label for="mileage">Mileage</label>
                                <input type="number" id="mileage" name="mileage" required>
                            </div>
                            <div class="form-group">
                                <label for="daily_rate">Daily Rate ($)</label>
                                <input type="number" id="daily_rate" name="daily_rate" step="0.01" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Add Vehicle</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Vehicle Inventory</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Year</th>
                                <th>License Plate</th>
                                <th>Status</th>
                                <th>Daily Rate</th>
                                <th>Mileage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['make']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['model']) . "</td>";
                                echo "<td>" . $row['year'] . "</td>";
                                echo "<td>" . htmlspecialchars($row['license_plate']) . "</td>";
                                echo "<td>" . ucfirst($row['status']) . "</td>";
                                echo "<td>$" . number_format($row['daily_rate'], 2) . "</td>";
                                echo "<td>" . number_format($row['mileage']) . "</td>";
                                echo "<td>";
                                echo "<button class='btn-edit' onclick='editVehicle(" . $row['id'] . ")'>Edit</button>";
                                echo "<button class='btn-delete' onclick='deleteVehicle(" . $row['id'] . ")'>Delete</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Edit Vehicle Modal -->
                <div id="editVehicleModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Vehicle</h3>
                            <span class="close" onclick="closeEditModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editVehicleForm">
                                <input type="hidden" id="edit_vehicle_id" name="id">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="edit_make">Make</label>
                                        <input type="text" id="edit_make" name="make" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_model">Model</label>
                                        <input type="text" id="edit_model" name="model" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_year">Year</label>
                                        <input type="number" id="edit_year" name="year" min="1900" max="2030" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_vin">VIN</label>
                                        <input type="text" id="edit_vin" name="vin" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_license_plate">License Plate</label>
                                        <input type="text" id="edit_license_plate" name="license_plate" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_color">Color</label>
                                        <input type="text" id="edit_color" name="color" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_mileage">Mileage</label>
                                        <input type="number" id="edit_mileage" name="mileage" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_daily_rate">Daily Rate ($)</label>
                                        <input type="number" id="edit_daily_rate" name="daily_rate" step="0.01" required>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-primary" onclick="saveVehicle()">Save Changes</button>
                            <button type="button" onclick="closeEditModal()" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer;">Cancel</button>
                        </div>
                    </div>
                </div>
                
                <script>
                function editVehicle(id) {
                    // Fetch vehicle data
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax=1&action=get_vehicle&id=' + id
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Populate form fields
                        document.getElementById('edit_vehicle_id').value = data.id;
                        document.getElementById('edit_make').value = data.make;
                        document.getElementById('edit_model').value = data.model;
                        document.getElementById('edit_year').value = data.year;
                        document.getElementById('edit_vin').value = data.vin;
                        document.getElementById('edit_license_plate').value = data.license_plate;
                        document.getElementById('edit_color').value = data.color;
                        document.getElementById('edit_mileage').value = data.mileage;
                        document.getElementById('edit_daily_rate').value = data.daily_rate;
                        
                        // Show modal
                        document.getElementById('editVehicleModal').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading vehicle data');
                    });
                }
                
                function closeEditModal() {
                    document.getElementById('editVehicleModal').style.display = 'none';
                }
                
                function saveVehicle() {
                    const form = document.getElementById('editVehicleForm');
                    const formData = new FormData(form);
                    formData.append('ajax', '1');
                    formData.append('action', 'update_vehicle');
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Vehicle updated successfully!');
                            closeEditModal();
                            location.reload();
                        } else {
                            alert('Error updating vehicle');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating vehicle');
                    });
                }
                
                function deleteVehicle(id) {
                    if (confirm('Are you sure you want to delete this vehicle?')) {
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'ajax=1&action=delete_vehicle&id=' + id
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Vehicle deleted successfully!');
                                location.reload();
                            } else {
                                alert(data.message || 'Error deleting vehicle');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting vehicle');
                        });
                    }
                }
                
                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('editVehicleModal');
                    if (event.target == modal) {
                        closeEditModal();
                    }
                }
                </script>
                <?php
                break;
                
            case 'customers':
                ?>
                <div class="page-header">
                    <h2>Customer Management</h2>
                    <p>Manage your customer database</p>
                </div>
                
                <?php if ($permissions->hasPermission('customers', 'create')): ?>
                <div class="form-section">
                    <h3>Add New Customer</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_customer">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="driver_license">Driver License</label>
                                <input type="text" id="driver_license" name="driver_license" required>
                            </div>
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Add Customer</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Customer List</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Driver License</th>
                                <th>Date of Birth</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['driver_license']) . "</td>";
                                echo "<td>" . date('M j, Y', strtotime($row['date_of_birth'])) . "</td>";
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'reservations':
                ?>
                <div class="page-header">
                    <h2>Reservation Management</h2>
                    <p>Manage vehicle reservations and bookings</p>
                </div>
                
                <?php if ($permissions->hasPermission('reservations', 'create')): ?>
                <div class="form-section">
                    <h3>Create New Reservation</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_reservation">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="customer_id">Customer</label>
                                <select id="customer_id" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vehicle_id">Vehicle</label>
                                <select id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, make, model, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make, model");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['make'] . ' ' . $row['model']) . " - $" . number_format($row['daily_rate'], 2) . "/day</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" required>
                            </div>
                            <div class="form-group">
                                <label for="pickup_location">Pickup Location</label>
                                <input type="text" id="pickup_location" name="pickup_location" required>
                            </div>
                            <div class="form-group">
                                <label for="dropoff_location">Dropoff Location</label>
                                <input type="text" id="dropoff_location" name="dropoff_location" required>
                            </div>
                            <div class="form-group">
                                <label for="total_amount">Total Amount ($)</label>
                                <input type="number" id="total_amount" name="total_amount" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Create Reservation</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Current Reservations</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                                       CONCAT(v.make, ' ', v.model) as vehicle_name
                                FROM reservations r
                                JOIN customers c ON r.customer_id = c.id
                                JOIN vehicles v ON r.vehicle_id = v.id
                                ORDER BY r.start_date DESC
                            ");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                                echo "<td>" . date('M j, Y', strtotime($row['start_date'])) . "</td>";
                                echo "<td>" . date('M j, Y', strtotime($row['end_date'])) . "</td>";
                                echo "<td>" . ucfirst($row['status']) . "</td>";
                                echo "<td>$" . number_format($row['total_amount'], 2) . "</td>";
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'maintenance':
                ?>
                <div class="page-header">
                    <h2>Maintenance Management</h2>
                    <p>Schedule and track vehicle maintenance</p>
                </div>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">Maintenance Schedule</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Maintenance Type</th>
                                <th>Scheduled Date</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT m.*, CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_name
                                FROM maintenance_schedules m
                                JOIN vehicles v ON m.vehicle_id = v.id
                                ORDER BY m.scheduled_date
                            ");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['maintenance_type']) . "</td>";
                                echo "<td>" . date('M j, Y', strtotime($row['scheduled_date'])) . "</td>";
                                echo "<td>" . ucfirst($row['status']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'users':
                ?>
                <div class="page-header">
                    <h2>User Management</h2>
                    <p>Manage system users and their access</p>
                </div>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">System Users</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT u.*, GROUP_CONCAT(r.display_name SEPARATOR ', ') as role_names
                                FROM users u
                                LEFT JOIN user_roles ur ON u.id = ur.user_id
                                LEFT JOIN roles r ON ur.role_id = r.id
                                GROUP BY u.id
                                ORDER BY u.last_name, u.first_name
                            ");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['role_names'] ?: 'No roles assigned') . "</td>";
                                echo "<td>" . ($row['is_active'] ? 'Active' : 'Inactive') . "</td>";
                                echo "<td>" . ($row['last_login'] ? date('M j, Y', strtotime($row['last_login'])) : 'Never') . "</td>";
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'roles':
                ?>
                <div class="page-header">
                    <h2>Role Management</h2>
                    <p>Manage user roles and permissions</p>
                </div>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">System Roles</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Display Name</th>
                                <th>Description</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT r.*, COUNT(ur.user_id) as user_count
                                FROM roles r
                                LEFT JOIN user_roles ur ON r.id = ur.role_id
                                GROUP BY r.id
                                ORDER BY r.name
                            ");
                            while ($row = $stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['display_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                                echo "<td>" . $row['user_count'] . "</td>";
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            default:
                echo "<div class='page-header'><h2>Page Not Found</h2><p>The requested page could not be found.</p></div>";
                break;
        }
        ?>
    </div>
</body>
</html>

