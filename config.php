<?php
// إعدادات قاعدة البيانات
$db_host = 'localhost';     // عنوان خادم قاعدة البيانات
$db_name = 'units_management_system';    // اسم قاعدة البيانات
$db_user = 'root';         // اسم المستخدم
$db_pass = '';             // كلمة المرور

// إعدادات النظام
define('SITE_NAME', 'نظام إدارة التعليم العالي');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 ميجابايت

// إعدادات الجلسة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// المنطقة الزمنية
date_default_timezone_set('Asia/Baghdad');

// تكوين عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ترميز UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

try {
    $pdo = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("خطأ في الاتصال بقاعدة البيانات");
}
?>
