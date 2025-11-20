<?php
/**
 * Turo CSV Import System
 * Comprehensive class for importing Turo trip earnings data into RAVEN
 */

class TuroImporter {
    private $pdo;
    private $importId;
    private $stats;
    private $errors;
    private $config;
    
    // Expected CSV columns (43 total)
    private $expectedColumns = [
        'Reservation ID', 'Guest', 'Vehicle', 'Vehicle name', 'Trip start', 'Trip end',
        'Pickup location', 'Return location', 'Trip status', 'Check-in odometer',
        'Check-out odometer', 'Distance traveled', 'Trip days', 'Trip price',
        'Boost price', '3-day discount', '1-week discount', '2-week discount',
        '3-week discount', '1-month discount', '2-month discount', '3-month discount',
        'Non-refundable discount', 'Early bird discount', 'Host promotional credit',
        'Delivery', 'Excess distance', 'Extras', 'Cancellation fee', 'Additional usage',
        'Late fee', 'Improper return fee', 'Airport operations fee', 'Tolls & tickets',
        'On-trip EV charging', 'Post-trip EV charging', 'Smoking', 'Cleaning',
        'Fines (paid to host)', 'Gas reimbursement', 'Gas fee', 'Other fees',
        'Sales tax', 'Total earnings'
    ];
    
    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'create_missing_vehicles' => true,
            'create_missing_customers' => true,
            'update_existing_reservations' => false,
            'skip_duplicates' => true,
            'batch_size' => 100,
            'debug_mode' => false
        ], $config);
        
        $this->initializeStats();
        $this->errors = [];
    }
    
    private function initializeStats() {
        $this->stats = [
            'total_records' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
            'vehicles_created' => 0,
            'vehicles_updated' => 0,
            'customers_created' => 0,
            'customers_updated' => 0,
            'reservations_created' => 0,
            'transactions_created' => 0
        ];
    }
    
    /**
     * Main import function
     */
    public function importCSV($filePath, $filename = null) {
        $startTime = microtime(true);
        $filename = $filename ?: basename($filePath);
        $fileSize = filesize($filePath);
        
        try {
            // Create import record
            $this->createImportRecord($filename, $fileSize);
            
            // Validate and parse CSV
            $data = $this->parseCSV($filePath);
            
            // Process data in batches
            $this->processDataBatches($data);
            
            // Finalize import
            $processingTime = round(microtime(true) - $startTime, 2);
            $this->finalizeImport('completed', $processingTime);
            
            return [
                'success' => true,
                'import_id' => $this->importId,
                'stats' => $this->stats,
                'processing_time' => $processingTime,
                'errors' => $this->errors
            ];
            
        } catch (Exception $e) {
            $this->logError('system', $e->getMessage(), null, 0);
            $this->finalizeImport('failed', microtime(true) - $startTime);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'import_id' => $this->importId,
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
        }
    }
    
    private function createImportRecord($filename, $fileSize) {
        $stmt = $this->pdo->prepare("
            INSERT INTO turo_imports (filename, file_size_bytes, import_settings, status) 
            VALUES (?, ?, ?, 'processing')
        ");
        $stmt->execute([$filename, $fileSize, json_encode($this->config)]);
        $this->importId = $this->pdo->lastInsertId();
    }
    
    private function parseCSV($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("CSV file not found: $filePath");
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: $filePath");
        }
        
        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception("Cannot read CSV header");
        }
        
        // Validate header
        $this->validateCSVHeader($header);
        
        // Read data
        $data = [];
        $rowNumber = 1;
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            if (count($row) !== count($header)) {
                $this->logError('parsing', "Row has incorrect number of columns", $row, $rowNumber);
                continue;
            }
            
            $data[] = array_combine($header, $row);
            $this->stats['total_records']++;
        }
        
        fclose($handle);
        return $data;
    }
    
    private function validateCSVHeader($header) {
        $missing = array_diff($this->expectedColumns, $header);
        if (!empty($missing)) {
            throw new Exception("Missing required columns: " . implode(', ', $missing));
        }
    }
    
    private function processDataBatches($data) {
        $batches = array_chunk($data, $this->config['batch_size']);
        
        foreach ($batches as $batchIndex => $batch) {
            $this->pdo->beginTransaction();
            
            try {
                foreach ($batch as $rowIndex => $row) {
                    $globalRowIndex = ($batchIndex * $this->config['batch_size']) + $rowIndex + 2; // +2 for header and 1-based indexing
                    $this->processRow($row, $globalRowIndex);
                }
                
                $this->pdo->commit();
                
            } catch (Exception $e) {
                $this->pdo->rollback();
                $this->logError('database', "Batch processing failed: " . $e->getMessage(), null, $batchIndex);
                
                // Process rows individually to identify specific failures
                foreach ($batch as $rowIndex => $row) {
                    $globalRowIndex = ($batchIndex * $this->config['batch_size']) + $rowIndex + 2;
                    try {
                        $this->pdo->beginTransaction();
                        $this->processRow($row, $globalRowIndex);
                        $this->pdo->commit();
                    } catch (Exception $rowError) {
                        $this->pdo->rollback();
                        $this->logError('database', $rowError->getMessage(), $row, $globalRowIndex);
                        $this->stats['failed_imports']++;
                    }
                }
            }
        }
    }
    
    private function processRow($row, $rowNumber) {
        try {
            // Skip empty or cancelled reservations with no earnings
            if (empty($row['Reservation ID']) || 
                (in_array($row['Trip status'], ['Guest cancellation', 'Host cancellation']) && 
                 floatval(str_replace(['$', ','], '', $row['Total earnings'])) == 0)) {
                return;
            }
            
            // Check for duplicate
            if ($this->config['skip_duplicates'] && $this->isDuplicateReservation($row['Reservation ID'])) {
                return;
            }
            
            // Parse and validate data
            $vehicleData = $this->parseVehicleData($row);
            $customerData = $this->parseCustomerData($row);
            $reservationData = $this->parseReservationData($row);
            $financialData = $this->parseFinancialData($row);
            
            // Process entities
            $vehicleId = $this->processVehicle($vehicleData);
            $customerId = $this->processCustomer($customerData);
            $reservationId = $this->processReservation($reservationData, $vehicleId, $customerId);
            $this->processFinancialTransactions($financialData, $reservationId, $row['Reservation ID']);
            
            $this->stats['successful_imports']++;
            
        } catch (Exception $e) {
            $this->logError('business_logic', $e->getMessage(), $row, $rowNumber);
            $this->stats['failed_imports']++;
        }
    }
    
    private function parseVehicleData($row) {
        // Parse vehicle description: "Owner's Make Model Year (State #License)"
        $vehicleDesc = $row['Vehicle'];
        $vehicleName = $row['Vehicle name'];
        
        // Extract license plate from description
        preg_match('/\(([^#]*#)?([^)]+)\)/', $vehicleDesc, $plateMatches);
        $licensePlate = isset($plateMatches[2]) ? trim($plateMatches[2]) : null;
        
        // Extract owner company
        preg_match('/^([^\']+)\'s/', $vehicleDesc, $ownerMatches);
        $ownerCompany = isset($ownerMatches[1]) ? trim($ownerMatches[1]) : 'Unknown';
        
        // Parse make, model, year from vehicle name
        $nameParts = explode(' ', trim($vehicleName));
        $year = null;
        $make = '';
        $model = '';
        
        // Find year (4-digit number)
        foreach ($nameParts as $index => $part) {
            if (preg_match('/^\d{4}$/', $part)) {
                $year = intval($part);
                $make = implode(' ', array_slice($nameParts, 0, $index));
                $model = implode(' ', array_slice($nameParts, $index + 1));
                break;
            }
        }
        
        // Fallback parsing if year not found
        if (!$year && count($nameParts) >= 3) {
            $make = $nameParts[0];
            $model = implode(' ', array_slice($nameParts, 1, -1));
            $year = intval(end($nameParts));
        }
        
        return [
            'turo_description' => $vehicleDesc,
            'turo_vehicle_name' => $vehicleName,
            'license_plate' => $licensePlate,
            'make' => $make ?: 'Unknown',
            'model' => $model ?: 'Unknown',
            'year' => $year ?: date('Y'),
            'owner_company' => $ownerCompany
        ];
    }
    
    private function parseCustomerData($row) {
        $guestName = trim($row['Guest']);
        $nameParts = explode(' ', $guestName);
        
        return [
            'turo_guest_name' => $guestName,
            'first_name' => $nameParts[0] ?? 'Unknown',
            'last_name' => isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Guest'
        ];
    }
    
    private function parseReservationData($row) {
        // Parse dates
        $startDate = $this->parseDate($row['Trip start']);
        $endDate = $this->parseDate($row['Trip end']);
        
        // Parse amounts
        $tripPrice = $this->parseAmount($row['Trip price']);
        $totalEarnings = $this->parseAmount($row['Total earnings']);
        
        // Parse odometer readings
        $checkInOdometer = $this->parseInteger($row['Check-in odometer']);
        $checkOutOdometer = $this->parseInteger($row['Check-out odometer']);
        $distanceTraveled = $this->parseInteger($row['Distance traveled']);
        $tripDays = $this->parseInteger($row['Trip days']);
        
        // Map status
        $status = $this->mapTuroStatus($row['Trip status']);
        
        return [
            'turo_reservation_id' => $row['Reservation ID'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_location' => $row['Pickup location'],
            'dropoff_location' => $row['Return location'],
            'total_amount' => $totalEarnings,
            'status' => $status,
            'check_in_odometer' => $checkInOdometer,
            'check_out_odometer' => $checkOutOdometer,
            'distance_traveled' => $distanceTraveled,
            'trip_days' => $tripDays,
            'turo_trip_price' => $tripPrice,
            'turo_total_earnings' => $totalEarnings
        ];
    }
    
    private function parseFinancialData($row) {
        $transactions = [];
        
        // Base price
        $tripPrice = $this->parseAmount($row['Trip price']);
        if ($tripPrice > 0) {
            $transactions[] = [
                'type' => 'turo_base_price',
                'amount' => $tripPrice,
                'description' => 'Turo base trip price',
                'category' => 'income'
            ];
        }
        
        // Boost price
        $boostPrice = $this->parseAmount($row['Boost price']);
        if ($boostPrice > 0) {
            $transactions[] = [
                'type' => 'turo_boost',
                'amount' => $boostPrice,
                'description' => 'Turo boost pricing',
                'category' => 'income'
            ];
        }
        
        // Discounts (negative amounts)
        $discountFields = [
            '3-day discount', '1-week discount', '2-week discount', '3-week discount',
            '1-month discount', '2-month discount', '3-month discount',
            'Non-refundable discount', 'Early bird discount', 'Host promotional credit'
        ];
        
        foreach ($discountFields as $field) {
            $amount = $this->parseAmount($row[$field]);
            if ($amount != 0) {
                $transactions[] = [
                    'type' => 'turo_discount',
                    'amount' => $amount, // Keep negative for discounts
                    'description' => "Turo discount: $field",
                    'category' => 'discount'
                ];
            }
        }
        
        // Fees and charges
        $feeFields = [
            'Delivery', 'Excess distance', 'Extras', 'Cancellation fee', 'Additional usage',
            'Late fee', 'Improper return fee', 'Airport operations fee', 'Tolls & tickets',
            'On-trip EV charging', 'Post-trip EV charging', 'Smoking', 'Cleaning',
            'Fines (paid to host)', 'Gas reimbursement', 'Gas fee', 'Other fees', 'Sales tax'
        ];
        
        foreach ($feeFields as $field) {
            $amount = $this->parseAmount($row[$field]);
            if ($amount != 0) {
                $transactions[] = [
                    'type' => 'turo_fee',
                    'amount' => $amount,
                    'description' => "Turo fee: $field",
                    'category' => 'fee'
                ];
            }
        }
        
        // Total earnings
        $totalEarnings = $this->parseAmount($row['Total earnings']);
        if ($totalEarnings > 0) {
            $transactions[] = [
                'type' => 'turo_earnings',
                'amount' => $totalEarnings,
                'description' => 'Turo total earnings',
                'category' => 'earnings'
            ];
        }
        
        return $transactions;
    }
    
    private function processVehicle($vehicleData) {
        // Check if vehicle exists by license plate
        $stmt = $this->pdo->prepare("SELECT id FROM vehicles WHERE license_plate = ?");
        $stmt->execute([$vehicleData['license_plate']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing vehicle with Turo data
            $stmt = $this->pdo->prepare("
                UPDATE vehicles 
                SET turo_vehicle_id = ?, owner_company = ?, turo_description = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $vehicleData['turo_vehicle_name'],
                $vehicleData['owner_company'],
                $vehicleData['turo_description'],
                $existing['id']
            ]);
            
            $this->stats['vehicles_updated']++;
            return $existing['id'];
            
        } else if ($this->config['create_missing_vehicles']) {
            // Create new vehicle
            $stmt = $this->pdo->prepare("
                INSERT INTO vehicles (
                    make, model, year, license_plate, vin, color, daily_rate,
                    turo_vehicle_id, owner_company, turo_description, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
            ");
            
            // Generate placeholder VIN
            $vin = 'TURO' . str_pad(rand(1, 999999999999), 13, '0', STR_PAD_LEFT);
            
            $stmt->execute([
                $vehicleData['make'],
                $vehicleData['model'],
                $vehicleData['year'],
                $vehicleData['license_plate'],
                $vin,
                'Unknown',
                50.00, // Default daily rate
                $vehicleData['turo_vehicle_name'],
                $vehicleData['owner_company'],
                $vehicleData['turo_description']
            ]);
            
            $this->stats['vehicles_created']++;
            return $this->pdo->lastInsertId();
            
        } else {
            throw new Exception("Vehicle not found and creation disabled: " . $vehicleData['license_plate']);
        }
    }
    
    private function processCustomer($customerData) {
        // Check if customer exists by Turo guest name
        $stmt = $this->pdo->prepare("SELECT id FROM customers WHERE turo_guest_name = ?");
        $stmt->execute([$customerData['turo_guest_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->stats['customers_updated']++;
            return $existing['id'];
            
        } else if ($this->config['create_missing_customers']) {
            // Create new customer with placeholder data
            $stmt = $this->pdo->prepare("
                INSERT INTO customers (
                    first_name, last_name, email, phone, address, driver_license,
                    date_of_birth, turo_guest_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Generate placeholder data
            $email = strtolower(str_replace(' ', '.', $customerData['turo_guest_name'])) . '@turo.guest';
            $driverLicense = 'TURO' . str_pad(rand(1, 999999999), 9, '0', STR_PAD_LEFT);
            
            $stmt->execute([
                $customerData['first_name'],
                $customerData['last_name'],
                $email,
                '000-000-0000',
                'Turo Guest Address',
                $driverLicense,
                '1990-01-01',
                $customerData['turo_guest_name']
            ]);
            
            $this->stats['customers_created']++;
            return $this->pdo->lastInsertId();
            
        } else {
            throw new Exception("Customer not found and creation disabled: " . $customerData['turo_guest_name']);
        }
    }
    
    private function processReservation($reservationData, $vehicleId, $customerId) {
        // Check for existing reservation
        $stmt = $this->pdo->prepare("SELECT id FROM reservations WHERE turo_reservation_id = ?");
        $stmt->execute([$reservationData['turo_reservation_id']]);
        $existing = $stmt->fetch();
        
        if ($existing && !$this->config['update_existing_reservations']) {
            return $existing['id'];
        }
        
        if ($existing) {
            // Update existing reservation
            $stmt = $this->pdo->prepare("
                UPDATE reservations SET
                    customer_id = ?, vehicle_id = ?, start_date = ?, end_date = ?,
                    pickup_location = ?, dropoff_location = ?, total_amount = ?, status = ?,
                    check_in_odometer = ?, check_out_odometer = ?, distance_traveled = ?,
                    trip_days = ?, turo_trip_price = ?, turo_total_earnings = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $customerId, $vehicleId, $reservationData['start_date'], $reservationData['end_date'],
                $reservationData['pickup_location'], $reservationData['dropoff_location'],
                $reservationData['total_amount'], $reservationData['status'],
                $reservationData['check_in_odometer'], $reservationData['check_out_odometer'],
                $reservationData['distance_traveled'], $reservationData['trip_days'],
                $reservationData['turo_trip_price'], $reservationData['turo_total_earnings'],
                $existing['id']
            ]);
            
            return $existing['id'];
            
        } else {
            // Create new reservation
            $stmt = $this->pdo->prepare("
                INSERT INTO reservations (
                    customer_id, vehicle_id, start_date, end_date, pickup_location,
                    dropoff_location, total_amount, status, turo_reservation_id,
                    check_in_odometer, check_out_odometer, distance_traveled,
                    trip_days, turo_trip_price, turo_total_earnings
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $customerId, $vehicleId, $reservationData['start_date'], $reservationData['end_date'],
                $reservationData['pickup_location'], $reservationData['dropoff_location'],
                $reservationData['total_amount'], $reservationData['status'],
                $reservationData['turo_reservation_id'], $reservationData['check_in_odometer'],
                $reservationData['check_out_odometer'], $reservationData['distance_traveled'],
                $reservationData['trip_days'], $reservationData['turo_trip_price'],
                $reservationData['turo_total_earnings']
            ]);
            
            $this->stats['reservations_created']++;
            return $this->pdo->lastInsertId();
        }
    }
    
    private function processFinancialTransactions($transactions, $reservationId, $turoReservationId) {
        foreach ($transactions as $transaction) {
            $stmt = $this->pdo->prepare("
                INSERT INTO financial_transactions (
                    reservation_id, transaction_type, amount, description,
                    turo_reservation_id, turo_transaction_category, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed')
            ");
            
            $stmt->execute([
                $reservationId,
                $transaction['type'],
                $transaction['amount'],
                $transaction['description'],
                $turoReservationId,
                $transaction['category']
            ]);
            
            $this->stats['transactions_created']++;
        }
    }
    
    // Helper functions
    private function parseDate($dateString) {
        if (empty($dateString)) return null;
        
        try {
            $date = new DateTime($dateString);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function parseAmount($amountString) {
        if (empty($amountString)) return 0;
        
        // Remove currency symbols and commas, handle negative values
        $cleaned = str_replace(['$', ',', ' '], '', $amountString);
        return floatval($cleaned);
    }
    
    private function parseInteger($intString) {
        if (empty($intString)) return null;
        
        $cleaned = str_replace([',', ' '], '', $intString);
        return is_numeric($cleaned) ? intval($cleaned) : null;
    }
    
    private function mapTuroStatus($turoStatus) {
        $statusMap = [
            'Completed' => 'completed',
            'Booked' => 'confirmed',
            'Guest cancellation' => 'cancelled',
            'Host cancellation' => 'cancelled',
            'Active' => 'active'
        ];
        
        return $statusMap[$turoStatus] ?? 'pending';
    }
    
    private function isDuplicateReservation($turoReservationId) {
        $stmt = $this->pdo->prepare("SELECT id FROM reservations WHERE turo_reservation_id = ?");
        $stmt->execute([$turoReservationId]);
        return $stmt->fetch() !== false;
    }
    
    private function logError($type, $message, $rawData = null, $rowNumber = null) {
        $this->errors[] = [
            'type' => $type,
            'message' => $message,
            'row' => $rowNumber,
            'data' => $rawData
        ];
        
        if ($this->importId) {
            $stmt = $this->pdo->prepare("
                INSERT INTO turo_import_errors (import_id, row_number, error_type, error_message, raw_data)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->importId,
                $rowNumber,
                $type,
                $message,
                $rawData ? json_encode($rawData) : null
            ]);
        }
    }
    
    private function finalizeImport($status, $processingTime) {
        if ($this->importId) {
            $stmt = $this->pdo->prepare("
                UPDATE turo_imports SET
                    status = ?, processing_time_seconds = ?, total_records = ?,
                    successful_imports = ?, failed_imports = ?, vehicles_created = ?,
                    vehicles_updated = ?, customers_created = ?, customers_updated = ?,
                    reservations_created = ?, transactions_created = ?,
                    error_log = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $status, $processingTime, $this->stats['total_records'],
                $this->stats['successful_imports'], $this->stats['failed_imports'],
                $this->stats['vehicles_created'], $this->stats['vehicles_updated'],
                $this->stats['customers_created'], $this->stats['customers_updated'],
                $this->stats['reservations_created'], $this->stats['transactions_created'],
                json_encode($this->errors), $this->importId
            ]);
        }
    }
    
    /**
     * Get import statistics
     */
    public function getStats() {
        return $this->stats;
    }
    
    /**
     * Get import errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get import history
     */
    public function getImportHistory($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM turo_import_summary 
            ORDER BY import_date DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

