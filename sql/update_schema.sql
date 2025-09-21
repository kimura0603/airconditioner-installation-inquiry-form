-- 既存のテーブルを拡張

-- 1. applicationsテーブルにステータスと複数希望日時を追加
ALTER TABLE applications
ADD COLUMN status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending' AFTER special_requests,
ADD COLUMN confirmed_date DATE AFTER status,
ADD COLUMN confirmed_time_slot ENUM('morning', 'afternoon', 'evening') AFTER confirmed_date;

-- preferred_timeカラムを削除（新しい仕組みに変更）
ALTER TABLE applications
DROP COLUMN preferred_time,
DROP COLUMN preferred_date;

-- 2. 時間枠マスターテーブルの作成
CREATE TABLE time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_name VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 時間枠の初期データ
INSERT INTO time_slots (slot_name, start_time, end_time, display_name) VALUES
('morning', '09:00:00', '12:00:00', '午前（9:00-12:00）'),
('afternoon', '12:00:00', '15:00:00', '午後（12:00-15:00）'),
('evening', '15:00:00', '18:00:00', '夕方（15:00-18:00）');

-- 3. お客様の希望日時テーブル（複数の候補日時を管理）
CREATE TABLE application_preferred_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    priority INT DEFAULT 1, -- 希望優先順位（1が最優先）
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    UNIQUE KEY unique_app_date_slot (application_id, preferred_date, time_slot)
);

-- 4. 予約枠管理テーブル（各日時の予約状況を管理）
CREATE TABLE reservation_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_date DATE NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening') NOT NULL,
    max_capacity INT DEFAULT 2, -- 1つの時間枠で受けられる最大件数
    current_bookings INT DEFAULT 0, -- 現在の予約件数
    is_available BOOLEAN DEFAULT TRUE, -- 管理者が手動で無効にできる
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_slot (reservation_date, time_slot)
);

-- 5. 予約確定履歴テーブル
CREATE TABLE reservation_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    original_status ENUM('pending', 'confirmed', 'cancelled') NOT NULL,
    new_status ENUM('pending', 'confirmed', 'cancelled') NOT NULL,
    confirmed_date DATE,
    confirmed_time_slot ENUM('morning', 'afternoon', 'evening'),
    reason TEXT,
    created_by VARCHAR(100), -- 操作者（管理者名など）
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- インデックスの追加
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_confirmed_date ON applications(confirmed_date);
CREATE INDEX idx_reservation_slots_date ON reservation_slots(reservation_date);
CREATE INDEX idx_application_preferred_slots_date ON application_preferred_slots(preferred_date);