<?php
// Simple Car Rental ERP System with Complete Edit Functionality
// Phase 5: Complete Edit Implementation
// Maintains SIMPLE, SIMPLE, SIMPLE architecture with full CRUD operations
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

// Password Recovery Class
class PasswordRecovery {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Generate secure password reset token
    public function generateResetToken($email) {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Generate secure token (32 bytes = 64 hex characters)
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
        
        // Store token in database
        $stmt = $this->pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at), 
            created_at = NOW()
        ");
        $stmt->execute([$user['id'], $token, $expires_at]);
        
        return $token;
    }
    
    // Send password reset email
    public function sendResetEmail($email, $token) {
        $reset_link = "https://admin.infiniteautorentals.com/reset-password.php?token=" . $token;
        
        $subject = "Password Reset Request - Car Rental ERP";
        $message = "
        <html>
        <head>
            <title>Password Reset Request</title>
        </head>
        <body>
            <h2>Password Reset Request</h2>
            <p>You have requested a password reset for your Car Rental ERP account.</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='{$reset_link}'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this reset, please ignore this email.</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@infiniteautorentals.com" . "\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
    
    // Generate new temporary password and email it
    public function generateAndEmailTemporaryPassword($email) {
        // Generate secure temporary password
        $temp_password = 'Temp' . rand(1000, 9999) . '!';
        
        // Update user password
        $password_hash = password_hash($temp_password, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, must_change_password = TRUE, updated_at = NOW()
            WHERE email = ? AND is_active = TRUE
        ");
        $stmt->execute([$password_hash, $email]);
        
        if ($stmt->rowCount() > 0) {
            // Send email with temporary password
            $subject = "Temporary Password - Car Rental ERP";
            $message = "
            <html>
            <head>
                <title>Temporary Password</title>
            </head>
            <body>
                <h2>Temporary Password</h2>
                <p>Your temporary password is: <strong>{$temp_password}</strong></p>
                <p>Please log in and change your password immediately.</p>
                <p><a href='https://admin.infiniteautorentals.com'>Login Here</a></p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@infiniteautorentals.com" . "\r\n";
            
            return mail($email, $subject, $message, $headers);
        }
        
        return false;
    }
}

// User Authentication Class
class UserAuth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Authenticate user login
    public function login($email, $password, $remember_me = false) {
        // Check for account lockout
        if ($this->isAccountLocked($email)) {
            return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts. Please try again later.'];
        }
        
        // Get user from database
        $stmt = $this->pdo->prepare("
            SELECT id, email, password_hash, first_name, last_name, is_active, must_change_password, failed_login_attempts, last_failed_login
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is deactivated. Please contact administrator.'];
            }
            
            // Reset failed login attempts
            $this->resetFailedLoginAttempts($email);
            
            // Create session
            $session_token = $this->createSession($user['id'], $remember_me);
            
            return [
                'success' => true, 
                'user' => $user, 
                'session_token' => $session_token,
                'must_change_password' => $user['must_change_password']
            ];
        } else {
            // Record failed login attempt
            $this->recordFailedLoginAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
    }
    
    // Check if account is locked
    private function isAccountLocked($email) {
        $stmt = $this->pdo->prepare("
            SELECT failed_login_attempts, last_failed_login 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        $attempts = $user['failed_login_attempts'];
        $last_failed = $user['last_failed_login'];
        
        if ($attempts >= 15) {
            // 1 hour lockout after 15 attempts
            return strtotime($last_failed) > (time() - 3600);
        } elseif ($attempts >= 10) {
            // 15 minute lockout after 10 attempts
            return strtotime($last_failed) > (time() - 900);
        } elseif ($attempts >= 5) {
            // 5 minute lockout after 5 attempts
            return strtotime($last_failed) > (time() - 300);
        }
        
        return false;
    }
    
    // Record failed login attempt
    private function recordFailedLoginAttempt($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1, 
                last_failed_login = NOW() 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    // Reset failed login attempts
    private function resetFailedLoginAttempts($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, 
                last_failed_login = NULL 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    // Create user session
    private function createSession($user_id, $remember_me = false) {
        // Generate secure session token
        $session_token = bin2hex(random_bytes(32));
        $expires_at = $remember_me ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store session in database
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $session_token, $expires_at]);
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['session_token'] = $session_token;
        
        // Set cookie for remember me
        if ($remember_me) {
            setcookie('remember_token', $session_token, strtotime('+30 days'), '/', '', true, true);
        }
        
        return $session_token;
    }
    
    // Validate session
    public function validateSession() {
        $session_token = $_SESSION['session_token'] ?? $_COOKIE['remember_token'] ?? null;
        
        if (!$session_token) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT us.user_id, u.email, u.first_name, u.last_name, u.is_active, u.must_change_password
            FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.session_token = ? AND us.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$session_token]);
        $session = $stmt->fetch();
        
        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['session_token'] = $session_token;
            return $session;
        }
        
        return false;
    }
    
    // Logout user
    public function logout() {
        $session_token = $_SESSION['session_token'] ?? null;
        
        if ($session_token) {
            // Remove session from database
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$session_token]);
        }
        
        // Clear session and cookies
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Change password
    public function changePassword($user_id, $current_password, $new_password) {
        // Verify current password
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        // Validate new password
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }
        
        // Update password
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, must_change_password = FALSE, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$password_hash, $user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}

// Permission Management Class
class Permission {
    private $pdo;
    private $user_id;
    private $user_roles;
    private $permissions;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->loadUserPermissions();
    }
    
    // Load user permissions from database
    private function loadUserPermissions() {
        $stmt = $this->pdo->prepare("
            SELECT r.role_name, r.display_name, s.screen_name, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$this->user_id]);
        
        $this->user_roles = [];
        $this->permissions = [];
        
        while ($row = $stmt->fetch()) {
            $this->user_roles[$row['role_name']] = $row['display_name'];
            
            if (!isset($this->permissions[$row['screen_name']])) {
                $this->permissions[$row['screen_name']] = [
                    'view' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false
                ];
            }
            
            // Grant permissions (OR logic - if any role grants permission, user has it)
            $this->permissions[$row['screen_name']]['view'] = $this->permissions[$row['screen_name']]['view'] || $row['can_view'];
            $this->permissions[$row['screen_name']]['create'] = $this->permissions[$row['screen_name']]['create'] || $row['can_create'];
            $this->permissions[$row['screen_name']]['edit'] = $this->permissions[$row['screen_name']]['edit'] || $row['can_edit'];
            $this->permissions[$row['screen_name']]['delete'] = $this->permissions[$row['screen_name']]['delete'] || $row['can_delete'];
        }
    }
    
    // Check if user can access a screen
    public function canAccess($screen) {
        return isset($this->permissions[$screen]) && $this->permissions[$screen]['view'];
    }
    
    // Check specific permissions
    public function canView($screen) {
        return isset($this->permissions[$screen]) && $this->permissions[$screen]['view'];
    }
    
    public function canCreate($screen) {
        return isset($this->permissions[$screen]) && $this->permissions[$screen]['create'];
    }
    
    public function canEdit($screen) {
        return isset($this->permissions[$screen]) && $this->permissions[$screen]['edit'];
    }
    
    public function canDelete($screen) {
        return isset($this->permissions[$screen]) && $this->permissions[$screen]['delete'];
    }
    
    // Check if user is Super Admin
    public function isSuperAdmin() {
        return isset($this->user_roles['super_admin']);
    }
    
    // Get user roles
    public function getUserRoles() {
        return $this->user_roles;
    }
    
    // Get permissions for a screen
    public function getScreenPermissions($screen) {
        return $this->permissions[$screen] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
    }
    
    // Get accessible screens
    public function getAccessibleScreens() {
        $screens = [];
        foreach ($this->permissions as $screen => $perms) {
            if ($perms['view']) {
                $screens[] = $screen;
            }
        }
        return $screens;
    }
}

// Initialize classes
$auth = new UserAuth($pdo);
$passwordRecovery = new PasswordRecovery($pdo);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Login action
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        $result = $auth->login($email, $password, $remember_me);
        
        if ($result['success']) {
            if ($result['must_change_password']) {
                $change_password_required = true;
            } else {
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
    
    // Forgot password action
    elseif ($action === 'forgot_password') {
        $email = $_POST['email'] ?? '';
        
        if ($passwordRecovery->generateAndEmailTemporaryPassword($email)) {
            $message = "A temporary password has been sent to your email address.";
        } else {
            $error = "Failed to send reset email. Please check your email address.";
        }
    }
    
    // Change password action
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $user_id = $_SESSION['user_id'] ?? null;
            if ($user_id) {
                $result = $auth->changePassword($user_id, $current_password, $new_password);
                if ($result['success']) {
                    $message = $result['message'];
                    $change_password_required = false;
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = "User not found.";
            }
        }
    }
    
    // Logout action
    elseif ($action === 'logout') {
        $auth->logout();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Vehicle actions
    elseif ($action === 'add_vehicle') {
        $stmt = $pdo->prepare("
            INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())
        ");
        $stmt->execute([
            $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'],
            $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']
        ]);
        $message = "Vehicle added successfully!";
    }
    
    elseif ($action === 'edit_vehicle') {
        $stmt = $pdo->prepare("
            UPDATE vehicles 
            SET make = ?, model = ?, year = ?, vin = ?, license_plate = ?, color = ?, mileage = ?, daily_rate = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'],
            $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate'], $_POST['status'], $_POST['vehicle_id']
        ]);
        $message = "Vehicle updated successfully!";
    }
    
    // Customer actions
    elseif ($action === 'add_customer') {
        $stmt = $pdo->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
            $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']
        ]);
        $message = "Customer added successfully!";
    }
    
    elseif ($action === 'edit_customer') {
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, driver_license = ?, date_of_birth = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
            $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth'], $_POST['customer_id']
        ]);
        $message = "Customer updated successfully!";
    }
    
    // Reservation actions
    elseif ($action === 'add_reservation') {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, notes, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'],
            $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']
        ]);
        $message = "Reservation created successfully!";
    }
    
    elseif ($action === 'edit_reservation') {
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET customer_id = ?, vehicle_id = ?, start_date = ?, end_date = ?, pickup_location = ?, dropoff_location = ?, total_amount = ?, notes = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['customer_id'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'],
            $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes'], $_POST['status'], $_POST['reservation_id']
        ]);
        $message = "Reservation updated successfully!";
    }
    
    // Maintenance actions
    elseif ($action === 'add_maintenance') {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['description'], $_POST['status']
        ]);
        $message = "Maintenance scheduled successfully!";
    }
    
    elseif ($action === 'edit_maintenance') {
        $stmt = $pdo->prepare("
            UPDATE maintenance_schedules 
            SET vehicle_id = ?, maintenance_type = ?, scheduled_date = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['description'], $_POST['status'], $_POST['maintenance_id']
        ]);
        $message = "Maintenance updated successfully!";
    }
    
    // User actions
    elseif ($action === 'add_user') {
        $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, first_name, last_name, password_hash, must_change_password, is_active, created_at) 
            VALUES (?, ?, ?, ?, TRUE, TRUE, NOW())
        ");
        $stmt->execute([
            $_POST['email'], $_POST['first_name'], $_POST['last_name'], $password_hash
        ]);
        $message = "User added successfully!";
    }
    
    elseif ($action === 'edit_user') {
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 10]);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email = ?, first_name = ?, last_name = ?, password_hash = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['email'], $_POST['first_name'], $_POST['last_name'], $password_hash, $_POST['is_active'], $_POST['user_id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email = ?, first_name = ?, last_name = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['is_active'], $_POST['user_id']
            ]);
        }
        $message = "User updated successfully!";
    }
    
    // Role actions
    elseif ($action === 'add_role') {
        $stmt = $pdo->prepare("
            INSERT INTO roles (role_name, display_name, description, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['role_name'], $_POST['display_name'], $_POST['description']
        ]);
        $message = "Role added successfully!";
    }
    
    elseif ($action === 'edit_role') {
        $stmt = $pdo->prepare("
            UPDATE roles 
            SET role_name = ?, display_name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['role_name'], $_POST['display_name'], $_POST['description'], $_POST['role_id']
        ]);
        $message = "Role updated successfully!";
    }
}

// Check if user is logged in
$current_user = $auth->validateSession();
$change_password_required = $current_user && $current_user['must_change_password'];

// Initialize permissions if user is logged in
$permissions = null;
if ($current_user) {
    $permissions = new Permission($pdo, $current_user['user_id']);
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$edit_id = $_GET['edit'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP - Complete Edit System</title>
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
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-welcome {
            color: #666;
            font-weight: 500;
        }
        
        .role-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .navigation {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .nav-tab {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .nav-tab:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
        }
        
        .nav-tab.active {
            background: white;
            border-bottom: 3px solid #667eea;
            font-weight: bold;
        }
        
        /* Colorful tab system */
        .nav-tab:nth-child(1) { border-top: 4px solid #007bff; }
        .nav-tab:nth-child(2) { border-top: 4px solid #ffc107; }
        .nav-tab:nth-child(3) { border-top: 4px solid #6f42c1; }
        .nav-tab:nth-child(4) { border-top: 4px solid #28a745; }
        .nav-tab:nth-child(5) { border-top: 4px solid #6f42c1; }
        .nav-tab:nth-child(6) { border-top: 4px solid #dc3545; }
        .nav-tab:nth-child(7) { border-top: 4px solid #fd7e14; }
        
        .permissions-info {
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 2rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
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
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .edit-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .edit-form h3 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-grid.single-column {
            grid-template-columns: 1fr;
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-field textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 0.9rem;
            margin-right: 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .navigation {
                justify-content: center;
            }
            
            .nav-tab {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<?php if (!$current_user): ?>
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-form">
            <h2>Sign In</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['forgot'])): ?>
                <!-- Forgot Password Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="forgot_password">
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <button type="submit" class="login-btn">Send Reset Email</button>
                </form>
                <div class="forgot-password">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Back to Login</a>
                </div>
            <?php else: ?>
                <!-- Login Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
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
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($change_password_required): ?>
    <!-- Password Change Required -->
    <div class="login-container">
        <div class="login-form">
            <h2>Password Change Required</h2>
            <p style="margin-bottom: 20px; color: #666;">You must change your password before continuing.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="login-btn">Change Password</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Main Application -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Car Rental ERP System</h1>
            <div class="user-info">
                <span class="user-welcome">Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
                <?php if ($permissions->isSuperAdmin()): ?>
                    <span class="role-badge">Super Admin</span>
                <?php endif; ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="navigation">
            <?php
            $screens = [
                'dashboard' => 'Dashboard',
                'vehicles' => 'Vehicles',
                'customers' => 'Customers',
                'reservations' => 'Reservations',
                'maintenance' => 'Maintenance',
                'users' => 'Users',
                'roles' => 'Roles'
            ];
            
            foreach ($screens as $screen_key => $screen_name) {
                if ($permissions->canAccess($screen_key)) {
                    $active_class = ($page === $screen_key) ? 'active' : '';
                    echo '<a href="?page=' . $screen_key . '" class="nav-tab ' . $active_class . '">' . $screen_name . '</a>';
                }
            }
            ?>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <!-- Permissions Info -->
        <?php if ($permissions->canAccess($page)): ?>
            <div class="permissions-info">
                Your permissions for this page: 
                <?php
                $perms = [];
                if ($permissions->canView($page)) $perms[] = 'View';
                if ($permissions->canCreate($page)) $perms[] = 'Create';
                if ($permissions->canEdit($page)) $perms[] = 'Edit';
                if ($permissions->canDelete($page)) $perms[] = 'Delete';
                echo implode(', ', $perms);
                ?>
            </div>
            
            <!-- Page Content -->
            <div class="main-content">
                <?php
                // Include page content based on current page
                switch ($page) {
                    case 'dashboard':
                        include 'pages/dashboard.php';
                        break;
                    case 'vehicles':
                        include 'pages/vehicles.php';
                        break;
                    case 'customers':
                        include 'pages/customers.php';
                        break;
                    case 'reservations':
                        include 'pages/reservations.php';
                        break;
                    case 'maintenance':
                        include 'pages/maintenance.php';
                        break;
                    case 'users':
                        if ($permissions->isSuperAdmin()) {
                            include 'pages/users.php';
                        }
                        break;
                    case 'roles':
                        if ($permissions->isSuperAdmin()) {
                            include 'pages/roles.php';
                        }
                        break;
                    default:
                        echo "<h2>Page Not Found</h2><p>The requested page does not exist.</p>";
                }
                ?>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                <h3>Access Denied</h3>
                <p>You do not have permission to access this page.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>

<?php
// Simple page implementations with edit functionality
if (!function_exists('include') || !file_exists('pages/dashboard.php')) {
    // Dashboard content
    if ($page === 'dashboard' && $permissions->canAccess('dashboard')) {
        echo '
        <h2>Dashboard</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;">
                <h3 style="font-size: 2.5rem; margin-bottom: 10px;">6</h3>
                <p>Total Vehicles</p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;">
                <h3 style="font-size: 2.5rem; margin-bottom: 10px;">6</h3>
                <p>Available Vehicles</p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;">
                <h3 style="font-size: 2.5rem; margin-bottom: 10px;">1</h3>
                <p>Active Reservations</p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;">
                <h3 style="font-size: 2.5rem; margin-bottom: 10px;">3</h3>
                <p>Pending Maintenance</p>
            </div>
        </div>
        
        <h3>Recent Activity</h3>
        <div class="data-table">
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
                    <tr>
                        <td>Jul 27, 2025</td>
                        <td>Michael Brown</td>
                        <td>Ford Escape</td>
                        <td>confirmed</td>
                        <td>$110.00</td>
                    </tr>
                    <tr>
                        <td>Jul 26, 2025</td>
                        <td>Sarah Johnson</td>
                        <td>Honda Civic</td>
                        <td>pending</td>
                        <td>$160.00</td>
                    </tr>
                    <tr>
                        <td>Jul 25, 2025</td>
                        <td>John Smith</td>
                        <td>Toyota Camry</td>
                        <td>confirmed</td>
                        <td>$135.00</td>
                    </tr>
                </tbody>
            </table>
        </div>';
    }
    
    // Vehicles page content with edit functionality
    if ($page === 'vehicles' && $permissions->canAccess('vehicles')) {
        // Check if editing a vehicle
        if ($edit_id && $permissions->canEdit('vehicles')) {
            $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
            $stmt->execute([$edit_id]);
            $vehicle = $stmt->fetch();
            
            if ($vehicle) {
                echo '
                <div class="edit-form">
                    <h3>Edit Vehicle</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_vehicle">
                        <input type="hidden" name="vehicle_id" value="' . $vehicle['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Make:</label>
                                <input type="text" name="make" value="' . htmlspecialchars($vehicle['make']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Model:</label>
                                <input type="text" name="model" value="' . htmlspecialchars($vehicle['model']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Year:</label>
                                <input type="number" name="year" value="' . $vehicle['year'] . '" required min="1900" max="2030">
                            </div>
                            <div class="form-field">
                                <label>VIN:</label>
                                <input type="text" name="vin" value="' . htmlspecialchars($vehicle['vin']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>License Plate:</label>
                                <input type="text" name="license_plate" value="' . htmlspecialchars($vehicle['license_plate']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Color:</label>
                                <input type="text" name="color" value="' . htmlspecialchars($vehicle['color']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Mileage:</label>
                                <input type="number" name="mileage" value="' . $vehicle['mileage'] . '" required min="0">
                            </div>
                            <div class="form-field">
                                <label>Daily Rate ($):</label>
                                <input type="number" name="daily_rate" value="' . $vehicle['daily_rate'] . '" required min="0" step="0.01">
                            </div>
                            <div class="form-field">
                                <label>Status:</label>
                                <select name="status" required>
                                    <option value="available"' . ($vehicle['status'] === 'available' ? ' selected' : '') . '>Available</option>
                                    <option value="rented"' . ($vehicle['status'] === 'rented' ? ' selected' : '') . '>Rented</option>
                                    <option value="maintenance"' . ($vehicle['status'] === 'maintenance' ? ' selected' : '') . '>Maintenance</option>
                                    <option value="out_of_service"' . ($vehicle['status'] === 'out_of_service' ? ' selected' : '') . '>Out of Service</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
                            <a href="?page=vehicles" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>Vehicle Management</h2>
            
            <div class="edit-form">
                <h3>Add New Vehicle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Make:</label>
                            <input type="text" name="make" required>
                        </div>
                        <div class="form-field">
                            <label>Model:</label>
                            <input type="text" name="model" required>
                        </div>
                        <div class="form-field">
                            <label>Year:</label>
                            <input type="number" name="year" required min="1900" max="2030">
                        </div>
                        <div class="form-field">
                            <label>VIN:</label>
                            <input type="text" name="vin" required>
                        </div>
                        <div class="form-field">
                            <label>License Plate:</label>
                            <input type="text" name="license_plate" required>
                        </div>
                        <div class="form-field">
                            <label>Color:</label>
                            <input type="text" name="color" required>
                        </div>
                        <div class="form-field">
                            <label>Mileage:</label>
                            <input type="number" name="mileage" required min="0">
                        </div>
                        <div class="form-field">
                            <label>Daily Rate ($):</label>
                            <input type="number" name="daily_rate" required min="0" step="0.01">
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>Add Vehicle</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>Vehicle Inventory</h3>
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
                <tbody>';
        
        // Get vehicles from database
        $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
        while ($vehicle = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($vehicle['make']) . '</td>
                <td>' . htmlspecialchars($vehicle['model']) . '</td>
                <td>' . htmlspecialchars($vehicle['year']) . '</td>
                <td>' . htmlspecialchars($vehicle['license_plate']) . '</td>
                <td>' . htmlspecialchars($vehicle['status']) . '</td>
                <td>$' . number_format($vehicle['daily_rate'], 2) . '</td>
                <td>' . number_format($vehicle['mileage']) . '</td>
                <td>
                    <div class="action-buttons">';
            
            if ($permissions->canEdit('vehicles')) {
                echo '<a href="?page=vehicles&edit=' . $vehicle['id'] . '" class="btn btn-success">Edit</a>';
            }
            
            echo '</div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
    
    // Customers page content with edit functionality
    if ($page === 'customers' && $permissions->canAccess('customers')) {
        // Check if editing a customer
        if ($edit_id && $permissions->canEdit('customers')) {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$edit_id]);
            $customer = $stmt->fetch();
            
            if ($customer) {
                echo '
                <div class="edit-form">
                    <h3>Edit Customer</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_customer">
                        <input type="hidden" name="customer_id" value="' . $customer['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>First Name:</label>
                                <input type="text" name="first_name" value="' . htmlspecialchars($customer['first_name']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Last Name:</label>
                                <input type="text" name="last_name" value="' . htmlspecialchars($customer['last_name']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Email:</label>
                                <input type="email" name="email" value="' . htmlspecialchars($customer['email']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Phone:</label>
                                <input type="tel" name="phone" value="' . htmlspecialchars($customer['phone']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Driver License:</label>
                                <input type="text" name="driver_license" value="' . htmlspecialchars($customer['driver_license']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Date of Birth:</label>
                                <input type="date" name="date_of_birth" value="' . $customer['date_of_birth'] . '" required>
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Address:</label>
                            <textarea name="address" required>' . htmlspecialchars($customer['address']) . '</textarea>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update Customer</button>
                            <a href="?page=customers" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>Customer Management</h2>
            
            <div class="edit-form">
                <h3>Add New Customer</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_customer">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>First Name:</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-field">
                            <label>Last Name:</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-field">
                            <label>Email:</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-field">
                            <label>Phone:</label>
                            <input type="tel" name="phone" required>
                        </div>
                        <div class="form-field">
                            <label>Driver License:</label>
                            <input type="text" name="driver_license" required>
                        </div>
                        <div class="form-field">
                            <label>Date of Birth:</label>
                            <input type="date" name="date_of_birth" required>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Address:</label>
                        <textarea name="address" required></textarea>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" ' . ($permissions->canCreate('customers') ? '' : 'disabled') . '>Add Customer</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>Customer List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Driver License</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Get customers from database
        $stmt = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name");
        while ($customer = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . '</td>
                <td>' . htmlspecialchars($customer['email']) . '</td>
                <td>' . htmlspecialchars($customer['phone']) . '</td>
                <td>' . htmlspecialchars($customer['driver_license']) . '</td>
                <td>
                    <div class="action-buttons">';
            
            if ($permissions->canEdit('customers')) {
                echo '<a href="?page=customers&edit=' . $customer['id'] . '" class="btn btn-success">Edit</a>';
            }
            
            echo '</div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
    
    // Reservations page content with edit functionality
    if ($page === 'reservations' && $permissions->canAccess('reservations')) {
        // Check if editing a reservation
        if ($edit_id && $permissions->canEdit('reservations')) {
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
            $stmt->execute([$edit_id]);
            $reservation = $stmt->fetch();
            
            if ($reservation) {
                echo '
                <div class="edit-form">
                    <h3>Edit Reservation</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_reservation">
                        <input type="hidden" name="reservation_id" value="' . $reservation['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Customer:</label>
                                <select name="customer_id" required>';
                
                // Get customers for dropdown
                $stmt_customers = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
                while ($customer = $stmt_customers->fetch()) {
                    $selected = ($customer['id'] == $reservation['customer_id']) ? ' selected' : '';
                    echo '<option value="' . $customer['id'] . '"' . $selected . '>' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . '</option>';
                }
                
                echo '</select>
                            </div>
                            <div class="form-field">
                                <label>Vehicle:</label>
                                <select name="vehicle_id" required>';
                
                // Get vehicles for dropdown
                $stmt_vehicles = $pdo->query("SELECT id, make, model, year, daily_rate FROM vehicles ORDER BY make, model");
                while ($vehicle = $stmt_vehicles->fetch()) {
                    $selected = ($vehicle['id'] == $reservation['vehicle_id']) ? ' selected' : '';
                    echo '<option value="' . $vehicle['id'] . '"' . $selected . '>' . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ') - $' . $vehicle['daily_rate'] . '/day') . '</option>';
                }
                
                echo '</select>
                            </div>
                            <div class="form-field">
                                <label>Start Date:</label>
                                <input type="date" name="start_date" value="' . $reservation['start_date'] . '" required>
                            </div>
                            <div class="form-field">
                                <label>End Date:</label>
                                <input type="date" name="end_date" value="' . $reservation['end_date'] . '" required>
                            </div>
                            <div class="form-field">
                                <label>Pickup Location:</label>
                                <input type="text" name="pickup_location" value="' . htmlspecialchars($reservation['pickup_location']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Dropoff Location:</label>
                                <input type="text" name="dropoff_location" value="' . htmlspecialchars($reservation['dropoff_location']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Total Amount ($):</label>
                                <input type="number" name="total_amount" value="' . $reservation['total_amount'] . '" required min="0" step="0.01">
                            </div>
                            <div class="form-field">
                                <label>Status:</label>
                                <select name="status" required>
                                    <option value="pending"' . ($reservation['status'] === 'pending' ? ' selected' : '') . '>Pending</option>
                                    <option value="confirmed"' . ($reservation['status'] === 'confirmed' ? ' selected' : '') . '>Confirmed</option>
                                    <option value="active"' . ($reservation['status'] === 'active' ? ' selected' : '') . '>Active</option>
                                    <option value="completed"' . ($reservation['status'] === 'completed' ? ' selected' : '') . '>Completed</option>
                                    <option value="cancelled"' . ($reservation['status'] === 'cancelled' ? ' selected' : '') . '>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Notes:</label>
                            <textarea name="notes">' . htmlspecialchars($reservation['notes']) . '</textarea>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update Reservation</button>
                            <a href="?page=reservations" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>Reservation Management</h2>
            
            <div class="edit-form">
                <h3>Create New Reservation</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_reservation">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Customer:</label>
                            <select name="customer_id" required>
                                <option value="">Select Customer</option>';
            
            // Get customers for dropdown
            $stmt = $pdo->query("SELECT id, first_name, last_name FROM customers ORDER BY last_name, first_name");
            while ($customer = $stmt->fetch()) {
                echo '<option value="' . $customer['id'] . '">' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . '</option>';
            }
            
            echo '</select>
                        </div>
                        <div class="form-field">
                            <label>Vehicle:</label>
                            <select name="vehicle_id" required>
                                <option value="">Select Vehicle</option>';
            
            // Get available vehicles for dropdown
            $stmt = $pdo->query("SELECT id, make, model, year, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make, model");
            while ($vehicle = $stmt->fetch()) {
                echo '<option value="' . $vehicle['id'] . '">' . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ') - $' . $vehicle['daily_rate'] . '/day') . '</option>';
            }
            
            echo '</select>
                        </div>
                        <div class="form-field">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-field">
                            <label>End Date:</label>
                            <input type="date" name="end_date" required>
                        </div>
                        <div class="form-field">
                            <label>Pickup Location:</label>
                            <input type="text" name="pickup_location" required>
                        </div>
                        <div class="form-field">
                            <label>Dropoff Location:</label>
                            <input type="text" name="dropoff_location" required>
                        </div>
                        <div class="form-field">
                            <label>Total Amount ($):</label>
                            <input type="number" name="total_amount" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Notes:</label>
                        <textarea name="notes"></textarea>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" ' . ($permissions->canCreate('reservations') ? '' : 'disabled') . '>Create Reservation</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>Current Reservations</h3>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Get reservations from database
        $stmt = $pdo->query("
            SELECT r.*, 
                   CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                   CONCAT(v.make, ' ', v.model) as vehicle_name
            FROM reservations r 
            JOIN customers c ON r.customer_id = c.id 
            JOIN vehicles v ON r.vehicle_id = v.id 
            ORDER BY r.start_date DESC
        ");
        while ($reservation = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($reservation['customer_name']) . '</td>
                <td>' . htmlspecialchars($reservation['vehicle_name']) . '</td>
                <td>' . date('M j', strtotime($reservation['start_date'])) . ' - ' . date('M j, Y', strtotime($reservation['end_date'])) . '</td>
                <td>' . htmlspecialchars($reservation['status']) . '</td>
                <td>$' . number_format($reservation['total_amount'], 2) . '</td>
                <td>
                    <div class="action-buttons">';
            
            if ($permissions->canEdit('reservations')) {
                echo '<a href="?page=reservations&edit=' . $reservation['id'] . '" class="btn btn-success">Edit</a>';
            }
            
            echo '</div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
    
    // Maintenance page content with edit functionality
    if ($page === 'maintenance' && $permissions->canAccess('maintenance')) {
        // Check if editing maintenance
        if ($edit_id && $permissions->canEdit('maintenance')) {
            $stmt = $pdo->prepare("SELECT * FROM maintenance_schedules WHERE id = ?");
            $stmt->execute([$edit_id]);
            $maintenance = $stmt->fetch();
            
            if ($maintenance) {
                echo '
                <div class="edit-form">
                    <h3>Edit Maintenance</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_maintenance">
                        <input type="hidden" name="maintenance_id" value="' . $maintenance['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Vehicle:</label>
                                <select name="vehicle_id" required>';
                
                // Get vehicles for dropdown
                $stmt_vehicles = $pdo->query("SELECT id, make, model, year FROM vehicles ORDER BY make, model");
                while ($vehicle = $stmt_vehicles->fetch()) {
                    $selected = ($vehicle['id'] == $maintenance['vehicle_id']) ? ' selected' : '';
                    echo '<option value="' . $vehicle['id'] . '"' . $selected . '>' . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')') . '</option>';
                }
                
                echo '</select>
                            </div>
                            <div class="form-field">
                                <label>Maintenance Type:</label>
                                <input type="text" name="maintenance_type" value="' . htmlspecialchars($maintenance['maintenance_type']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Scheduled Date:</label>
                                <input type="date" name="scheduled_date" value="' . $maintenance['scheduled_date'] . '" required>
                            </div>
                            <div class="form-field">
                                <label>Status:</label>
                                <select name="status" required>
                                    <option value="scheduled"' . ($maintenance['status'] === 'scheduled' ? ' selected' : '') . '>Scheduled</option>
                                    <option value="in_progress"' . ($maintenance['status'] === 'in_progress' ? ' selected' : '') . '>In Progress</option>
                                    <option value="completed"' . ($maintenance['status'] === 'completed' ? ' selected' : '') . '>Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Description:</label>
                            <textarea name="description">' . htmlspecialchars($maintenance['description']) . '</textarea>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update Maintenance</button>
                            <a href="?page=maintenance" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>Maintenance Management</h2>
            
            <div class="edit-form">
                <h3>Schedule Maintenance</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_maintenance">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Vehicle:</label>
                            <select name="vehicle_id" required>
                                <option value="">Select Vehicle</option>';
            
            // Get vehicles for dropdown
            $stmt = $pdo->query("SELECT id, make, model, year FROM vehicles ORDER BY make, model");
            while ($vehicle = $stmt->fetch()) {
                echo '<option value="' . $vehicle['id'] . '">' . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')') . '</option>';
            }
            
            echo '</select>
                        </div>
                        <div class="form-field">
                            <label>Maintenance Type:</label>
                            <input type="text" name="maintenance_type" required placeholder="e.g., Oil Change, Tire Rotation">
                        </div>
                        <div class="form-field">
                            <label>Scheduled Date:</label>
                            <input type="date" name="scheduled_date" required>
                        </div>
                        <div class="form-field">
                            <label>Status:</label>
                            <select name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Description:</label>
                        <textarea name="description" placeholder="Detailed description of maintenance work"></textarea>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" ' . ($permissions->canCreate('maintenance') ? '' : 'disabled') . '>Schedule Maintenance</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>Maintenance Schedule</h3>
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Get maintenance records from database
        $stmt = $pdo->query("
            SELECT m.*, 
                   CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_info
            FROM maintenance_schedules m 
            JOIN vehicles v ON m.vehicle_id = v.id 
            ORDER BY m.scheduled_date DESC
        ");
        while ($maintenance = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($maintenance['vehicle_info']) . '</td>
                <td>' . htmlspecialchars($maintenance['maintenance_type']) . '</td>
                <td>' . date('M j, Y', strtotime($maintenance['scheduled_date'])) . '</td>
                <td>' . htmlspecialchars($maintenance['status']) . '</td>
                <td>
                    <div class="action-buttons">';
            
            if ($permissions->canEdit('maintenance')) {
                echo '<a href="?page=maintenance&edit=' . $maintenance['id'] . '" class="btn btn-success">Edit</a>';
            }
            
            echo '</div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
    
    // Users page content with edit functionality (Super Admin only)
    if ($page === 'users' && $permissions->canAccess('users') && $permissions->isSuperAdmin()) {
        // Check if editing a user
        if ($edit_id && $permissions->canEdit('users')) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$edit_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo '
                <div class="edit-form">
                    <h3>Edit User</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="' . $user['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Email:</label>
                                <input type="email" name="email" value="' . htmlspecialchars($user['email']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>First Name:</label>
                                <input type="text" name="first_name" value="' . htmlspecialchars($user['first_name']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Last Name:</label>
                                <input type="text" name="last_name" value="' . htmlspecialchars($user['last_name']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>New Password (leave blank to keep current):</label>
                                <input type="password" name="password">
                            </div>
                            <div class="form-field">
                                <label>Status:</label>
                                <select name="is_active" required>
                                    <option value="1"' . ($user['is_active'] ? ' selected' : '') . '>Active</option>
                                    <option value="0"' . (!$user['is_active'] ? ' selected' : '') . '>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="?page=users" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>User Management</h2>
            
            <div class="edit-form">
                <h3>Add New User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Email:</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-field">
                            <label>First Name:</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-field">
                            <label>Last Name:</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-field">
                            <label>Temporary Password:</label>
                            <input type="password" name="password" required>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>System Users</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Get users with their roles
        $stmt = $pdo->query("
            SELECT u.*, 
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as role_names
            FROM users u 
            LEFT JOIN user_roles ur ON u.id = ur.user_id 
            LEFT JOIN roles r ON ur.role_id = r.id 
            GROUP BY u.id 
            ORDER BY u.last_name, u.first_name
        ");
        while ($user = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
                <td>' . htmlspecialchars($user['role_names'] ?: 'No roles assigned') . '</td>
                <td>' . ($user['is_active'] ? 'Active' : 'Inactive') . '</td>
                <td>
                    <div class="action-buttons">
                        <a href="?page=users&edit=' . $user['id'] . '" class="btn btn-success">Edit</a>
                        <a href="?page=user_roles&user_id=' . $user['id'] . '" class="btn btn-warning">Roles</a>
                    </div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
    
    // Roles page content with edit functionality (Super Admin only)
    if ($page === 'roles' && $permissions->canAccess('roles') && $permissions->isSuperAdmin()) {
        // Check if editing a role
        if ($edit_id && $permissions->canEdit('roles')) {
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$edit_id]);
            $role = $stmt->fetch();
            
            if ($role) {
                echo '
                <div class="edit-form">
                    <h3>Edit Role</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_role">
                        <input type="hidden" name="role_id" value="' . $role['id'] . '">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Role Name:</label>
                                <input type="text" name="role_name" value="' . htmlspecialchars($role['role_name']) . '" required>
                            </div>
                            <div class="form-field">
                                <label>Display Name:</label>
                                <input type="text" name="display_name" value="' . htmlspecialchars($role['display_name']) . '" required>
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Description:</label>
                            <textarea name="description">' . htmlspecialchars($role['description']) . '</textarea>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Update Role</button>
                            <a href="?page=roles" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>';
            }
        } else {
            echo '
            <h2>Role Management</h2>
            
            <div class="edit-form">
                <h3>Add New Role</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_role">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Role Name:</label>
                            <input type="text" name="role_name" required>
                        </div>
                        <div class="form-field">
                            <label>Display Name:</label>
                            <input type="text" name="display_name" required>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Description:</label>
                        <textarea name="description"></textarea>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Add Role</button>
                    </div>
                </form>
            </div>';
        }
        
        echo '
        <div class="data-table">
            <h3>System Roles</h3>
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
                <tbody>';
        
        // Get roles with user count
        $stmt = $pdo->query("
            SELECT r.*, 
                   COUNT(ur.user_id) as user_count
            FROM roles r 
            LEFT JOIN user_roles ur ON r.id = ur.role_id 
            GROUP BY r.id 
            ORDER BY r.role_name
        ");
        while ($role = $stmt->fetch()) {
            echo '<tr>
                <td>' . htmlspecialchars($role['role_name']) . '</td>
                <td>' . htmlspecialchars($role['display_name']) . '</td>
                <td>' . htmlspecialchars($role['description']) . '</td>
                <td>' . $role['user_count'] . '</td>
                <td>
                    <div class="action-buttons">
                        <a href="?page=roles&edit=' . $role['id'] . '" class="btn btn-success">Edit</a>
                        <a href="?page=role_permissions&role_id=' . $role['id'] . '" class="btn btn-warning">Permissions</a>
                    </div>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
}
?>

