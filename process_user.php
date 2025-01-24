<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $pdo->beginTransaction();

    // التحقق من وجود المستخدم
    if (!isset($_POST['user_id'])) {
        throw new Exception('معرف المستخدم مطلوب');
    }

    $userId = (int)$_POST['user_id'];
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleId = $_POST['role_id'] ?? '';
    $collegeId = $_POST['college_id'] ?? null;
    $password = $_POST['password'] ?? '';

    // التحقق من البيانات المطلوبة
    if (empty($username) || empty($fullName) || empty($email) || empty($roleId)) {
        throw new Exception('جميع الحقول مطلوبة');
    }

    // التحقق من وجود الدور
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('نوع المستخدم غير صالح');
    }

    // التحقق من عدم تكرار اسم المستخدم والبريد الإلكتروني
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE (username = ? OR email = ?) 
        AND id != ?
    ");
    $stmt->execute([$username, $email, $userId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل');
    }

    // تحديث بيانات المستخدم
    if (!empty($password)) {
        // تحديث مع كلمة المرور
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = ?,
                full_name = ?,
                email = ?,
                password = ?,
                role_id = ?,
                college_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $username,
            $fullName,
            $email,
            $hashedPassword,
            $roleId,
            $collegeId,
            $userId
        ]);
    } else {
        // تحديث بدون كلمة المرور
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = ?,
                full_name = ?,
                email = ?,
                role_id = ?,
                college_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $username,
            $fullName,
            $email,
            $roleId,
            $collegeId,
            $userId
        ]);
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('لم يتم إجراء أي تغييرات');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث بيانات المستخدم بنجاح'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("خطأ في معالجة بيانات المستخدم: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء معالجة البيانات']);
} 