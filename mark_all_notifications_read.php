<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE receiver_id = ? 
        AND is_read = 0
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديد جميع الإشعارات كمقروءة'
    ]);
} catch (PDOException $e) {
    error_log("خطأ في تحديث الإشعارات: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث الإشعارات'
    ]);
} 