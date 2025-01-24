<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('معرف الكلية مطلوب');
    }

    $collegeId = (int)$_GET['id'];
    
    // التحقق من وجود الكلية
    $stmt = $pdo->prepare("SELECT * FROM colleges WHERE id = ?");
    $stmt->execute([$collegeId]);
    $college = $stmt->fetch();
    
    if (!$college) {
        throw new Exception('الكلية غير موجودة');
    }

    // حذف الكلية
    $stmt = $pdo->prepare("DELETE FROM colleges WHERE id = ?");
    $stmt->execute([$collegeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('فشل في حذف الكلية');
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف الكلية بنجاح'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 