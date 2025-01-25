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
    $collegeId = isset($_POST['college_id']) ? (int)$_POST['college_id'] : 0;
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (empty($name)) {
        throw new Exception('اسم الوحدة مطلوب');
    }

    if ($collegeId <= 0) {
        throw new Exception('الكلية مطلوبة');
    }

    if ($userId <= 0) {
        throw new Exception('رئيس الوحدة مطلوب');
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO units (
                name, 
                college_id,
                user_id,
                description,
                is_active,
                created_at,
                created_by
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $name,
            $collegeId,
            $userId,
            $description,
            $isActive,
            $_SESSION['user_id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تمت إضافة الوحدة بنجاح'
        ]);
    } 
    elseif ($action === 'edit') {
        if (!isset($_POST['id'])) {
            throw new Exception('معرف الوحدة مطلوب');
        }

        $unitId = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("
            UPDATE units 
            SET name = ?, 
                college_id = ?,
                user_id = ?,
                description = ?,
                is_active = ?,
                updated_at = NOW(),
                updated_by = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name,
            $collegeId,
            $userId,
            $description,
            $isActive,
            $_SESSION['user_id'],
            $unitId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('لم يتم العثور على الوحدة أو لم يتم إجراء أي تغييرات');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الوحدة بنجاح'
        ]);
    } 
    else {
        throw new Exception('إجراء غير صالح');
    }

} catch (Exception $e) {
    error_log("خطأ في معالجة الوحدة: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
