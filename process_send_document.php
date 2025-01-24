<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: documents.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $documentId = $_POST['document_id'] ?? null;
    $sendType = $_POST['send_type'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // التحقق من وجود الكتاب فقط، بدون التحقق من الحالة
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception('الكتاب غير موجود');
    }

    // معالجة الإرسال حسب النوع
    switch ($sendType) {
        case 'single':
            // إرسال لجهة واحدة
            if (empty($_POST['receiver_type']) || empty($_POST['receiver_id'])) {
                throw new Exception('يجب تحديد المستلم');
            }

            $updateStmt = $pdo->prepare("
                UPDATE documents 
                SET receiver_type = ?,
                    receiver_id = ?,
                    status = 'sent',
                    send_date = NOW(),
                    send_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $_POST['receiver_type'],
                $_POST['receiver_id'],
                $notes,
                $documentId
            ]);
            break;

        case 'multiple':
        case 'broadcast':
            // إرسال لعدة جهات أو تعميم
            if (empty($_POST['receivers'])) {
                throw new Exception('يجب اختيار مستلم واحد على الأقل');
            }

            // تحديث الكتاب الأصلي
            $updateOriginal = $pdo->prepare("
                UPDATE documents 
                SET status = 'sent',
                    send_date = NOW(),
                    send_notes = ?,
                    is_broadcast = ?
                WHERE id = ?
            ");
            $updateOriginal->execute([
                $notes,
                ($sendType === 'broadcast' ? 1 : 0),
                $documentId
            ]);

            // إنشاء نسخ للمستلمين
            foreach ($_POST['receivers'] as $receiver) {
                list($receiverType, $receiverId) = explode(':', $receiver);
                
                $insertCopy = $pdo->prepare("
                    INSERT INTO document_copies (
                        original_id, receiver_type, receiver_id, 
                        title, content, file_path, status, 
                        created_at, send_date, send_notes
                    )
                    SELECT 
                        id, ?, ?, title, content, file_path,
                        'pending', created_at, NOW(), ?
                    FROM documents WHERE id = ?
                ");
                $insertCopy->execute([
                    $receiverType,
                    $receiverId,
                    $notes,
                    $documentId
                ]);

                // إضافة إشعار للمستلم
                addNotification(
                    $receiverId,
                    'كتاب جديد',
                    "تم استلام كتاب جديد: " . $document['title'],
                    $documentId,
                    'document'
                );
            }
            break;

        default:
            throw new Exception('نوع إرسال غير صالح');
    }

    // إضافة سجل في تاريخ الكتاب
    $addHistory = $pdo->prepare("
        INSERT INTO document_history (document_id, user_id, action, notes)
        VALUES (?, ?, 'send', ?)
    ");
    $addHistory->execute([$documentId, $_SESSION['user_id'], $notes]);

    $pdo->commit();
    header("Location: documents.php?success=sent");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('خطأ: ' . $e->getMessage());
}
?> 