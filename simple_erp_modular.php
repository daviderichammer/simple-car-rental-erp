<?php
// Simple Car Rental ERP System - MODULAR VERSION with Edit Functionality for ALL PAGES
// Complete edit functionality for Vehicles, Customers, Reservations, Maintenance, Users, and Roles
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
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-container {
                background: white;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 400px;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .login-header h1 {
                color: #333;
                margin-bottom: 0.5rem;
            }
            
            .login-header p {
                color: #666;
                font-size: 0.9rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                color: #333;
                font-weight: 500;
            }
            
            .form-group input {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #e1e5e9;
                border-radius: 5px;
                font-size: 1rem;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .checkbox-group {
                display: flex;
                align-items: center;
                margin-bottom: 1rem;
            }
            
            .checkbox-group input {
                width: auto;
                margin-right: 0.5rem;
            }
            
            .btn-login {
                width: 100%;
                padding: 0.75rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 1rem;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .btn-login:hover {
                transform: translateY(-2px);
            }
            
            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 0.75rem;
                border-radius: 5px;
                margin-bottom: 1rem;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>Car Rental ERP</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
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
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Simple permissions class
class SimplePermissions {
    public function hasPermission($resource, $action) {
        return true; // For now, all authenticated users have all permissions
    }
}

$permissions = new SimplePermissions();

// Handle form submissions and AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        
        switch ($_POST['action']) {
            case 'get_vehicle':
                $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($vehicle);
                exit;
                
            case 'update_vehicle':
                $stmt = $pdo->prepare("UPDATE vehicles SET make = ?, model = ?, year = ?, vin = ?, license_plate = ?, color = ?, mileage = ?, daily_rate = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_vehicle':
                // Check if vehicle has active reservations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'active')");
                $stmt->execute([$_POST['id']]);
                $activeReservations = $stmt->fetchColumn();
                
                if ($activeReservations > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active reservations']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_customer':
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($customer);
                exit;
                
            case 'update_customer':
                $stmt = $pdo->prepare("UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, driver_license = ?, date_of_birth = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_customer':
                // Check if customer has active reservations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = ? AND status IN ('pending', 'confirmed', 'active')");
                $stmt->execute([$_POST['id']]);
                $activeReservations = $stmt->fetchColumn();
                
                if ($activeReservations > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete customer with active reservations']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_reservation':
                $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($reservation);
                exit;
                
            case 'update_reservation':
                $stmt = $pdo->prepare("UPDATE reservations SET customer_id = ?, vehicle_id = ?, start_date = ?, end_date = ?, pickup_location = ?, dropoff_location = ?, total_amount = ?, status = ?, notes = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['status'], $_POST['notes'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_reservation':
                $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_maintenance':
                $stmt = $pdo->prepare("SELECT * FROM maintenance_schedules WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($maintenance);
                exit;
                
            case 'update_maintenance':
                $stmt = $pdo->prepare("UPDATE maintenance_schedules SET vehicle_id = ?, maintenance_type = ?, scheduled_date = ?, status = ?, description = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['status'], $_POST['description'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_maintenance':
                $stmt = $pdo->prepare("DELETE FROM maintenance_schedules WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_user':
                $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, is_active FROM users WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($user);
                exit;
                
            case 'update_user':
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, is_active = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['is_active'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_user':
                // Prevent deleting current user
                if ($_POST['id'] == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_role':
                $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($role);
                exit;
                
            case 'update_role':
                $stmt = $pdo->prepare("UPDATE roles SET name = ?, display_name = ?, description = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['name'], $_POST['display_name'], $_POST['description'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_role':
                // Check if role is assigned to users
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
                $stmt->execute([$_POST['id']]);
                $assignedUsers = $stmt->fetchColumn();
                
                if ($assignedUsers > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete role assigned to users']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
        }
    } else {
        // Handle regular form submissions
        switch ($_POST['action']) {
            case 'logout':
                session_destroy();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                
            case 'add_vehicle':
                $stmt = $pdo->prepare("INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')");
                $stmt->execute([$_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']]);
                break;
                
            case 'add_customer':
                $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']]);
                break;
                
            case 'add_reservation':
                $stmt = $pdo->prepare("INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']]);
                break;
                
            case 'add_maintenance':
                $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, status, description) VALUES (?, ?, ?, 'scheduled', ?)");
                $stmt->execute([$_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['description']]);
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . ($_GET['page'] ?? 'dashboard'));
        exit;
    }
}

$current_page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 1.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info span {
            color: #666;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .nav-tabs {
            background: rgba(255, 255, 255, 0.9);
            padding: 0;
            display: flex;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tab {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .nav-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .badge {
            background: #667eea;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            min-width: 20px;
            text-align: center;
        }
        
        .permissions-banner {
            background: #d4edda;
            color: #155724;
            padding: 0.5rem 2rem;
            font-size: 0.9rem;
        }
        
        .container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }
        
        .stat-card .number {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .data-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1rem 2rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .nav-tabs {
                flex-wrap: wrap;
            }
            
            .nav-tab {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
            
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Car Rental ERP System</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
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
        // Include the appropriate page
        $page_file = "pages/{$current_page}.php";
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            echo "<div class='page-header'><h2>Page Not Found</h2><p>The requested page could not be found.</p></div>";
        }
        ?>
    </div>

    <script>
        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Vehicle functions
        function editVehicle(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_vehicle&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_vehicle_id').value = data.id;
                document.getElementById('edit_make').value = data.make;
                document.getElementById('edit_model').value = data.model;
                document.getElementById('edit_year').value = data.year;
                document.getElementById('edit_vin').value = data.vin;
                document.getElementById('edit_license_plate').value = data.license_plate;
                document.getElementById('edit_color').value = data.color;
                document.getElementById('edit_mileage').value = data.mileage;
                document.getElementById('edit_daily_rate').value = data.daily_rate;
                
                document.getElementById('editVehicleModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading vehicle data');
            });
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
                    closeModal('editVehicleModal');
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
        
        // Customer functions
        function editCustomer(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_customer&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_customer_id').value = data.id;
                document.getElementById('edit_customer_first_name').value = data.first_name;
                document.getElementById('edit_customer_last_name').value = data.last_name;
                document.getElementById('edit_customer_email').value = data.email;
                document.getElementById('edit_customer_phone').value = data.phone;
                document.getElementById('edit_customer_address').value = data.address;
                document.getElementById('edit_customer_driver_license').value = data.driver_license;
                document.getElementById('edit_customer_date_of_birth').value = data.date_of_birth;
                
                document.getElementById('editCustomerModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading customer data');
            });
        }
        
        function saveCustomer() {
            const form = document.getElementById('editCustomerForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'update_customer');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Customer updated successfully!');
                    closeModal('editCustomerModal');
                    location.reload();
                } else {
                    alert('Error updating customer');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating customer');
            });
        }
        
        function deleteCustomer(id) {
            if (confirm('Are you sure you want to delete this customer?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&action=delete_customer&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Customer deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting customer');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting customer');
                });
            }
        }
        
        // Reservation functions
        function editReservation(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_reservation&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_reservation_id').value = data.id;
                document.getElementById('edit_customer_id').value = data.customer_id;
                document.getElementById('edit_vehicle_id').value = data.vehicle_id;
                document.getElementById('edit_start_date').value = data.start_date;
                document.getElementById('edit_end_date').value = data.end_date;
                document.getElementById('edit_pickup_location').value = data.pickup_location;
                document.getElementById('edit_dropoff_location').value = data.dropoff_location;
                document.getElementById('edit_total_amount').value = data.total_amount;
                document.getElementById('edit_status').value = data.status;
                document.getElementById('edit_notes').value = data.notes;
                
                document.getElementById('editReservationModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading reservation data');
            });
        }
        
        function saveReservation() {
            const form = document.getElementById('editReservationForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'update_reservation');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reservation updated successfully!');
                    closeModal('editReservationModal');
                    location.reload();
                } else {
                    alert('Error updating reservation');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating reservation');
            });
        }
        
        function deleteReservation(id) {
            if (confirm('Are you sure you want to delete this reservation?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&action=delete_reservation&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reservation deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting reservation');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting reservation');
                });
            }
        }
        
        // Maintenance functions
        function editMaintenance(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_maintenance&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_maintenance_id').value = data.id;
                document.getElementById('edit_maintenance_vehicle_id').value = data.vehicle_id;
                document.getElementById('edit_maintenance_type').value = data.maintenance_type;
                document.getElementById('edit_scheduled_date').value = data.scheduled_date;
                document.getElementById('edit_maintenance_status').value = data.status;
                document.getElementById('edit_maintenance_description').value = data.description;
                
                document.getElementById('editMaintenanceModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading maintenance data');
            });
        }
        
        function saveMaintenance() {
            const form = document.getElementById('editMaintenanceForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'update_maintenance');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Maintenance updated successfully!');
                    closeModal('editMaintenanceModal');
                    location.reload();
                } else {
                    alert('Error updating maintenance');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating maintenance');
            });
        }
        
        function deleteMaintenance(id) {
            if (confirm('Are you sure you want to delete this maintenance record?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&action=delete_maintenance&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Maintenance deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting maintenance');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting maintenance');
                });
            }
        }
        
        // User functions
        function editUser(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_user&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_user_id').value = data.id;
                document.getElementById('edit_user_first_name').value = data.first_name;
                document.getElementById('edit_user_last_name').value = data.last_name;
                document.getElementById('edit_user_email').value = data.email;
                document.getElementById('edit_user_is_active').value = data.is_active;
                
                document.getElementById('editUserModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading user data');
            });
        }
        
        function saveUser() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'update_user');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User updated successfully!');
                    closeModal('editUserModal');
                    location.reload();
                } else {
                    alert('Error updating user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user');
            });
        }
        
        function deleteUser(id) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&action=delete_user&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting user');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting user');
                });
            }
        }
        
        // Role functions
        function editRole(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_role&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_role_id').value = data.id;
                document.getElementById('edit_role_name').value = data.name;
                document.getElementById('edit_role_display_name').value = data.display_name;
                document.getElementById('edit_role_description').value = data.description;
                
                document.getElementById('editRoleModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading role data');
            });
        }
        
        function saveRole() {
            const form = document.getElementById('editRoleForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'update_role');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Role updated successfully!');
                    closeModal('editRoleModal');
                    location.reload();
                } else {
                    alert('Error updating role');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating role');
            });
        }
        
        function deleteRole(id) {
            if (confirm('Are you sure you want to delete this role?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&action=delete_role&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Role deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting role');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting role');
                });
            }
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

