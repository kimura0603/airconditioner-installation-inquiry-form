-- application_preferred_slotsテーブルに論理削除カラムを追加
ALTER TABLE application_preferred_slots
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT '論理削除日時（NULLなら有効）',
ADD COLUMN deleted_reason ENUM('confirmed', 'cancelled', 'manual') NULL DEFAULT NULL COMMENT '削除理由（confirmed=確定により削除、cancelled=キャンセル、manual=手動削除）';

-- インデックスを追加（削除されていないレコードの検索を高速化）
CREATE INDEX idx_application_preferred_slots_active ON application_preferred_slots (application_id, deleted_at);