<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
if (!hasPermission('delete_unit')) {
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح لك بحذف الوحدات'
    ]);
    exit;
}

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'معرف الوحدة غير صحيح'
    ]);
    exit;
}

$unitId = (int)$_GET['id'];

try {
    // التحقق من وجود الوحدة
    $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmt->execute([$unitId]);
    $unit = $stmt->fetch();

    if (!$unit) {
        echo json_encode([
            'success' => false,
            'message' => 'الوحدة غير موجودة'
        ]);
        exit;
    }

    // التحقق من صلاحية المستخدم لحذف هذه الوحدة
    if ($_SESSION['user_role'] !== 'admin') {
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM user_entities ue
            INNER JOIN university_divisions ud ON ue.entity_id = ud.id
            WHERE ue.user_id = ? 
            AND ue.entity_type = 'division'
            AND ue.is_primary = 1
            AND ud.university_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $unit['university_id']]);
        
        if (!$stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'غير مصرح لك بحذف هذه الوحدة'
            ]);
            exit;
        }
    }

    // حذف الوحدة
    $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
    $stmt->execute([$unitId]);

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف الوحدة بنجاح'
    ]);

} catch (PDOException $e) {
    error_log("خطأ في حذف الوحدة: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء حذف الوحدة'
    ]);
} 