<?php
// Simple Car Rental ERP System with Fixed Layout
// Fixes CSS layout issues causing huge blank spaces
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
        // Check for account lockout
        $stmt = $this->pdo->prepare("
            SELECT failed_login_attempts, last_failed_login 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user_security = $stmt->fetch();
        
        if ($user_security) {
            $lockout_time = $this->calculateLockoutTime($user_security['failed_login_attempts']);
            if ($lockout_time > 0 && 
                $user_security['last_failed_login'] && 
                strtotime($user_security['last_failed_login']) > (time() - $lockout_time)) {
                return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts. Please try again later.'];
            }
        }
        
        // Attempt login
        $stmt = $this->pdo->prepare("
            SELECT id, email, first_name, last_name, password_hash, is_active, must_change_password
            FROM users 
            WHERE email = ? AND is_active = TRUE
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset failed login attempts
            $this->resetFailedLoginAttempts($email);
            
            // Create session
            $session_token = $this->createSession($user['id'], $remember_me);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['session_token'] = $session_token;
            $_SESSION['must_change_password'] = $user['must_change_password'];
            
            // Log successful login
            $this->logSecurityEvent($user['id'], 'login_success', 'User logged in successfully');
            
            return ['success' => true, 'user' => $user];
        } else {
            // Increment failed login attempts
            $this->incrementFailedLoginAttempts($email);
            
            // Log failed login
            if ($user) {
                $this->logSecurityEvent($user['id'], 'login_failed', 'Invalid password');
            } else {
                $this->logSecurityEvent(null, 'login_failed', 'Invalid email: ' . $email);
            }
            
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }
    
    private function calculateLockoutTime($failed_attempts) {
        if ($failed_attempts >= 15) return 3600; // 1 hour
        if ($failed_attempts >= 10) return 900;  // 15 minutes
        if ($failed_attempts >= 5) return 300;   // 5 minutes
        return 0;
    }
    
    private function incrementFailedLoginAttempts($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW()
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    private function resetFailedLoginAttempts($email) {
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
        $expires_at = $remember_me ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $token, $expires_at]);
        
        // Set cookie
        $cookie_time = $remember_me ? time() + (30 * 24 * 60 * 60) : 0;
        setcookie('session_token', $token, $cookie_time, '/', '', true, true);
        
        return $token;
    }
    
    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT us.user_id, u.email, u.first_name, u.last_name, u.must_change_password
            FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.session_token = ? AND us.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$_SESSION['session_token']]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Update session activity
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW() 
                WHERE session_token = ?
            ");
            $stmt->execute([$_SESSION['session_token']]);
            
            return $session;
        }
        
        return false;
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Remove session from database
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
            
            // Clear cookie
            setcookie('session_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Clear session
        session_destroy();
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password hash
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Update password
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, must_change_password = FALSE, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_hash, $user_id]);
        
        // Log password change
        $this->logSecurityEvent($user_id, 'password_changed', 'User changed password');
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    private function logSecurityEvent($user_id, $event_type, $description) {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (user_id, event_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $event_type,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}

// Permission Management Class
class Permission {
    private $pdo;
    private $user_id;
    private $user_roles = null;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    public function getUserRoles() {
        if ($this->user_roles === null) {
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.name, r.display_name, r.description
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ? AND r.is_active = TRUE
            ");
            $stmt->execute([$this->user_id]);
            $this->user_roles = $stmt->fetchAll();
        }
        return $this->user_roles;
    }
    
    public function canAccess($screen_name) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ? AND rp.can_view = TRUE
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function canCreate($screen_name) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ? AND rp.can_create = TRUE
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function canEdit($screen_name) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ? AND rp.can_edit = TRUE
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function canDelete($screen_name) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND s.name = ? AND rp.can_delete = TRUE
        ");
        $stmt->execute([$this->user_id, $screen_name]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function getPermissions($screen_name) {
        $permissions = [];
        if ($this->canAccess($screen_name)) $permissions[] = 'View';
        if ($this->canCreate($screen_name)) $permissions[] = 'Create';
        if ($this->canEdit($screen_name)) $permissions[] = 'Edit';
        if ($this->canDelete($screen_name)) $permissions[] = 'Delete';
        return $permissions;
    }
    
    public function getAccessibleScreens() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT s.name, s.display_name, s.description
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND rp.can_view = TRUE
            ORDER BY s.display_order
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll();
    }
}

// Initialize classes
$user = new User($pdo);
$current_user = null;
$permission = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $current_user = $user->validateSession();
    if ($current_user) {
        $permission = new Permission($pdo, $current_user['user_id']);
    } else {
        // Invalid session, redirect to login
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $user->logout();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    $login_result = $user->login($email, $password, $remember_me);
    
    if ($login_result['success']) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error_message = $login_result['message'];
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!$current_user) {
        $error_message = 'User not found';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match';
        } elseif (strlen($new_password) < 8) {
            $error_message = 'Password must be at least 8 characters long';
        } else {
            $change_result = $user->changePassword($current_user['user_id'], $current_password, $new_password);
            if ($change_result['success']) {
                $_SESSION['must_change_password'] = false;
                $success_message = $change_result['message'];
                // Refresh user data
                $current_user = $user->validateSession();
            } else {
                $error_message = $change_result['message'];
            }
        }
    }
}

// If not logged in, show login page
if (!$current_user) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Car Rental ERP - Login</title>
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
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            
            .login-form {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            
            .login-form h1 {
                text-align: center;
                margin-bottom: 10px;
                color: #333;
            }
            
            .login-form p {
                text-align: center;
                color: #666;
                margin-bottom: 30px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #333;
                font-weight: 500;
            }
            
            .form-group input[type="email"],
            .form-group input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            
            .form-group input[type="email"]:focus,
            .form-group input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .checkbox-group {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .checkbox-group input[type="checkbox"] {
                margin-right: 8px;
            }
            
            .btn {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
            
            .alert {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            
            .alert-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
        </style>
    </head>
    <body>
        <form method="POST" class="login-form">
            <h1>Car Rental ERP</h1>
            <p>Please sign in to your account</p>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
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
            
            <button type="submit" class="btn">Sign In</button>
            <input type="hidden" name="action" value="login">
        </form>
    </body>
    </html>
    <?php
    exit;
}

// If user must change password, show password change form
if ($current_user['must_change_password']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Car Rental ERP - Change Password</title>
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
                padding: 20px;
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 1rem 2rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .user-info {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .logout-btn {
                background: rgba(255,255,255,0.2);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                transition: background 0.3s;
            }
            
            .logout-btn:hover {
                background: rgba(255,255,255,0.3);
            }
            
            .password-change-container {
                max-width: 500px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #333;
                font-weight: 500;
            }
            
            .form-group input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            
            .form-group input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .btn {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
            
            .alert {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            
            .alert-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            
            .alert-warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
            }
            
            .password-requirements {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            
            .password-requirements h4 {
                margin-bottom: 10px;
                color: #333;
            }
            
            .password-requirements ul {
                margin-left: 20px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Car Rental ERP System</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
                <a href="?action=logout" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="password-change-container">
            <h2>Password Change Required</h2>
            
            <div class="alert alert-warning">
                <strong>Security Notice:</strong> You must change your password before accessing the system.
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Mix of uppercase and lowercase letters recommended</li>
                        <li>Include numbers and special characters for better security</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn">Change Password</button>
                <input type="hidden" name="action" value="change_password">
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// Check if user has access to the requested page
if (!$permission->canAccess($page)) {
    $page = 'dashboard'; // Fallback to dashboard
}

// Get accessible screens for navigation
$accessible_screens = $permission->getAccessibleScreens();

// Handle form submissions for each page
$success_message = '';
$error_message = '';

// Vehicle management
if ($page === 'vehicles' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_vehicle' && $permission->canCreate('vehicles')) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())
            ");
            $stmt->execute([
                $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'],
                $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']
            ]);
            $success_message = "Vehicle added successfully!";
        } catch (Exception $e) {
            $error_message = "Error adding vehicle: " . $e->getMessage();
        }
    }
}

// Customer management
if ($page === 'customers' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_customer' && $permission->canCreate('customers')) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
                $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']
            ]);
            $success_message = "Customer added successfully!";
        } catch (Exception $e) {
            $error_message = "Error adding customer: " . $e->getMessage();
        }
    }
}

// Reservation management
if ($page === 'reservations' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_reservation' && $permission->canCreate('reservations')) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())
            ");
            $stmt->execute([
                $_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'],
                $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']
            ]);
            $success_message = "Reservation added successfully!";
        } catch (Exception $e) {
            $error_message = "Error adding reservation: " . $e->getMessage();
        }
    }
}

// Maintenance management
if ($page === 'maintenance' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_maintenance' && $permission->canCreate('maintenance')) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description, status, created_at)
                VALUES (?, ?, ?, ?, 'scheduled', NOW())
            ");
            $stmt->execute([
                $_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['description']
            ]);
            $success_message = "Maintenance scheduled successfully!";
        } catch (Exception $e) {
            $error_message = "Error scheduling maintenance: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP - Fixed Layout</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .nav-tabs {
            background: #fff;
            padding: 0 2rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .nav-tab {
            padding: 15px 25px;
            background: none;
            border: none;
            color: #666;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
            position: relative;
        }
        
        .nav-tab:hover {
            color: #333;
            background: #f8f9fa;
        }
        
        .nav-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }
        
        /* Colorful tab indicators */
        .nav-tab[data-page="dashboard"].active { border-bottom-color: #007bff; color: #007bff; }
        .nav-tab[data-page="vehicles"].active { border-bottom-color: #ffc107; color: #ffc107; }
        .nav-tab[data-page="customers"].active { border-bottom-color: #6f42c1; color: #6f42c1; }
        .nav-tab[data-page="reservations"].active { border-bottom-color: #20c997; color: #20c997; }
        .nav-tab[data-page="maintenance"].active { border-bottom-color: #e83e8c; color: #e83e8c; }
        .nav-tab[data-page="users"].active { border-bottom-color: #dc3545; color: #dc3545; }
        .nav-tab[data-page="roles"].active { border-bottom-color: #fd7e14; color: #fd7e14; }
        
        .main-content {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .permission-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-tabs {
                padding: 0 1rem;
                overflow-x: auto;
            }
            
            .nav-tab {
                padding: 12px 20px;
                white-space: nowrap;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Car Rental ERP System</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
            <?php 
            $roles = $permission->getUserRoles();
            if (!empty($roles)): 
            ?>
                <span class="user-badge"><?php echo htmlspecialchars($roles[0]['display_name']); ?></span>
            <?php endif; ?>
            <a href="?action=logout" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="nav-tabs">
        <?php foreach ($accessible_screens as $screen): ?>
            <a href="?page=<?php echo $screen['name']; ?>" 
               class="nav-tab <?php echo $page === $screen['name'] ? 'active' : ''; ?>"
               data-page="<?php echo $screen['name']; ?>">
                <?php echo htmlspecialchars($screen['display_name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="main-content">
        <div class="permission-info">
            Your permissions for this page: <?php echo implode(', ', $permission->getPermissions($page)); ?>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php
        // Display page content based on current page
        switch ($page) {
            case 'dashboard':
                // Get dashboard statistics
                $stats = [];
                $stats['total_vehicles'] = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
                $stats['available_vehicles'] = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'")->fetchColumn();
                $stats['active_reservations'] = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed'")->fetchColumn();
                $stats['pending_maintenance'] = $pdo->query("SELECT COUNT(*) FROM maintenance_schedules WHERE status = 'scheduled'")->fetchColumn();
                
                // Get recent activity
                $recent_activity = $pdo->query("
                    SELECT r.created_at as date, 
                           CONCAT(c.first_name, ' ', c.last_name) as customer,
                           CONCAT(v.make, ' ', v.model) as vehicle,
                           r.status,
                           r.total_amount
                    FROM reservations r
                    JOIN customers c ON r.customer_id = c.id
                    JOIN vehicles v ON r.vehicle_id = v.id
                    ORDER BY r.created_at DESC
                    LIMIT 5
                ")->fetchAll();
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_vehicles']; ?></div>
                        <div class="stat-label">Total Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['available_vehicles']; ?></div>
                        <div class="stat-label">Available Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['active_reservations']; ?></div>
                        <div class="stat-label">Active Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_maintenance']; ?></div>
                        <div class="stat-label">Pending Maintenance</div>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title">Recent Activity</h2>
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
                                <?php foreach ($recent_activity as $activity): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($activity['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($activity['customer']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['vehicle']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['status']); ?></td>
                                        <td>$<?php echo number_format($activity['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'vehicles':
                // Get all vehicles
                $vehicles = $pdo->query("
                    SELECT * FROM vehicles 
                    ORDER BY created_at DESC
                ")->fetchAll();
                ?>
                
                <?php if ($permission->canCreate('vehicles')): ?>
                    <div class="content-section">
                        <h2 class="section-title">Add New Vehicle</h2>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="make">Make:</label>
                                    <input type="text" id="make" name="make" required>
                                </div>
                                <div class="form-group">
                                    <label for="model">Model:</label>
                                    <input type="text" id="model" name="model" required>
                                </div>
                                <div class="form-group">
                                    <label for="year">Year:</label>
                                    <input type="number" id="year" name="year" min="1900" max="2030" required>
                                </div>
                                <div class="form-group">
                                    <label for="vin">VIN:</label>
                                    <input type="text" id="vin" name="vin" required>
                                </div>
                                <div class="form-group">
                                    <label for="license_plate">License Plate:</label>
                                    <input type="text" id="license_plate" name="license_plate" required>
                                </div>
                                <div class="form-group">
                                    <label for="color">Color:</label>
                                    <input type="text" id="color" name="color" required>
                                </div>
                                <div class="form-group">
                                    <label for="mileage">Mileage:</label>
                                    <input type="number" id="mileage" name="mileage" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label for="daily_rate">Daily Rate ($):</label>
                                    <input type="number" id="daily_rate" name="daily_rate" min="0" step="0.01" required>
                                </div>
                            </div>
                            <button type="submit" class="btn">Add Vehicle</button>
                            <input type="hidden" name="action" value="add_vehicle">
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="content-section">
                    <h2 class="section-title">Vehicle Inventory</h2>
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
                                    <?php if ($permission->canEdit('vehicles')): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vehicle['make']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['status']); ?></td>
                                        <td>$<?php echo number_format($vehicle['daily_rate'], 2); ?></td>
                                        <td><?php echo number_format($vehicle['mileage']); ?></td>
                                        <?php if ($permission->canEdit('vehicles')): ?>
                                            <td>
                                                <button class="btn btn-secondary">Edit</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'customers':
                // Get all customers
                $customers = $pdo->query("
                    SELECT * FROM customers 
                    ORDER BY created_at DESC
                ")->fetchAll();
                ?>
                
                <?php if ($permission->canCreate('customers')): ?>
                    <div class="content-section">
                        <h2 class="section-title">Add New Customer</h2>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name:</label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name:</label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone:</label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                                <div class="form-group">
                                    <label for="address">Address:</label>
                                    <textarea id="address" name="address" rows="3" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="driver_license">Driver License:</label>
                                    <input type="text" id="driver_license" name="driver_license" required>
                                </div>
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth:</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" required>
                                </div>
                            </div>
                            <button type="submit" class="btn">Add Customer</button>
                            <input type="hidden" name="action" value="add_customer">
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="content-section">
                    <h2 class="section-title">Customer Directory</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Driver License</th>
                                    <th>Date of Birth</th>
                                    <?php if ($permission->canEdit('customers')): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['driver_license']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($customer['date_of_birth'])); ?></td>
                                        <?php if ($permission->canEdit('customers')): ?>
                                            <td>
                                                <button class="btn btn-secondary">Edit</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'reservations':
                // Get customers and vehicles for dropdowns
                $customers = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY first_name")->fetchAll();
                $vehicles = $pdo->query("SELECT id, make, model, year, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make")->fetchAll();
                
                // Get all reservations
                $reservations = $pdo->query("
                    SELECT r.*, 
                           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                           CONCAT(v.make, ' ', v.model, ' ', v.year) as vehicle_name
                    FROM reservations r
                    JOIN customers c ON r.customer_id = c.id
                    JOIN vehicles v ON r.vehicle_id = v.id
                    ORDER BY r.created_at DESC
                ")->fetchAll();
                ?>
                
                <?php if ($permission->canCreate('reservations')): ?>
                    <div class="content-section">
                        <h2 class="section-title">Create New Reservation</h2>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="customer_id">Customer:</label>
                                    <select id="customer_id" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>">
                                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="vehicle_id">Vehicle:</label>
                                    <select id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year'] . ' - $' . $vehicle['daily_rate'] . '/day'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Start Date:</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date:</label>
                                    <input type="date" id="end_date" name="end_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="pickup_location">Pickup Location:</label>
                                    <input type="text" id="pickup_location" name="pickup_location" required>
                                </div>
                                <div class="form-group">
                                    <label for="dropoff_location">Dropoff Location:</label>
                                    <input type="text" id="dropoff_location" name="dropoff_location" required>
                                </div>
                                <div class="form-group">
                                    <label for="total_amount">Total Amount ($):</label>
                                    <input type="number" id="total_amount" name="total_amount" min="0" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="notes">Notes:</label>
                                    <textarea id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn">Create Reservation</button>
                            <input type="hidden" name="action" value="add_reservation">
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="content-section">
                    <h2 class="section-title">Reservation History</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Total Amount</th>
                                    <?php if ($permission->canEdit('reservations')): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['vehicle_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($reservation['start_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($reservation['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['status']); ?></td>
                                        <td>$<?php echo number_format($reservation['total_amount'], 2); ?></td>
                                        <?php if ($permission->canEdit('reservations')): ?>
                                            <td>
                                                <button class="btn btn-secondary">Edit</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'maintenance':
                // Get vehicles for dropdown
                $vehicles = $pdo->query("SELECT id, make, model, year FROM vehicles ORDER BY make")->fetchAll();
                
                // Get all maintenance schedules
                $maintenance = $pdo->query("
                    SELECT ms.*, 
                           CONCAT(v.make, ' ', v.model, ' ', v.year) as vehicle_name
                    FROM maintenance_schedules ms
                    JOIN vehicles v ON ms.vehicle_id = v.id
                    ORDER BY ms.scheduled_date DESC
                ")->fetchAll();
                ?>
                
                <?php if ($permission->canCreate('maintenance')): ?>
                    <div class="content-section">
                        <h2 class="section-title">Schedule Maintenance</h2>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="vehicle_id">Vehicle:</label>
                                    <select id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="maintenance_type">Maintenance Type:</label>
                                    <input type="text" id="maintenance_type" name="maintenance_type" required>
                                </div>
                                <div class="form-group">
                                    <label for="scheduled_date">Scheduled Date:</label>
                                    <input type="date" id="scheduled_date" name="scheduled_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description:</label>
                                    <textarea id="description" name="description" rows="3" required></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn">Schedule Maintenance</button>
                            <input type="hidden" name="action" value="add_maintenance">
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="content-section">
                    <h2 class="section-title">Maintenance Schedule</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Maintenance Type</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <?php if ($permission->canEdit('maintenance')): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['vehicle_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['maintenance_type']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($item['scheduled_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['status']); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <?php if ($permission->canEdit('maintenance')): ?>
                                            <td>
                                                <button class="btn btn-secondary">Edit</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'users':
                if ($permission->canAccess('users')):
                    // Get all users
                    $users = $pdo->query("
                        SELECT u.*, GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles
                        FROM users u
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        GROUP BY u.id
                        ORDER BY u.created_at DESC
                    ")->fetchAll();
                    ?>
                    
                    <div class="content-section">
                        <h2 class="section-title">User Management</h2>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Roles</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <?php if ($permission->canEdit('users')): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user_item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user_item['roles'] ?: 'No roles assigned'); ?></td>
                                            <td><?php echo $user_item['is_active'] ? 'Active' : 'Inactive'; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($user_item['created_at'])); ?></td>
                                            <?php if ($permission->canEdit('users')): ?>
                                                <td>
                                                    <button class="btn btn-secondary">Edit</button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php
                else:
                    echo '<div class="content-section"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
                endif;
                break;
                
            case 'roles':
                if ($permission->canAccess('roles')):
                    // Get all roles
                    $roles = $pdo->query("
                        SELECT r.*, COUNT(ur.user_id) as user_count
                        FROM roles r
                        LEFT JOIN user_roles ur ON r.id = ur.role_id
                        GROUP BY r.id
                        ORDER BY r.created_at DESC
                    ")->fetchAll();
                    ?>
                    
                    <div class="content-section">
                        <h2 class="section-title">Role Management</h2>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Role Name</th>
                                        <th>Display Name</th>
                                        <th>Description</th>
                                        <th>Users</th>
                                        <th>Status</th>
                                        <?php if ($permission->canEdit('roles')): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($role['name']); ?></td>
                                            <td><?php echo htmlspecialchars($role['display_name']); ?></td>
                                            <td><?php echo htmlspecialchars($role['description']); ?></td>
                                            <td><?php echo $role['user_count']; ?></td>
                                            <td><?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?></td>
                                            <?php if ($permission->canEdit('roles')): ?>
                                                <td>
                                                    <button class="btn btn-secondary">Edit</button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php
                else:
                    echo '<div class="content-section"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
                endif;
                break;
                
            default:
                echo '<div class="content-section"><h2>Page Not Found</h2><p>The requested page could not be found.</p></div>';
        }
        ?>
    </div>
</body>
</html>

