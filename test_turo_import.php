<?php
/**
 * Test script for Turo CSV import functionality
 * Tests the import system with actual Turo CSV files
 */

require_once 'TuroImporter.php';

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'car_rental_erp',
    'username' => 'root',
    'password' => 'SecureRootPass123!'
];

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ Database connection successful\n";
    
    // Test CSV files
    $csvFiles = [
        '/home/ubuntu/upload/trip_earnings_export_20251002.csv',
        '/home/ubuntu/upload/trip_earnings_export_20251002(1).csv'
    ];
    
    // Import configuration
    $config = [
        'create_missing_vehicles' => true,
        'create_missing_customers' => true,
        'update_existing_reservations' => false,
        'skip_duplicates' => true,
        'batch_size' => 50,
        'debug_mode' => true
    ];
    
    echo "\n🚀 Starting Turo CSV Import Test\n";
    echo "================================\n";
    
    foreach ($csvFiles as $index => $csvFile) {
        echo "\n📁 Processing file " . ($index + 1) . ": " . basename($csvFile) . "\n";
        echo "File size: " . number_format(filesize($csvFile)) . " bytes\n";
        
        if (!file_exists($csvFile)) {
            echo "❌ File not found: $csvFile\n";
            continue;
        }
        
        // Create importer instance
        $importer = new TuroImporter($pdo, $config);
        
        // Start import
        $startTime = microtime(true);
        $result = $importer->importCSV($csvFile);
        $endTime = microtime(true);
        
        // Display results
        echo "\n📊 Import Results:\n";
        echo "Status: " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
        echo "Processing time: " . round($endTime - $startTime, 2) . " seconds\n";
        
        if ($result['success']) {
            $stats = $result['stats'];
            echo "\n📈 Statistics:\n";
            echo "Total records: " . $stats['total_records'] . "\n";
            echo "Successful imports: " . $stats['successful_imports'] . "\n";
            echo "Failed imports: " . $stats['failed_imports'] . "\n";
            echo "Vehicles created: " . $stats['vehicles_created'] . "\n";
            echo "Vehicles updated: " . $stats['vehicles_updated'] . "\n";
            echo "Customers created: " . $stats['customers_created'] . "\n";
            echo "Customers updated: " . $stats['customers_updated'] . "\n";
            echo "Reservations created: " . $stats['reservations_created'] . "\n";
            echo "Transactions created: " . $stats['transactions_created'] . "\n";
            
            $successRate = $stats['total_records'] > 0 ? 
                round(($stats['successful_imports'] / $stats['total_records']) * 100, 2) : 0;
            echo "Success rate: {$successRate}%\n";
            
        } else {
            echo "Error: " . $result['error'] . "\n";
        }
        
        // Display errors if any
        if (!empty($result['errors'])) {
            echo "\n⚠️  Errors encountered:\n";
            foreach (array_slice($result['errors'], 0, 10) as $error) { // Show first 10 errors
                echo "- Row {$error['row']}: {$error['message']}\n";
            }
            if (count($result['errors']) > 10) {
                echo "... and " . (count($result['errors']) - 10) . " more errors\n";
            }
        }
        
        echo "\n" . str_repeat("-", 50) . "\n";
    }
    
    // Display summary statistics
    echo "\n📋 Database Summary After Import:\n";
    echo "=================================\n";
    
    // Count records
    $tables = ['vehicles', 'customers', 'reservations', 'financial_transactions', 'turo_imports'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo ucfirst($table) . ": " . number_format($count) . " records\n";
    }
    
    // Show recent Turo imports
    echo "\n📊 Recent Turo Imports:\n";
    $stmt = $pdo->query("
        SELECT filename, import_date, total_records, successful_imports, 
               failed_imports, status, processing_time_seconds
        FROM turo_imports 
        WHERE filename != 'system_initialization'
        ORDER BY import_date DESC 
        LIMIT 5
    ");
    
    while ($import = $stmt->fetch()) {
        echo "- {$import['filename']}: {$import['successful_imports']}/{$import['total_records']} ";
        echo "({$import['status']}) in {$import['processing_time_seconds']}s\n";
    }
    
    // Show sample data
    echo "\n🔍 Sample Imported Data:\n";
    echo "========================\n";
    
    // Sample vehicles
    echo "\nVehicles (Turo):\n";
    $stmt = $pdo->query("
        SELECT make, model, year, license_plate, owner_company 
        FROM vehicles 
        WHERE owner_company IS NOT NULL 
        LIMIT 5
    ");
    while ($vehicle = $stmt->fetch()) {
        echo "- {$vehicle['year']} {$vehicle['make']} {$vehicle['model']} ";
        echo "({$vehicle['license_plate']}) - {$vehicle['owner_company']}\n";
    }
    
    // Sample reservations
    echo "\nReservations (Turo):\n";
    $stmt = $pdo->query("
        SELECT r.turo_reservation_id, c.first_name, c.last_name, 
               v.make, v.model, r.start_date, r.end_date, r.status
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.turo_reservation_id IS NOT NULL
        ORDER BY r.start_date DESC
        LIMIT 5
    ");
    while ($reservation = $stmt->fetch()) {
        echo "- #{$reservation['turo_reservation_id']}: {$reservation['first_name']} {$reservation['last_name']} ";
        echo "rented {$reservation['make']} {$reservation['model']} ";
        echo "({$reservation['start_date']} to {$reservation['end_date']}) - {$reservation['status']}\n";
    }
    
    echo "\n✅ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

