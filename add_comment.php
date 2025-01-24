<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

try {
    // التحقق من الصلاحيات
    if (!hasPermission('add_comments')) {
        throw new Exception('ليس لديك صلاحية لإضافة تعليقات');
    }

    // التحقق من وجود البيانات
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['document_id']) || !isset($data['content'])) {
        throw new Exception('جميع الحقول مطلوبة');
    }

    $documentId = $data['document_id'];
    $content = trim($data['content']);

    if (empty($content)) {
        throw new Exception('محتوى التعليق مطلوب');
    }

    // التحقق من وجود الكتاب
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception('الكتاب غير موجود');
    }

    $pdo->beginTransaction();

    // إضافة التعليق
    $addComment = $pdo->prepare("
        INSERT INTO document_comments (document_id, user_id, content, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $addComment->execute([
        $documentId,
        $_SESSION['user_id'],
        $content
    ]);

    // تحديث وقت آخر تعديل للكتاب
    $updateDoc = $pdo->prepare("
        UPDATE documents 
        SET updated_at = NOW()
        WHERE id = ?
    ");
    $updateDoc->execute([$documentId]);

    // إضافة سجل في تاريخ الكتاب
    $addHistory = $pdo->prepare("
        INSERT INTO document_history (document_id, user_id, action, notes)
        VALUES (?, ?, 'comment', ?)
    ");
    $addHistory->execute([
        $documentId,
        $_SESSION['user_id'],
        'تمت إضافة تعليق: ' . mb_substr($content, 0, 50) . (mb_strlen($content) > 50 ? '...' : '')
    ]);

    $pdo->commit();

    sendJsonResponse([
        'success' => true,
        'message' => 'تم إضافة التعليق بنجاح'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
} 