<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

while (true) {
    $lastCheck = date('Y-m-d H:i:s', strtotime('-30 seconds'));
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            AND created_at > ? 
            AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $lastCheck]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($notifications)) {
            echo "data: " . json_encode(['notifications' => $notifications]) . "\n\n";
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }

    ob_flush();
    flush();
    sleep(5); // تحديث كل 5 ثواني
}
