<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المستخدم مطلوب']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COALESCE(un.name, '') as university_name,
               COALESCE(c.name, '') as college_name
        FROM users u
        LEFT JOIN universities un ON u.university_id = un.id
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE u.id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // حذف كلمة المرور من البيانات المرسلة
    unset($user['password']);
    
    echo json_encode($user);
} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات المستخدم: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
} 