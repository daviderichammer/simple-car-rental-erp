<?php
// Simple Car Rental ERP System with Password Recovery
// Phase 4: Password Recovery System Implementation
// Maintains SIMPLE, SIMPLE, SIMPLE architecture with comprehensive security
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
    
    // Validate reset token
    public function validateResetToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT prt.user_id, u.email, u.first_name, u.last_name
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    // Generate new temporary password
    public function generateTemporaryPassword() {
        // Generate secure temporary password (12 characters)
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = 'SecureRootPass123!';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    // Reset password with temporary password
    public function resetPassword($token, $new_password) {
        $user = $this->validateResetToken($token);
        if (!$user) {
            return false;
        }
        
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Update user password and force password change
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, must_change_password = TRUE, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$password_hash, $user['user_id']]);
        
        // Mark token as used
        $stmt = $this->pdo->prepare("
            UPDATE password_reset_tokens 
            SET used_at = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        return $user;
    }
    
    // Send password reset email
    public function sendResetEmail($email, $token, $new_password) {
        $reset_link = "https://admin.infiniteautorentals.com/?action=reset_password&token=" . $token;
        
        $subject = "Password Reset - Car Rental ERP System";
        $message = $this->getEmailTemplate($email, $new_password, $reset_link);
        
        $headers = [
            'From: Car Rental ERP <noreply@infiniteautorentals.com>',
            'Reply-To: support@infiniteautorentals.com',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    // Professional email template
    private function getEmailTemplate($email, $new_password, $reset_link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .password-box { background: #fff; border: 2px solid #667eea; padding: 15px; margin: 20px 0; text-align: center; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Car Rental ERP System</h1>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>You have requested a password reset for your Car Rental ERP account (<strong>{$email}</strong>).</p>
                    
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong> This is a temporary password that expires in 1 hour. You will be required to change it upon first login.
                    </div>
                    
                    <p>Your new temporary password is:</p>
                    <div class='password-box'>
                        <strong style='font-size: 18px; color: #667eea;'>{$new_password}</strong>
                    </div>
                    
                    <p>Please use this temporary password to log in to the system:</p>
                    <p style='text-align: center;'>
                        <a href='https://admin.infiniteautorentals.com' class='button'>Login to ERP System</a>
                    </p>
                    
                    <div class='warning'>
                        <strong>Important Security Instructions:</strong>
                        <ul>
                            <li>This temporary password expires in 1 hour</li>
                            <li>You must change your password immediately after logging in</li>
                            <li>Choose a strong password with at least 8 characters</li>
                            <li>If you did not request this reset, contact support immediately</li>
                        </ul>
                    </div>
                    
                    <p>For security reasons, this email and temporary password will be invalid after 1 hour or after you successfully change your password.</p>
                    
                    <p>If you have any questions or need assistance, please contact our support team.</p>
                    
                    <p>Best regards,<br>Car Rental ERP Support Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>© 2025 Infinite Auto Rentals. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Clean up expired tokens
    public function cleanupExpiredTokens() {
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

// User Authentication Class (Enhanced with Password Recovery)
class UserAuth {
    private $pdo;
    private $passwordRecovery;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->passwordRecovery = new PasswordRecovery($pdo);
    }
    
    // Enhanced login with password change enforcement
    public function login($email, $password, $remember_me = false) {
        // Clean up expired tokens
        $this->passwordRecovery->cleanupExpiredTokens();
        
        // Check if account is locked
        if ($this->isAccountLocked($email)) {
            return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts. Please try again later.'];
        }
        
        // Get user data
        $stmt = $this->pdo->prepare("
            SELECT id, email, password_hash, first_name, last_name, is_active, failed_login_attempts, locked_until, must_change_password
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['is_active']) {
            $this->recordFailedAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        
        // Reset failed attempts on successful login
        $this->resetFailedAttempts($email);
        
        // Create session
        $session_token = $this->createSession($user['id'], $remember_me);
        
        // Check if password change is required
        if ($user['must_change_password']) {
            return [
                'success' => true, 
                'message' => 'Login successful. You must change your password.',
                'must_change_password' => true,
                'user' => $user
            ];
        }
        
        return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
    }
    
    // Change password (for forced password changes)
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password hash
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        // Validate new password strength
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }
        
        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Update password and clear must_change_password flag
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, must_change_password = FALSE, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_password_hash, $user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
    
    // Process forgot password request
    public function processForgotPassword($email) {
        $token = $this->passwordRecovery->generateResetToken($email);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Email address not found or account is inactive.'];
        }
        
        // Generate temporary password
        $temp_password = $this->passwordRecovery->generateTemporaryPassword();
        
        // Reset password with temporary password
        $user = $this->passwordRecovery->resetPassword($token, $temp_password);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to process password reset.'];
        }
        
        // Send email with temporary password
        $email_sent = $this->passwordRecovery->sendResetEmail($email, $token, $temp_password);
        
        if (!$email_sent) {
            return ['success' => false, 'message' => 'Failed to send reset email. Please contact support.'];
        }
        
        return ['success' => true, 'message' => 'A temporary password has been sent to your email address.'];
    }
    
    // Check if account is locked
    private function isAccountLocked($email) {
        $stmt = $this->pdo->prepare("
            SELECT failed_login_attempts, locked_until 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    // Record failed login attempt
    private function recordFailedAttempt($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    WHEN failed_login_attempts + 1 >= 3 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE NULL
                END
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    // Reset failed attempts
    private function resetFailedAttempts($email) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, locked_until = NULL 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    // Create user session
    private function createSession($user_id, $remember_me = false) {
        $session_token = bin2hex(random_bytes(32));
        $expires_at = $remember_me ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $session_token, $expires_at]);
        
        // Set session cookie
        $cookie_expires = $remember_me ? time() + (30 * 24 * 60 * 60) : 0;
        setcookie('session_token', $session_token, $cookie_expires, '/', '', true, true);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['session_token'] = $session_token;
        
        return $session_token;
    }
    
    // Validate session
    public function validateSession() {
        $session_token = $_COOKIE['session_token'] ?? $_SESSION['session_token'] ?? null;
        
        if (!$session_token) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT us.user_id, u.email, u.first_name, u.last_name, u.must_change_password
            FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.session_token = ? AND us.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$session_token]);
        $session = $stmt->fetch();
        
        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['user_email'] = $session['email'];
            $_SESSION['user_name'] = $session['first_name'] . ' ' . $session['last_name'];
            $_SESSION['must_change_password'] = $session['must_change_password'];
            return $session;
        }
        
        return false;
    }
    
    // Logout
    public function logout() {
        $session_token = $_COOKIE['session_token'] ?? $_SESSION['session_token'] ?? null;
        
        if ($session_token) {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$session_token]);
        }
        
        setcookie('session_token', '', time() - 3600, '/', '', true, true);
        session_destroy();
    }
}

// Permission System (from Phase 3)
class Permission {
    private $pdo;
    private $user_id;
    private $user_permissions = null;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->loadUserPermissions();
    }
    
    private function loadUserPermissions() {
        $stmt = $this->pdo->prepare("
            SELECT s.name as screen_name, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN screens s ON rp.screen_id = s.id
            WHERE ur.user_id = ? AND r.is_active = TRUE AND s.is_active = TRUE
        ");
        $stmt->execute([$this->user_id]);
        
        $this->user_permissions = [];
        while ($row = $stmt->fetch()) {
            $screen = $row['screen_name'];
            if (!isset($this->user_permissions[$screen])) {
                $this->user_permissions[$screen] = [
                    'view' => false, 'create' => false, 'edit' => false, 'delete' => false
                ];
            }
            
            if ($row['can_view']) $this->user_permissions[$screen]['view'] = true;
            if ($row['can_create']) $this->user_permissions[$screen]['create'] = true;
            if ($row['can_edit']) $this->user_permissions[$screen]['edit'] = true;
            if ($row['can_delete']) $this->user_permissions[$screen]['delete'] = true;
        }
    }
    
    public function canAccess($screen) {
        return isset($this->user_permissions[$screen]) && $this->user_permissions[$screen]['view'];
    }
    
    public function canCreate($screen) {
        return isset($this->user_permissions[$screen]) && $this->user_permissions[$screen]['create'];
    }
    
    public function canEdit($screen) {
        return isset($this->user_permissions[$screen]) && $this->user_permissions[$screen]['edit'];
    }
    
    public function canDelete($screen) {
        return isset($this->user_permissions[$screen]) && $this->user_permissions[$screen]['delete'];
    }
    
    public function getPermissions($screen) {
        return $this->user_permissions[$screen] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
    }
    
    public function getPermissionString($screen) {
        $perms = $this->getPermissions($screen);
        $permissions = [];
        if ($perms['view']) $permissions[] = 'View';
        if ($perms['create']) $permissions[] = 'Create';
        if ($perms['edit']) $permissions[] = 'Edit';
        if ($perms['delete']) $permissions[] = 'Delete';
        return implode(', ', $permissions);
    }
    
    public function isSuperAdmin() {
        $stmt = $this->pdo->prepare("
            SELECT r.name 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name = 'Super Admin'
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetch() !== false;
    }
    
    public function getUserRoles() {
        $stmt = $this->pdo->prepare("
            SELECT r.name, r.display_name
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.is_active = TRUE
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll();
    }
}

// Initialize authentication
$auth = new UserAuth($pdo);
$current_user = null;
$permissions = null;

// Handle password change requirement
$must_change_password = false;

// Check if user is logged in
$session = $auth->validateSession();
if ($session) {
    $current_user = $session;
    $permissions = new Permission($pdo, $session['user_id']);
    $must_change_password = $session['must_change_password'];
}

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $remember_me = isset($_POST['remember_me']);
                
                $result = $auth->login($email, $password, $remember_me);
                
                if ($result['success']) {
                    if (isset($result['must_change_password']) && $result['must_change_password']) {
                        $must_change_password = true;
                        $current_user = $result['user'];
                        $permissions = new Permission($pdo, $result['user']['id']);
                        $message = $result['message'];
                    } else {
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'forgot_password':
                $email = $_POST['email'] ?? '';
                $result = $auth->processForgotPassword($email);
                
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'change_password':
                if ($current_user) {
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    if ($new_password !== $confirm_password) {
                        $error = 'New passwords do not match.';
                    } else {
                        $result = $auth->changePassword($current_user['id'], $current_password, $new_password);
                        
                        if ($result['success']) {
                            $must_change_password = false;
                            $_SESSION['must_change_password'] = false;
                            $message = $result['message'];
                        } else {
                            $error = $result['message'];
                        }
                    }
                }
                break;
                
            case 'logout':
                $auth->logout();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
    }
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// If user must change password, force them to password change page
if ($must_change_password && $page !== 'change_password') {
    $page = 'change_password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_user ? 'Car Rental ERP - Password Recovery System' : 'Login - Car Rental ERP'; ?></title>
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
        
        /* Login Page Styles */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-form, .forgot-password-form, .change-password-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-form h1, .forgot-password-form h1, .change-password-form h1 {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }
        
        .login-form p, .forgot-password-form p, .change-password-form p {
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
        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="text"]:focus {
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
        
        .forgot-password-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .forgot-password-link a:hover {
            text-decoration: underline;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #667eea;
            text-decoration: none;
        }
        
        /* Main Application Styles */
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
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .nav-tabs {
            background: #f8f9fa;
            padding: 0 2rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 0;
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
        }
        
        .nav-tab:hover {
            color: #333;
            background: #e9ecef;
        }
        
        .nav-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }
        
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
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        /* Password Change Form Styles */
        .password-change-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            
            .login-form, .forgot-password-form, .change-password-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

<?php if (!$current_user): ?>
    <!-- Login/Forgot Password Forms -->
    <div class="login-container">
        <?php if (isset($_GET['action']) && $_GET['action'] === 'forgot_password'): ?>
            <!-- Forgot Password Form -->
            <form class="forgot-password-form" method="POST">
                <h1>Reset Password</h1>
                <p>Enter your email address to receive a temporary password</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <input type="hidden" name="action" value="forgot_password">
                <button type="submit" class="btn">Send Reset Email</button>
                
                <div class="back-to-login">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>">← Back to Login</a>
                </div>
            </form>
        <?php else: ?>
            <!-- Login Form -->
            <form class="login-form" method="POST">
                <h1>Car Rental ERP</h1>
                <p>Please sign in to continue</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
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
                
                <input type="hidden" name="action" value="login">
                <button type="submit" class="btn">Sign In</button>
                
                <div class="forgot-password-link">
                    <a href="?action=forgot_password">Forgot your password?</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Main Application -->
    <div class="header">
        <h1>Car Rental ERP System</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
            <?php 
            $roles = $permissions->getUserRoles();
            if ($roles): 
            ?>
                <span class="user-badge"><?php echo htmlspecialchars($roles[0]['display_name']); ?></span>
            <?php endif; ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <?php if ($must_change_password): ?>
        <!-- Password Change Required -->
        <div class="main-content">
            <div class="password-change-container">
                <h2>Password Change Required</h2>
                <div class="alert alert-warning">
                    <strong>Security Notice:</strong> You must change your password before accessing the system.
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
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
                    
                    <input type="hidden" name="action" value="change_password">
                    <button type="submit" class="btn">Change Password</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <?php
            $screens = [
                'dashboard' => 'Dashboard',
                'vehicles' => 'Vehicles', 
                'customers' => 'Customers',
                'reservations' => 'Reservations',
                'maintenance' => 'Maintenance'
            ];
            
            // Add admin screens for Super Admin
            if ($permissions->isSuperAdmin()) {
                $screens['users'] = 'Users';
                $screens['roles'] = 'Roles';
            }
            
            foreach ($screens as $screen_key => $screen_name):
                if ($permissions->canAccess($screen_key)):
            ?>
                <a href="?page=<?php echo $screen_key; ?>" class="nav-tab <?php echo $page === $screen_key ? 'active' : ''; ?>">
                    <?php echo $screen_name; ?>
                </a>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if ($permissions->canAccess($page)): ?>
                <div class="permission-info">
                    Your permissions for this page: <?php echo $permissions->getPermissionString($page); ?>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
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
                            echo "<h2>Users</h2><p>This page is accessible to you and will be fully implemented with role-based controls.</p>";
                        }
                        break;
                    case 'roles':
                        if ($permissions->isSuperAdmin()) {
                            echo "<h2>Roles</h2><p>This page is accessible to you and will be fully implemented with role-based controls.</p>";
                        }
                        break;
                    default:
                        echo "<h2>Page Not Found</h2><p>The requested page does not exist.</p>";
                }
                ?>
            <?php else: ?>
                <div class="alert alert-error">
                    <h3>Access Denied</h3>
                    <p>You do not have permission to access this page.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>

<?php
// Simple page implementations (placeholder for now)
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
        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 5px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th style="padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;">Date</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;">Customer</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;">Vehicle</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Jul 27, 2025</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Michael Brown</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Ford Escape</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">confirmed</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">$110.00</td>
                </tr>
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Jul 26, 2025</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Sarah Johnson</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">Honda Civic</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">pending</td>
                    <td style="padding: 15px; border-bottom: 1px solid #dee2e6;">$160.00</td>
                </tr>
                <tr>
                    <td style="padding: 15px;">Jul 25, 2025</td>
                    <td style="padding: 15px;">John Smith</td>
                    <td style="padding: 15px;">Toyota Camry</td>
                    <td style="padding: 15px;">confirmed</td>
                    <td style="padding: 15px;">$135.00</td>
                </tr>
            </tbody>
        </table>';
    }
    
    // Vehicles content
    if ($page === 'vehicles' && $permissions->canAccess('vehicles')) {
        echo '
        <h2>Vehicle Management</h2>
        
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h3>Add New Vehicle</h3>
            <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Make:</label>
                    <input type="text" name="make" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Model:</label>
                    <input type="text" name="model" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Year:</label>
                    <input type="number" name="year" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">VIN:</label>
                    <input type="text" name="vin" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">License Plate:</label>
                    <input type="text" name="license_plate" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Color:</label>
                    <input type="text" name="color" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Mileage:</label>
                    <input type="number" name="mileage" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Daily Rate ($):</label>
                    <input type="number" step="0.01" name="daily_rate" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>
                </div>
                <div style="grid-column: span 2;">
                    <button type="submit" name="action" value="add_vehicle" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;" ' . ($permissions->canCreate('vehicles') ? '' : 'disabled') . '>Add Vehicle</button>
                </div>
            </form>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3>Vehicle Inventory</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Make</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Model</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Year</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">License Plate</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Daily Rate</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Mileage</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Get vehicles from database
        $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY make, model");
        while ($vehicle = $stmt->fetch()) {
            echo '<tr>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($vehicle['make']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($vehicle['model']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($vehicle['year']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($vehicle['license_plate']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($vehicle['status']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">$' . number_format($vehicle['daily_rate'], 2) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' . number_format($vehicle['mileage']) . '</td>
                <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">';
            
            if ($permissions->canEdit('vehicles')) {
                echo '<button style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">Edit</button>';
            }
            
            echo '</td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>';
    }
}
?>

