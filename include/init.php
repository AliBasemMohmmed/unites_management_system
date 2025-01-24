<?php
session_start();

// تضمين الملفات الأساسية
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// إعداد معالج الأخطاء
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// التحقق من وضع الصيانة
if ($config['maintenance_mode'] && !isAdmin()) {
    die('النظام في وضع الصيانة. يرجى المحاولة لاحقاً.');
}

// إعداد اتصال قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

// تعيين الترميز
mb_internal_encoding('UTF-8');
?>
