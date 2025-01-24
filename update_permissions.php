<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من أن المستخدم مدير
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بهذا الإجراء']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['user_id'];
        $permissions = $_POST['permissions'] ?? [];
        
        // حذف الصلاحيات الحالية
        $stmt = $pdo->prepare("DELETE FROM permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // إضافة الصلاحيات الجديدة
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO permissions (user_id, permission_name) VALUES (?, ?)");
            foreach ($permissions as $permission) {
                $stmt->execute([$userId, $permission]);
            }
        }
        
        $pdo->commit();
        
        // تسجيل النشاط
        logSystemActivity("تم تحديث صلاحيات المستخدم #$userId", 'permissions', $_SESSION['user_id']);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} 