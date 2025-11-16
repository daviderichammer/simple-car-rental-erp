<?php
// Simple Car Rental ERP System - Complete Version
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

// User Authentication Class
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function login($email, $password, $remember_me = false) {
        // Check if account is locked
        $stmt = $this->pdo->prepare("SELECT failed_login_attempts, last_failed_login FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user_check = $stmt->fetch();
        
        if ($user_check && $this->isAccountLocked($user_check)) {
            return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts. Please try again later.'];
        }
        
        // Verify credentials
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email, u.password_hash, u.first_name, u.last_name, u.is_active, u.must_change_password,
                   GROUP_CONCAT(r.name) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.email = ? AND u.is_active = TRUE
            GROUP BY u.id
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($email);
            
            // Create session
            $session_token = $this->createSession($user['id'], $remember_me);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_roles'] = $user['roles'];
            $_SESSION['session_token'] = $session_token;
            
            // Set remember me cookie if requested
            if ($remember_me) {
                setcookie('remember_token', $session_token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            }
            
            return ['success' => true, 'must_change_password' => $user['must_change_password']];
        } else {
            // Record failed attempt
            $this->recordFailedAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }
    
    private function isAccountLocked($user) {
        $attempts = $user['failed_login_attempts'];
        $last_failed = $user['last_failed_login'];
        
        if ($attempts >= 15) {
            return strtotime($last_failed) > (time() - 3600); // 1 hour lockout
        } elseif ($attempts >= 10) {
            return strtotime($last_failed) > (time() - 900); // 15 minute lockout
        } elseif ($attempts >= 5) {
            return strtotime($last_failed) > (time() - 300); // 5 minute lockout
        }
        
        return false;
    }
    
    private function recordFailedAttempt($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1, 
                last_failed_login = NOW() 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    private function resetFailedAttempts($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, 
                last_failed_login = NULL 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    private function createSession($user_id, $remember_me = false) {
        $token = bin2hex(random_bytes(32));
        $expires_at = $remember_me ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $token, $expires_at]);
        
        return $token;
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }
        
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $this->validateSession();
    }
    
    private function validateSession() {
        if (!isset($_SESSION['session_token'])) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT us.user_id 
            FROM user_sessions us 
            WHERE us.session_token = ? AND us.expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['session_token']]);
        
        return $stmt->fetch() !== false;
    }
}

// Permission Class for Role-Based Access Control
class Permission {
    private $pdo;
    private $user_id;
    private $user_roles;
    
    public function __construct($pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->loadUserRoles();
    }
    
    private function loadUserRoles() {
        if (!$this->user_id) {
            $this->user_roles = [];
            return;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT r.name, r.display_name
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$this->user_id]);
        $this->user_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function canAccess($screen_name) {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as can_access
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ?
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $result = $stmt->fetch();
        
        return $result['can_access'] > 0;
    }
    
    public function hasPermission($screen_name, $permission_type) {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ?
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $permissions = $stmt->fetch();
        
        if (!$permissions) {
            return false;
        }
        
        switch ($permission_type) {
            case 'view': return $permissions['can_view'];
            case 'create': return $permissions['can_create'];
            case 'edit': return $permissions['can_edit'];
            case 'delete': return $permissions['can_delete'];
            default: return false;
        }
    }
    
    public function isSuperAdmin() {
        foreach ($this->user_roles as $role) {
            if ($role['name'] === 'super_admin') {
                return true;
            }
        }
        return false;
    }
    
    public function getUserPermissions($screen_name) {
        $permissions = [];
        if ($this->hasPermission($screen_name, 'view')) $permissions[] = 'View';
        if ($this->hasPermission($screen_name, 'create')) $permissions[] = 'Create';
        if ($this->hasPermission($screen_name, 'edit')) $permissions[] = 'Edit';
        if ($this->hasPermission($screen_name, 'delete')) $permissions[] = 'Delete';
        
        return $permissions;
    }
    
    public function getAccessibleScreens() {
        if ($this->isSuperAdmin()) {
            $stmt = $this->pdo->prepare("SELECT name, display_name FROM screens ORDER BY display_order");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT s.name, s.display_name
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ?
            ORDER BY s.display_order
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Initialize classes
$user = new User($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $remember_me = isset($_POST['remember_me']);
                
                $result = $user->login($email, $password, $remember_me);
                if ($result['success']) {
                    if ($result['must_change_password']) {
                        header('Location: ?change_password=1');
                    } else {
                        header('Location: ?page=dashboard');
                    }
                    exit;
                } else {
                    $error_message = $result['message'];
                }
                break;
                
            case 'logout':
                $user->logout();
                header('Location: ?');
                exit;
                break;
                
            case 'add_vehicle':
                if ($user->isLoggedIn()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')
                    ");
                    $stmt->execute([
                        $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'],
                        $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']
                    ]);
                    header('Location: ?page=vehicles&success=1');
                    exit;
                }
                break;
                
            case 'add_customer':
                if ($user->isLoggedIn()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
                        $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']
                    ]);
                    header('Location: ?page=customers&success=1');
                    exit;
                }
                break;
                
            case 'add_reservation':
                if ($user->isLoggedIn()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
                    ");
                    $stmt->execute([
                        $_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'],
                        $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']
                    ]);
                    header('Location: ?page=reservations&success=1');
                    exit;
                }
                break;
                
            case 'add_maintenance':
                if ($user->isLoggedIn()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description, status) 
                        VALUES (?, ?, ?, ?, 'scheduled')
                    ");
                    $stmt->execute([
                        $_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['description']
                    ]);
                    header('Location: ?page=maintenance&success=1');
                    exit;
                }
                break;
        }
    }
}

// Check if user is logged in
$is_logged_in = $user->isLoggedIn();

// Initialize permissions if logged in
$permissions = null;
if ($is_logged_in) {
    $permissions = new Permission($pdo, $_SESSION['user_id']);
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// If not logged in, show login page
if (!$is_logged_in) {
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
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                color: #555;
                font-weight: 500;
            }
            
            .form-group input {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #ddd;
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
                margin-right: 0.5rem;
            }
            
            .login-btn {
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
            
            .login-btn:hover {
                transform: translateY(-2px);
            }
            
            .forgot-password {
                text-align: center;
                margin-top: 1rem;
            }
            
            .forgot-password a {
                color: #667eea;
                text-decoration: none;
            }
            
            .error-message {
                background: #fee;
                color: #c33;
                padding: 0.75rem;
                border-radius: 5px;
                margin-bottom: 1rem;
                border: 1px solid #fcc;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>Car Rental ERP</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Remember me for 30 days</label>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
            </form>
            
            <div class="forgot-password">
                <a href="?forgot=1">Forgot your password?</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Main application for logged-in users
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
            color: #555;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .navigation {
            background: rgba(255, 255, 255, 0.9);
            padding: 0;
            display: flex;
            overflow-x: auto;
        }
        
        .nav-tab {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #555;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            position: relative;
        }
        
        .nav-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #333;
        }
        
        .nav-tab.active {
            color: #333;
            font-weight: 600;
        }
        
        .nav-tab.active.dashboard { border-bottom-color: #007bff; background: rgba(0, 123, 255, 0.1); }
        .nav-tab.active.vehicles { border-bottom-color: #ffc107; background: rgba(255, 193, 7, 0.1); }
        .nav-tab.active.customers { border-bottom-color: #6f42c1; background: rgba(111, 66, 193, 0.1); }
        .nav-tab.active.reservations { border-bottom-color: #20c997; background: rgba(32, 201, 151, 0.1); }
        .nav-tab.active.maintenance { border-bottom-color: #6f42c1; background: rgba(111, 66, 193, 0.1); }
        .nav-tab.active.users { border-bottom-color: #dc3545; background: rgba(220, 53, 69, 0.1); }
        .nav-tab.active.roles { border-bottom-color: #fd7e14; background: rgba(253, 126, 20, 0.1); }
        
        .permissions-bar {
            background: rgba(23, 162, 184, 0.1);
            padding: 0.75rem 2rem;
            color: #0c5460;
            font-size: 0.9rem;
            border-left: 4px solid #17a2b8;
        }
        
        .main-content {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            margin: 0;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
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
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            color: #333;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .main-content {
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
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
    
    <nav class="navigation">
        <?php
        $accessible_screens = $permissions->getAccessibleScreens();
        foreach ($accessible_screens as $screen) {
            $active_class = ($page === $screen['name']) ? 'active ' . $screen['name'] : '';
            echo "<a href='?page={$screen['name']}' class='nav-tab {$active_class}'>{$screen['display_name']}</a>";
        }
        ?>
    </nav>
    
    <div class="permissions-bar">
        Your permissions for this page: <?php echo implode(', ', $permissions->getUserPermissions($page)); ?>
    </div>
    
    <div class="main-content">
        <?php
        // Display success message if present
        if (isset($_GET['success'])) {
            echo '<div class="success-message">Operation completed successfully!</div>';
        }
        
        // Display page content based on current page
        switch ($page) {
            case 'dashboard':
                ?>
                <div class="page-header">
                    <h2>Dashboard</h2>
                    <p>Overview of your car rental business</p>
                </div>
                
                <div class="stats-grid">
                    <?php
                    // Get statistics
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM vehicles");
                    $total_vehicles = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as available FROM vehicles WHERE status = 'available'");
                    $available_vehicles = $stmt->fetch()['available'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as active FROM reservations WHERE status = 'confirmed'");
                    $active_reservations = $stmt->fetch()['active'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM maintenance_schedules WHERE status = 'scheduled'");
                    $pending_maintenance = $stmt->fetch()['pending'];
                    ?>
                    
                    <div class="stat-card">
                        <h3>Total Vehicles</h3>
                        <div class="number"><?php echo $total_vehicles; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Available Vehicles</h3>
                        <div class="number"><?php echo $available_vehicles; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Active Reservations</h3>
                        <div class="number"><?php echo $active_reservations; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Pending Maintenance</h3>
                        <div class="number"><?php echo $pending_maintenance; ?></div>
                    </div>
                </div>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">Recent Activity</h3>
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
                                <input type="number" id="year" name="year" min="1990" max="2030" required>
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
                        <button type="submit" class="btn">Add Vehicle</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">Vehicle Inventory</h3>
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
                                echo "<td><button class='btn-edit'>Edit</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
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
                        <button type="submit" class="btn">Add Customer</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">Customer List</h3>
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
                                    while ($customer = $stmt->fetch()) {
                                        echo "<option value='{$customer['id']}'>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</option>";
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
                                    while ($vehicle = $stmt->fetch()) {
                                        echo "<option value='{$vehicle['id']}'>" . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) . " - $" . number_format($vehicle['daily_rate'], 2) . "/day</option>";
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
                        <button type="submit" class="btn">Create Reservation</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">Current Reservations</h3>
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
                
                <?php if ($permissions->hasPermission('maintenance', 'create')): ?>
                <div class="form-section">
                    <h3>Schedule Maintenance</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_maintenance">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="vehicle_id">Vehicle</label>
                                <select id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, make, model, license_plate FROM vehicles ORDER BY make, model");
                                    while ($vehicle = $stmt->fetch()) {
                                        echo "<option value='{$vehicle['id']}'>" . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')') . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="maintenance_type">Maintenance Type</label>
                                <input type="text" id="maintenance_type" name="maintenance_type" required>
                            </div>
                            <div class="form-group">
                                <label for="scheduled_date">Scheduled Date</label>
                                <input type="date" id="scheduled_date" name="scheduled_date" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3" required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn">Schedule Maintenance</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="data-table">
                    <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">Maintenance Schedule</h3>
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
                                ORDER BY m.scheduled_date DESC
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
                if ($permissions->isSuperAdmin()) {
                    ?>
                    <div class="page-header">
                        <h2>User Management</h2>
                        <p>Manage system users and their access</p>
                    </div>
                    
                    <div class="form-section">
                        <h3>Add New User</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_user">
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
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <?php
                                        $stmt = $pdo->query("SELECT id, name, display_name FROM roles ORDER BY name");
                                        while ($role = $stmt->fetch()) {
                                            echo "<option value='{$role['id']}'>" . htmlspecialchars($role['display_name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn">Add User</button>
                        </form>
                    </div>
                    
                    <div class="data-table">
                        <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">System Users</h3>
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
                                    SELECT u.*, GROUP_CONCAT(r.display_name) as role_names
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
                } else {
                    echo "<div class='page-header'><h2>Access Denied</h2><p>You don't have permission to access this page.</p></div>";
                }
                break;
                
            case 'roles':
                if ($permissions->isSuperAdmin()) {
                    ?>
                    <div class="page-header">
                        <h2>Role Management</h2>
                        <p>Manage user roles and permissions</p>
                    </div>
                    
                    <div class="data-table">
                        <h3 style="padding: 1rem; margin: 0; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">System Roles</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Display Name</th>
                                    <th>Description</th>
                                    <th>Users Count</th>
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
                } else {
                    echo "<div class='page-header'><h2>Access Denied</h2><p>You don't have permission to access this page.</p></div>";
                }
                break;
                
            default:
                echo "<div class='page-header'><h2>Page Not Found</h2><p>The requested page could not be found.</p></div>";
                break;
        }
        ?>
    </div>
</body>
</html>

