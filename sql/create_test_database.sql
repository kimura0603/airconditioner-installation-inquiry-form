-- テスト用データベースの完全なスキーマ作成

-- applications テーブル
CREATE TABLE IF NOT EXISTS applications (
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
    special_requests TEXT,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    confirmed_date DATE,
    confirmed_time_slot ENUM('morning', 'afternoon', 'evening'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_confirmed_date (confirmed_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- time_slots テーブル
CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_name ENUM('morning', 'afternoon', 'evening') NOT NULL UNIQUE,
    display_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 時間帯の初期データ
INSERT IGNORE INTO time_slots (slot_name, display_name, start_time, end_time) VALUES
('morning', '午前（9:00-12:00）', '09:00:00', '12:00:00'),
('afternoon', '午後（12:00-15:00）', '12:00:00', '15:00:00'),
('evening', '夕方（15:00-18:00）', '15:00:00', '18:00:00');

-- application_preferred_slots テーブル（論理削除対応）
CREATE TABLE IF NOT EXISTS application_preferred_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    priority INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT '論理削除日時（NULLなら有効）',
    deleted_reason ENUM('confirmed', 'cancelled', 'manual') NULL DEFAULT NULL COMMENT '削除理由',
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application_id (application_id),
    INDEX idx_date_slot (preferred_date, time_slot),
    INDEX idx_application_preferred_slots_active (application_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reservation_slots テーブル
CREATE TABLE IF NOT EXISTS reservation_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    max_capacity INT DEFAULT 2,
    current_bookings INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_slot (date, time_slot),
    INDEX idx_date (date),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- availability_settings テーブル
CREATE TABLE IF NOT EXISTS availability_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day_slot (day_of_week, time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- デフォルトの可用性設定（全ての日時で利用可能）
INSERT IGNORE INTO availability_settings (day_of_week, time_slot, is_available) VALUES
('monday', 'morning', TRUE), ('monday', 'afternoon', TRUE), ('monday', 'evening', TRUE),
('tuesday', 'morning', TRUE), ('tuesday', 'afternoon', TRUE), ('tuesday', 'evening', TRUE),
('wednesday', 'morning', TRUE), ('wednesday', 'afternoon', TRUE), ('wednesday', 'evening', TRUE),
('thursday', 'morning', TRUE), ('thursday', 'afternoon', TRUE), ('thursday', 'evening', TRUE),
('friday', 'morning', TRUE), ('friday', 'afternoon', TRUE), ('friday', 'evening', TRUE),
('saturday', 'morning', TRUE), ('saturday', 'afternoon', TRUE), ('saturday', 'evening', TRUE),
('sunday', 'morning', TRUE), ('sunday', 'afternoon', TRUE), ('sunday', 'evening', TRUE);

-- date_availability_overrides テーブル
CREATE TABLE IF NOT EXISTS date_availability_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    is_available BOOLEAN NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_slot_override (date, time_slot),
    INDEX idx_date_override (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- booking_settings テーブル
CREATE TABLE IF NOT EXISTS booking_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- デフォルト設定を挿入
INSERT IGNORE INTO booking_settings (setting_key, setting_value, setting_description) VALUES
('booking_advance_days', '30', '何日先まで予約受付可能か（日数）'),
('booking_minimum_advance_hours', '24', '予約受付の最低何時間前までか'),
('booking_enabled', '1', '予約受付が有効かどうか（1=有効、0=無効）');