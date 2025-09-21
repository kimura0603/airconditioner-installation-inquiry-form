CREATE DATABASE IF NOT EXISTS air_conditioner_db;
USE air_conditioner_db;

CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    postal_code VARCHAR(10) NOT NULL,
    address TEXT NOT NULL,
    building_type ENUM('house', 'apartment', 'office', 'store') NOT NULL,
    floor_number INT,
    room_type ENUM('living', 'bedroom', 'kitchen', 'office', 'other') NOT NULL,
    room_size ENUM('6jo', '8jo', '10jo', '12jo', '14jo', '16jo', '18jo_over') NOT NULL,
    ac_type ENUM('wall_mounted', 'ceiling_cassette', 'floor_standing', 'ceiling_concealed') NOT NULL,
    ac_capacity ENUM('2.2kw', '2.5kw', '2.8kw', '3.6kw', '4.0kw', '5.6kw', '7.1kw') NOT NULL,
    existing_ac ENUM('yes', 'no') NOT NULL,
    existing_ac_removal ENUM('yes', 'no') DEFAULT 'no',
    electrical_work ENUM('none', 'outlet_addition', 'voltage_change', 'circuit_addition') NOT NULL,
    piping_work ENUM('new', 'existing_reuse', 'partial_replacement') NOT NULL,
    wall_drilling ENUM('yes', 'no') NOT NULL,
    preferred_date DATE,
    preferred_time ENUM('morning', 'afternoon', 'evening', 'flexible') DEFAULT 'flexible',
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);