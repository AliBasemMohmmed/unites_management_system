<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['college_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف الكلية مطلوب']);
    exit;
}

try {
    // جلب المستخدمين من نفس الكلية
    $stmt = $pdo->prepare("
        SELECT id, full_name 
        FROM users 
        WHERE role = 'unit'
        AND college_id = ?
        ORDER BY full_name
    ");
    
    $stmt->execute([$_GET['college_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
} catch (PDOException $e) {
    error_log("خطأ في جلب المستخدمين: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
} 