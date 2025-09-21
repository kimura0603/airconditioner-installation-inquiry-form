# 予約システムテストガイド

## 概要

この予約システムでは、PHPUnitを使用して包括的なテストスイートを実装しています。テストは以下の問題を防ぐために設計されています：

- 予約確定時の希望日時レコード残存問題
- 予約枠カウントの不整合
- 確定済み日時への重複予約
- データ整合性の問題

## テスト構成

### 1. Unit Tests (`tests/Unit/`)

#### `ReservationConfirmationTest.php`
- **基本的な予約確定処理のテスト**: 単一申し込みの確定処理
- **複数申し込みでの予約確定テスト**: 競合する希望日時の処理
- **満席時間帯での確定試行テスト**: エラーハンドリングの確認
- **既確定申し込みの再確定試行テスト**: 重複処理の防止
- **予約枠リリースによる空き状況復活テスト**: カウンタの正確性

### 2. Integration Tests (`tests/Integration/`)

#### `ApiTest.php`
- **空き状況取得APIのテスト**: `get_available_slots.php`の動作確認
- **確定済み時間帯のAPI表示テスト**: `available: false`の確認
- **満席時間帯のAPIレスポンステスト**: 正確なカウント表示
- **予約確定APIのテスト**: `confirm_reservation.php`の動作確認
- **不正な確定API呼び出しテスト**: エラーハンドリング

#### `ReservationWorkflowTest.php`
- **基本的な予約ワークフロー**: 申し込み→確定→状態確認の流れ
- **複数顧客の競合予約処理**: 実際の運用シナリオ
- **データ不整合の検出と修正**: 整合性チェック
- **大量申し込みでの性能テスト**: スケーラビリティ確認

## 実行方法

### 1. 簡単実行（推奨）

```bash
# 全テストの実行
./run-tests.sh

# カバレッジレポート付きで実行
./run-tests.sh --coverage
```

### 2. 手動実行

```bash
# Composerの依存関係をインストール
docker run --rm -v $(pwd):/app composer install

# テストデータベースのセットアップ
docker exec air-conditionner-db-1 mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS air_conditioner_db_test;"
docker exec air-conditionner-db-1 mysql -u root -ppassword air_conditioner_db_test < sql/init.sql

# 個別テストの実行
./vendor/bin/phpunit tests/Unit/ReservationConfirmationTest.php
./vendor/bin/phpunit tests/Integration/ApiTest.php
./vendor/bin/phpunit tests/Integration/ReservationWorkflowTest.php

# 全テストの実行
./vendor/bin/phpunit
```

## テストで検証される重要なケース

### 1. 申し込み番号6で発生した問題のテスト

```php
public function testSlotReleaseAfterConfirmation(): void
{
    // 3つの申し込みで同じ日時を希望
    // 1つを別日時で確定 → 共有スロットのカウント減少を確認
    // 2つ目も別日時で確定 → さらにカウント減少を確認
    // 3つ目を共有スロットで確定 → 最終的に確定済みになることを確認
}
```

### 2. 複数顧客の競合シナリオ

```php
public function testCompetingReservationsWorkflow(): void
{
    // 人気日時に3件の希望 → 満席状態
    // 1件を人気日時で確定 → 2件の希望残存
    // 1件を代替日時で確定 → 1件の希望残存
    // 最後の1件は確定できない（既に満席）
}
```

### 3. データ整合性チェック

```php
private function validateDataConsistency($applicationId, $confirmedDate, $confirmedTimeSlot): void
{
    // 1. アプリケーション状態の確認
    // 2. 希望日時レコードの完全削除確認
    // 3. 確定日時の予約不可状態確認
    // 4. 確定済みアプリケーションの希望日時残存チェック
    // 5. 予約枠カウントの整合性チェック
}
```

## テスト結果の解釈

### 成功例
```
✓ Basic reservation confirmation
✓ Multiple applications reservation confirmation
✓ Confirmation on full slot
✓ Confirmation on already confirmed application
✓ Slot release after confirmation
```

### 失敗例（修正前の状態）
```
✗ Slot release after confirmation
  Failed asserting that 1 matches expected 0.
  Expected slot count to be 0 after release, but was 1.
```

## 継続的なテスト実行

### 開発時
```bash
# ファイル変更を監視してテストを自動実行
fswatch -o src/ tests/ | xargs -n1 -I{} ./run-tests.sh
```

### CI/CD統合
```yaml
# GitHub Actions例
- name: Run Tests
  run: |
    docker-compose up -d
    ./run-tests.sh
    docker-compose down
```

## トラブルシューティング

### よくある問題

1. **データベース接続エラー**
   ```bash
   # Dockerコンテナが起動しているか確認
   docker-compose ps

   # 必要に応じて再起動
   docker-compose down && docker-compose up -d
   ```

2. **テストデータベースの問題**
   ```bash
   # テストデータベースの再作成
   docker exec air-conditionner-db-1 mysql -u root -ppassword -e "DROP DATABASE IF EXISTS air_conditioner_db_test;"
   docker exec air-conditionner-db-1 mysql -u root -ppassword -e "CREATE DATABASE air_conditioner_db_test;"
   ```

3. **依存関係の問題**
   ```bash
   # Composerの依存関係を再インストール
   rm -rf vendor/
   docker run --rm -v $(pwd):/app composer install
   ```

## テスト追加のガイドライン

新しいテストを追加する際は：

1. **適切なテストカテゴリを選択**
   - Unit: 単一機能のテスト
   - Integration: 複数コンポーネントの連携テスト

2. **テストケース名は説明的に**
   ```php
   public function testReservationConfirmationReleasesOtherPreferredSlots(): void
   ```

3. **Arrange-Act-Assert パターンを使用**
   ```php
   // Arrange: テストデータの準備
   $applicationId = $this->createTestApplication(...);

   // Act: テスト対象の実行
   $result = $this->confirmReservation(...);

   // Assert: 結果の検証
   $this->assertTrue($result['success']);
   ```

4. **クリーンアップの確保**
   - TestCaseクラスが自動的にクリーンアップを実行
   - 必要に応じて追加のクリーンアップを実装