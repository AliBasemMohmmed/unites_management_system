<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['college_id']) || !is_numeric($_GET['college_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'معرف الكلية غير صحيح'
    ]);
    exit;
}

$collegeId = (int)$_GET['college_id'];

try {
    // جلب المستخدمين المتاحين كرؤساء وحدات
    // استثناء المستخدمين الذين تم تعيينهم بالفعل كرؤساء وحدات
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            u.full_name
        FROM users u
        WHERE u.role_id = 2 
        AND u.college_id = ?
        AND u.id NOT IN (
            SELECT user_id 
            FROM units 
            WHERE user_id IS NOT NULL
            AND college_id = ?
        )
        ORDER BY u.full_name
    ");
    
    $stmt->execute([$collegeId, $collegeId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo json_encode([
            'success' => true,
            'message' => 'لا يوجد رؤساء وحدات متاحين في هذه الكلية',
            'data' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    }

} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات المستخدمين: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب البيانات'
    ]);
} 