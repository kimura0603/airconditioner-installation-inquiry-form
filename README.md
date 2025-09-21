# エアコン工事申し込みフォームシステム

エアコン工事業者のお客様向けの申し込みフォームシステムです。Docker環境でPHP+MySQLを使用して構築されています。

## 機能

- **申し込みフォーム**: エアコン設置工事の詳細情報を収集
- **データベース保存**: 申し込み情報をMySQLデータベースに保存
- **管理画面**: 受け付けた申し込み一覧の表示
- **レスポンシブデザイン**: スマートフォン・タブレット対応

## 必要な情報

フォームでは以下の情報を収集します：

### お客様情報
- お名前（必須）
- 電話番号（必須）
- メールアドレス

### 設置場所情報
- 郵便番号（必須）
- 住所（必須）
- 建物種別（必須）: 一戸建て、アパート・マンション、オフィス、店舗
- 階数

### エアコン詳細
- 設置予定の部屋（必須）: リビング、寝室、キッチンなど
- 部屋の広さ（必須）: 6畳〜18畳以上
- エアコンの種類（必須）: 壁掛け型、天井カセット型など
- エアコンの能力（必須）: 2.2kW〜7.1kW

### 工事詳細
- 既設エアコンの有無（必須）
- 既設エアコンの撤去
- 電気工事（必須）: 不要、コンセント増設、電圧変更、回路増設
- 配管工事（必須）: 新規配管、既設配管再利用、一部配管交換
- 壁穴あけ工事（必須）

### 希望日時
- 希望日
- 希望時間帯: 午前中、午後、夕方、指定なし
- 特記事項・ご要望

## セットアップ

### 1. リポジトリのクローン
```bash
git clone <repository-url>
cd air-conditionner
```

### 2. Dockerでの起動
```bash
docker-compose up -d
```

### 3. アクセス
- 申し込みフォーム: http://localhost:8080
- 管理画面: http://localhost:8080/admin.php

## ファイル構成

```
air-conditionner/
├── docker-compose.yml          # Docker設定
├── Dockerfile                  # PHP Apache設定
├── apache-config.conf          # Apache設定
├── sql/
│   └── init.sql               # データベース初期化
└── src/                       # PHPアプリケーション
    ├── index.php              # 申し込みフォーム
    ├── process_form.php       # フォーム処理
    ├── admin.php              # 管理画面
    ├── styles.css             # スタイルシート
    ├── config/
    │   └── database.php       # データベース接続
    └── models/
        └── Application.php    # アプリケーションモデル
```

## データベース

### テーブル: applications

| カラム名 | 型 | 説明 |
|----------|----|----|
| id | INT | 申し込み番号（自動採番） |
| customer_name | VARCHAR(100) | お客様名 |
| customer_phone | VARCHAR(20) | 電話番号 |
| customer_email | VARCHAR(100) | メールアドレス |
| postal_code | VARCHAR(10) | 郵便番号 |
| address | TEXT | 住所 |
| building_type | ENUM | 建物種別 |
| floor_number | INT | 階数 |
| room_type | ENUM | 部屋の種類 |
| room_size | ENUM | 部屋の広さ |
| ac_type | ENUM | エアコンの種類 |
| ac_capacity | ENUM | エアコンの能力 |
| existing_ac | ENUM | 既設エアコンの有無 |
| existing_ac_removal | ENUM | 既設エアコンの撤去 |
| electrical_work | ENUM | 電気工事 |
| piping_work | ENUM | 配管工事 |
| wall_drilling | ENUM | 壁穴あけ工事 |
| preferred_date | DATE | 希望日 |
| preferred_time | ENUM | 希望時間帯 |
| special_requests | TEXT | 特記事項 |
| created_at | TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | 更新日時 |

## 技術仕様

- **PHP**: 8.1
- **MySQL**: 8.0
- **Webサーバー**: Apache
- **フレームワーク**: 純粋なPHP（フレームワーク不使用）
- **CSS**: レスポンシブデザイン

## 開発

### ローカル開発環境
```bash
# コンテナの起動
docker-compose up -d

# ログの確認
docker-compose logs -f

# コンテナの停止
docker-compose down
```

### データベースアクセス
```bash
# MySQLコンテナに接続
docker-compose exec db mysql -u root -p air_conditioner_db
```# airconditioner-installation-inquiry-form
