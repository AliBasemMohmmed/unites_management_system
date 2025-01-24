<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if (!hasPermission('process_document')) {
  die('غير مصرح لك بمعالجة الكتب');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $documentId = $_POST['document_id'];
  $action = $_POST['action'];
  $notes = $_POST['notes'];
  
  try {
    $pdo->beginTransaction();
    
    // تحديث حالة الكتاب
    $status = '';
    switch ($action) {
      case 'receive':
        $status = 'received';
        break;
      case 'process':
        $status = 'processed';
        break;
      case 'forward':
        $status = 'forwarded';
        break;
      case 'reject':
        $status = 'rejected';
        break;
    }
    
    $updateDoc = $pdo->prepare("
      UPDATE documents 
      SET status = ?, 
          processor_id = ?,
          updated_at = NOW()
      WHERE id = ?
    ");
    $updateDoc->execute([$status, $_SESSION['user_id'], $documentId]);
    
    // إضافة سجل المتابعة
    $addHistory = $pdo->prepare("
      INSERT INTO document_history (document_id, user_id, action, notes)
      VALUES (?, ?, ?, ?)
    ");
    $addHistory->execute([$documentId, $_SESSION['user_id'], $action, $notes]);
    
    // إضافة إشعار
    $document = $pdo->prepare("SELECT * FROM documents WHERE id = ?")->execute([$documentId])->fetch();
    $notification = $pdo->prepare("
      INSERT INTO notifications (user_id, title, content, related_id, related_type)
      VALUES (?, ?, ?, ?, ?)
    ");
    
    // إشعار للمرسل
    $notification->execute([
      $document['sender_id'],
      'تحديث حالة الكتاب',
      "تم $action الكتاب: {$document['title']}",
      $documentId,
      'document'
    ]);
    
    $pdo->commit();
    
    header("Location: document_workflow.php?id=$documentId&success=1");
    exit;
  } catch (Exception $e) {
    $pdo->rollBack();
    die('حدث خطأ أثناء معالجة الكتاب: ' . $e->getMessage());
  }
}
?>
