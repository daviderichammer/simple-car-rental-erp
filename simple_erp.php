<?php
// Simple Car Rental ERP System
// No complex JavaScript, just basic HTML forms that work

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

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    try {
        if (isset($_POST['add_vehicle'])) {
            $stmt = $pdo->prepare("INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['make'], $_POST['model'], $_POST['year'], $_POST['vin'], 
                $_POST['license_plate'], $_POST['color'], $_POST['mileage'], $_POST['daily_rate']
            ]);
            $message = "Vehicle added successfully!";
        }
        
        if (isset($_POST['add_customer'])) {
            $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], 
                $_POST['address'], $_POST['driver_license'], $_POST['date_of_birth']
            ]);
            $message = "Customer added successfully!";
        }
        
        if (isset($_POST['add_reservation'])) {
            // Convert date format if needed
            $start_date = date('Y-m-d', strtotime($_POST['start_date']));
            $end_date = date('Y-m-d', strtotime($_POST['end_date']));
            
            $stmt = $pdo->prepare("INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['customer_id'], $_POST['vehicle_id'], $start_date, $end_date, 
                $_POST['pickup_location'], $_POST['dropoff_location'], $_POST['total_amount'], $_POST['notes']
            ]);
            $message = "Reservation added successfully!";
        }
        
        if (isset($_POST['add_maintenance'])) {
            // Convert date format if needed
            $scheduled_date = date('Y-m-d', strtotime($_POST['scheduled_date']));
            
            $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['vehicle_id'], $_POST['maintenance_type'], $scheduled_date, $_POST['description']
            ]);
            $message = "Maintenance scheduled successfully!";
        }
        
        if (isset($_POST['update_vehicle_status'])) {
            $stmt = $pdo->prepare("UPDATE vehicles SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['vehicle_id']]);
            $message = "Vehicle status updated successfully!";
        }
        
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental ERP - Simple System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            text-align: center;
        }
        
        .nav {
            background: #34495e;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 5px;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            margin-right: 10px;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        .nav a:hover, .nav a.active {
            background: #2c3e50;
        }
        
        .card {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .table tr:hover {
            background: #f5f5f5;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
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
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        
        .col {
            flex: 1;
            padding: 10px;
            min-width: 300px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .nav a {
                display: block;
                margin-bottom: 5px;
            }
            
            .col {
                min-width: 100%;
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th, .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Car Rental ERP System</h1>
        </div>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="?page=dashboard" <?php echo $page == 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a>
            <a href="?page=vehicles" <?php echo $page == 'vehicles' ? 'class="active"' : ''; ?>>Vehicles</a>
            <a href="?page=customers" <?php echo $page == 'customers' ? 'class="active"' : ''; ?>>Customers</a>
            <a href="?page=reservations" <?php echo $page == 'reservations' ? 'class="active"' : ''; ?>>Reservations</a>
            <a href="?page=maintenance" <?php echo $page == 'maintenance' ? 'class="active"' : ''; ?>>Maintenance</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php
        switch($page) {
            case 'dashboard':
                // Get statistics
                $vehicle_count = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
                $customer_count = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
                $active_reservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('confirmed', 'active')")->fetchColumn();
                $available_vehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'")->fetchColumn();
                ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $vehicle_count; ?></div>
                        <div class="stat-label">Total Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $available_vehicles; ?></div>
                        <div class="stat-label">Available Vehicles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $customer_count; ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $active_reservations; ?></div>
                        <div class="stat-label">Active Reservations</div>
                    </div>
                </div>
                
                <div class="card">
                    <h2>Recent Reservations</h2>
                    <?php
                    $recent_reservations = $pdo->query("
                        SELECT r.*, c.first_name, c.last_name, v.make, v.model 
                        FROM reservations r 
                        JOIN customers c ON r.customer_id = c.id 
                        JOIN vehicles v ON r.vehicle_id = v.id 
                        ORDER BY r.created_at DESC 
                        LIMIT 5
                    ")->fetchAll();
                    ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_reservations as $reservation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['make'] . ' ' . $reservation['model']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['status']); ?></td>
                                <td>$<?php echo htmlspecialchars($reservation['total_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'vehicles':
                ?>
                <div class="row">
                    <div class="col">
                        <div class="card">
                            <h2>Add New Vehicle</h2>
                            <form method="POST">
                                <div class="form-group">
                                    <label>Make:</label>
                                    <input type="text" name="make" required>
                                </div>
                                <div class="form-group">
                                    <label>Model:</label>
                                    <input type="text" name="model" required>
                                </div>
                                <div class="form-group">
                                    <label>Year:</label>
                                    <input type="number" name="year" min="1900" max="2030" required>
                                </div>
                                <div class="form-group">
                                    <label>VIN:</label>
                                    <input type="text" name="vin" maxlength="17" required>
                                </div>
                                <div class="form-group">
                                    <label>License Plate:</label>
                                    <input type="text" name="license_plate" required>
                                </div>
                                <div class="form-group">
                                    <label>Color:</label>
                                    <input type="text" name="color">
                                </div>
                                <div class="form-group">
                                    <label>Mileage:</label>
                                    <input type="number" name="mileage" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Daily Rate ($):</label>
                                    <input type="number" name="daily_rate" step="0.01" min="0" required>
                                </div>
                                <button type="submit" name="add_vehicle" class="btn">Add Vehicle</button>
                            </form>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card">
                            <h2>Update Vehicle Status</h2>
                            <form method="POST">
                                <div class="form-group">
                                    <label>Vehicle:</label>
                                    <select name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php
                                        $vehicles = $pdo->query("SELECT id, make, model, year, license_plate FROM vehicles ORDER BY make, model")->fetchAll();
                                        foreach($vehicles as $vehicle):
                                        ?>
                                        <option value="<?php echo $vehicle['id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year'] . ' (' . $vehicle['license_plate'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status:</label>
                                    <select name="status" required>
                                        <option value="available">Available</option>
                                        <option value="rented">Rented</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="out_of_service">Out of Service</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_vehicle_status" class="btn">Update Status</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h2>All Vehicles</h2>
                    <?php
                    $vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY make, model")->fetchAll();
                    ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Year</th>
                                <th>License Plate</th>
                                <th>Color</th>
                                <th>Mileage</th>
                                <th>Daily Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($vehicles as $vehicle): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vehicle['make']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['color']); ?></td>
                                <td><?php echo number_format($vehicle['mileage']); ?></td>
                                <td>$<?php echo number_format($vehicle['daily_rate'], 2); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'customers':
                ?>
                <div class="card">
                    <h2>Add New Customer</h2>
                    <form method="POST">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label>First Name:</label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name:</label>
                                    <input type="text" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Email:</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone:</label>
                                    <input type="tel" name="phone">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label>Address:</label>
                                    <textarea name="address"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Driver License:</label>
                                    <input type="text" name="driver_license" required>
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth:</label>
                                    <input type="date" name="date_of_birth">
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_customer" class="btn">Add Customer</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2>All Customers</h2>
                    <?php
                    $customers = $pdo->query("SELECT * FROM customers ORDER BY last_name, first_name")->fetchAll();
                    ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Driver License</th>
                                <th>Date of Birth</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo htmlspecialchars($customer['driver_license']); ?></td>
                                <td><?php echo htmlspecialchars($customer['date_of_birth']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'reservations':
                ?>
                <div class="card">
                    <h2>Add New Reservation</h2>
                    <form method="POST">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label>Customer:</label>
                                    <select name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        <?php
                                        $customers = $pdo->query("SELECT id, first_name, last_name, email FROM customers ORDER BY last_name, first_name")->fetchAll();
                                        foreach($customers as $customer):
                                        ?>
                                        <option value="<?php echo $customer['id']; ?>">
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['email'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Vehicle:</label>
                                    <select name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php
                                        $available_vehicles = $pdo->query("SELECT id, make, model, year, license_plate, daily_rate FROM vehicles WHERE status = 'available' ORDER BY make, model")->fetchAll();
                                        foreach($available_vehicles as $vehicle):
                                        ?>
                                        <option value="<?php echo $vehicle['id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year'] . ' (' . $vehicle['license_plate'] . ') - $' . $vehicle['daily_rate'] . '/day'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Start Date:</label>
                                    <input type="date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>End Date:</label>
                                    <input type="date" name="end_date" required>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label>Pickup Location:</label>
                                    <input type="text" name="pickup_location">
                                </div>
                                <div class="form-group">
                                    <label>Dropoff Location:</label>
                                    <input type="text" name="dropoff_location">
                                </div>
                                <div class="form-group">
                                    <label>Total Amount ($):</label>
                                    <input type="number" name="total_amount" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Notes:</label>
                                    <textarea name="notes"></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_reservation" class="btn">Add Reservation</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2>All Reservations</h2>
                    <?php
                    $reservations = $pdo->query("
                        SELECT r.*, c.first_name, c.last_name, c.email, v.make, v.model, v.year, v.license_plate 
                        FROM reservations r 
                        JOIN customers c ON r.customer_id = c.id 
                        JOIN vehicles v ON r.vehicle_id = v.id 
                        ORDER BY r.start_date DESC
                    ")->fetchAll();
                    ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Pickup</th>
                                <th>Dropoff</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['make'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($reservation['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['pickup_location']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['dropoff_location']); ?></td>
                                <td>$<?php echo number_format($reservation['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($reservation['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
                
            case 'maintenance':
                ?>
                <div class="card">
                    <h2>Schedule Maintenance</h2>
                    <form method="POST">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label>Vehicle:</label>
                                    <select name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php
                                        $vehicles = $pdo->query("SELECT id, make, model, year, license_plate FROM vehicles ORDER BY make, model")->fetchAll();
                                        foreach($vehicles as $vehicle):
                                        ?>
                                        <option value="<?php echo $vehicle['id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year'] . ' (' . $vehicle['license_plate'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Maintenance Type:</label>
                                    <input type="text" name="maintenance_type" required placeholder="e.g., Oil Change, Tire Rotation, Brake Service">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label>Scheduled Date:</label>
                                    <input type="date" name="scheduled_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Description:</label>
                                    <textarea name="description" placeholder="Additional details about the maintenance"></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_maintenance" class="btn">Schedule Maintenance</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2>Maintenance Schedule</h2>
                    <?php
                    $maintenance = $pdo->query("
                        SELECT m.*, v.make, v.model, v.year, v.license_plate 
                        FROM maintenance_schedules m 
                        JOIN vehicles v ON m.vehicle_id = v.id 
                        ORDER BY m.scheduled_date DESC
                    ")->fetchAll();
                    ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Maintenance Type</th>
                                <th>Scheduled Date</th>
                                <th>Completed Date</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($maintenance as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['make'] . ' ' . $item['model'] . ' (' . $item['license_plate'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($item['maintenance_type']); ?></td>
                                <td><?php echo htmlspecialchars($item['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($item['completed_date'] ?: 'Not completed'); ?></td>
                                <td><?php echo htmlspecialchars($item['status']); ?></td>
                                <td><?php echo $item['cost'] ? '$' . number_format($item['cost'], 2) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                break;
        }
        ?>
    </div>
</body>
</html>

