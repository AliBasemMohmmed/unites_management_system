<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    // التحقق من الصلاحيات
    if (!hasPermission('archive_documents')) {
        throw new Exception('ليس لديك صلاحية لأرشفة الكتب');
    }

    // التحقق من وجود البيانات
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'])) {
        throw new Exception('معرف الكتاب مطلوب');
    }

    $documentId = $data['id'];

    // التحقق من وجود الكتاب
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception('الكتاب غير موجود');
    }

    // التحقق من أن الكتاب ليس مؤرشفاً بالفعل
    if ($document['status'] === 'archived') {
        throw new Exception('الكتاب مؤرشف بالفعل');
    }

    $pdo->beginTransaction();

    // تحديث حالة الكتاب
    $updateDoc = $pdo->prepare("
        UPDATE documents 
        SET status = 'archived',
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateDoc->execute([$documentId]);

    // إضافة سجل في تاريخ الكتاب
    $addHistory = $pdo->prepare("
        INSERT INTO document_history (document_id, user_id, action, notes)
        VALUES (?, ?, 'archive', 'تم أرشفة الكتاب')
    ");
    $addHistory->execute([$documentId, $_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'تم أرشفة الكتاب بنجاح'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 