<?php
// Simple Car Rental ERP System with Role-Based Access Control
// Phase 3: Role-Based Access Control Implementation
// Maintains SIMPLE, SIMPLE, SIMPLE architecture with enterprise security

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

// Authentication Classes
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function authenticate($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $this->logFailedAttempt($email);
            return false;
        }
        
        // Check if account is locked
        if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
            return false;
        }
        
        if (password_verify($password, $user['password_hash'])) {
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            return $user;
        } else {
            $this->incrementFailedAttempts($user['id']);
            return false;
        }
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUserRoles($userId) {
        $stmt = $this->pdo->prepare("
            SELECT r.* FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? AND r.is_active = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function logFailedAttempt($email) {
        error_log("Failed login attempt for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
    }
    
    private function resetFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function incrementFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts >= 9 THEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    WHEN failed_login_attempts >= 2 THEN DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                    ELSE NULL
                END
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
}

class Session {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createSession($userId, $rememberMe = false) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = $rememberMe ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, 
            $token, 
            $expiresAt, 
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Set secure cookie
        $cookieOptions = [
            'expires' => $rememberMe ? strtotime('+30 days') : 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        setcookie('auth_token', $token, $cookieOptions);
        $_SESSION['user_id'] = $userId;
        $_SESSION['auth_token'] = $token;
        
        return $token;
    }
    
    public function validateSession() {
        $token = $_COOKIE['auth_token'] ?? $_SESSION['auth_token'] ?? null;
        
        if (!$token) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.* FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.session_token = ? AND s.expires_at > NOW() AND s.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Update last activity
            $this->updateLastActivity($token);
            $_SESSION['user_id'] = $session['user_id'];
            return $session;
        }
        
        return false;
    }
    
    public function destroySession($token = null) {
        $token = $token ?? $_COOKIE['auth_token'] ?? $_SESSION['auth_token'] ?? null;
        
        if ($token) {
            $stmt = $this->pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
            $stmt->execute([$token]);
        }
        
        // Clear cookies and session
        setcookie('auth_token', '', time() - 3600, '/');
        session_destroy();
    }
    
    private function updateLastActivity($token) {
        $stmt = $this->pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?");
        $stmt->execute([$token]);
    }
}

// NEW: Permission Class for Role-Based Access Control
class Permission {
    private $pdo;
    private $userPermissions = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function loadUserPermissions($userId) {
        $stmt = $this->pdo->prepare("
            SELECT s.name as screen_name, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND r.is_active = 1 AND s.is_active = 1
        ");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize permissions by screen
        $this->userPermissions = [];
        foreach ($permissions as $perm) {
            $screen = $perm['screen_name'];
            if (!isset($this->userPermissions[$screen])) {
                $this->userPermissions[$screen] = [
                    'can_view' => false,
                    'can_create' => false,
                    'can_edit' => false,
                    'can_delete' => false
                ];
            }
            
            // Use OR logic - if any role grants permission, user has it
            $this->userPermissions[$screen]['can_view'] = $this->userPermissions[$screen]['can_view'] || $perm['can_view'];
            $this->userPermissions[$screen]['can_create'] = $this->userPermissions[$screen]['can_create'] || $perm['can_create'];
            $this->userPermissions[$screen]['can_edit'] = $this->userPermissions[$screen]['can_edit'] || $perm['can_edit'];
            $this->userPermissions[$screen]['can_delete'] = $this->userPermissions[$screen]['can_delete'] || $perm['can_delete'];
        }
        
        return $this->userPermissions;
    }
    
    public function canAccess($screen, $action = 'view') {
        if (!isset($this->userPermissions[$screen])) {
            return false;
        }
        
        $actionKey = 'can_' . $action;
        return isset($this->userPermissions[$screen][$actionKey]) ? 
               $this->userPermissions[$screen][$actionKey] : false;
    }
    
    public function getAccessibleScreens() {
        $accessible = [];
        foreach ($this->userPermissions as $screen => $perms) {
            if ($perms['can_view']) {
                $accessible[] = $screen;
            }
        }
        return $accessible;
    }
    
    public function getUserRoles($userId) {
        $stmt = $this->pdo->prepare("
            SELECT r.name FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? AND r.is_active = 1
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }
    
    public function isSuperAdmin($userId) {
        $roles = $this->getUserRoles($userId);
        return in_array('Super Admin', $roles);
    }
}

// Initialize authentication and permissions
$userAuth = new User($pdo);
$sessionManager = new Session($pdo);
$permissionManager = new Permission($pdo);

// Check if user is logged in
$currentUser = $sessionManager->validateSession();
$isLoggedIn = $currentUser !== false;

// Load user permissions if logged in
$userPermissions = [];
$accessibleScreens = [];
$isSuperAdmin = false;

if ($isLoggedIn) {
    $userPermissions = $permissionManager->loadUserPermissions($currentUser['user_id']);
    $accessibleScreens = $permissionManager->getAccessibleScreens();
    $isSuperAdmin = $permissionManager->isSuperAdmin($currentUser['user_id']);
}

// Handle authentication actions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['login'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if ($email && $password) {
            $user = $userAuth->authenticate($email, $password);
            if ($user) {
                $sessionManager->createSession($user['id'], $rememberMe);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "Invalid email or password. Please try again.";
            }
        } else {
            $error = "Please enter both email and password.";
        }
    }
    
    if (isset($_POST['logout'])) {
        $sessionManager->destroySession();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// If not logged in, show login form
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Car Rental ERP</title>
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
            
            label {
                display: block;
                margin-bottom: 0.5rem;
                color: #333;
                font-weight: 500;
            }
            
            input[type="email"], input[type="password"] {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 1rem;
                transition: border-color 0.3s;
            }
            
            input[type="email"]:focus, input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .checkbox-group {
                display: flex;
                align-items: center;
                margin-bottom: 1.5rem;
            }
            
            .checkbox-group input[type="checkbox"] {
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
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .login-btn:hover {
                transform: translateY(-2px);
            }
            
            .error {
                background: #fee;
                color: #c33;
                padding: 0.75rem;
                border-radius: 5px;
                margin-bottom: 1rem;
                border: 1px solid #fcc;
            }
            
            .forgot-password {
                text-align: center;
                margin-top: 1rem;
            }
            
            .forgot-password a {
                color: #667eea;
                text-decoration: none;
                font-size: 0.9rem;
            }
            
            .forgot-password a:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 480px) {
                .login-container {
                    margin: 1rem;
                    padding: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>Car Rental ERP</h1>
                <p>Please sign in to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
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
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Remember me for 30 days</label>
                </div>
                
                <button type="submit" name="login" class="login-btn">Sign In</button>
            </form>
            
            <div class="forgot-password">
                <a href="?forgot_password=1">Forgot your password?</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get current page and validate access
$page = $_GET['page'] ?? 'dashboard';

// Check if user has access to the requested page
if (!$permissionManager->canAccess($page, 'view')) {
    // If user doesn't have access, redirect to first accessible page or show error
    if (!empty($accessibleScreens)) {
        $page = $accessibleScreens[0]; // Redirect to first accessible page
    } else {
        $error = "You don't have permission to access any screens. Please contact your administrator.";
        $page = 'dashboard'; // Fallback
    }
}

// Handle form submissions (existing ERP functionality with permission checks)
if ($_POST && !isset($_POST['login']) && !isset($_POST['logout'])) {
    try {
        if (isset($_POST['add_vehicle']) && $permissionManager->canAccess('vehicles', 'create')) {
            $stmt = $pdo->prepare("INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], 
                $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']
            ]);
            $message = "Vehicle added successfully!";
        }
        
        if (isset($_POST['add_customer']) && $permissionManager->canAccess('customers', 'create')) {
            $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], 
                $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']
            ]);
            $message = "Customer added successfully!";
        }
        
        if (isset($_POST['add_reservation']) && $permissionManager->canAccess('reservations', 'create')) {
            $start_date = date('Y-m-d', strtotime($_POST['start_date']));
            $end_date = date('Y-m-d', strtotime($_POST['end_date']));
            
            $stmt = $pdo->prepare("INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['customer_id'], $_POST['vehicle_id'], $start_date, $end_date, 
                $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']
            ]);
            $message = "Reservation added successfully!";
        }
        
        if (isset($_POST['schedule_maintenance']) && $permissionManager->canAccess('maintenance', 'create')) {
            $scheduled_date = date('Y-m-d', strtotime($_POST['scheduled_date']));
            
            $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['vehicle_id'], $_POST['maintenance_type'], $scheduled_date, $_POST['description']
            ]);
            $message = "Maintenance scheduled successfully!";
        }
        
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Function to get screen display name
function getScreenDisplayName($screenName, $pdo) {
    $stmt = $pdo->prepare("SELECT display_name FROM screens WHERE name = ?");
    $stmt->execute([$screenName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['display_name'] : ucfirst($screenName);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP - Role-Based System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .user-roles {
            font-size: 0.8rem;
            opacity: 0.9;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .nav {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .nav a {
            text-decoration: none;
            color: #333;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            transition: all 0.3s;
            font-weight: 500;
            border: 2px solid transparent;
        }
        
        .nav a:hover, .nav a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .nav a.disabled {
            color: #ccc;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .nav a.disabled:hover {
            background: none;
            color: #ccc;
            transform: none;
        }
        
        .content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        
        .permission-denied {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #ffeaa7;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .form-section.disabled {
            opacity: 0.6;
            background: #f5f5f5;
            border-left-color: #ccc;
        }
        
        .form-section h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input:disabled, select:disabled, textarea:disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        button:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .permission-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #1565c0;
        }
        
        @media (max-width: 768px) {
            .nav ul {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
            
            .content {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Car Rental ERP System</h1>
            <div class="user-info">
                <div>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></span>
                    <div class="user-roles"><?php echo implode(', ', $permissionManager->getUserRoles($currentUser['user_id'])); ?></div>
                </div>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <nav class="nav">
            <ul>
                <?php
                // Dynamic navigation based on user permissions
                $allScreens = [
                    'dashboard' => 'Dashboard',
                    'vehicles' => 'Vehicles', 
                    'customers' => 'Customers',
                    'reservations' => 'Reservations',
                    'maintenance' => 'Maintenance',
                    'users' => 'Users',
                    'roles' => 'Roles'
                ];
                
                foreach ($allScreens as $screenName => $displayName) {
                    $hasAccess = $permissionManager->canAccess($screenName, 'view');
                    $activeClass = ($page === $screenName) ? 'active' : '';
                    $disabledClass = !$hasAccess ? 'disabled' : '';
                    
                    if ($hasAccess) {
                        echo "<li><a href=\"?page=$screenName\" class=\"$activeClass\">$displayName</a></li>";
                    } else {
                        echo "<li><a href=\"#\" class=\"$disabledClass\" title=\"No access\">$displayName</a></li>";
                    }
                }
                ?>
            </ul>
        </nav>

        <div class="content">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php
            // Show permission info for current page
            if (isset($userPermissions[$page])) {
                $perms = $userPermissions[$page];
                $permList = [];
                if ($perms['can_view']) $permList[] = 'View';
                if ($perms['can_create']) $permList[] = 'Create';
                if ($perms['can_edit']) $permList[] = 'Edit';
                if ($perms['can_delete']) $permList[] = 'Delete';
                
                if (!empty($permList)) {
                    echo "<div class=\"permission-info\">Your permissions for this page: " . implode(', ', $permList) . "</div>";
                }
            }
            
            // Include page content based on permissions and current page
            if ($page === 'dashboard' && $permissionManager->canAccess('dashboard', 'view')) {
                // Dashboard content
                $vehicleCount = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
                $availableVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'Available'")->fetchColumn();
                $activeReservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE start_date <= CURDATE() AND end_date >= CURDATE()")->fetchColumn();
                $pendingMaintenance = $pdo->query("SELECT COUNT(*) FROM maintenance_schedules WHERE status = 'scheduled'")->fetchColumn();
                ?>
                
                <h2>Dashboard</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $vehicleCount; ?></div>
                        <div class="stat-label">Total Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $availableVehicles; ?></div>
                        <div class="stat-label">Available Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $activeReservations; ?></div>
                        <div class="stat-label">Active Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $pendingMaintenance; ?></div>
                        <div class="stat-label">Pending Maintenance</div>
                    </div>
                </div>
                
                <h3>Recent Activity</h3>
                <div class="table-container">
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
                                SELECT r.start_date, 
                                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                                       CONCAT(v.make, ' ', v.model) as vehicle_name,
                                       r.status,
                                       r.total_amount
                                FROM reservations r
                                JOIN customers c ON r.customer_id = c.id
                                JOIN vehicles v ON r.vehicle_id = v.id
                                ORDER BY r.start_date DESC
                                LIMIT 10
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($row['start_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['vehicle_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            
            elseif ($page === 'vehicles' && $permissionManager->canAccess('vehicles', 'view')) {
                $canCreate = $permissionManager->canAccess('vehicles', 'create');
                $canEdit = $permissionManager->canAccess('vehicles', 'edit');
                ?>
                <h2>Vehicle Management</h2>
                
                <div class="form-section <?php echo !$canCreate ? 'disabled' : ''; ?>">
                    <h3>Add New Vehicle</h3>
                    <?php if (!$canCreate): ?>
                        <div class="permission-denied">You don't have permission to add new vehicles.</div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="make">Make:</label>
                                <input type="text" id="make" name="make" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="model">Model:</label>
                                <input type="text" id="model" name="model" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="year">Year:</label>
                                <input type="number" id="year" name="year" min="1900" max="2030" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="vin">VIN:</label>
                                <input type="text" id="vin" name="vin" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license_plate">License Plate:</label>
                                <input type="text" id="license_plate" name="license_plate" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="color">Color:</label>
                                <input type="text" id="color" name="color" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mileage">Mileage:</label>
                                <input type="number" id="mileage" name="mileage" min="0" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="daily_rate">Daily Rate ($):</label>
                                <input type="number" id="daily_rate" name="daily_rate" min="0" step="0.01" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <button type="submit" name="add_vehicle" <?php echo !$canCreate ? 'disabled' : ''; ?>>Add Vehicle</button>
                    </form>
                </div>
                
                <h3>Vehicle Inventory</h3>
                <div class="table-container">
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
                                <?php if ($canEdit): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['make']); ?></td>
                                    <td><?php echo htmlspecialchars($row['model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['year']); ?></td>
                                    <td><?php echo htmlspecialchars($row['license_plate']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td>$<?php echo number_format($row['daily_rate'], 2); ?></td>
                                    <td><?php echo number_format($row['mileage']); ?></td>
                                    <?php if ($canEdit): ?>
                                        <td>
                                            <button onclick="alert('Edit functionality would be implemented here')">Edit</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            
            elseif ($page === 'customers' && $permissionManager->canAccess('customers', 'view')) {
                $canCreate = $permissionManager->canAccess('customers', 'create');
                ?>
                <h2>Customer Management</h2>
                
                <div class="form-section <?php echo !$canCreate ? 'disabled' : ''; ?>">
                    <h3>Add New Customer</h3>
                    <?php if (!$canCreate): ?>
                        <div class="permission-denied">You don't have permission to add new customers.</div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name:</label>
                                <input type="text" id="first_name" name="first_name" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone:</label>
                                <input type="tel" id="phone" name="phone" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address">Address:</label>
                            <textarea id="address" name="address" rows="3" <?php echo !$canCreate ? 'disabled' : 'required'; ?>></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="driver_license">Driver License:</label>
                                <input type="text" id="driver_license" name="driver_license" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth:</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" <?php echo !$canCreate ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>
                        <button type="submit" name="add_customer" <?php echo !$canCreate ? 'disabled' : ''; ?>>Add Customer</button>
                    </form>
                </div>
                
                <h3>Customer List</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Driver License</th>
                                <th>Date of Birth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['driver_license']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($row['date_of_birth'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            
            // Add other pages (reservations, maintenance, users, roles) with similar permission checks
            elseif (in_array($page, ['reservations', 'maintenance', 'users', 'roles'])) {
                if ($permissionManager->canAccess($page, 'view')) {
                    echo "<h2>" . getScreenDisplayName($page, $pdo) . "</h2>";
                    echo "<p>This page is accessible to you and will be fully implemented with role-based controls.</p>";
                    
                    if ($page === 'users' || $page === 'roles') {
                        if (!$isSuperAdmin) {
                            echo "<div class=\"permission-denied\">Full access to this page requires Super Admin privileges.</div>";
                        }
                    }
                } else {
                    echo "<div class=\"permission-denied\">You don't have permission to access this page.</div>";
                }
            }
            
            else {
                echo "<div class=\"permission-denied\">You don't have permission to access this page.</div>";
            }
            ?>
        </div>
    </div>
</body>
</html>

