<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('معرف الوثيقة مطلوب');
    }

    // التحقق من الصلاحيات
    if (!hasPermission('view_documents')) {
        throw new Exception('ليس لديك صلاحية لعرض الوثائق');
    }

    // جلب بيانات الوثيقة
    $stmt = $pdo->prepare("SELECT d.*, 
        CASE 
            WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
            WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
            WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
            WHEN d.sender_type = 'user' THEN (SELECT full_name FROM users WHERE id = d.sender_id)
        END as sender_name,
        CASE 
            WHEN d.receiver_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.receiver_id)
            WHEN d.receiver_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.receiver_id)
            WHEN d.receiver_type = 'unit' THEN (SELECT name FROM units WHERE id = d.receiver_id)
        END as receiver_name
        FROM documents d
        WHERE d.id = ?");
    
    $stmt->execute([$id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception('الوثيقة غير موجودة');
    }

    // جلب المرفقات
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE document_id = ?");
    $stmt->execute([$id]);
    $document['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse([
        'success' => true,
        'data' => $document
    ]);

} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}