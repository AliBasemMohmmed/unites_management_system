<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

// التحقق من الصلاحيات
if (!hasPermission('delete_unit')) {
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح لك بحذف الوحدات'
    ]);
    exit;
}

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('معرف الوحدة غير صحيح');
    }

    $unitId = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
    $stmt->execute([$unitId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('لم يتم العثور على الوحدة');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم حذف الوحدة بنجاح'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 