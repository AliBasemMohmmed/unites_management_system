<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit('يجب تسجيل الدخول للوصول إلى هذه الصفحة');
    }
}

// دالة للتحقق من أن المستخدم مدير وإعادة التوجيه إذا لم يكن كذلك
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit('غير مصرح لك بالوصول');
    }
}

// دالة للحصول على جميع صلاحيات المستخدم
function getUserPermissions($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT permission_name 
            FROM permissions 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

// دالة لإضافة صلاحية جديدة للمستخدم
function addPermission($user_id, $permission_name) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO permissions (user_id, permission_name) 
            VALUES (?, ?)
        ");
        return $stmt->execute([$user_id, $permission_name]);
    } catch (PDOException $e) {
        return false;
    }
}

// دالة لحذف صلاحية من المستخدم
function removePermission($user_id, $permission_name) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM permissions 
            WHERE user_id = ? AND permission_name = ?
        ");
        return $stmt->execute([$user_id, $permission_name]);
    } catch (PDOException $e) {
        return false;
    }
}

// دالة للحصول على دور المستخدم
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// دالة لتسجيل نشاط النظام
function logSystemActivity($action, $type, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (action, type, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$action, $type, $user_id]);
    } catch (PDOException $e) {
        error_log("خطأ في تسجيل نشاط النظام: " . $e->getMessage());
    }
}

function setUserEntityInfo($userId) {
    global $pdo;
    
    // جلب معلومات المستخدم والجهة
    $stmt = $pdo->prepare("
        SELECT u.*, ue.entity_type, ue.entity_id 
        FROM users u
        LEFT JOIN user_entities ue ON u.id = ue.user_id AND ue.is_primary = 1
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // تعيين معلومات المستخدم في الجلسة
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['entity_type'] = $user['entity_type'] ?? 'user';
        $_SESSION['entity_id'] = $user['entity_id'] ?? $userId;
        
        // تعيين معلومات إضافية
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];
    } else {
        // القيم الافتراضية
        $_SESSION['entity_type'] = 'user';
        $_SESSION['entity_id'] = $userId;
        $_SESSION['user_role'] = 'user';
    }
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// دالة لتحديث معلومات المستخدم في الجلسة
function updateUserSession($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.id as role_id, r.name as role_name, r.display_name as role_display_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['role_display_name'] = $user['role_display_name'];
            return true;
        }
    } catch (PDOException $e) {
        error_log("خطأ في تحديث معلومات المستخدم: " . $e->getMessage());
    }
    return false;
}
?>
