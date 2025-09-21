# エアコン工事申し込みフォーム - デプロイメント手順

このドキュメントでは、エアコン工事申し込みフォームシステムをVPSやレンタルサーバーにデプロイする手順を説明します。

## システム概要

**最新機能（v2.0）:**
- 📅 **高度な予約システム**: 日時枠管理、空き状況の自動制御
- 🎯 **直感的UI**: Flatpickrカレンダー、モーダル時間選択
- 🛡️ **管理画面統合**: セキュアな admin 配下での一元管理
- 📊 **ダッシュボード**: 統計表示、クイックアクション
- 🔄 **予約管理**: ワンクリック確定・キャンセル、カレンダー表示

## 目次
1. [システム構成](#システム構成)
2. [VPSでのデプロイ（Dockerを使用）](#vpsでのデプロイdockerを使用)
3. [VPSでのデプロイ（手動設定）](#vpsでのデプロイ手動設定)
4. [レンタルサーバーでのデプロイ](#レンタルサーバーでのデプロイ)
5. [セキュリティ設定](#セキュリティ設定)
6. [バックアップとメンテナンス](#バックアップとメンテナンス)
7. [運用ガイド](#運用ガイド)

---

## システム構成

### ディレクトリ構造
```
src/
├── admin/                      # 管理画面（要認証）
│   ├── index.php              # ダッシュボード
│   ├── reservations.php       # 予約管理
│   ├── application_detail.php # 申し込み詳細表示
│   └── .htaccess              # セキュリティ設定
├── models/                     # データモデル
│   ├── Application.php        # 申し込み管理
│   ├── ReservationSlot.php    # 予約枠管理
│   └── ApplicationPreferredSlot.php
├── config/
│   └── database.php           # DB接続設定
├── index.php                  # 顧客用申し込みフォーム
├── process_form.php           # 申し込み処理
├── get_available_slots.php    # 空き状況API
└── styles.css                 # スタイルシート
```

### データベース構成
- `applications` - 申し込み情報
- `reservation_slots` - 各日時の予約枠管理
- `application_preferred_slots` - 顧客希望日時（最大3つ）
- `time_slots` - 時間枠マスター（午前・午後・夕方）
- `reservation_confirmations` - 予約確定履歴

### アクセスURL
- **顧客用**: `https://your-domain.com/`
- **管理用**: `https://your-domain.com/admin/`

---

## VPSでのデプロイ（Dockerを使用）

### 前提条件
- Ubuntu 20.04+ または CentOS 7+
- Docker & Docker Compose がインストール済み
- ドメインまたは固定IPアドレス

### 1. サーバーセットアップ

```bash
# サーバーに接続
ssh root@your-server-ip

# システムアップデート
sudo apt update && sudo apt upgrade -y

# 必要なパッケージのインストール
sudo apt install git curl wget -y
```

### 2. Docker & Docker Composeのインストール

```bash
# Dockerのインストール
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Docker Composeのインストール
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Dockerサービス開始
sudo systemctl start docker
sudo systemctl enable docker
```

### 3. アプリケーションのデプロイ

```bash
# アプリケーションディレクトリの作成
sudo mkdir -p /var/www/air-conditioner
cd /var/www/air-conditioner

# ファイルのアップロード（方法1: Gitから）
git clone <your-repository-url> .

# または ファイルのアップロード（方法2: SCPで直接転送）
# ローカルから: scp -r ./air-conditionner/* root@your-server-ip:/var/www/air-conditioner/
```

### 4. 本番環境用設定の調整

```bash
# docker-compose.prod.yml の作成
cat > docker-compose.prod.yml << 'EOF'
version: '3.8'

services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
    volumes:
      - ./src:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=air_conditioner_db
      - DB_USER=app_user
      - DB_PASSWORD=your_secure_password_here
    restart: unless-stopped

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: your_root_password_here
      MYSQL_DATABASE: air_conditioner_db
      MYSQL_USER: app_user
      MYSQL_PASSWORD: your_secure_password_here
    ports:
      - "127.0.0.1:3306:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./sql:/docker-entrypoint-initdb.d
      - ./backup:/backup
    restart: unless-stopped

volumes:
  db_data:
EOF

# 強力なパスワードの生成
openssl rand -base64 32  # これをMYSQL_ROOT_PASSWORDに使用
openssl rand -base64 32  # これをMYSQL_PASSWORDに使用
```

### 5. アプリケーションの起動

```bash
# 本番環境での起動
sudo docker-compose -f docker-compose.prod.yml up -d

# ログの確認
sudo docker-compose -f docker-compose.prod.yml logs -f
```

### 6. SSL証明書の設定（Let's Encrypt）

```bash
# Certbotのインストール
sudo apt install certbot -y

# Nginxリバースプロキシの設定
sudo apt install nginx -y

# Nginx設定ファイル
sudo cat > /etc/nginx/sites-available/air-conditioner << 'EOF'
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

# サイトの有効化
sudo ln -s /etc/nginx/sites-available/air-conditioner /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

# SSL証明書の取得
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

---

## VPSでのデプロイ（手動設定）

### 1. LAMP環境のセットアップ

```bash
# Apache、MySQL、PHPのインストール
sudo apt update
sudo apt install apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring -y

# Apache設定
sudo a2enmod rewrite
sudo systemctl start apache2
sudo systemctl enable apache2

# MySQL設定
sudo mysql_secure_installation
```

### 2. データベースセットアップ

```bash
# MySQLにログイン
sudo mysql -u root -p

# データベースとユーザーの作成
CREATE DATABASE air_conditioner_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON air_conditioner_db.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# テーブルの作成
mysql -u app_user -p air_conditioner_db < sql/init.sql
```

### 3. アプリケーションファイルの配置

```bash
# Webディレクトリにファイルをコピー
sudo cp -r src/* /var/www/html/
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
```

### 4. 設定ファイルの調整

```bash
# データベース設定の更新
sudo nano /var/www/html/config/database.php

# 以下のように変更:
# $this->host = 'localhost';
# $this->username = 'app_user';
# $this->password = 'your_secure_password';
```

---

## レンタルサーバーでのデプロイ

### 対応サーバー例
- さくらのレンタルサーバー
- エックスサーバー
- ロリポップ
- ConoHa WING

### 1. 事前確認

**必要な要件:**
- PHP 8.1以上
- MySQL 5.7以上
- PDO MySQL拡張
- .htaccessサポート

### 2. ファイルアップロード

**FTP/SFTPでのアップロード:**
```bash
# ローカルからsrcフォルダの内容をpublic_htmlにアップロード
# 構造例:
public_html/
├── index.php
├── process_form.php
├── admin.php
├── styles.css
├── config/
│   └── database.php
└── models/
    └── Application.php
```

### 3. データベース設定

**コントロールパネルから:**
1. MySQLデータベースを作成
2. データベースユーザーを作成
3. sql/init.sqlの内容をphpMyAdminで実行

**database.php の調整:**
```php
<?php
class Database {
    private $host = 'mysql-server-host';  // サーバー提供のホスト名
    private $db_name = 'database_name';   // 作成したDB名
    private $username = 'db_username';    // DBユーザー名
    private $password = 'db_password';    // DBパスワード
    // ...
}
?>
```

### 4. .htaccess設定

```bash
# public_html/.htaccess
RewriteEngine On

# HTTPS強制リダイレクト（SSL対応サーバーの場合）
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# セキュリティヘッダー
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# 設定ファイルへのアクセス禁止
<Files "*.conf">
    Order allow,deny
    Deny from all
</Files>

# ログファイルへのアクセス禁止
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

---

## セキュリティ設定

### 1. 基本的なセキュリティ対策

**データベース:**
```sql
-- 強力なパスワードの設定
ALTER USER 'app_user'@'localhost' IDENTIFIED BY 'very_strong_password_123!@#';

-- 不要な権限の削除
REVOKE ALL PRIVILEGES ON *.* FROM 'app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON air_conditioner_db.* TO 'app_user'@'localhost';
```

**ファイル権限:**
```bash
# 適切なファイル権限の設定
find /var/www/html -type f -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;
chmod 600 /var/www/html/config/database.php
```

### 2. 管理画面の保護

**Basic認証の追加:**
```bash
# .htpasswd ファイルの作成
sudo htpasswd -c /var/www/.htpasswd admin

# admin.php 保護用の .htaccess
cat > /var/www/html/.htaccess_admin << 'EOF'
<Files "admin.php">
    AuthType Basic
    AuthName "管理画面"
    AuthUserFile /var/www/.htpasswd
    Require valid-user
</Files>
EOF
```

### 3. ファイアウォール設定（VPS）

```bash
# UFWの設定
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## バックアップとメンテナンス

### 1. 自動バックアップスクリプト

```bash
# backup.sh の作成
cat > /home/backup.sh << 'EOF'
#!/bin/bash

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup"
DB_NAME="air_conditioner_db"
DB_USER="app_user"
DB_PASS="your_password"

# ディレクトリ作成
mkdir -p $BACKUP_DIR

# データベースバックアップ
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# ファイルバックアップ
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html

# 古いバックアップの削除（30日以上経過）
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /home/backup.sh

# Cronジョブの設定（毎日午前2時に実行）
echo "0 2 * * * /home/backup.sh >> /var/log/backup.log 2>&1" | sudo crontab -
```

### 2. ログローテーション

```bash
# ログローテーション設定
sudo cat > /etc/logrotate.d/air-conditioner << 'EOF'
/var/log/air-conditioner/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF
```

### 3. モニタリング

**基本的なヘルスチェック:**
```bash
# health_check.sh
cat > /home/health_check.sh << 'EOF'
#!/bin/bash

# Webサーバーの確認
if curl -f http://localhost/ > /dev/null 2>&1; then
    echo "$(date): Web server OK"
else
    echo "$(date): Web server DOWN" | mail -s "Alert: Web server down" admin@example.com
    sudo systemctl restart apache2
fi

# データベースの確認
if mysql -u app_user -pyour_password -e "SELECT 1" air_conditioner_db > /dev/null 2>&1; then
    echo "$(date): Database OK"
else
    echo "$(date): Database DOWN" | mail -s "Alert: Database down" admin@example.com
fi
EOF

chmod +x /home/health_check.sh

# 5分ごとに実行
echo "*/5 * * * * /home/health_check.sh >> /var/log/health_check.log" | crontab -
```

---

## トラブルシューティング

### よくある問題と解決方法

**1. データベース接続エラー**
```bash
# ログの確認
sudo tail -f /var/log/mysql/error.log
sudo tail -f /var/log/apache2/error.log

# 接続テスト
mysql -u app_user -p air_conditioner_db
```

**2. ファイルの権限エラー**
```bash
# 権限の再設定
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
```

**3. SSL証明書エラー**
```bash
# 証明書の更新
sudo certbot renew --dry-run
sudo certbot renew
```

**4. 表示が崩れる場合**
- ブラウザのキャッシュをクリア
- CSS/JSファイルのパスを確認
- .htaccessの設定を確認

---

## セキュリティチェックリスト

- [ ] データベースのパスワードを強力なものに変更
- [ ] 管理画面にBasic認証を設定
- [ ] SSL証明書を導入
- [ ] ファイル権限を適切に設定
- [ ] 不要なファイルを削除
- [ ] バックアップスクリプトを設定
- [ ] ログローテーションを設定
- [ ] ファイアウォールを設定
- [ ] 定期的なセキュリティアップデート
- [ ] 監視・アラート機能を設定

このドキュメントに従って設定することで、安全で安定したエアコン工事申し込みフォームシステムを運用できます。