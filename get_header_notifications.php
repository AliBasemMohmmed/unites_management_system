<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE receiver_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();

    if (empty($notifications)) {
        echo '<div class="dropdown-item text-center text-muted py-3">
                <i class="fas fa-bell-slash mb-2"></i>
                <p class="mb-0">لا توجد إشعارات جديدة</p>
              </div>';
    } else {
        foreach ($notifications as $notification) {
            $isUnread = !$notification['is_read'];
            echo '<a class="dropdown-item notification-item ' . ($isUnread ? 'unread' : '') . '" href="notifications.php#notification-' . $notification['id'] . '">
                    <div class="d-flex align-items-center">
                        <div class="notification-icon ' . htmlspecialchars($notification['type']) . ' me-3">
                            <i class="' . htmlspecialchars($notification['icon']) . '"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1 ' . ($isUnread ? 'fw-bold' : '') . '">' . htmlspecialchars($notification['title']) . '</p>
                            <small class="text-muted">' . timeAgo($notification['created_at']) . '</small>
                        </div>
                        ' . ($isUnread ? '<span class="badge bg-primary rounded-pill ms-2"></span>' : '') . '
                    </div>
                </a>';
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في جلب الإشعارات: " . $e->getMessage());
    echo '<div class="dropdown-item text-center text-muted py-3">حدث خطأ في تحميل الإشعارات</div>';
} 