<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    global $pdo;
    
    // جلب آخر 10 إشعارات للمستخدم
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($notifications);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في جلب الإشعارات']);
}
?> 