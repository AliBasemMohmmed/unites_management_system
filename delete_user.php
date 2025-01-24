<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    if (!isset($_POST['user_id'])) {
        throw new Exception('معرف المستخدم مطلوب');
    }

    $userId = (int)$_POST['user_id'];

    $pdo->beginTransaction();

    // حذف المستخدم
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('لم يتم العثور على المستخدم');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف المستخدم بنجاح'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 