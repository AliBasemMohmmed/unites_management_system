<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('معرف الكلية مطلوب');
    }

    $collegeId = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM colleges c 
        WHERE c.id = ?
    ");
    
    $stmt->execute([$collegeId]);
    $college = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$college) {
        throw new Exception('الكلية غير موجودة');
    }

    echo json_encode([
        'id' => $college['id'],
        'name' => $college['name'],
        'success' => true
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 