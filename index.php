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
/* Remove debug dotted borders from form fields */
input, select, textarea {
    border: 1px solid #ced4da !important;
}

input:focus, select:focus, textarea:focus {
    border-color: #80bdff !important;
    outline: 0 !important;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25) !important;
}
        </style>

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


// Handle GET AJAX requests for Details modals
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    if ($action === 'get_vehicle_details') {
        try {
            $vin = $_GET['vin'] ?? '';
            
            // Get vehicle info
            $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vin = ?");
            $stmt->execute([$vin]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Vehicle not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
                exit;
            }
            
            // Get owner info
            $stmt = $pdo->prepare("
                SELECT o.*, v.vin 
                FROM vehicle_owners o 
                JOIN vehicles v ON o.vin = v.vin 
                WHERE v.vin = ? 
                ORDER BY o.created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$vin]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get maintenance history
            $stmt = $pdo->prepare("
                SELECT m.*, v.make, v.model 
                FROM maintenance_schedules m 
                JOIN vehicles v ON m.vehicle_id = v.id 
                WHERE m.vehicle_id = ? 
                ORDER BY m.scheduled_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$vin]);
            $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get repair history
            $stmt = $pdo->prepare("
                SELECT * FROM repair_history 
                WHERE vehicle_id = ? 
                ORDER BY repair_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$vin]);
            $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get active rental_history
            $stmt = $pdo->prepare("
                SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email as customer_email
                FROM rental_history r 
                JOIN customers c ON r.guest_name = c.turo_guest_name 
                WHERE r.vehicle_identifier = ? AND r.trip_status IN ('confirmed', 'active')
                ORDER BY r.trip_start DESC
            ");
            $stmt->execute([$vin]);
            $rental_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get expenses
            $stmt = $pdo->prepare("
                SELECT ft.* FROM financial_transactions ft JOIN rental_history rh ON ft.reservation_id = rh.id 
                WHERE rh.vehicle_identifier = ? 
                ORDER BY ft.transaction_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$vin]);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate statistics
            $stats = [
                'total_trips' => count($rental_history),
                'total_revenue' => array_sum(array_column($rental_history, 'total_cost')),
                'total_expenses' => array_sum(array_column($expenses, 'amount')),
                'total_mileage' => $vehicle['mileage'] ?? 0
            ];
            
            echo json_encode([
                'success' => true,
                'vehicle' => $vehicle,
                'owner' => $owner,
                'maintenance' => $maintenance,
                'repairs' => $repairs,
                'rental_history' => $rental_history,
                'expenses' => $expenses,
                'statistics' => $stats
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle GET AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_maintenance':
            $stmt = $pdo->prepare("SELECT * FROM maintenance_schedules WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'maintenance' => $maintenance]);
            exit;
            
        case 'get_repair':
            $stmt = $pdo->prepare("SELECT * FROM repair_history WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $repair = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'repair' => $repair]);
            exit;
            
        case 'get_turo_sync_status':
            // Mock data for now - will be replaced with real data from scraping service
            $response = [
                'success' => true,
                'service_status' => [
                    'running' => true,
                    'uptime' => '2d 14h 32m'
                ],
                'queue' => [
                    'completed' => 0,
                    'total' => 0
                ],
                'metrics' => [
                    'success_rate' => 98,
                    'successful' => 147,
                    'total' => 150
                ],
                'last_sync' => [
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                    'details' => 'Synced 3 rental_history'
                ],
                'data_quality' => [
                    'rental_history_synced' => $pdo->query("SELECT COUNT(*) FROM rental_history WHERE data_source = 'turo_web'")->fetchColumn(),
                    'rental_history_today' => $pdo->query("SELECT COUNT(*) FROM rental_history WHERE data_source = 'turo_web' AND DATE(created_at) = CURDATE()")->fetchColumn(),
                    'vehicles_tracked' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
                    'vehicles_active' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")->fetchColumn(),
                    'completeness' => 95,
                    'failed_tasks' => 0
                ],
                'recent_scrapes' => [],
                'failed_tasks' => []
            ];
            
            // Get recent scraping operations from turo_sync_logs table (if exists)
            try {
                $stmt = $pdo->query("SELECT * FROM turo_sync_logs ORDER BY created_at DESC LIMIT 50");
                $logs = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $logs[] = [
                        'timestamp' => $row['created_at'],
                        'operation' => $row['operation'] ?? 'Sync',
                        'vehicle' => $row['vehicle_info'] ?? '--',
                        'status' => $row['status'] ?? 'success',
                        'duration' => $row['duration'] ?? 0,
                        'details' => $row['details'] ?? ''
                    ];
                }
                $response['recent_scrapes'] = $logs;
            } catch (Exception $e) {
                // Table might not exist yet
                $response['recent_scrapes'] = [];
            }
            
            echo json_encode($response);
            exit;
            
        case 'get_turo_account':
            $stmt = $pdo->prepare("SELECT * FROM turo_accounts WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'account' => $account]);
            exit;
            // Get Vehicle Details with all related data
            case 'get_vehicle_details':
                try {
                    $vin = $_GET['vin'] ?? '';
                    
                    // Get vehicle info
                    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vin = ?");
                    $stmt->execute([$vin]);
                    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$vehicle) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Vehicle not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
                        exit;
                    }
                    
                    // Get owner info
                    $stmt = $pdo->prepare("
                        SELECT o.*, v.vin 
                        FROM vehicle_owners o 
                        JOIN vehicles v ON o.vin = v.vin 
                        WHERE v.vin = ? 
                        ORDER BY o.created_at DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([$vin]);
                    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get maintenance history (last 10)
                    $stmt = $pdo->prepare("
                        SELECT m.*, v.make, v.model 
                        FROM maintenance_schedules m 
                        JOIN vehicles v ON m.vehicle_id = v.id 
                        WHERE m.vehicle_id = ? 
                        ORDER BY m.scheduled_date DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$vin]);
                    $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get repair history (last 10)
                    $stmt = $pdo->prepare("
                        SELECT * FROM repair_history 
                        WHERE vehicle_id = ? 
                        ORDER BY repair_date DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$vin]);
                    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get active rental_history
                    $stmt = $pdo->prepare("
                        SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email as customer_email, c.phone as customer_phone
                        FROM rental_history r 
                        JOIN customers c ON r.guest_name = c.turo_guest_name 
                        WHERE r.vehicle_identifier = ? AND r.trip_status IN ('confirmed', 'active')
                        ORDER BY r.trip_start DESC
                    ");
                    $stmt->execute([$vin]);
                    $rental_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get recent expenses (last 10)
                    $stmt = $pdo->prepare("
                        SELECT ft.* FROM financial_transactions ft
                        JOIN rental_history rh ON ft.trip_id = rh.trip_id
                        WHERE rh.vehicle_identifier = ?
                        ORDER BY ft.transaction_date DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$vin]);
                    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate statistics
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_trips,
                            SUM(total_cost) as total_revenue,
                            AVG(DATEDIFF(end_date, start_date)) as avg_trip_days
                        FROM rental_history 
                        WHERE vehicle_identifier = ? AND trip_status = 'completed'
                    ");
                    $stmt->execute([$vin]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get total expenses
                    $stmt = $pdo->prepare("SELECT SUM(amount) as total_expenses FROM financial_transactions ft JOIN rental_history rh ON ft.trip_id = rh.trip_id WHERE rh.vehicle_identifier = ?");
                    $stmt->execute([$vin]);
                    $expense_total = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['total_expenses'] = $expense_total['total_expenses'] ?? 0;
                    $stats['net_profit'] = ($stats['total_revenue'] ?? 0) - ($stats['total_expenses'] ?? 0);
                    
                    echo json_encode([
                        'success' => true,
                        'vehicle' => $vehicle,
                        'owner' => $owner,
                        'maintenance' => $maintenance,
                        'repairs' => $repairs,
                        'rental_history' => $rental_history,
                        'expenses' => $expenses,
                        'statistics' => $stats
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            
            // Get Owner Details with all owned vehicles
            case 'get_owner_details':
                try {
                    $owner_id = $_GET['id'] ?? '';
                    
                    // Get owner info
                    $stmt = $pdo->prepare("SELECT * FROM vehicle_owners WHERE id = ?");
                    $stmt->execute([$owner_id]);
                    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$owner) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Owner not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Owner not found']);
                        exit;
                    }
                    
                    // Get all owned vehicles
                    $stmt = $pdo->prepare("
                        SELECT v.*, o.created_at, o.owner_type
                        FROM vehicles v 
                        JOIN vehicle_owners o ON v.vin = o.vin 
                        WHERE o.owner_name = ? 
                        ORDER BY o.created_at DESC
                    ");
                    $stmt->execute([$owner['owner_name']]);
                    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate statistics
                    $stats = [
                        'total_vehicles' => count($vehicles),
                        'active_vehicles' => 0,
                        'total_value' => 0
                    ];
                    
                    foreach ($vehicles as $v) {
                        // All vehicles in the list are considered active
                        $stats['active_vehicles']++;
                        $stats['total_value'] += $v['daily_rate'] ?? 0;
                    }
                    
                    // Get total revenue from all owned vehicles
                    $vins = array_column($vehicles, 'vin');
                    if (!empty($vins)) {
                        $placeholders = str_repeat('?,', count($vins) - 1) . '?';
                        $stmt = $pdo->prepare("
                            SELECT SUM(trip_price) as total_revenue 
                            FROM rental_history 
                            WHERE vehicle_identifier IN ($placeholders) AND trip_status = 'completed'
                        ");
                        $stmt->execute($vins);
                        $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
                        $stats['total_revenue'] = $revenue['total_revenue'] ?? 0;
                    } else {
                        $stats['total_revenue'] = 0;
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'owner' => $owner,
                        'vehicles' => $vehicles,
                        'statistics' => $stats
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            
            // Get Customer Details with reservation history
            case 'get_customer_details':
                try {
                    $customer_id = $_GET['id'] ?? '';
                    
                    // Get customer info
                    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$customer) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Customer not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
                        exit;
                    }
                    
                    $guest_name = $customer['turo_guest_name'] ?? '';
                    
                    // Get all rental_history
                    $stmt = $pdo->prepare("
                        SELECT r.*, v.make, v.model, v.year, v.color, v.license_plate
                        FROM rental_history r 
                        JOIN vehicles v ON r.vehicle_identifier = v.vin 
                        WHERE r.guest_name = ? 
                        ORDER BY r.trip_start DESC
                    ");
                    $stmt->execute([$guest_name]);
                    $rental_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate statistics
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_rental_history,
                            SUM(CASE WHEN trip_status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
                            SUM(CASE WHEN trip_status = 'active' THEN 1 ELSE 0 END) as active_trips,
                            SUM(CASE WHEN trip_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_trips,
                            SUM(trip_price) as total_spent,
                            AVG(trip_price) as avg_trip_cost,
                            MIN(trip_start) as first_rental,
                            MAX(trip_start) as last_rental
                        FROM rental_history 
                        WHERE guest_name = ?
                    ");
                    $stmt->execute([$guest_name]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get favorite vehicles (most rented)
                    $stmt = $pdo->prepare("
                        SELECT v.make, v.model, v.vin, COUNT(*) as rental_count
                        FROM rental_history r
                        JOIN vehicles v ON r.vehicle_identifier = v.vin
                        WHERE r.guest_name = ?
                        GROUP BY v.vin
                        ORDER BY rental_count DESC
                        LIMIT 3
                    ");
                    $stmt->execute([$guest_name]);
                    $favorite_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'customer' => $customer,
                        'rental_history' => $rental_history,
                        'statistics' => $stats,
                        'favorite_vehicles' => $favorite_vehicles
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            
            // Get Reservation Details with customer and vehicle info
            case 'get_reservation_details':
                try {
                    $reservation_id = $_GET['id'] ?? '';
                    
                    // Get reservation info
                    $stmt = $pdo->prepare("SELECT * FROM rental_history WHERE reservation_id = ?");
                    $stmt->execute([$reservation_id]);
                    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$reservation) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Reservation not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
                        exit;
                    }
                    
                    // Get customer info
                    $stmt = $pdo->prepare("SELECT * FROM customers WHERE turo_guest_name = ?");
                    $stmt->execute([$reservation['guest_name']]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get vehicle info
                    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vin = ?");
                    $stmt->execute([$reservation['vehicle_identifier']]);
                    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get customer's other rental_history with this vehicle
                    $stmt = $pdo->prepare("
                        SELECT * FROM rental_history 
                        WHERE guest_name = ? AND vehicle_identifier = ? AND reservation_id != ?
                        ORDER BY trip_start DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$reservation['guest_name'], $reservation['vehicle_identifier'], $reservation_id]);
                    $previous_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate rental duration and costs
                    $start = new DateTime($reservation['trip_start']);
                    $end = new DateTime($reservation['trip_end']);
                    $duration = $start->diff($end)->days;
                    
                    $details = [
                        'duration_days' => $duration,
                        'daily_rate' => $vehicle['daily_rate'] ?? 0,
                        'subtotal' => $duration * ($vehicle['daily_rate'] ?? 0),
                        'total' => $reservation['trip_price'] ?? 0
                    ];
                    
                    echo json_encode([
                        'success' => true,
                        'reservation' => $reservation,
                        'customer' => $customer,
                        'vehicle' => $vehicle,
                        'previous_rentals' => $previous_rentals,
                        'details' => $details
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            
            // Get Maintenance Details
            case 'get_maintenance_details':
                try {
                    $maintenance_id = $_GET['id'] ?? '';
                    
                    // Get maintenance info
                    $stmt = $pdo->prepare("SELECT * FROM maintenance_schedules WHERE id = ?");
                    $stmt->execute([$maintenance_id]);
                    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$maintenance) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Maintenance record not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Maintenance record not found']);
                        exit;
                    }
                    
                    // Get vehicle info
                    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
                    $stmt->execute([$maintenance['vehicle_id']]);
                    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get other maintenance for this vehicle
                    $stmt = $pdo->prepare("
                        SELECT * FROM maintenance_schedules 
                        WHERE vehicle_id = ? AND id != ?
                        ORDER BY scheduled_date DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$maintenance['vehicle_id'], $maintenance_id]);
                    $other_maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'maintenance' => $maintenance,
                        'vehicle' => $vehicle,
                        'other_maintenance' => $other_maintenance
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
            
            // Get Repair Details
            case 'get_repair_details':
                try {
                    $repair_id = $_GET['id'] ?? '';
                    
                    // Get repair info
                    $stmt = $pdo->prepare("SELECT * FROM repair_history WHERE id = ?");
                    $stmt->execute([$repair_id]);
                    $repair = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$repair) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Repair record not found', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Repair record not found']);
                        exit;
                    }
                    
                    // Get vehicle info
                    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vin = ?");
                    $stmt->execute([$repair['vin']]);
                    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get other repairs for this vehicle
                    $stmt = $pdo->prepare("
                        SELECT * FROM repair_history 
                        WHERE vehicle_id = ? AND id != ?
                        ORDER BY repair_date DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$repair['vin'], $repair_id]);
                    $other_repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate total repair costs for this vehicle
                    $stmt = $pdo->prepare("SELECT SUM(cost) as total_cost FROM repair_history WHERE vehicle_id = ?");
                    $stmt->execute([$repair['vin']]);
                    $cost_total = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'repair' => $repair,
                        'vehicle' => $vehicle,
                        'other_repairs' => $other_repairs,
                        'total_repair_cost' => $cost_total['total_cost'] ?? 0
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
    }
}

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
                
            case 'create_vehicle':
            case 'update_vehicle':
            case 'create_vehicle':
                try {
                    $make = $_POST['make'] ?? '';
                    $model = $_POST['model'] ?? '';
                    $year = $_POST['year'] ?? '';
                    $color = $_POST['color'] ?? '';
                    $vin = $_POST['vin'] ?? '';
                    $license_plate = $_POST['license_plate'] ?? '';
                    $mileage = $_POST['mileage'] ?? 0;
                    $status = $_POST['status'] ?? 'available';
                    $daily_rate = $_POST['daily_rate'] ?? 0;
                    $airport = $_POST['airport'] ?? '';
                    $bouncie_id = $_POST['bouncie_id'] ?? '';
                    $sunpass_id = $_POST['sunpass_id'] ?? '';
                    $ezpass_id = $_POST['ezpass_id'] ?? '';
                    $lockbox_code = $_POST['lockbox_code'] ?? '';
                    $mister_carwash_rfid = $_POST['mister_carwash_rfid'] ?? '';
                    $fuel_type = $_POST['fuel_type'] ?? '';
                    $fuel_capacity = $_POST['fuel_capacity'] ?? null;
                    $oil_type = $_POST['oil_type'] ?? '';
                    $oil_change_interval = $_POST['oil_change_interval'] ?? '';
                    $tire_front_size = $_POST['tire_front_size'] ?? '';
                    $tire_rear_size = $_POST['tire_rear_size'] ?? '';
                    $registration_expiry = $_POST['registration_expiry'] ?? '';
                    $date_added = $_POST['date_added'] ?? null;
                    
                    // Validation
                    if (empty($make) || empty($model) || empty($year) || empty($vin) || empty($daily_rate)) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Make, model, year, VIN, and daily rate are required', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Make, model, year, VIN, and daily rate are required']);
                        exit;
                    }
                    
                    // Check if VIN already exists
                    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE vin = ?");
                    $stmt->execute([$vin]);
                    if ($stmt->fetch()) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Vehicle with this VIN already exists', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Vehicle with this VIN already exists']);
                        exit;
                    }
                    
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO vehicles (
                            make, model, year, color, vin, license_plate, mileage, status, daily_rate,
                            airport, bouncie_id, sunpass_id, ezpass_id, lockbox_code, mister_carwash_rfid,
                            fuel_type, fuel_capacity, oil_type, oil_change_interval,
                            tire_front_size, tire_rear_size, registration_expiry, date_added, created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $make, $model, $year, $color, $vin, $license_plate, $mileage, $status, $daily_rate,
                        $airport, $bouncie_id, $sunpass_id, $ezpass_id, $lockbox_code, $mister_carwash_rfid,
                        $fuel_type, $fuel_capacity, $oil_type, $oil_change_interval,
                        $tire_front_size, $tire_rear_size, $registration_expiry, $date_added
                    ]);
                    
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Vehicle added successfully', 'success');</script>";
        echo json_encode(['success' => true, 'message' => 'Vehicle added successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
                
                $stmt = $pdo->prepare("UPDATE vehicles SET make = ?, model = ?, year = ?, vin = ?, license_plate = ?, color = ?, mileage = ?, daily_rate = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_vehicle':
                // Check if vehicle has active rental_history
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM rental_history WHERE vehicle_identifier = ? AND status IN ('pending', 'confirmed', 'active')");
                $stmt->execute([$_POST['id']]);
                $activeReservations = $stmt->fetchColumn();
                
                if ($activeReservations > 0) {
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Cannot delete vehicle with active rental_history', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active rental_history']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'create_owner':
                try {
                    $vin = $_POST['vin'] ?? '';
                    $owner_name = $_POST['owner_name'] ?? '';
                    $owner_type = $_POST['owner_type'] ?? null;
                    
                    // Validation
                    if (empty($vin) || empty($owner_name)) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('VIN and owner name are required', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'VIN and owner name are required']);
                        exit;
                    }
                    
                    // Insert into database
                    $stmt = $pdo->prepare("INSERT INTO vehicle_owners (vin, owner_name, owner_type) VALUES (?, ?, ?)");
                    $stmt->execute([$vin, $owner_name, $owner_type]);
                    
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Owner added successfully', 'success');</script>";
        echo json_encode(['success' => true, 'message' => 'Owner added successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'delete_owner':
                try {
                    $id = $_POST['id'] ?? '';
                    
                    if (empty($id)) {
                        // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Owner ID is required', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Owner ID is required']);
                        exit;
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM vehicle_owners WHERE id = ?");
                    $success = $stmt->execute([$id]);
                    
                    echo json_encode(['success' => $success, 'message' => $success ? 'Owner deleted successfully' : 'Failed to delete owner']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
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
                // Check if customer has active rental_history
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM rental_history WHERE guest_name = ? AND status IN ('pending', 'confirmed', 'active')");
                $stmt->execute([$_POST['id']]);
                $activeReservations = $stmt->fetchColumn();
                
                if ($activeReservations > 0) {
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Cannot delete customer with active rental_history', 'error');</script>";
        echo json_encode(['success' => false, 'message' => 'Cannot delete customer with active rental_history']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_reservation':
                $stmt = $pdo->prepare("SELECT * FROM rental_history WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($reservation);
                exit;
                
            case 'update_reservation':
                $stmt = $pdo->prepare("UPDATE rental_history SET guest_name = ?, vehicle_id = ?, start_date = ?, end_date = ?, pickup_location = ?, dropoff_location = ?, total_amount = ?, status = ?, notes = ? WHERE id = ?");
                $success = $stmt->execute([$_POST['guest_name'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['status'], $_POST['notes'], $_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
              case 'delete_owner':
                $stmt = $pdo->prepare("DELETE FROM vehicle_owners WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'create_maintenance':
                $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, status, description) VALUES (?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['vehicle_id'], $_POST['maintenance_type'], $_POST['scheduled_date'], $_POST['status'], $_POST['description']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'get_maintenance':
                $stmt = $pdo->prepare("SELECT * FROM maintenance_schedules WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'maintenance' => $maintenance]);
                exit;
                
            case 'edit_maintenance':
                $stmt = $pdo->prepare("UPDATE maintenance_schedules SET vehicle_id = ?, maintenance_type = ?, scheduled_date = ?, status = ?, description = ? WHERE id = ?");
                $success = $stmt->execute([
                    $_POST['vehicle_id'],
                    $_POST['maintenance_type'],
                    $_POST['scheduled_date'],
                    $_POST['status'],
                    $_POST['description'],
                    $_POST['id']
                ]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_maintenance':
                $stmt = $pdo->prepare("DELETE FROM maintenance_schedules WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'bulk_delete_maintenance':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM maintenance_schedules WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_owners':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM vehicle_owners WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_expenses':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM expense_refunds WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_work_orders':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM work_orders WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_vehicles':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_customers':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            case 'bulk_delete_rental_history':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM rental_history WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            // Repairs handlers
            case 'create_repair':
                $stmt = $pdo->prepare("INSERT INTO repair_history (vin, repair_date, repair_type, description, cost, vendor, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['vin'], $_POST['repair_date'], $_POST['repair_type'], $_POST['description'], $_POST['cost'], $_POST['vendor'], $_POST['status'], $_POST['notes'] ?? '']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'add_repair':
                $stmt = $pdo->prepare("INSERT INTO repair_history (vehicle_id, repair_date, mileage, problem_description, repair_description, cost, vendor, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([
                    $_POST['vehicle_id'],
                    $_POST['repair_date'] ?: null,
                    $_POST['mileage'] ?: null,
                    $_POST['problem_description'] ?: null,
                    $_POST['repair_description'] ?: null,
                    $_POST['cost'] ?: null,
                    $_POST['vendor'] ?: null,
                    $_POST['status'] ?: 'pending'
                ]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Repair added successfully!' : 'Failed to add repair']);
                exit;
                
            case 'edit_repair':
                $stmt = $pdo->prepare("UPDATE repair_history SET vehicle_id = ?, repair_date = ?, mileage = ?, problem_description = ?, repair_description = ?, cost = ?, vendor = ?, status = ? WHERE id = ?");
                $success = $stmt->execute([
                    $_POST['vehicle_id'],
                    $_POST['repair_date'] ?: null,
                    $_POST['mileage'] ?: null,
                    $_POST['problem_description'] ?: null,
                    $_POST['repair_description'] ?: null,
                    $_POST['cost'] ?: null,
                    $_POST['vendor'] ?: null,
                    $_POST['status'] ?: 'pending',
                    $_POST['id']
                ]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Repair updated successfully!' : 'Failed to update repair']);
                exit;
                
            case 'delete_repair':
                $stmt = $pdo->prepare("DELETE FROM repair_history WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'bulk_delete_repairs':
                $ids = explode(',', $_POST['ids']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM repair_history WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                echo json_encode(['success' => $success, 'deleted_count' => $stmt->rowCount()]);
                exit;
                
            // Turo Accounts handlers
            case 'add_turo_account':
                $stmt = $pdo->prepare("INSERT INTO turo_accounts (account_name, email, password, location, is_active) VALUES (?, ?, ?, ?, ?)");
                $success = $stmt->execute([
                    $_POST['account_name'],
                    $_POST['email'],
                    $_POST['password'],
                    $_POST['location'],
                    isset($_POST['is_active']) ? 1 : 0
                ]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'edit_turo_account':
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    // Update with password
                    $stmt = $pdo->prepare("UPDATE turo_accounts SET account_name = ?, email = ?, password = ?, location = ?, is_active = ? WHERE id = ?");
                    $success = $stmt->execute([
                        $_POST['account_name'],
                        $_POST['email'],
                        $_POST['password'],
                        $_POST['location'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['id']
                    ]);
                } else {
                    // Update without password
                    $stmt = $pdo->prepare("UPDATE turo_accounts SET account_name = ?, email = ?, location = ?, is_active = ? WHERE id = ?");
                    $success = $stmt->execute([
                        $_POST['account_name'],
                        $_POST['email'],
                        $_POST['location'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['id']
                    ]);
                }
                echo json_encode(['success' => $success]);
                exit;
                
            case 'deactivate_turo_account':
                $stmt = $pdo->prepare("UPDATE turo_accounts SET is_active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'activate_turo_account':
                $stmt = $pdo->prepare("UPDATE turo_accounts SET is_active = 1 WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Reservations handlers
            case 'create_reservation':
                $stmt = $pdo->prepare("INSERT INTO rental_history (guest_name, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['guest_name'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['status'], $_POST['notes'] ?? '']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_reservation':
                $stmt = $pdo->prepare("DELETE FROM rental_history WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Customers handlers
            case 'create_customer':
                $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, city, state, zip, license_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['state'] ?? '', $_POST['zip'] ?? '', $_POST['license_number'] ?? '', $_POST['notes'] ?? '']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_customer':
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Work Orders handlers
            case 'create_work_order':
                $stmt = $pdo->prepare("INSERT INTO work_orders (vehicle_id, title, description, priority, status, assigned_to, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['vehicle_id'], $_POST['title'], $_POST['description'], $_POST['priority'], $_POST['status'], $_POST['assigned_to'] ?? null, $_POST['due_date'] ?? null]);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_work_order':
                $stmt = $pdo->prepare("DELETE FROM work_orders WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Expenses handlers
            case 'create_expense':
                $stmt = $pdo->prepare("INSERT INTO expenses (vehicle_id, category, description, amount, transaction_date, vendor, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['vehicle_id'] ?? null, $_POST['category'], $_POST['description'], $_POST['amount'], $_POST['transaction_date'], $_POST['vendor'] ?? '', $_POST['payment_method'] ?? '', $_POST['notes'] ?? '']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_expense':
                $stmt = $pdo->prepare("DELETE FROM financial_transactions WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Team handlers
            case 'create_team_member':
                $stmt = $pdo->prepare("INSERT INTO team_members (name, email, phone, role, department, hire_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'] ?? '', $_POST['role'], $_POST['department'] ?? '', $_POST['hire_date'] ?? null, $_POST['status'] ?? 'active', $_POST['notes'] ?? '']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_team_member':
                $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Users handlers
            case 'create_user':
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, status) VALUES (?, ?, ?, ?, ?)");
                $success = $stmt->execute([$_POST['username'], $_POST['email'], $hashedPassword, $_POST['role_id'], $_POST['status'] ?? 'active']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_user':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Roles handlers
            case 'create_role':
                $stmt = $pdo->prepare("INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)");
                $success = $stmt->execute([$_POST['name'], $_POST['description'] ?? '', $_POST['permissions'] ?? '{}']);
                echo json_encode(['success' => $success]);
                exit;
                
            case 'delete_role':
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => $success]);
                exit;
                
            // Invitations handlers
            case 'create_invitation':
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("INSERT INTO invitations (email, role_id, token, expires_at, status) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending')");
                $success = $stmt->execute([$_POST['email'], $_POST['role_id'], $token]);
                echo json_encode(['success' => $success, 'token' => $token]);
                exit;
                
            case 'delete_invitation':
                $stmt = $pdo->prepare("DELETE FROM invitations WHERE id = ?");
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
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Cannot delete your own account', 'error');</script>";
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
                    // Toast notification
        echo "<script>if(typeof showToast === 'function') showToast('Cannot delete role assigned to users', 'error');</script>";
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
                $stmt = $pdo->prepare("INSERT INTO rental_history (guest_name, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$_POST['guest_name'], $_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'], $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']]);
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
            
            /* Mobile Table Responsiveness */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 1rem;
            }
            
            table {
                min-width: 800px;
                font-size: 0.85rem;
            }
            
            table th:last-child,
            table td:last-child {
                position: sticky;
                right: 0;
                background: white;
                box-shadow: -2px 0 5px rgba(0,0,0,0.1);
                z-index: 10;
            }
            
            .btn-edit, .btn-delete {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
                min-width: 60px;
                touch-action: manipulation;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
            
            .modal-footer {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .modal-footer button {
                width: 100%;
                margin: 0;
            }
            
            .nav-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
            }
            
            .nav-tab {
                font-size: 0.85rem;
                padding: 0.5rem 0.8rem;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
        }

        /* Sidebar Navigation Styles */
        .app-wrapper {
            display: flex;
            min-height: calc(100vh - 60px);
        }

        .sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: calc(100vh - 60px);
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 100;
            top: 60px;
        }

        .sidebar.collapsed {
            transform: translateX(-260px);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            font-size: 1.1rem;
            color: #333;
            font-weight: 600;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #667eea;
            padding: 0.25rem;
            display: none;
        }

        .nav-menu {
            padding: 1rem 0;
        }

        .nav-group {
            margin-bottom: 1.5rem;
        }

        .nav-group-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: #666;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(102, 126, 234, 0.08);
            color: #667eea;
            padding-left: 1.75rem;
        }

        .nav-item.active {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            font-weight: 600;
            border-left: 3px solid #667eea;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            margin-left: auto;
            background: #667eea;
            color: white;
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            min-width: 24px;
            text-align: center;
        }


        /* Fix content area width and padding */
        .main-content-wrapper {
            flex: 1;
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 60px);
            padding: 20px;
            max-width: calc(100vw - 260px);
            overflow-x: auto;
        }
        
        .main-content-wrapper.expanded {
            margin-left: 0;
            max-width: 100vw;
        }
        
        /* Remove debug borders from form elements */
        input, select, textarea {
            border: 1px solid #ddd !important;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #6c63ff !important;
            outline: none;
            box-shadow: 0 0 0 2px rgba(108, 99, 255, 0.1);
        }
        
        /* Fix container fluid width */
        .container-fluid {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }
        
        /* Ensure cards display properly */
        .card {
            margin-bottom: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Fix table responsiveness */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            margin-bottom: 1rem;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }










        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #667eea;
            padding: 0.25rem;
            display: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content-wrapper {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .sidebar-toggle {
                display: block;
            }
        }

        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left"><button class="mobile-toggle" onclick="toggleSidebar()">☰</button><h1>Car Rental ERP System</h1></div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
    
    
    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>🚗 Menu</h2>
                <button class="sidebar-toggle" onclick="toggleSidebar()">✕</button>
            </div>
            
            <div class="nav-menu">
                <!-- Dashboard -->
                <div class="nav-group">
                    <div class="nav-group-title">
                        <span>📊</span> OVERVIEW
                    </div>
                    <a href="?page=dashboard" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                        <span class="nav-badge">4</span>
                    </a>
                </div>
                
                <!-- Fleet Management -->
                <div class="nav-group">
                    <div class="nav-group-title">
                        <span>🚙</span> FLEET MANAGEMENT
                    </div>
                    <a href="?page=vehicles" class="nav-item <?php echo $current_page === 'vehicles' ? 'active' : ''; ?>">
                        <span class="nav-icon">🚗</span>
                        <span>Vehicles</span>
                        <span class="nav-badge">165</span>
                    </a>
                    <a href="?page=owners" class="nav-item <?php echo $current_page === 'owners' ? 'active' : ''; ?>">
                        <span class="nav-icon">👤</span>
                        <span>Owners</span>
                        <span class="nav-badge">158</span>
                    </a>
                    <a href="?page=maintenance" class="nav-item <?php echo $current_page === 'maintenance' ? 'active' : ''; ?>">
                        <span class="nav-icon">🔧</span>
                        <span>Maintenance</span>
                        <span class="nav-badge">2</span>
                    </a>
                    <a href="?page=repairs" class="nav-item <?php echo $current_page === 'repairs' ? 'active' : ''; ?>">
                        <span class="nav-icon">🛠️</span>
                        <span>Repairs</span>
                        <span class="nav-badge">2</span>
                    </a>
                </div>
                
                <!-- Operations -->
                <div class="nav-group">
                    <div class="nav-group-title">
                        <span>📋</span> OPERATIONS
                    </div>
                    <a href="?page=rental_history" class="nav-item <?php echo $current_page === 'rental_history' ? 'active' : ''; ?>">
                        <span class="nav-icon">📜</span>
                        <span>Rentals</span>
                        <span class="nav-badge">3,404</span>
                    </a>
                    <a href="?page=customers" class="nav-item <?php echo $current_page === 'customers' ? 'active' : ''; ?>">
                        <span class="nav-icon">👥</span>
                        <span>Customers</span>
                        <span class="nav-badge">6</span>
                    </a>
                    <a href="?page=work_orders" class="nav-item <?php echo $current_page === 'work_orders' ? 'active' : ''; ?>">
                        <span class="nav-icon">📝</span>
                        <span>Work Orders</span>
                        <span class="nav-badge">670</span>
                    </a>
                    <a href="?page=expenses" class="nav-item <?php echo $current_page === 'expenses' ? 'active' : ''; ?>">
                        <span class="nav-icon">💰</span>
                        <span>Expenses</span>
                        <span class="nav-badge">608</span>
                    </a>
                </div>
                
                <!-- Analytics & Reports -->
                <div class="nav-group">
                    <div class="nav-group-title">
                        <span>📈</span> ANALYTICS & REPORTS
                    </div>
                    <a href="?page=analytics" class="nav-item <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                        <span class="nav-icon">📊</span>
                        <span>Analytics</span>
                    </a>
                    <a href="?page=turo_metrics" class="nav-item <?php echo $current_page === 'turo_metrics' ? 'active' : ''; ?>">
                        <span class="nav-icon">📉</span>
                        <span>Turo Metrics</span>
                    </a>
                    <a href="?page=ev_charging" class="nav-item <?php echo $current_page === 'ev_charging' ? 'active' : ''; ?>">
                        <span class="nav-icon">⚡</span>
                        <span>EV Charging</span>
                    </a>
                    <a href="?page=turo_import" class="nav-item <?php echo $current_page === 'turo_import' ? 'active' : ''; ?>">
                        <span class="nav-icon">📥</span>
                        <span>Turo Import</span>
                    </a>
                    <a href="?page=turo_sync_logs" class="nav-item <?php echo $current_page === 'turo_sync_logs' ? 'active' : ''; ?>">
                        <span class="nav-icon">📊</span>
                        <span>Turo Sync Logs</span>
                    </a>
                </div>
                
                <!-- System & Users -->
                <div class="nav-group">
                    <div class="nav-group-title">
                        <span>⚙️</span> SYSTEM & USERS
                    </div>
                    <a href="?page=team" class="nav-item <?php echo $current_page === 'team' ? 'active' : ''; ?>">
                        <span class="nav-icon">👨‍💼</span>
                        <span>Team</span>
                        <span class="nav-badge">15</span>
                    </a>
                    <a href="?page=users" class="nav-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                        <span class="nav-icon">👤</span>
                        <span>Users</span>
                        <span class="nav-badge">1</span>
                    </a>
                    <a href="?page=roles" class="nav-item <?php echo $current_page === 'roles' ? 'active' : ''; ?>">
                        <span class="nav-icon">🔐</span>
                        <span>Roles</span>
                        <span class="nav-badge">4</span>
                    </a>
                    <a href="?page=user_invitations" class="nav-item <?php echo $current_page === 'user_invitations' ? 'active' : ''; ?>">
                        <span class="nav-icon">✉️</span>
                        <span>Invitations</span>
                    </a>
                    <a href="?page=telephone" class="nav-item <?php echo $current_page === 'telephone' ? 'active' : ''; ?>">
                        <span class="nav-icon">📞</span>
                        <span>Telephone</span>
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Main Content Area -->
        <div class="main-content-wrapper" id="mainContent">

<!-- Toast Notification System -->
<style>
/* Toast Container */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    pointer-events: none;
}

/* Toast Base Styles */
.toast {
    min-width: 300px;
    max-width: 500px;
    padding: 16px 20px;
    margin-bottom: 12px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    pointer-events: auto;
    animation: slideIn 0.3s ease-out;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.5;
}

.toast-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.toast-content {
    flex: 1;
    color: #fff;
}

.toast-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.toast-message {
    opacity: 0.95;
    font-size: 13px;
}

.toast-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    opacity: 0.7;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
    flex-shrink: 0;
}

.toast-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
}

.toast-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.toast-error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.toast-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.toast-info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.toast-exit {
    animation: slideOut 0.3s ease-in forwards;
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 0 0 8px 8px;
    animation: progress linear;
}

@keyframes progress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

@media (max-width: 640px) {
    .toast-container {
        left: 20px;
        right: 20px;
    }
    
    .toast {
        min-width: auto;
        max-width: none;
    }
}
</style>

<div id="toastContainer" class="toast-container"></div>

<script>
const ToastNotification = {
    container: null,
    
    init() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(options) {
        if (!this.container) this.init();
        
        const {
            type = 'info',
            title = '',
            message = '',
            duration = 4000,
            closable = true
        } = options;
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                ${message ? `<div class="toast-message">${message}</div>` : ''}
            </div>
            ${closable ? '<button class="toast-close" onclick="ToastNotification.close(this.parentElement)">×</button>' : ''}
            ${duration > 0 ? `<div class="toast-progress" style="animation-duration: ${duration}ms"></div>` : ''}
        `;
        
        this.container.appendChild(toast);
        
        if (duration > 0) {
            setTimeout(() => {
                this.close(toast);
            }, duration);
        }
        
        return toast;
    },
    
    close(toast) {
        if (!toast) return;
        
        toast.classList.add('toast-exit');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    },
    
    success(title, message, duration) {
        return this.show({ type: 'success', title, message, duration });
    },
    
    error(title, message, duration) {
        return this.show({ type: 'error', title, message, duration });
    },
    
    warning(title, message, duration) {
        return this.show({ type: 'warning', title, message, duration });
    },
    
    info(title, message, duration) {
        return this.show({ type: 'info', title, message, duration });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    ToastNotification.init();
});

window.ToastNotification = ToastNotification;
window.showToast = (type, title, message, duration) => ToastNotification.show({ type, title, message, duration });
</script>
<!-- End Toast Notification System -->
    
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
                document.getElementById('edit_guest_name').value = data.id;
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
                document.getElementById('edit_guest_name').value = data.guest_name;
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
    </script>
<!-- Vehicle Details Modal -->
<style>
.details-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.details-modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 1200px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.details-modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.details-modal-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.details-modal-close {
    color: white;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background 0.2s;
}

.details-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.details-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #e0e0e0;
    padding: 0 20px;
}

.details-tab {
    padding: 15px 25px;
    cursor: pointer;
    border: none;
    background: none;
    font-size: 15px;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    position: relative;
    top: 2px;
}

.details-tab:hover {
    color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.details-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: white;
}

.details-modal-body {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}

.details-tab-content {
    display: none;
}

.details-tab-content.active {
    display: block;
}

.details-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.details-info-item {
    display: flex;
    flex-direction: column;
}

.details-info-label {
    font-size: 12px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.details-info-value {
    font-size: 16px;
    color: #333;
    font-weight: 500;
}

.details-info-value.clickable {
    color: #667eea;
    cursor: pointer;
    text-decoration: underline;
}

.details-info-value.clickable:hover {
    color: #764ba2;
}

.details-section-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 30px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}

.details-table {
</script>
<style>
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.details-table thead {
    background: #f8f9fa;
}

.details-table th {
    padding: 12px 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.details-table td {
    padding: 12px 15px;
    border-top: 1px solid #e0e0e0;
    font-size: 14px;
    color: #333;
}

.details-table tr:hover {
    background: #f8f9fa;
}

.details-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.details-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.details-stat-label {
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.details-stat-value {
    font-size: 28px;
    font-weight: 700;
}

.details-empty {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 15px;
}

.details-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.details-badge.status-available {
    background: #d4edda;
    color: #155724;
}

.details-badge.status-rented {
    background: #fff3cd;
    color: #856404;
}

.details-badge.status-maintenance {
    background: #f8d7da;
    color: #721c24;
}

.details-badge.status-completed {
    background: #d4edda;
    color: #155724;
}

.details-badge.status-scheduled {
    background: #d1ecf1;
    color: #0c5460;
}

.details-badge.status-confirmed {
    background: #d1ecf1;
    color: #0c5460;
}

.details-badge.status-active {
    background: #fff3cd;
    color: #856404;
}

.details-quick-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.details-action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.details-action-btn.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.details-action-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.details-action-btn.secondary {
    background: #f8f9fa;
    color: #333;
}

.details-action-btn.secondary:hover {
    background: #e9ecef;
}

.details-loading {
    text-align: center;
    padding: 60px;
    font-size: 16px;
    color: #666;
}

.details-error {
    text-align: center;
    padding: 60px;
    color: #dc3545;
    font-size: 16px;
}
</style>

<!-- Vehicle Details Modal HTML -->
<div id="vehicleDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>🚗 <span id="vehicleDetailsTitle">Vehicle Details</span></h2>
            <button class="details-modal-close" onclick="closeVehicleDetailsModal()">&times;</button>
        </div>
        
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchVehicleTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchVehicleTab('owner')">👤 Owner</button>
            <button class="details-tab" onclick="switchVehicleTab('maintenance')">🔧 Maintenance</button>
            <button class="details-tab" onclick="switchVehicleTab('repairs')">🛠️ Repairs</button>
            <button class="details-tab" onclick="switchVehicleTab('rental_history')">📅 Reservations</button>
            <button class="details-tab" onclick="switchVehicleTab('expenses')">💰 Expenses</button>
            <button class="details-tab" onclick="switchVehicleTab('stats')">📊 Statistics</button>
        </div>
        
        <div class="details-modal-body" id="vehicleDetailsBody">
            <div class="details-loading">Loading vehicle details...</div>
        </div>
    </div>
</div>

<script>
let currentVehicleData = null;

function showVehicleDetails(vin) {
    document.getElementById('vehicleDetailsModal').style.display = 'block';
    document.getElementById('vehicleDetailsBody').innerHTML = '<div class="details-loading">Loading vehicle details...</div>';
    
    // Fetch vehicle details
    fetch('index.php?ajax=1&action=get_vehicle_details&vin=' + encodeURIComponent(vin))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentVehicleData = data;
                renderVehicleDetails(data);
            } else {
                document.getElementById('vehicleDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load vehicle details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('vehicleDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeVehicleDetailsModal() {
    document.getElementById('vehicleDetailsModal').style.display = 'none';
    currentVehicleData = null;
}

function switchVehicleTab(tabName) {
    // Update tab buttons
    const tabs = document.querySelectorAll('.details-tabs .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Render tab content
    if (currentVehicleData) {
        renderVehicleTabContent(tabName, currentVehicleData);
    }
}

function renderVehicleDetails(data) {
    const v = data.vehicle;
    document.getElementById('vehicleDetailsTitle').textContent = 
        `${v.year || ''} ${v.make || ''} ${v.model || ''}`.trim() || 'Vehicle Details';
    
    // Render initial tab (info)
    renderVehicleTabContent('info', data);
}

function renderVehicleTabContent(tabName, data) {
    const body = document.getElementById('vehicleDetailsBody');
    const v = data.vehicle;
    const owner = data.owner;
    const maintenance = data.maintenance || [];
    const repairs = data.repairs || [];
    const rental_history = data.rental_history || [];
    const expenses = data.expenses || [];
    const stats = data.statistics || {};
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">VIN</div>
                        <div class="details-info-value">${v.vin || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Make</div>
                        <div class="details-info-value">${v.make || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Model</div>
                        <div class="details-info-value">${v.model || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Year</div>
                        <div class="details-info-value">${v.year || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Color</div>
                        <div class="details-info-value">${v.color || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">License Plate</div>
                        <div class="details-info-value">${v.license_plate || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Status</div>
                        <div class="details-info-value">
                            <span class="details-badge status-${(v.status || '').toLowerCase()}">${v.status || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Daily Rate</div>
                        <div class="details-info-value">$${v.daily_rate || '0'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Location</div>
                        <div class="details-info-value">${v.location || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Mileage</div>
                        <div class="details-info-value">${v.mileage || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Bouncie ID</div>
                        <div class="details-info-value">${v.bouncie_id || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">SunPass ID</div>
                        <div class="details-info-value">${v.sunpass_id || 'N/A'}</div>
                    </div>
                </div>
                
                <div class="details-quick-actions">
                    <button class="details-action-btn primary" onclick="alert('Edit functionality coming soon')">✏️ Edit Vehicle</button>
                    <button class="details-action-btn secondary" onclick="alert('Add maintenance functionality coming soon')">🔧 Add Maintenance</button>
                    <button class="details-action-btn secondary" onclick="alert('New reservation functionality coming soon')">📅 New Reservation</button>
                </div>
            `;
            break;
            
        case 'owner':
            if (owner) {
                html = `
                    <div class="details-section-title">Current Owner</div>
                    <div class="details-info-grid">
                        <div class="details-info-item">
                            <div class="details-info-label">Owner Name</div>
                            <div class="details-info-value clickable" onclick="showOwnerDetails(${owner.id})">
                                ${owner.owner_name || 'N/A'}
                            </div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Ownership Type</div>
                            <div class="details-info-value">${owner.ownership_type || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Start Date</div>
                            <div class="details-info-value">${owner.created_at || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">End Date</div>
                            <div class="details-info-value">${owner.ownership_end_date || 'Active'}</div>
                        </div>
                    </div>
                    <div class="details-quick-actions">
                        <button class="details-action-btn primary" onclick="showOwnerDetails(${owner.id})">👤 View Full Owner Profile</button>
                    </div>
                `;
            } else {
                html = '<div class="details-empty">No owner information available</div>';
            }
            break;
            
        case 'maintenance':
            if (maintenance.length > 0) {
                html = `
                    <div class="details-section-title">Maintenance History (${maintenance.length} records)</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${maintenance.map(m => `
                                <tr style="cursor: pointer;" onclick="showMaintenanceDetails(${m.id})">
                                    <td>${m.scheduled_date || 'N/A'}</td>
                                    <td>${m.maintenance_type || 'N/A'}</td>
                                    <td><span class="details-badge status-${(m.status || '').toLowerCase()}">${m.status || 'N/A'}</span></td>
                                    <td>${m.description || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No maintenance records found</div>';
            }
            break;
            
        case 'repairs':
            if (repairs.length > 0) {
                html = `
                    <div class="details-section-title">Repair History (${repairs.length} records)</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${repairs.map(r => `
                                <tr style="cursor: pointer;" onclick="showRepairDetails(${r.id})">
                                    <td>${r.repair_date || 'N/A'}</td>
                                    <td>${r.description || 'N/A'}</td>
                                    <td>$${r.cost || '0'}</td>
                                    <td><span class="details-badge status-${(r.trip_status || '').toLowerCase()}">${r.trip_status || 'N/A'}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No repair records found</div>';
            }
            break;
            
        case 'rental_history':
            if (rental_history.length > 0) {
                html = `
                    <div class="details-section-title">Active Reservations (${rental_history.length})</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rental_history.map(r => `
                                <tr style="cursor: pointer;" onclick="showReservationDetails(${r.id})">
                                    <td class="clickable" onclick="event.stopPropagation(); showCustomerDetails(${r.guest_name})">${r.customer_name || 'N/A'}</td>
                                    <td>${r.trip_start || 'N/A'}</td>
                                    <td>${r.trip_end || 'N/A'}</td>
                                    <td><span class="details-badge status-${(r.trip_status || '').toLowerCase()}">${r.trip_status || 'N/A'}</span></td>
                                    <td>${r.customer_phone || r.customer_email || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No active rental_history</div>';
            }
            break;
            
        case 'expenses':
            if (expenses.length > 0) {
                const totalExpenses = expenses.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
                html = `
                    <div class="details-section-title">Recent Expenses (${expenses.length} records)</div>
                    <div class="details-stat-card" style="margin-bottom: 20px;">
                        <div class="details-stat-label">Total Expenses</div>
                        <div class="details-stat-value">$${totalExpenses.toFixed(2)}</div>
                    </div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${expenses.map(e => `
                                <tr>
                                    <td>${e.date || 'N/A'}</td>
                                    <td>${e.category || 'N/A'}</td>
                                    <td>${e.description || 'N/A'}</td>
                                    <td>$${e.amount || '0'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No expense records found</div>';
            }
            break;
            
        case 'stats':
            html = `
                <div class="details-section-title">Vehicle Statistics</div>
                <div class="details-stats-grid">
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Trips</div>
                        <div class="details-stat-value">${stats.total_trips || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Revenue</div>
                        <div class="details-stat-value">$${parseFloat(stats.total_revenue || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Expenses</div>
                        <div class="details-stat-value">$${parseFloat(stats.total_expenses || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Net Profit</div>
                        <div class="details-stat-value">$${parseFloat(stats.net_profit || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Avg Trip Duration</div>
                        <div class="details-stat-value">${parseFloat(stats.avg_trip_days || 0).toFixed(1)} days</div>
                    </div>
                </div>
            `;
            break;
    }
    
    body.innerHTML = html;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('vehicleDetailsModal');
    if (event.target == modal) {
        closeVehicleDetailsModal();
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeVehicleDetailsModal();
    }
});
</script>
<!-- 
    COMPREHENSIVE DETAILS MODAL SYSTEM
    Includes modals for: Vehicle, Owner, Customer, Reservation, Maintenance, Repair
    With cross-linking, tabs, and quick actions
-->

<style>
/* Shared styles for all details modals - already included in vehicle_details_modal.html */
</style>

<!-- All Details Modals will be added to index.php after the toast notification system -->
<!-- This file contains the HTML structure and JavaScript for all 6 modals -->

<!-- 1. Vehicle Details Modal - Already created in vehicle_details_modal.html -->

<!-- 2. Owner Details Modal -->
<div id="ownerDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>👤 <span id="ownerDetailsTitle">Owner Details</span></h2>
            <button class="details-modal-close" onclick="closeOwnerDetailsModal()">&times;</button>
        </div>
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchOwnerTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchOwnerTab('vehicles')">🚗 Vehicles</button>
            <button class="details-tab" onclick="switchOwnerTab('stats')">📊 Statistics</button>
        </div>
        <div class="details-modal-body" id="ownerDetailsBody">
            <div class="details-loading">Loading owner details...</div>
        </div>
    </div>
</div>

<!-- 3. Customer Details Modal -->
<div id="customerDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>👤 <span id="customerDetailsTitle">Customer Details</span></h2>
            <button class="details-modal-close" onclick="closeCustomerDetailsModal()">&times;</button>
        </div>
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchCustomerTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchCustomerTab('rental_history')">📅 Reservations</button>
            <button class="details-tab" onclick="switchCustomerTab('favorites')">⭐ Favorites</button>
            <button class="details-tab" onclick="switchCustomerTab('stats')">📊 Statistics</button>
        </div>
        <div class="details-modal-body" id="customerDetailsBody">
            <div class="details-loading">Loading customer details...</div>
        </div>
    </div>
</div>

<!-- 4. Reservation Details Modal -->
<div id="reservationDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>📅 <span id="reservationDetailsTitle">Reservation Details</span></h2>
            <button class="details-modal-close" onclick="closeReservationDetailsModal()">&times;</button>
        </div>
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchReservationTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchReservationTab('customer')">👤 Customer</button>
            <button class="details-tab" onclick="switchReservationTab('vehicle')">🚗 Vehicle</button>
            <button class="details-tab" onclick="switchReservationTab('history')">📜 History</button>
        </div>
        <div class="details-modal-body" id="reservationDetailsBody">
            <div class="details-loading">Loading reservation details...</div>
        </div>
    </div>
</div>

<!-- 5. Maintenance Details Modal -->
<div id="maintenanceDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>🔧 <span id="maintenanceDetailsTitle">Maintenance Details</span></h2>
            <button class="details-modal-close" onclick="closeMaintenanceDetailsModal()">&times;</button>
        </div>
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchMaintenanceTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchMaintenanceTab('vehicle')">🚗 Vehicle</button>
            <button class="details-tab" onclick="switchMaintenanceTab('history')">📜 History</button>
        </div>
        <div class="details-modal-body" id="maintenanceDetailsBody">
            <div class="details-loading">Loading maintenance details...</div>
        </div>
    </div>
</div>

<!-- 6. Repair Details Modal -->
<div id="repairDetailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h2>🛠️ <span id="repairDetailsTitle">Repair Details</span></h2>
            <button class="details-modal-close" onclick="closeRepairDetailsModal()">&times;</button>
        </div>
        <div class="details-tabs">
            <button class="details-tab active" onclick="switchRepairTab('info')">📋 Information</button>
            <button class="details-tab" onclick="switchRepairTab('vehicle')">🚗 Vehicle</button>
            <button class="details-tab" onclick="switchRepairTab('history')">📜 History</button>
        </div>
        <div class="details-modal-body" id="repairDetailsBody">
            <div class="details-loading">Loading repair details...</div>
        </div>
    </div>
</div>

<script>
// JavaScript for all Details Modals
// This provides cross-linking functionality between all modals

// Owner Details Modal Functions
let currentOwnerData = null;

function showOwnerDetails(ownerId) {
    // Close any open modals first
    closeAllDetailsModals();
    
    document.getElementById('ownerDetailsModal').style.display = 'block';
    document.getElementById('ownerDetailsBody').innerHTML = '<div class="details-loading">Loading owner details...</div>';
    
    fetch('index.php?ajax=1&action=get_owner_details&id=' + ownerId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentOwnerData = data;
                renderOwnerDetails(data);
            } else {
                document.getElementById('ownerDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load owner details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('ownerDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeOwnerDetailsModal() {
    document.getElementById('ownerDetailsModal').style.display = 'none';
    currentOwnerData = null;
}

function switchOwnerTab(tabName) {
    const tabs = document.querySelectorAll('#ownerDetailsModal .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    if (currentOwnerData) {
        renderOwnerTabContent(tabName, currentOwnerData);
    }
}

function renderOwnerDetails(data) {
    const owner = data.owner;
    document.getElementById('ownerDetailsTitle').textContent = owner.owner_name || 'Owner Details';
    renderOwnerTabContent('info', data);
}

function renderOwnerTabContent(tabName, data) {
    const body = document.getElementById('ownerDetailsBody');
    const owner = data.owner;
    const vehicles = data.vehicles || [];
    const stats = data.statistics || {};
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">Owner Name</div>
                        <div class="details-info-value">${owner.owner_name || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Ownership Type</div>
                        <div class="details-info-value">${owner.ownership_type || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Vehicle (VIN)</div>
                        <div class="details-info-value clickable" onclick="showVehicleDetails('${owner.vin}')">${owner.vin || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Start Date</div>
                        <div class="details-info-value">${owner.created_at || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">End Date</div>
                        <div class="details-info-value">${owner.ownership_end_date || 'Active'}</div>
                    </div>
                </div>
            `;
            break;
            
        case 'vehicles':
            if (vehicles.length > 0) {
                html = `
                    <div class="details-section-title">Owned Vehicles (${vehicles.length})</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>VIN</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Daily Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${vehicles.map(v => `
                                <tr style="cursor: pointer;" onclick="showVehicleDetails('${v.vin}')">
                                    <td>${v.year || ''} ${v.make || ''} ${v.model || ''}</td>
                                    <td>${v.vin || 'N/A'}</td>
                                    <td>${v.created_at || 'N/A'}</td>
                                    <td>${v.ownership_end_date || 'Active'}</td>
                                    <td>$${v.daily_rate || '0'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No vehicles found</div>';
            }
            break;
            
        case 'stats':
            html = `
                <div class="details-section-title">Owner Statistics</div>
                <div class="details-stats-grid">
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Vehicles</div>
                        <div class="details-stat-value">${stats.total_vehicles || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Active Vehicles</div>
                        <div class="details-stat-value">${stats.active_vehicles || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Revenue</div>
                        <div class="details-stat-value">$${parseFloat(stats.total_revenue || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Fleet Value</div>
                        <div class="details-stat-value">$${parseFloat(stats.total_value || 0).toFixed(2)}</div>
                    </div>
                </div>
            `;
            break;
    }
    
    body.innerHTML = html;
}

// Customer Details Modal Functions
let currentCustomerData = null;

function showCustomerDetails(customerId) {
    closeAllDetailsModals();
    
    document.getElementById('customerDetailsModal').style.display = 'block';
    document.getElementById('customerDetailsBody').innerHTML = '<div class="details-loading">Loading customer details...</div>';
    
    fetch('index.php?ajax=1&action=get_customer_details&id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentCustomerData = data;
                renderCustomerDetails(data);
            } else {
                document.getElementById('customerDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load customer details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('customerDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeCustomerDetailsModal() {
    document.getElementById('customerDetailsModal').style.display = 'none';
    currentCustomerData = null;
}

function switchCustomerTab(tabName) {
    const tabs = document.querySelectorAll('#customerDetailsModal .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    if (currentCustomerData) {
        renderCustomerTabContent(tabName, currentCustomerData);
    }
}

function renderCustomerDetails(data) {
    const customer = data.customer;
    document.getElementById('customerDetailsTitle').textContent = customer.turo_guest_name || 'Customer Details';
    renderCustomerTabContent('info', data);
}

function renderCustomerTabContent(tabName, data) {
    const body = document.getElementById('customerDetailsBody');
    const customer = data.customer;
    const rental_history = data.rental_history || [];
    const favorites = data.favorite_vehicles || [];
    const stats = data.statistics || {};
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">Name</div>
                        <div class="details-info-value">${customer.turo_guest_name || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Email</div>
                        <div class="details-info-value">${customer.email || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Phone</div>
                        <div class="details-info-value">${customer.phone || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Address</div>
                        <div class="details-info-value">${customer.address || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">City</div>
                        <div class="details-info-value">${customer.city || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">State</div>
                        <div class="details-info-value">${customer.state || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">ZIP Code</div>
                        <div class="details-info-value">${customer.zip_code || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">License Number</div>
                        <div class="details-info-value">${customer.license_number || 'N/A'}</div>
                    </div>
                </div>
                ${customer.notes ? `
                    <div class="details-section-title">Notes</div>
                    <p>${customer.notes}</p>
                ` : ''}
            `;
            break;
            
        case 'rental_history':
            if (rental_history.length > 0) {
                html = `
                    <div class="details-section-title">Reservation History (${rental_history.length})</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rental_history.map(r => `
                                <tr style="cursor: pointer;" onclick="showReservationDetails(${r.id})">
                                    <td class="clickable" onclick="event.stopPropagation(); showVehicleDetails('${r.vin}')">${r.make || ''} ${r.model || ''}</td>
                                    <td>${r.trip_start || 'N/A'}</td>
                                    <td>${r.trip_end || 'N/A'}</td>
                                    <td><span class="details-badge status-${(r.trip_status || '').toLowerCase()}">${r.trip_status || 'N/A'}</span></td>
                                    <td>$${r.total_cost || '0'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No rental_history found</div>';
            }
            break;
            
        case 'favorites':
            if (favorites.length > 0) {
                html = `
                    <div class="details-section-title">Favorite Vehicles</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>VIN</th>
                                <th>Times Rented</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${favorites.map(v => `
                                <tr style="cursor: pointer;" onclick="showVehicleDetails('${v.vin}')">
                                    <td>${v.make || ''} ${v.model || ''}</td>
                                    <td>${v.vin || 'N/A'}</td>
                                    <td>${v.rental_count || 0}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No favorite vehicles yet</div>';
            }
            break;
            
        case 'stats':
            html = `
                <div class="details-section-title">Customer Statistics</div>
                <div class="details-stats-grid">
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Reservations</div>
                        <div class="details-stat-value">${stats.total_rental_history || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Completed Trips</div>
                        <div class="details-stat-value">${stats.completed_trips || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Active Trips</div>
                        <div class="details-stat-value">${stats.active_trips || 0}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Total Spent</div>
                        <div class="details-stat-value">$${parseFloat(stats.total_spent || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">Avg Trip Cost</div>
                        <div class="details-stat-value">$${parseFloat(stats.avg_trip_cost || 0).toFixed(2)}</div>
                    </div>
                    <div class="details-stat-card">
                        <div class="details-stat-label">First Rental</div>
                        <div class="details-stat-value" style="font-size: 16px;">${stats.first_rental || 'N/A'}</div>
                    </div>
                </div>
            `;
            break;
    }
    
    body.innerHTML = html;
}

// Reservation Details Modal Functions
let currentReservationData = null;

function showReservationDetails(reservationId) {
    closeAllDetailsModals();
    
    document.getElementById('reservationDetailsModal').style.display = 'block';
    document.getElementById('reservationDetailsBody').innerHTML = '<div class="details-loading">Loading reservation details...</div>';
    
    fetch('index.php?ajax=1&action=get_reservation_details&id=' + reservationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentReservationData = data;
                renderReservationDetails(data);
            } else {
                document.getElementById('reservationDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load reservation details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('reservationDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeReservationDetailsModal() {
    document.getElementById('reservationDetailsModal').style.display = 'none';
    currentReservationData = null;
}

function switchReservationTab(tabName) {
    const tabs = document.querySelectorAll('#reservationDetailsModal .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    if (currentReservationData) {
        renderReservationTabContent(tabName, currentReservationData);
    }
}

function renderReservationDetails(data) {
    const reservation = data.reservation;
    document.getElementById('reservationDetailsTitle').textContent = `Reservation #${reservation.id || ''}`;
    renderReservationTabContent('info', data);
}

function renderReservationTabContent(tabName, data) {
    const body = document.getElementById('reservationDetailsBody');
    const reservation = data.reservation;
    const customer = data.customer;
    const vehicle = data.vehicle;
    const previous = data.previous_rentals || [];
    const details = data.details || {};
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">Reservation ID</div>
                        <div class="details-info-value">#${reservation.id || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Customer</div>
                        <div class="details-info-value clickable" onclick="showCustomerDetails(${customer?.id || 0})">${customer?.turo_guest_name || reservation.guest_name || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Vehicle</div>
                        <div class="details-info-value clickable" onclick="showVehicleDetails('${reservation.vehicle_identifier}')">${vehicle?.year || ''} ${vehicle?.make || ''} ${vehicle?.model || ''}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Start Date</div>
                        <div class="details-info-value">${reservation.trip_start || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">End Date</div>
                        <div class="details-info-value">${reservation.trip_end || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Duration</div>
                        <div class="details-info-value">${details.duration_days || 0} days</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Daily Rate</div>
                        <div class="details-info-value">$${details.daily_rate || '0'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Total Cost</div>
                        <div class="details-info-value">$${details.total || '0'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Status</div>
                        <div class="details-info-value">
                            <span class="details-badge status-${(reservation.status || '').toLowerCase()}">${reservation.status || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'customer':
            if (customer) {
                html = `
                    <div class="details-section-title">Customer Information</div>
                    <div class="details-info-grid">
                        <div class="details-info-item">
                            <div class="details-info-label">Name</div>
                            <div class="details-info-value clickable" onclick="showCustomerDetails(${customer.id})">${customer.name || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Email</div>
                            <div class="details-info-value">${customer.email || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Phone</div>
                            <div class="details-info-value">${customer.phone || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">License Number</div>
                            <div class="details-info-value">${customer.license_number || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="details-quick-actions">
                        <button class="details-action-btn primary" onclick="showCustomerDetails(${customer.id})">👤 View Full Customer Profile</button>
                    </div>
                `;
            } else {
                html = '<div class="details-empty">Customer information not available</div>';
            }
            break;
            
        case 'vehicle':
            if (vehicle) {
                html = `
                    <div class="details-section-title">Vehicle Information</div>
                    <div class="details-info-grid">
                        <div class="details-info-item">
                            <div class="details-info-label">Vehicle</div>
                            <div class="details-info-value clickable" onclick="showVehicleDetails('${vehicle.vin}')">${vehicle.year || ''} ${vehicle.make || ''} ${vehicle.model || ''}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">VIN</div>
                            <div class="details-info-value">${vehicle.vin || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">License Plate</div>
                            <div class="details-info-value">${vehicle.license_plate || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Color</div>
                            <div class="details-info-value">${vehicle.color || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="details-quick-actions">
                        <button class="details-action-btn primary" onclick="showVehicleDetails('${vehicle.vin}')">🚗 View Full Vehicle Profile</button>
                    </div>
                `;
            } else {
                html = '<div class="details-empty">Vehicle information not available</div>';
            }
            break;
            
        case 'history':
            if (previous.length > 0) {
                html = `
                    <div class="details-section-title">Previous Rentals (Same Customer & Vehicle)</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${previous.map(r => `
                                <tr style="cursor: pointer;" onclick="showReservationDetails(${r.id})">
                                    <td>${r.trip_start || 'N/A'}</td>
                                    <td>${r.trip_end || 'N/A'}</td>
                                    <td><span class="details-badge status-${(r.trip_status || '').toLowerCase()}">${r.trip_status || 'N/A'}</span></td>
                                    <td>$${r.total_cost || '0'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No previous rentals found for this customer and vehicle combination</div>';
            }
            break;
    }
    
    body.innerHTML = html;
}

// Maintenance Details Modal Functions
let currentMaintenanceData = null;

function showMaintenanceDetails(maintenanceId) {
    closeAllDetailsModals();
    
    document.getElementById('maintenanceDetailsModal').style.display = 'block';
    document.getElementById('maintenanceDetailsBody').innerHTML = '<div class="details-loading">Loading maintenance details...</div>';
    
    fetch('index.php?ajax=1&action=get_maintenance_details&id=' + maintenanceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentMaintenanceData = data;
                renderMaintenanceDetails(data);
            } else {
                document.getElementById('maintenanceDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load maintenance details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('maintenanceDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeMaintenanceDetailsModal() {
    document.getElementById('maintenanceDetailsModal').style.display = 'none';
    currentMaintenanceData = null;
}

function switchMaintenanceTab(tabName) {
    const tabs = document.querySelectorAll('#maintenanceDetailsModal .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    if (currentMaintenanceData) {
        renderMaintenanceTabContent(tabName, currentMaintenanceData);
    }
}

function renderMaintenanceDetails(data) {
    const maintenance = data.maintenance;
    document.getElementById('maintenanceDetailsTitle').textContent = maintenance.maintenance_type || 'Maintenance Details';
    renderMaintenanceTabContent('info', data);
}

function renderMaintenanceTabContent(tabName, data) {
    const body = document.getElementById('maintenanceDetailsBody');
    const maintenance = data.maintenance;
    const vehicle = data.vehicle;
    const other = data.other_maintenance || [];
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">Maintenance Type</div>
                        <div class="details-info-value">${maintenance.maintenance_type || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Scheduled Date</div>
                        <div class="details-info-value">${maintenance.scheduled_date || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Status</div>
                        <div class="details-info-value">
                            <span class="details-badge status-${(maintenance.status || '').toLowerCase()}">${maintenance.status || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Vehicle</div>
                        <div class="details-info-value clickable" onclick="showVehicleDetails('${maintenance.vin}')">${vehicle?.year || ''} ${vehicle?.make || ''} ${vehicle?.model || ''}</div>
                    </div>
                </div>
                ${maintenance.description ? `
                    <div class="details-section-title">Description</div>
                    <p>${maintenance.description}</p>
                ` : ''}
            `;
            break;
            
        case 'vehicle':
            if (vehicle) {
                html = `
                    <div class="details-section-title">Vehicle Information</div>
                    <div class="details-info-grid">
                        <div class="details-info-item">
                            <div class="details-info-label">Vehicle</div>
                            <div class="details-info-value clickable" onclick="showVehicleDetails('${vehicle.vin}')">${vehicle.year || ''} ${vehicle.make || ''} ${vehicle.model || ''}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">VIN</div>
                            <div class="details-info-value">${vehicle.vin || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">License Plate</div>
                            <div class="details-info-value">${vehicle.license_plate || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Status</div>
                            <div class="details-info-value">
                                <span class="details-badge status-${(vehicle.status || '').toLowerCase()}">${vehicle.status || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="details-quick-actions">
                        <button class="details-action-btn primary" onclick="showVehicleDetails('${vehicle.vin}')">🚗 View Full Vehicle Profile</button>
                    </div>
                `;
            } else {
                html = '<div class="details-empty">Vehicle information not available</div>';
            }
            break;
            
        case 'history':
            if (other.length > 0) {
                html = `
                    <div class="details-section-title">Other Maintenance for This Vehicle (${other.length})</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${other.map(m => `
                                <tr style="cursor: pointer;" onclick="showMaintenanceDetails(${m.id})">
                                    <td>${m.scheduled_date || 'N/A'}</td>
                                    <td>${m.maintenance_type || 'N/A'}</td>
                                    <td><span class="details-badge status-${(m.status || '').toLowerCase()}">${m.status || 'N/A'}</span></td>
                                    <td>${m.description || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No other maintenance records found</div>';
            }
            break;
    }
    
    body.innerHTML = html;
}

// Repair Details Modal Functions
let currentRepairData = null;

function showRepairDetails(repairId) {
    closeAllDetailsModals();
    
    document.getElementById('repairDetailsModal').style.display = 'block';
    document.getElementById('repairDetailsBody').innerHTML = '<div class="details-loading">Loading repair details...</div>';
    
    fetch('index.php?ajax=1&action=get_repair_details&id=' + repairId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentRepairData = data;
                renderRepairDetails(data);
            } else {
                document.getElementById('repairDetailsBody').innerHTML = 
                    '<div class="details-error">Error: ' + (data.message || 'Failed to load repair details') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('repairDetailsBody').innerHTML = 
                '<div class="details-error">Network error: ' + error.message + '</div>';
        });
}

function closeRepairDetailsModal() {
    document.getElementById('repairDetailsModal').style.display = 'none';
    currentRepairData = null;
}

function switchRepairTab(tabName) {
    const tabs = document.querySelectorAll('#repairDetailsModal .details-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    if (currentRepairData) {
        renderRepairTabContent(tabName, currentRepairData);
    }
}

function renderRepairDetails(data) {
    const repair = data.repair;
    document.getElementById('repairDetailsTitle').textContent = repair.problem_description || repair.repair_description || 'Repair Details';
    renderRepairTabContent('info', data);
}

function renderRepairTabContent(tabName, data) {
    const body = document.getElementById('repairDetailsBody');
    const repair = data.repair;
    const vehicle = data.vehicle;
    const other = data.other_repairs || [];
    const totalCost = data.total_repair_cost || 0;
    
    let html = '';
    
    switch(tabName) {
        case 'info':
            html = `
                <div class="details-info-grid">
                    <div class="details-info-item">
                        <div class="details-info-label">Repair Date</div>
                        <div class="details-info-value">${repair.repair_date || 'N/A'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Cost</div>
                        <div class="details-info-value">$${repair.cost || '0'}</div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Status</div>
                        <div class="details-info-value">
                            <span class="details-badge status-${(repair.status || '').toLowerCase()}">${repair.status || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="details-info-item">
                        <div class="details-info-label">Vehicle</div>
                        <div class="details-info-value clickable" onclick="showVehicleDetails('${vehicle?.vin || ''}')">${vehicle?.year || ''} ${vehicle?.make || ''} ${vehicle?.model || ''}</div>
                    </div>
                </div>
                ${repair.description ? `
                    <div class="details-section-title">Description</div>
                    <p>${repair.description}</p>
                ` : ''}
                <div class="details-stat-card" style="margin-top: 20px;">
                    <div class="details-stat-label">Total Repair Costs for This Vehicle</div>
                    <div class="details-stat-value">$${parseFloat(totalCost).toFixed(2)}</div>
                </div>
            `;
            break;
            
        case 'vehicle':
            if (vehicle) {
                html = `
                    <div class="details-section-title">Vehicle Information</div>
                    <div class="details-info-grid">
                        <div class="details-info-item">
                            <div class="details-info-label">Vehicle</div>
                            <div class="details-info-value clickable" onclick="showVehicleDetails('${vehicle.vin}')">${vehicle.year || ''} ${vehicle.make || ''} ${vehicle.model || ''}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">VIN</div>
                            <div class="details-info-value">${vehicle.vin || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">License Plate</div>
                            <div class="details-info-value">${vehicle.license_plate || 'N/A'}</div>
                        </div>
                        <div class="details-info-item">
                            <div class="details-info-label">Status</div>
                            <div class="details-info-value">
                                <span class="details-badge status-${(vehicle.status || '').toLowerCase()}">${vehicle.status || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="details-quick-actions">
                        <button class="details-action-btn primary" onclick="showVehicleDetails('${vehicle.vin}')">🚗 View Full Vehicle Profile</button>
                    </div>
                `;
            } else {
                html = '<div class="details-empty">Vehicle information not available</div>';
            }
            break;
            
        case 'history':
            if (other.length > 0) {
                html = `
                    <div class="details-section-title">Other Repairs for This Vehicle (${other.length})</div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${other.map(r => `
                                <tr style="cursor: pointer;" onclick="showRepairDetails(${r.id})">
                                    <td>${r.repair_date || 'N/A'}</td>
                                    <td>${r.description || 'N/A'}</td>
                                    <td>$${r.cost || '0'}</td>
                                    <td><span class="details-badge status-${(r.trip_status || '').toLowerCase()}">${r.trip_status || 'N/A'}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html = '<div class="details-empty">No other repair records found</div>';
            }
            break;
    }
    
    body.innerHTML = html;
}

// Utility function to close all details modals
function closeAllDetailsModals() {
    closeVehicleDetailsModal();
    closeOwnerDetailsModal();
    closeCustomerDetailsModal();
    closeReservationDetailsModal();
    closeMaintenanceDetailsModal();
    closeRepairDetailsModal();
}

// Global event listeners for all modals
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('details-modal')) {
        closeAllDetailsModals();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAllDetailsModals();
    }
});
</script>
</body></html>
