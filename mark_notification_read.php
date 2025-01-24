<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'معرف الإشعار مطلوب'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND receiver_id = ?
    ");
    
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على الإشعار'
        ]);
    }
} catch (PDOException $e) {
    error_log("خطأ في تحديث الإشعار: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث الإشعار'
    ]);
}
?> 