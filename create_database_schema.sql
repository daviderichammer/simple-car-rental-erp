-- Car Rental ERP Database Schema
-- Simple and straightforward database design

CREATE DATABASE IF NOT EXISTS car_rental_erp;
USE car_rental_erp;

-- Vehicles table
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    vin VARCHAR(17) UNIQUE NOT NULL,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    color VARCHAR(30),
    mileage INT DEFAULT 0,
    status ENUM('available', 'rented', 'maintenance', 'out_of_service') DEFAULT 'available',
    daily_rate DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    driver_license VARCHAR(50) UNIQUE NOT NULL,
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pickup_location VARCHAR(200),
    dropoff_location VARCHAR(200),
    total_amount DECIMAL(10,2),
    status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Maintenance schedules table
CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    maintenance_type VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    completed_date DATE,
    cost DECIMAL(10,2),
    description TEXT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Financial transactions table
CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    transaction_type ENUM('payment', 'refund', 'fee', 'deposit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    INDEX idx_reservation_id (reservation_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
);

-- Insert sample data
INSERT INTO vehicles (make, model, year, vin, license_plate, color, mileage, daily_rate) VALUES
('Toyota', 'Camry', 2022, '1HGBH41JXMN109186', 'ABC123', 'Silver', 15000, 45.00),
('Honda', 'Civic', 2023, '2HGFC2F59NH123456', 'XYZ789', 'Blue', 8000, 40.00),
('Ford', 'Escape', 2021, '1FMCU0HD1MUA12345', 'DEF456', 'Red', 22000, 55.00),
('Chevrolet', 'Malibu', 2022, '1G1ZE5ST4NF123456', 'GHI789', 'White', 12000, 42.00),
('Nissan', 'Altima', 2023, '1N4AL3AP5NC123456', 'JKL012', 'Black', 5000, 48.00);

INSERT INTO customers (first_name, last_name, email, phone, address, driver_license, date_of_birth) VALUES
('John', 'Smith', 'john.smith@email.com', '555-0101', '123 Main St, Anytown, ST 12345', 'DL123456789', '1985-03-15'),
('Sarah', 'Johnson', 'sarah.johnson@email.com', '555-0102', '456 Oak Ave, Somewhere, ST 12346', 'DL987654321', '1990-07-22'),
('Michael', 'Brown', 'michael.brown@email.com', '555-0103', '789 Pine Rd, Elsewhere, ST 12347', 'DL456789123', '1988-11-08'),
('Emily', 'Davis', 'emily.davis@email.com', '555-0104', '321 Elm St, Nowhere, ST 12348', 'DL789123456', '1992-05-30'),
('David', 'Wilson', 'david.wilson@email.com', '555-0105', '654 Maple Dr, Anywhere, ST 12349', 'DL321654987', '1987-09-12');

INSERT INTO reservations (customer_id, vehicle_id, start_date, end_date, pickup_location, dropoff_location, total_amount, status) VALUES
(1, 1, '2025-07-25', '2025-07-28', 'Main Office', 'Main Office', 135.00, 'confirmed'),
(2, 2, '2025-07-26', '2025-07-30', 'Airport', 'Downtown', 160.00, 'pending'),
(3, 3, '2025-07-27', '2025-07-29', 'Main Office', 'Main Office', 110.00, 'confirmed');

INSERT INTO maintenance_schedules (vehicle_id, maintenance_type, scheduled_date, description, status) VALUES
(1, 'Oil Change', '2025-08-01', 'Regular oil change and filter replacement', 'scheduled'),
(2, 'Tire Rotation', '2025-08-05', 'Rotate tires and check alignment', 'scheduled'),
(3, 'Brake Inspection', '2025-08-10', 'Check brake pads and fluid', 'scheduled');

INSERT INTO financial_transactions (reservation_id, transaction_type, amount, payment_method, description, status) VALUES
(1, 'payment', 135.00, 'Credit Card', 'Payment for reservation #1', 'completed'),
(2, 'deposit', 50.00, 'Credit Card', 'Security deposit for reservation #2', 'completed');

