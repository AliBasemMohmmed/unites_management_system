<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من وجود البيانات المطلوبة
    $requiredFields = ['id', 'document_id', 'title', 'content'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            header("Location: edit_document.php?id=" . $_POST['id'] . "&error=missing_fields");
            exit;
        }
    }

    $id = $_POST['id'];
    $documentId = $_POST['document_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    // التحقق من تفرد معرف الكتاب
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE document_id = ? AND id != ?");
    $checkStmt->execute([$documentId, $id]);
    if ($checkStmt->fetchColumn() > 0) {
        header("Location: edit_document.php?id=$id&error=duplicate_id");
        exit;
    }

    // جلب معلومات الملف الحالي
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $currentDocument = $stmt->fetch(PDO::FETCH_ASSOC);
    $filePath = $currentDocument['file_path'];

    // معالجة الملف المرفق الجديد إذا تم تحميله
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // حذف الملف القديم إذا وجد
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }

        $fileName = uniqid() . '_' . basename($_FILES['document_file']['name']);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
            header("Location: edit_document.php?id=$id&error=file_upload");
            exit;
        }
    }

    try {
        global $pdo;
        $pdo->beginTransaction();

        // تحديث الوثيقة
        $stmt = $pdo->prepare("
            UPDATE documents 
            SET document_id = ?, title = ?, content = ?, file_path = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $documentId,
            $title,
            $content,
            $filePath,
            $id
        ]);

        // إضافة سجل في تاريخ الكتاب
        $historyStmt = $pdo->prepare("
            INSERT INTO document_history (
                document_id, user_id, action, notes
            ) VALUES (?, ?, 'edit', 'تم تعديل الكتاب')
        ");
        $historyStmt->execute([$id, $_SESSION['user_id']]);

        $pdo->commit();
        header("Location: documents.php?success=edit");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        // إذا تم تحميل ملف جديد وحدث خطأ، نقوم بحذفه
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK && file_exists($filePath)) {
            unlink($filePath);
        }
        header("Location: edit_document.php?id=$id&error=1");
        exit;
    }
}

// إذا لم تكن الطريقة POST
header('Location: documents.php');
exit; 