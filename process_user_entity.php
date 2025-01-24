<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    die('غير مصرح لك بإدارة انتماءات المستخدمين');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;

if (!$userId) {
    die('معرف المستخدم مطلوب');
}

try {
    switch ($action) {
        case 'add':
            $entityType = $_POST['entity_type'];
            $entityId = $_POST['entity_id'];
            $role = $_POST['role'] ?? 'employee'; // القيمة الافتراضية هي موظف
            $isPrimary = isset($_POST['is_primary']);
            
            // التحقق من عدم وجود رئيس آخر للجهة إذا كان الدور المطلوب هو رئيس
            if ($role === 'head') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM user_entities 
                    WHERE entity_type = ? 
                    AND entity_id = ? 
                    AND role = 'head'
                    AND user_id != ?
                ");
                $stmt->execute([$entityType, $entityId, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('يوجد رئيس آخر لهذه الجهة بالفعل');
                }
            }
            
            // إضافة الانتماء مع الدور
            $stmt = $pdo->prepare("
                INSERT INTO user_entities (user_id, entity_type, entity_id, role, is_primary)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE role = ?, is_primary = ?
            ");
            
            if ($stmt->execute([$userId, $entityType, $entityId, $role, $isPrimary, $role, $isPrimary])) {
                // إذا كان الدور هو رئيس، قم بتحديث دور المستخدم في جدول المستخدمين
                if ($role === 'head') {
                    $userRole = '';
                    switch ($entityType) {
                        case 'unit':
                            $userRole = 'unit_head';
                            break;
                        case 'division':
                            $userRole = 'division_head';
                            break;
                        case 'ministry':
                            $userRole = 'department_head';
                            break;
                    }
                    if ($userRole) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$userRole, $userId]);
                    }
                }
                $_SESSION['success'] = 'تم إضافة الانتماء والدور بنجاح';
            }
            break;
            
        case 'remove':
            $entityType = $_GET['entity_type'];
            $entityId = $_GET['entity_id'];
            
            // التحقق مما إذا كان المستخدم رئيساً قبل الحذف
            $stmt = $pdo->prepare("
                SELECT role 
                FROM user_entities 
                WHERE user_id = ? 
                AND entity_type = ? 
                AND entity_id = ?
            ");
            $stmt->execute([$userId, $entityType, $entityId]);
            $currentRole = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                DELETE FROM user_entities 
                WHERE user_id = ? AND entity_type = ? AND entity_id = ?
            ");
            
            if ($stmt->execute([$userId, $entityType, $entityId])) {
                // إذا كان رئيساً، قم بإعادة تعيين دوره في جدول المستخدمين إلى موظف عادي
                if ($currentRole === 'head') {
                    $stmt = $pdo->prepare("UPDATE users SET role = 'employee' WHERE id = ?");
                    $stmt->execute([$userId]);
                }
                $_SESSION['success'] = 'تم إزالة الانتماء بنجاح';
            }
            break;
            
        case 'update_role':
            $entityType = $_POST['entity_type'];
            $entityId = $_POST['entity_id'];
            $newRole = $_POST['role'];
            
            // التحقق من عدم وجود رئيس آخر للجهة
            if ($newRole === 'head') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM user_entities 
                    WHERE entity_type = ? 
                    AND entity_id = ? 
                    AND role = 'head'
                    AND user_id != ?
                ");
                $stmt->execute([$entityType, $entityId, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('يوجد رئيس آخر لهذه الجهة بالفعل');
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_entities 
                SET role = ?
                WHERE user_id = ? AND entity_type = ? AND entity_id = ?
            ");
            
            if ($stmt->execute([$newRole, $userId, $entityType, $entityId])) {
                // تحديث دور المستخدم في جدول المستخدمين
                if ($newRole === 'head') {
                    $userRole = '';
                    switch ($entityType) {
                        case 'unit':
                            $userRole = 'unit_head';
                            break;
                        case 'division':
                            $userRole = 'division_head';
                            break;
                        case 'ministry':
                            $userRole = 'department_head';
                            break;
                    }
                    if ($userRole) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$userRole, $userId]);
                    }
                }
                $_SESSION['success'] = 'تم تحديث الدور بنجاح';
            }
            break;
            
        default:
            die('إجراء غير صالح');
    }
    
    // إعادة تحميل معلومات الجهة في الجلسة إذا كان المستخدم الحالي
    if ($userId == $_SESSION['user_id']) {
        setUserEntityInfo($userId);
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
}

header("Location: manage_user_entities.php?user_id=$userId");
exit;
?> 