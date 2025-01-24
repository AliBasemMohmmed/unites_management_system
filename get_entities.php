<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        throw new Exception('نوع الجهة مطلوب');
    }

    $entities = [];
    
    switch ($type) {
        case 'ministry':
            $stmt = $pdo->query("SELECT id, name FROM ministry_departments ORDER BY name");
            $entities = $stmt->fetchAll();
            break;
            
        case 'division':
            $stmt = $pdo->query("SELECT id, name FROM university_divisions ORDER BY name");
            $entities = $stmt->fetchAll();
            break;
            
        case 'unit':
            $stmt = $pdo->query("SELECT id, name FROM units ORDER BY name");
            $entities = $stmt->fetchAll();
            break;
            
        default:
            throw new Exception('نوع جهة غير صحيح');
    }

    sendJsonResponse([
        'success' => true,
        'entities' => $entities
    ]);

} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
} 