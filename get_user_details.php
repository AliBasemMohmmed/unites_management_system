<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if (!isset($_POST['user_id'])) {
        throw new Exception('معرف المستخدم مطلوب');
    }

    $userId = (int)$_POST['user_id'];

    $stmt = $pdo->prepare("
        SELECT u.*, c.name as college_name
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE u.id = ?
    ");
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('لم يتم العثور على المستخدم');
    }

    echo json_encode([
        'success' => true,
        'data' => $user
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 