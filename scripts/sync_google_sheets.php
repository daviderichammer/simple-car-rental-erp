#!/usr/bin/env php
<?php
/**
 * Google Sheets Sync Script
 * Syncs data from Google Sheets to the Car Rental ERP database
 * Can be run manually or via cron job
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/sheets_sync_error.log');

// Start output buffering for logging
ob_start();

echo "=== Google Sheets Sync Started at " . date('Y-m-d H:i:s') . " ===\n";

// Database configuration
$db_host = 'localhost';
$db_name = 'car_rental_erp';
$db_user = 'root';
$db_pass = getenv('SLICIE_MYSQL_ROOT_PASSWORD') ?: 'SecureRootPass123!';

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connected\n";
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Google Sheets API setup
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

$client = new Client();
$client->setApplicationName('Car Rental ERP Sync');
$client->setScopes([Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/../service_account_credentials.json');

$service = new Sheets($client);

// Master workbook ID
$masterSpreadsheetId = '1415uGwJHepVG593IjLAeqfyti8uJB5p1TEF3EXXZaB0';

// Track sync statistics
$stats = [
    'vehicles_added' => 0,
    'vehicles_updated' => 0,
    'work_orders_added' => 0,
    'expenses_added' => 0,
    'rentals_added' => 0,
    'errors' => []
];

echo "\n=== Syncing Vehicles ===\n";

try {
    // Get vehicles from Google Sheets
    $range = 'Cars!A2:BH';
    $response = $service->spreadsheets_values->get($masterSpreadsheetId, $range);
    $rows = $response->getValues();
    
    echo "Found " . count($rows) . " vehicles in Google Sheets\n";
    
    foreach ($rows as $row) {
        if (empty($row[0])) continue; // Skip empty rows
        
        $vin = $row[0] ?? '';
        if (empty($vin)) continue;
        
        // Check if vehicle exists
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE vin = ?");
        $stmt->execute([$vin]);
        $existing = $stmt->fetch();
        
        $data = [
            'make' => $row[2] ?? '',
            'model' => $row[3] ?? '',
            'year' => $row[1] ?? null,
            'vin' => $vin,
            'license_plate' => $row[5] ?? null,
            'color' => $row[4] ?? '',
            'airport' => $row[6] ?? null,
            'bouncie_id' => $row[7] ?? null,
            'sunpass_id' => $row[8] ?? null,
            'ezpass_id' => $row[9] ?? null,
            'lockbox_code' => $row[10] ?? null,
            'fuel_type' => $row[11] ?? null,
            'fuel_tank_capacity' => $row[12] ?? null,
            'oil_type' => $row[13] ?? null,
            'oil_change_interval' => $row[14] ?? null,
            'tire_size_front' => $row[15] ?? null,
            'tire_size_rear' => $row[16] ?? null,
            'registration_expiry' => $row[17] ?? null,
            'date_added' => $row[18] ?? null,
            'mistercarwash_rfid' => $row[19] ?? null
        ];
        
        if ($existing) {
            // Update existing vehicle
            $stmt = $pdo->prepare("UPDATE vehicles SET 
                make = ?, model = ?, year = ?, license_plate = ?, color = ?,
                airport = ?, bouncie_id = ?, sunpass_id = ?, ezpass_id = ?,
                lockbox_code = ?, fuel_type = ?, fuel_tank_capacity = ?,
                oil_type = ?, oil_change_interval = ?, tire_size_front = ?,
                tire_size_rear = ?, registration_expiry = ?, date_added = ?,
                mistercarwash_rfid = ?
                WHERE vin = ?");
            $stmt->execute([
                $data['make'], $data['model'], $data['year'], $data['license_plate'],
                $data['color'], $data['airport'], $data['bouncie_id'], $data['sunpass_id'],
                $data['ezpass_id'], $data['lockbox_code'], $data['fuel_type'],
                $data['fuel_tank_capacity'], $data['oil_type'], $data['oil_change_interval'],
                $data['tire_size_front'], $data['tire_size_rear'], $data['registration_expiry'],
                $data['date_added'], $data['mistercarwash_rfid'], $vin
            ]);
            $stats['vehicles_updated']++;
        } else {
            // Insert new vehicle
            $stmt = $pdo->prepare("INSERT INTO vehicles 
                (make, model, year, vin, license_plate, color, airport, bouncie_id,
                sunpass_id, ezpass_id, lockbox_code, fuel_type, fuel_tank_capacity,
                oil_type, oil_change_interval, tire_size_front, tire_size_rear,
                registration_expiry, date_added, mistercarwash_rfid, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
            $stmt->execute([
                $data['make'], $data['model'], $data['year'], $data['vin'],
                $data['license_plate'], $data['color'], $data['airport'], $data['bouncie_id'],
                $data['sunpass_id'], $data['ezpass_id'], $data['lockbox_code'],
                $data['fuel_type'], $data['fuel_tank_capacity'], $data['oil_type'],
                $data['oil_change_interval'], $data['tire_size_front'], $data['tire_size_rear'],
                $data['registration_expiry'], $data['date_added'], $data['mistercarwash_rfid']
            ]);
            $stats['vehicles_added']++;
        }
    }
    
    echo "✓ Vehicles synced: {$stats['vehicles_added']} added, {$stats['vehicles_updated']} updated\n";
    
} catch (Exception $e) {
    $error = "Vehicle sync error: " . $e->getMessage();
    echo "✗ $error\n";
    $stats['errors'][] = $error;
}

echo "\n=== Syncing Work Orders ===\n";

try {
    // Sync TPA work orders
    $tpaWorkbookId = '1GxGO3Uw_t0Gd2pAqSgJG_jmOYZTwHBrMoLQTZNQIpxs';
    $range = 'Jobs!A2:H';
    $response = $service->spreadsheets_values->get($tpaWorkbookId, $range);
    $rows = $response->getValues();
    
    echo "Found " . count($rows) . " TPA work orders\n";
    
    foreach ($rows as $row) {
        if (empty($row[0])) continue;
        
        $job_number = $row[0];
        
        // Check if work order exists
        $stmt = $pdo->prepare("SELECT id FROM work_orders WHERE job_number = ? AND location = 'TPA'");
        $stmt->execute([$job_number]);
        if ($stmt->fetch()) continue; // Skip if exists
        
        $stmt = $pdo->prepare("INSERT INTO work_orders 
            (job_number, job_datetime, location, assigned_to, cost, status, details_url, notes)
            VALUES (?, ?, 'TPA', ?, ?, 'Completed', ?, ?)");
        $stmt->execute([
            $job_number,
            $row[1] ?? null,
            $row[2] ?? null,
            $row[3] ?? null,
            $row[4] ?? null,
            $row[5] ?? null
        ]);
        $stats['work_orders_added']++;
    }
    
    echo "✓ Work orders synced: {$stats['work_orders_added']} added\n";
    
} catch (Exception $e) {
    $error = "Work order sync error: " . $e->getMessage();
    echo "✗ $error\n";
    $stats['errors'][] = $error;
}

echo "\n=== Syncing Expense Refunds ===\n";

try {
    // Sync expense refunds
    $csWorkbookId = '1Vu9-7NyQ9PBxmFRWFmjjPgJUmJfkIo0gUPnMBBNqGWg';
    
    // Parking refunds
    $range = 'Parking Refunds!A2:F';
    $response = $service->spreadsheets_values->get($csWorkbookId, $range);
    $rows = $response->getValues();
    
    echo "Found " . count($rows) . " parking refunds\n";
    $added = 0;
    
    foreach ($rows as $row) {
        if (empty($row[0])) continue;
        
        // Check if refund exists
        $stmt = $pdo->prepare("SELECT id FROM expense_refunds WHERE refund_date = ? AND amount = ? AND type = 'Parking'");
        $stmt->execute([$row[0] ?? null, $row[2] ?? null]);
        if ($stmt->fetch()) continue;
        
        $stmt = $pdo->prepare("INSERT INTO expense_refunds 
            (refund_date, guest_name, amount, location, status, type)
            VALUES (?, ?, ?, ?, 'Processed', 'Parking')");
        $stmt->execute([
            $row[0] ?? null,
            $row[1] ?? null,
            $row[2] ?? null,
            $row[3] ?? null
        ]);
        $added++;
    }
    
    $stats['expenses_added'] += $added;
    echo "✓ Expense refunds synced: {$stats['expenses_added']} added\n";
    
} catch (Exception $e) {
    $error = "Expense sync error: " . $e->getMessage();
    echo "✗ $error\n";
    $stats['errors'][] = $error;
}

echo "\n=== Syncing Rental History ===\n";

try {
    // Sync TPA rentals
    $tpaWorkbookId = '1GxGO3Uw_t0Gd2pAqSgJG_jmOYZTwHBrMoLQTZNQIpxs';
    $range = 'Rentals!A2:Z';
    $response = $service->spreadsheets_values->get($tpaWorkbookId, $range);
    $rows = $response->getValues();
    
    echo "Found " . count($rows) . " TPA rentals\n";
    
    foreach ($rows as $row) {
        if (empty($row[0])) continue;
        
        $reservation_id = $row[0];
        
        // Check if rental exists
        $stmt = $pdo->prepare("SELECT id FROM rental_history WHERE reservation_id = ? AND location = 'TPA'");
        $stmt->execute([$reservation_id]);
        if ($stmt->fetch()) continue;
        
        $stmt = $pdo->prepare("INSERT INTO rental_history 
            (reservation_id, guest_name, vehicle_name, vehicle_identifier, license_plate,
            color, trip_start, trip_end, trip_days, trip_status, pickup_location,
            return_location, checkin_odometer, checkout_odometer, distance_traveled,
            reservation_link, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'TPA')");
        $stmt->execute([
            $reservation_id,
            $row[1] ?? null,
            $row[2] ?? null,
            $row[3] ?? null,
            $row[4] ?? null,
            $row[5] ?? null,
            $row[6] ?? null,
            $row[7] ?? null,
            $row[8] ?? null,
            $row[9] ?? null,
            $row[10] ?? null,
            $row[11] ?? null,
            $row[12] ?? null,
            $row[13] ?? null,
            $row[14] ?? null,
            $row[15] ?? null
        ]);
        $stats['rentals_added']++;
    }
    
    echo "✓ Rental history synced: {$stats['rentals_added']} added\n";
    
} catch (Exception $e) {
    $error = "Rental sync error: " . $e->getMessage();
    echo "✗ $error\n";
    $stats['errors'][] = $error;
}

// Log sync to database
try {
    $stmt = $pdo->prepare("INSERT INTO sync_log 
        (last_sync_timestamp, rows_synced, status, error_message, sheet_name)
        VALUES (NOW(), ?, ?, ?, 'All Sheets')");
    $total_synced = $stats['vehicles_added'] + $stats['vehicles_updated'] + 
                    $stats['work_orders_added'] + $stats['expenses_added'] + 
                    $stats['rentals_added'];
    $status = empty($stats['errors']) ? 'success' : 'partial';
    $error_message = empty($stats['errors']) ? null : implode("\n", $stats['errors']);
    $stmt->execute([$total_synced, $status, $error_message]);
    echo "\n✓ Sync logged to database\n";
} catch (Exception $e) {
    echo "\n✗ Failed to log sync: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Sync Summary ===\n";
echo "Vehicles: {$stats['vehicles_added']} added, {$stats['vehicles_updated']} updated\n";
echo "Work Orders: {$stats['work_orders_added']} added\n";
echo "Expense Refunds: {$stats['expenses_added']} added\n";
echo "Rental History: {$stats['rentals_added']} added\n";
echo "Total Records Synced: " . ($stats['vehicles_added'] + $stats['vehicles_updated'] + 
    $stats['work_orders_added'] + $stats['expenses_added'] + $stats['rentals_added']) . "\n";

if (!empty($stats['errors'])) {
    echo "\nErrors encountered:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n=== Sync Completed at " . date('Y-m-d H:i:s') . " ===\n";

// Save output to log file
$output = ob_get_clean();
file_put_contents('/tmp/sheets_sync_last.log', $output);
echo $output;

// Return JSON if called via web
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => empty($stats['errors']),
        'stats' => $stats,
        'log' => $output
    ]);
}
?>
