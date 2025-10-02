-- Turo Integration Schema Updates
-- Adds necessary fields and tables for Turo CSV import functionality

USE car_rental_erp;

-- Add Turo integration fields to vehicles table
ALTER TABLE vehicles 
ADD COLUMN IF NOT EXISTS turo_vehicle_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS owner_company VARCHAR(100),
ADD COLUMN IF NOT EXISTS turo_description TEXT;

-- Add Turo integration fields to customers table  
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS turo_guest_name VARCHAR(100);

-- Add Turo integration fields to reservations table
ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS turo_reservation_id VARCHAR(50) UNIQUE,
ADD COLUMN IF NOT EXISTS check_in_odometer INT,
ADD COLUMN IF NOT EXISTS check_out_odometer INT,
ADD COLUMN IF NOT EXISTS distance_traveled INT,
ADD COLUMN IF NOT EXISTS trip_days INT,
ADD COLUMN IF NOT EXISTS turo_trip_price DECIMAL(10,2),
ADD COLUMN IF NOT EXISTS turo_total_earnings DECIMAL(10,2);

-- Update reservation status enum to include Turo statuses
ALTER TABLE reservations 
MODIFY COLUMN status ENUM(
    'pending', 'confirmed', 'active', 'completed', 'cancelled',
    'booked', 'guest_cancellation', 'host_cancellation'
) DEFAULT 'pending';

-- Update financial transaction types to include Turo-specific types
ALTER TABLE financial_transactions 
MODIFY COLUMN transaction_type ENUM(
    'payment', 'refund', 'fee', 'deposit', 
    'turo_base_price', 'turo_discount', 'turo_fee', 'turo_earnings',
    'turo_boost', 'turo_delivery', 'turo_excess_distance', 'turo_extras',
    'turo_cancellation', 'turo_late_fee', 'turo_cleaning', 'turo_tolls',
    'turo_gas', 'turo_other'
) NOT NULL;

-- Add Turo reference to financial transactions
ALTER TABLE financial_transactions 
ADD COLUMN IF NOT EXISTS turo_reservation_id VARCHAR(50),
ADD COLUMN IF NOT EXISTS turo_transaction_category VARCHAR(50);

-- Create Turo import tracking table
CREATE TABLE IF NOT EXISTS turo_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_records INT DEFAULT 0,
    successful_imports INT DEFAULT 0,
    failed_imports INT DEFAULT 0,
    vehicles_created INT DEFAULT 0,
    vehicles_updated INT DEFAULT 0,
    customers_created INT DEFAULT 0,
    customers_updated INT DEFAULT 0,
    reservations_created INT DEFAULT 0,
    transactions_created INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed', 'cancelled') DEFAULT 'processing',
    error_log TEXT,
    processing_time_seconds INT,
    file_size_bytes BIGINT,
    import_settings JSON
);

-- Create Turo import errors table for detailed error tracking
CREATE TABLE IF NOT EXISTS turo_import_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    row_number INT,
    error_type ENUM('validation', 'parsing', 'database', 'business_logic') NOT NULL,
    error_message TEXT NOT NULL,
    raw_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_id) REFERENCES turo_imports(id) ON DELETE CASCADE
);

-- Create Turo vehicle mapping table for complex vehicle identification
CREATE TABLE IF NOT EXISTS turo_vehicle_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turo_vehicle_description VARCHAR(500) NOT NULL,
    turo_vehicle_name VARCHAR(200) NOT NULL,
    vehicle_id INT,
    license_plate VARCHAR(20),
    make VARCHAR(50),
    model VARCHAR(50),
    year INT,
    owner_company VARCHAR(100),
    confidence_score DECIMAL(3,2) DEFAULT 1.00,
    manual_verification BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    INDEX idx_license_plate (license_plate),
    INDEX idx_turo_description (turo_vehicle_description(100))
);

-- Create Turo customer mapping table for customer identification
CREATE TABLE IF NOT EXISTS turo_customer_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turo_guest_name VARCHAR(100) NOT NULL,
    customer_id INT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    confidence_score DECIMAL(3,2) DEFAULT 1.00,
    manual_verification BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_guest_name (turo_guest_name),
    INDEX idx_customer_name (first_name, last_name)
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_vehicles_turo_id ON vehicles(turo_vehicle_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_owner ON vehicles(owner_company);
CREATE INDEX IF NOT EXISTS idx_customers_turo_name ON customers(turo_guest_name);
CREATE INDEX IF NOT EXISTS idx_reservations_turo_id ON reservations(turo_reservation_id);
CREATE INDEX IF NOT EXISTS idx_transactions_turo_id ON financial_transactions(turo_reservation_id);
CREATE INDEX IF NOT EXISTS idx_imports_status ON turo_imports(status);
CREATE INDEX IF NOT EXISTS idx_imports_date ON turo_imports(import_date);

-- Create view for Turo import summary
CREATE OR REPLACE VIEW turo_import_summary AS
SELECT 
    ti.id,
    ti.filename,
    ti.import_date,
    ti.total_records,
    ti.successful_imports,
    ti.failed_imports,
    ti.vehicles_created,
    ti.vehicles_updated,
    ti.customers_created,
    ti.customers_updated,
    ti.reservations_created,
    ti.transactions_created,
    ti.status,
    ti.processing_time_seconds,
    ROUND(ti.file_size_bytes / 1024 / 1024, 2) as file_size_mb,
    ROUND((ti.successful_imports / ti.total_records) * 100, 2) as success_rate,
    COUNT(tie.id) as error_count
FROM turo_imports ti
LEFT JOIN turo_import_errors tie ON ti.id = tie.import_id
GROUP BY ti.id
ORDER BY ti.import_date DESC;

-- Create view for Turo financial summary
CREATE OR REPLACE VIEW turo_financial_summary AS
SELECT 
    r.turo_reservation_id,
    r.id as reservation_id,
    c.first_name,
    c.last_name,
    v.make,
    v.model,
    v.license_plate,
    r.start_date,
    r.end_date,
    r.trip_days,
    r.turo_trip_price,
    r.turo_total_earnings,
    SUM(CASE WHEN ft.transaction_type LIKE 'turo_%' AND ft.amount > 0 THEN ft.amount ELSE 0 END) as total_income,
    SUM(CASE WHEN ft.transaction_type LIKE 'turo_%' AND ft.amount < 0 THEN ABS(ft.amount) ELSE 0 END) as total_deductions,
    COUNT(ft.id) as transaction_count
FROM reservations r
LEFT JOIN customers c ON r.customer_id = c.id
LEFT JOIN vehicles v ON r.vehicle_id = v.id
LEFT JOIN financial_transactions ft ON r.turo_reservation_id = ft.turo_reservation_id
WHERE r.turo_reservation_id IS NOT NULL
GROUP BY r.turo_reservation_id
ORDER BY r.start_date DESC;

-- Insert initial configuration data
INSERT IGNORE INTO turo_imports (filename, status, total_records) 
VALUES ('system_initialization', 'completed', 0);

COMMIT;

