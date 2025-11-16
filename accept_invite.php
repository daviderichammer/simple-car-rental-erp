<?php
// Public Invitation Acceptance Page
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

$error = '';
$invitation = null;
$step = 1; // Step 1: Verify token, Step 2: Set password and profile

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    $error = 'Invalid invitation link. Please check your email for the correct link.';
} else {
    // Verify token and get invitation
    $stmt = $pdo->prepare("
        SELECT ui.*, r.name as role_name 
        FROM user_invitations ui
        LEFT JOIN roles r ON ui.role_id = r.id
        WHERE ui.token = ? AND ui.status = 'pending' AND ui.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        $error = 'This invitation is invalid or has expired. Please contact your administrator for a new invitation.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $phone = trim($_POST['phone']);
    $title = trim($_POST['title']);
    $department = trim($_POST['department']);
    $bio = trim($_POST['bio']);
    $timezone = $_POST['timezone'];
    
    // Validate
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Create user account
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, phone, title, department, bio, timezone, invitation_token, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $invitation['email'],
                $password_hash,
                $first_name,
                $last_name,
                $phone,
                $title,
                $department,
                $bio,
                $timezone,
                $token
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Assign role to user
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $invitation['role_id']]);
            
            // Update invitation status
            $stmt = $pdo->prepare("UPDATE user_invitations SET status = 'accepted', accepted_at = NOW() WHERE id = ?");
            $stmt->execute([$invitation['id']]);
            
            // Redirect to login page with success message
            header('Location: index.php?registered=1');
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create account: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation - Car Rental ERP</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .invite-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            margin: 20px;
        }
        .invite-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .invite-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .invite-body {
            padding: 40px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="invite-card">
        <div class="invite-header">
            <i class="fas fa-envelope-open-text fa-3x mb-3"></i>
            <h2>Welcome to Car Rental ERP</h2>
            <p class="mb-0">Complete your registration to get started</p>
        </div>
        <div class="invite-body">
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($invitation): ?>
            <div class="alert alert-info mb-4">
                <strong>Email:</strong> <?php echo htmlspecialchars($invitation['email']); ?><br>
                <strong>Role:</strong> <?php echo htmlspecialchars($invitation['role_name']); ?>
            </div>
            
            <form method="POST" action="" id="registrationForm">
                <h5 class="mb-3">Personal Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="+1 (555) 123-4567">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="title">Job Title</label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Fleet Manager">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Operations">
                </div>
                
                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select class="form-control" id="timezone" name="timezone">
                        <option value="America/New_York">Eastern Time (ET)</option>
                        <option value="America/Chicago">Central Time (CT)</option>
                        <option value="America/Denver">Mountain Time (MT)</option>
                        <option value="America/Los_Angeles">Pacific Time (PT)</option>
                        <option value="America/Phoenix">Arizona Time (MST)</option>
                        <option value="America/Anchorage">Alaska Time (AKT)</option>
                        <option value="Pacific/Honolulu">Hawaii Time (HST)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bio">Bio / Notes (Optional)</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us a bit about yourself..."></textarea>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Set Your Password</h5>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="form-text text-muted">Must be at least 8 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirm Password *</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                    <small class="form-text" id="passwordMatch"></small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">
                    <i class="fas fa-check-circle"></i> Complete Registration
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        $('#password').on('input', function() {
            var password = $(this).val();
            var strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
            
            var color = '#dc3545';
            if (strength >= 50) color = '#ffc107';
            if (strength >= 75) color = '#28a745';
            
            $('#passwordStrength').css({
                'width': strength + '%',
                'background-color': color
            });
        });
        
        // Password match indicator
        $('#password_confirm').on('input', function() {
            var password = $('#password').val();
            var confirm = $(this).val();
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    $('#passwordMatch').text('✓ Passwords match').removeClass('text-danger').addClass('text-success');
                } else {
                    $('#passwordMatch').text('✗ Passwords do not match').removeClass('text-success').addClass('text-danger');
                }
            } else {
                $('#passwordMatch').text('');
            }
        });
    </script>
</body>
</html>
