<?php
require_once 'config.php';
session_start();

// تسجيل عملية تسجيل الخروج في سجل النشاطات
if (isset($_SESSION['user_id'])) {
    try {
        $logLogout = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, details) 
            VALUES (?, 'logout', 'user', ?)
        ");
        $logLogout->execute([
            $_SESSION['user_id'],
            json_encode([
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ])
        ]);
    } catch (PDOException $e) {
        // تجاهل أي أخطاء في تسجيل النشاط
    }
}

// حذف كل متغيرات الجلسة
$_SESSION = array();

// حذف كوكيز الجلسة
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header('Location: login.php');
exit; 