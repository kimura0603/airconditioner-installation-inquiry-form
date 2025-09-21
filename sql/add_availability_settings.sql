-- 曜日・時間帯の基本設定テーブル
CREATE TABLE IF NOT EXISTS availability_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day_slot (day_of_week, time_slot)
);

-- 基本設定のデフォルトデータを挿入（月〜金曜日の全時間帯を有効）
INSERT IGNORE INTO availability_settings (day_of_week, time_slot, is_available) VALUES
('monday', 'morning', TRUE),
('monday', 'afternoon', TRUE),
('monday', 'evening', TRUE),
('tuesday', 'morning', TRUE),
('tuesday', 'afternoon', TRUE),
('tuesday', 'evening', TRUE),
('wednesday', 'morning', TRUE),
('wednesday', 'afternoon', TRUE),
('wednesday', 'evening', TRUE),
('thursday', 'morning', TRUE),
('thursday', 'afternoon', TRUE),
('thursday', 'evening', TRUE),
('friday', 'morning', TRUE),
('friday', 'afternoon', TRUE),
('friday', 'evening', TRUE),
('saturday', 'morning', FALSE),
('saturday', 'afternoon', FALSE),
('saturday', 'evening', FALSE),
('sunday', 'morning', FALSE),
('sunday', 'afternoon', FALSE),
('sunday', 'evening', FALSE);

-- 特定日の予約可用性を管理するテーブル（既存のreservation_slotsテーブルを拡張）
-- 管理者が手動で設定した特定日の可用性を記録
CREATE TABLE IF NOT EXISTS date_availability_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    is_available BOOLEAN NOT NULL,
    is_manual_override BOOLEAN DEFAULT TRUE,
    reason VARCHAR(255),
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_slot (date, time_slot)
);