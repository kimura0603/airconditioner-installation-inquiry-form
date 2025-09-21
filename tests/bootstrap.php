<?php

require_once __DIR__ . '/../vendor/autoload.php';

// テスト環境用の設定
define('TEST_MODE', true);

// マルチバイト文字列エンコーディング設定
mb_internal_encoding('UTF-8');

// エラー報告設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');