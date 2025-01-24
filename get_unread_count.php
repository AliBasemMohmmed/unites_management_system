<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
} catch (PDOException $e) {
    error_log("خطأ في جلب عدد الإشعارات: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب عدد الإشعارات'
    ]);
} 