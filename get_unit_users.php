<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if (!isset($_GET['college_id'])) {
        throw new Exception('معرف الكلية مطلوب');
    }

    $collegeId = (int)$_GET['college_id'];

    // جلب المستخدمين الذين لديهم دور رئيس وحدة (role_id = 2) في نفس الكلية
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.full_name
        FROM users u
        WHERE u.college_id = ?
        AND u.role_id = 2
        ORDER BY u.full_name
    ");
    
    $stmt->execute([$collegeId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // طباعة البيانات للتحقق
    error_log("College ID: " . $collegeId);
    error_log("Number of users found: " . count($users));
    foreach ($users as $user) {
        error_log("User ID: " . $user['id'] . ", Name: " . $user['full_name']);
    }
    
    if (empty($users)) {
        echo json_encode([
            'success' => false,
            'data' => [],
            'message' => 'لا يوجد رؤساء وحدات متاحين في هذه الكلية'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $users
    ]);

} catch (Exception $e) {
    error_log("خطأ في جلب رؤساء الوحدات: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
} 