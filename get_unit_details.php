<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الوحدة غير صحيح']);
    exit;
}

$unitId = (int)$_GET['id'];

try {
    // جلب بيانات الوحدة
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            c.name as college_name,
            m.full_name as manager_name
        FROM units u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN users m ON u.user_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$unitId]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الوحدة غير موجودة']);
        exit;
    }

    error_log("تم جلب بيانات الوحدة: " . print_r($unit, true));

    // جلب قائمة الكليات
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM colleges 
        ORDER BY name
    ");
    $stmt->execute();
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("تم جلب الكليات: " . print_r($colleges, true));

    // جلب قائمة المستخدمين المتاحين كرؤساء للوحدة
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name
        FROM users u
        WHERE u.role_id = 2
        AND (u.college_id = ? OR u.id = ?)
        ORDER BY u.full_name
    ");
    $stmt->execute([$unit['college_id'], $unit['user_id']]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("تم جلب رؤساء الوحدات: " . print_r($managers, true));

    // تجهيز البيانات للإرجاع
    $response = [
        'success' => true,
        'id' => $unit['id'],
        'name' => $unit['name'],
        'description' => $unit['description'],
        'college_id' => $unit['college_id'],
        'user_id' => $unit['user_id'],
        'is_active' => $unit['is_active'],
        'colleges' => $colleges,
        'managers' => $managers,
        'college_name' => $unit['college_name'],
        'manager_name' => $unit['manager_name']
    ];

    error_log("البيانات النهائية: " . print_r($response, true));

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات الوحدة: " . $e->getMessage());
    error_log("تتبع الخطأ: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء جلب البيانات']);
} 