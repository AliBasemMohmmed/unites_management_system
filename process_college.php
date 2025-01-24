<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
$userRole = $_SESSION['user_role'];
$userEntityType = $_SESSION['entity_type'] ?? null;
$userEntityId = $_SESSION['entity_id'] ?? null;

if ($userRole !== 'admin' && $userEntityType !== 'division') {
    die('غير مصرح لك بإدارة الكليات');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (empty($_POST['name']) || empty($_POST['university_id'])) {
            throw new Exception('جميع الحقول مطلوبة');
        }

        $name = trim($_POST['name']);
        $universityId = $_POST['university_id'];

        // التحقق من صلاحية الوصول للجامعة
        if ($userRole !== 'admin') {
            // التحقق من الانتماء الرئيسي للمستخدم
            $stmt = $pdo->prepare("
                SELECT ue.entity_id 
                FROM user_entities ue 
                INNER JOIN university_divisions ud ON ue.entity_id = ud.id
                WHERE ue.user_id = ? 
                AND ue.entity_type = 'division' 
                AND ue.is_primary = 1
                AND ud.university_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $universityId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('غير مصرح لك بإضافة كليات لهذه الجامعة');
            }
        }

        // إضافة كلية جديدة
        if (!isset($_POST['id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO colleges (
                    name, 
                    university_id, 
                    created_at,
                    created_by
                ) VALUES (
                    :name,
                    :university_id,
                    NOW(),
                    :created_by
                )
            ");
            
            $result = $stmt->execute([
                ':name' => $name,
                ':university_id' => $universityId,
                ':created_by' => $_SESSION['user_id']
            ]);

            if (!$result) {
                throw new Exception("فشل في إضافة الكلية");
            }

            $collegeId = $pdo->lastInsertId();
            $_SESSION['success'] = 'تم إضافة الكلية بنجاح';
            
            // تسجيل النشاط
            logSystemActivity(
                "تم إضافة كلية جديدة: $name", 
                'college', 
                $_SESSION['user_id']
            );
        }
        // تعديل كلية موجودة
        else {
            $stmt = $pdo->prepare("
                UPDATE colleges 
                SET name = :name,
                    university_id = :university_id,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
            ");
            
            $result = $stmt->execute([
                ':name' => $name,
                ':university_id' => $universityId,
                ':updated_by' => $_SESSION['user_id'],
                ':id' => $_POST['id']
            ]);

            if (!$result) {
                throw new Exception("فشل في تعديل الكلية");
            }

            $_SESSION['success'] = 'تم تعديل الكلية بنجاح';
            
            // تسجيل النشاط
            logSystemActivity(
                "تم تعديل الكلية: $name", 
                'college', 
                $_SESSION['user_id']
            );
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("خطأ في معالجة الكلية: " . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
}

// العودة إلى صفحة الكليات
header('Location: colleges.php');
exit; 