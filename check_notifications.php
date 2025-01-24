<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $lastCheck = $data['lastCheck'] ?? date('Y-m-d H:i:s');
    
    // التحقق من وجود إشعارات جديدة
    $hasNew = checkNewNotifications($_SESSION['user_id'], $lastCheck);
    
    // جلب عدد الإشعارات غير المقروءة
    $count = getUnreadNotificationsCount($_SESSION['user_id']);
    
    echo json_encode([
        'hasNew' => $hasNew,
        'count' => $count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في التحقق من الإشعارات']);
}
?> 