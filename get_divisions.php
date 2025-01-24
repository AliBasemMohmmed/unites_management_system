<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['university_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف الجامعة مطلوب']);
    exit;
}

$universityId = $_GET['university_id'];

try {
    // جلب الشعب التابعة للجامعة
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM university_divisions 
        WHERE university_id = ? 
        ORDER BY name
    ");
    
    $stmt->execute([$universityId]);
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($divisions);
} catch (PDOException $e) {
    error_log("خطأ في جلب الشعب: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
}
?> 