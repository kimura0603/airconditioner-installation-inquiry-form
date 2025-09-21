FROM php:8.1-apache

# PDO MySQL拡張をインストール
RUN docker-php-ext-install pdo pdo_mysql

# mod_rewriteを有効化
RUN a2enmod rewrite

# 設定ファイルをコピー
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# 作業ディレクトリを設定
WORKDIR /var/www/html