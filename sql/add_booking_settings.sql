-- 予約システム設定テーブル
CREATE TABLE IF NOT EXISTS booking_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- デフォルト設定を挿入
INSERT IGNORE INTO booking_settings (setting_key, setting_value, setting_description) VALUES
('booking_advance_days', '30', '何日先まで予約受付可能か（日数）'),
('booking_minimum_advance_hours', '24', '予約受付の最低何時間前までか'),
('booking_enabled', '1', '予約受付が有効かどうか（1=有効、0=無効）');