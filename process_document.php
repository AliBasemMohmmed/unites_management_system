<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من وجود البيانات المطلوبة
    $requiredFields = ['document_id', 'title', 'content'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            header("Location: create_document.php?error=missing_fields");
            exit;
        }
    }

    // التحقق من تفرد معرف الكتاب
    $documentId = $_POST['document_id'];
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE document_id = ?");
    $checkStmt->execute([$documentId]);
    if ($checkStmt->fetchColumn() > 0) {
        header("Location: create_document.php?error=duplicate_id");
        exit;
    }

    // أخذ معلومات المرسل من الجلسة
    $senderType = $_SESSION['entity_type'] ?? 'unit'; // القيمة الافتراضية 'unit'
    $senderId = $_SESSION['entity_id'] ?? $_SESSION['user_id'];

    $title = $_POST['title'];
    $content = $_POST['content'];
    
    // معالجة الملف المرفق
    $filePath = null;
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['document_file']['name']);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
            header("Location: create_document.php?error=file_upload");
            exit;
        }
    }
    
    try {
        global $pdo;
        $pdo->beginTransaction();

        // إدخال الكتاب كمسودة
        $stmt = $pdo->prepare("
            INSERT INTO documents (
                document_id, title, content, sender_type, sender_id,
                file_path, status, created_at, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, 'draft', NOW(), ?
            )
        ");
        
        $stmt->execute([
            $documentId,
            $title,
            $content,
            $senderType,
            $senderId,
            $filePath,
            $_SESSION['user_id']
        ]);

        $insertedId = $pdo->lastInsertId();

        // إضافة سجل في تاريخ الكتاب
        $historyStmt = $pdo->prepare("
            INSERT INTO document_history (
                document_id, user_id, action, notes
            ) VALUES (?, ?, 'create', 'تم إنشاء الكتاب')
        ");
        $historyStmt->execute([$insertedId, $_SESSION['user_id']]);

        $pdo->commit();
        
        // توجيه المستخدم إلى صفحة إرسال الكتاب مباشرة بدون إرجاع بيانات
        header("Location: send_document.php?id=" . $insertedId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
        // تعديل رسالة الخطأ لتكون أكثر عمومية
        header("Location: documents.php?error=1");
        exit;
    }
}

// إذا لم تكن الطريقة POST
header('Location: documents.php');
exit;
?>
