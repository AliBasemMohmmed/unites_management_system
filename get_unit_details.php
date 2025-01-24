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
            un.name as university_name,
            c.name as college_name,
            d.name as division_name
        FROM units u
        LEFT JOIN universities un ON u.university_id = un.id
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN university_divisions d ON u.division_id = d.id
        WHERE u.id = ?
    ");
    $stmt->execute([$unitId]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الوحدة غير موجودة']);
        exit;
    }

    // جلب قائمة الجامعات
    $universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // جلب الكليات التابعة للجامعة
    $stmt = $pdo->prepare("SELECT id, name FROM colleges WHERE university_id = ? ORDER BY name");
    $stmt->execute([$unit['university_id']]);
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب الشعب التابعة للجامعة
    $stmt = $pdo->prepare("SELECT id, name FROM university_divisions WHERE university_id = ? ORDER BY name");
    $stmt->execute([$unit['university_id']]);
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تجهيز البيانات للإرجاع
    $response = [
        'success' => true,
        'id' => $unit['id'],
        'name' => $unit['name'],
        'description' => $unit['description'],
        'university_id' => $unit['university_id'],
        'college_id' => $unit['college_id'],
        'division_id' => $unit['division_id'],
        'is_active' => $unit['is_active'],
        'universities' => $universities,
        'colleges' => $colleges,
        'divisions' => $divisions
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات الوحدة: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء جلب البيانات']);
} 