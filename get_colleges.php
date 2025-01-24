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
    // جلب كليات الجامعة المحددة
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM colleges 
        WHERE university_id = :university_id 
        ORDER BY name
    ");
    
    $stmt->execute(['university_id' => $universityId]);
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($colleges);
} catch (PDOException $e) {
    error_log("خطأ في جلب الكليات: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب البيانات']);
} 