<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

// التحقق من صلاحيات المستخدم
if (!hasPermission('view_colleges')) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية لعرض الكليات']);
    exit;
}

if (!isset($_GET['university_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف الجامعة مطلوب']);
    exit;
}

$universityId = $_GET['university_id'];

try {
    // جلب كليات الجامعة المحددة مع معلومات إضافية
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.created_at,
            c.updated_at,
            c.created_by,
            c.updated_by
        FROM colleges c
        WHERE c.university_id = :university_id 
        ORDER BY c.name
    ");
    
    $stmt->execute(['university_id' => $universityId]);
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($colleges);
} catch (PDOException $e) {
    error_log("خطأ في جلب الكليات: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
} 