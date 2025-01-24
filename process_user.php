<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    if (isset($_POST['update_user'])) {
        // التحقق من وجود الجامعة
        if (!empty($_POST['university_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM universities WHERE id = ?");
            $stmt->execute([$_POST['university_id']]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("الجامعة المحددة غير موجودة");
            }
        }

        // التحقق من وجود الكلية إذا تم تحديدها
        if (!empty($_POST['college_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM colleges WHERE id = ? AND university_id = ?");
            $stmt->execute([$_POST['college_id'], $_POST['university_id']]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("الكلية المحددة غير موجودة أو لا تنتمي للجامعة المحددة");
            }
        }

        // التحقق من عدم تكرار اسم المستخدم والبريد الإلكتروني
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE (username = ? OR email = ?) 
            AND id != ?
        ");
        $stmt->execute([
            $_POST['username'],
            $_POST['email'],
            $_POST['user_id']
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل");
        }

        // تحديث بيانات المستخدم
        $sql = "UPDATE users SET 
                username = :username,
                full_name = :full_name,
                email = :email,
                role = :role,
                university_id = :university_id,
                college_id = :college_id,
                updated_at = CURRENT_TIMESTAMP";

        // إضافة تحديث كلمة المرور إذا تم تقديمها
        if (!empty($_POST['password'])) {
            $sql .= ", password = :password";
        }

        $sql .= " WHERE id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            'username' => $_POST['username'],
            'full_name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'role' => $_POST['role'],
            'university_id' => !empty($_POST['university_id']) ? $_POST['university_id'] : null,
            'college_id' => !empty($_POST['college_id']) ? $_POST['college_id'] : null,
            'user_id' => $_POST['user_id']
        ];

        if (!empty($_POST['password'])) {
            $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $stmt->execute($params);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'تم تحديث المستخدم بنجاح']);
    }
    elseif (isset($_POST['delete_user'])) {
        // حذف صلاحيات المستخدم أولاً
        $stmt = $pdo->prepare("DELETE FROM permissions WHERE user_id = ?");
        $stmt->execute([$_POST['user_id']]);

        // حذف المستخدم
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم بنجاح']);
    }
    else {
        throw new Exception("عملية غير صالحة");
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("خطأ في معالجة بيانات المستخدم: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء معالجة البيانات']);
} 