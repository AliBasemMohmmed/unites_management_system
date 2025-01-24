<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // قراءة البيانات من الطلب
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'معرف الوثيقة مطلوب'], 400);
    }

    try {
        global $pdo;
        $pdo->beginTransaction();

        // جلب معلومات الملف قبل الحذف
        $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            throw new Exception('الوثيقة غير موجودة');
        }

        // حذف الوثيقة
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);

        // حذف الملف المرفق إذا وجد
        if ($document['file_path'] && file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }

        $pdo->commit();
        sendJsonResponse(['success' => true, 'message' => 'تم حذف الوثيقة بنجاح']);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// إذا لم تكن الطريقة POST
sendJsonResponse(['success' => false, 'message' => 'طريقة طلب غير صحيحة'], 405); 