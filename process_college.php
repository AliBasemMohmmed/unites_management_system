<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        throw new Exception('اسم الكلية مطلوب');
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO colleges (name, created_at, created_by) 
            VALUES (?, NOW(), ?)
        ");
        
        $stmt->execute([$name, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تمت إضافة الكلية بنجاح'
        ]);
    } 
    elseif ($action === 'edit') {
        if (!isset($_POST['id'])) {
            throw new Exception('معرف الكلية مطلوب');
        }

        $collegeId = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("
            UPDATE colleges 
            SET name = ?, 
                updated_by = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$name, $_SESSION['user_id'], $collegeId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('لم يتم العثور على الكلية أو لم يتم إجراء أي تغييرات');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الكلية بنجاح'
        ]);
    } 
    else {
        throw new Exception('إجراء غير صالح');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 