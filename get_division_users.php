<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['university_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف الجامعة مطلوب']);
    exit;
}

$universityId = $_GET['university_id'];

try {
    // جلب المستخدمين من نوع شعبة التابعين للجامعة المحددة
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name 
        FROM users u
        WHERE u.role = 'division'
        AND u.university_id = :university_id
        ORDER BY u.full_name
    ");
    
    $stmt->execute(['university_id' => $universityId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        // إذا لم نجد مستخدمين، نعيد قائمة فارغة
        echo json_encode([]);
    } else {
        echo json_encode($users);
    }
} catch (PDOException $e) {
    error_log("خطأ في جلب المستخدمين: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
} 