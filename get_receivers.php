<?php
require_once 'functions.php';
require_once 'auth.php';
require_once 'config.php';

// تعيين رؤوس CORS وترميز UTF-8
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    // التحقق من الاتصال بقاعدة البيانات
    if (!isset($pdo)) {
        throw new Exception('خطأ في الاتصال بقاعدة البيانات');
    }

    // التحقق من وجود الجداول
    try {
        $pdo->query("SELECT 1 FROM universities LIMIT 1");
        $pdo->query("SELECT 1 FROM colleges LIMIT 1");
        $pdo->query("SELECT 1 FROM units LIMIT 1");
    } catch (PDOException $e) {
        throw new Exception('الجداول المطلوبة غير موجودة');
    }

    $receivers = [];

    // جلب الجامعات
    $stmt = $pdo->prepare("SELECT id, name FROM universities ORDER BY name");
    $stmt->execute();
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($universities)) {
        $universityItems = [];
        $universityItems[] = ['id' => 'all_universities', 'name' => 'جميع الجامعات'];
        foreach ($universities as $uni) {
            $universityItems[] = ['id' => strval($uni['id']), 'name' => $uni['name']];
        }
        
        $receivers[] = [
            'group' => 'الجامعات',
            'type' => 'universities',
            'items' => $universityItems
        ];

        // جلب الكليات لكل جامعة
        $stmt = $pdo->prepare("SELECT id, name FROM colleges WHERE university_id = ? ORDER BY name");
        foreach ($universities as $uni) {
            $stmt->execute([$uni['id']]);
            $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($colleges)) {
                $collegeItems = [];
                $collegeItems[] = ['id' => 'all_colleges_' . $uni['id'], 'name' => 'جميع الكليات'];
                foreach ($colleges as $college) {
                    $collegeItems[] = ['id' => strval($college['id']), 'name' => $college['name']];
                }
                
                $receivers[] = [
                    'group' => 'كليات ' . $uni['name'],
                    'type' => 'colleges',
                    'parent_id' => strval($uni['id']),
                    'items' => $collegeItems
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $receivers
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?> 