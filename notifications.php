<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// الحصول على عدد الإشعارات غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = $stmt->fetchColumn();

// الحصول على الإشعارات مع الترتيب حسب التاريخ
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE receiver_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات - نظام إدارة التعليم العالي</title>

    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-color: #004d40;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .notification-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: pointer;
            border: none;
            background: white;
            position: relative;
        }
        
        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .unread {
            background: linear-gradient(to right, #f0f7ff, white);
            border-right: 4px solid var(--primary-color);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            font-size: 1.2em;
            transition: all 0.3s ease;
        }

        .notification-card:hover .notification-icon {
            transform: scale(1.1);
        }

        .notification-icon.unit {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #0d6efd;
        }

        .notification-icon.college {
            background: linear-gradient(135deg, #f3e5f5, #e1bee7);
            color: #9c27b0;
        }

        .notification-icon.university {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
        }

        .notification-icon.system {
            background: linear-gradient(135deg, #fce4ec, #f8bbd0);
            color: #c2185b;
        }

        .notification-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .notification-message {
            color: #666;
            margin-bottom: 8px;
        }

        .notification-meta {
            font-size: 0.85em;
            color: #999;
        }

        .mark-read-btn {
            padding: 5px 15px;
            border-radius: 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }

        .mark-read-btn:hover {
            background-color: #00695c;
            transform: scale(1.05);
        }

        .notification-details {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            position: absolute;
            background: white;
            z-index: 1000;
            width: 100%;
            left: 0;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .notification-details.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #00796b);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .close-details {
            position: absolute;
            top: 10px;
            left: 10px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-details:hover {
            background: #f5f5f5;
            color: #333;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="animate__animated animate__fadeIn mb-0">
                    الإشعارات
                </h2>
                <?php if ($unreadCount > 0): ?>
                <button class="btn btn-light animate__animated animate__fadeIn" onclick="markAllAsRead()">
                    <i class="fas fa-check-double me-2"></i>
                    تحديد الكل كمقروء
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div id="notifications-container">
            <?php if (empty($notifications)): ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="fas fa-bell-slash"></i>
                <h4>لا توجد إشعارات</h4>
                <p class="text-muted">ستظهر هنا الإشعارات الجديدة عند وصولها</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-card animate__animated animate__fadeIn <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                     id="notification-<?php echo $notification['id']; ?>"
                     onclick="toggleDetails(<?php echo $notification['id']; ?>)">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="notification-icon <?php echo htmlspecialchars($notification['type']); ?>">
                                <i class="<?php echo htmlspecialchars($notification['icon']); ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <div class="notification-meta">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo timeAgo($notification['created_at']); ?>
                                        </div>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                    <button class="btn btn-sm mark-read-btn" 
                                            onclick="markAsRead(<?php echo $notification['id']; ?>, event)">
                                        <i class="fas fa-check me-1"></i>
                                        تحديد كمقروء
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-details" id="details-<?php echo $notification['id']; ?>">
                                    <button class="close-details" onclick="closeDetails(<?php echo $notification['id']; ?>, event)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>المرسل:</strong> <?php echo htmlspecialchars($notification['sender_name'] ?? 'النظام'); ?></p>
                                            <p><strong>تاريخ الإرسال:</strong> <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>النوع:</strong> <?php echo getNotificationTypeName($notification['type']); ?></p>
                                            <p><strong>الحالة:</strong> <?php echo $notification['is_read'] ? 'مقروء' : 'غير مقروء'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script>
    // إضافة متغير عام لتتبع حالة النافذة
    let isDetailsOpen = {};

    function toggleDetails(id, event) {
        if (event) {
            event.stopPropagation();
        }
        
        // إغلاق جميع النوافذ المفتوحة الأخرى
        Object.keys(isDetailsOpen).forEach(key => {
            if (key != id && isDetailsOpen[key]) {
                const otherDetails = document.getElementById(`details-${key}`);
                if (otherDetails) {
                    otherDetails.classList.remove('show');
                    isDetailsOpen[key] = false;
                }
            }
        });

        const details = document.getElementById(`details-${id}`);
        if (!isDetailsOpen[id]) {
            details.classList.add('show');
            isDetailsOpen[id] = true;
        }
    }

    function closeDetails(id, event) {
        event.stopPropagation();
        const details = document.getElementById(`details-${id}`);
        details.classList.remove('show');
        isDetailsOpen[id] = false;
    }

    // إضافة مستمع حدث للنقر على المستند
    document.addEventListener('click', (e) => {
        // التحقق مما إذا كان النقر خارج أي بطاقة إشعار وخارج التفاصيل
        if (!e.target.closest('.notification-card') && !e.target.closest('.notification-details')) {
            Object.keys(isDetailsOpen).forEach(id => {
                if (isDetailsOpen[id]) {
                    const details = document.getElementById(`details-${id}`);
                    if (details) {
                        details.classList.remove('show');
                        isDetailsOpen[id] = false;
                    }
                }
            });
        }
    });

    // إضافة مستمعات أحداث للماوس
    document.querySelectorAll('.notification-card').forEach(card => {
        const id = card.id.split('-')[1];
        
        card.addEventListener('mouseenter', (e) => {
            toggleDetails(id, e);
        });
    });

    function markAsRead(id, event) {
        event.stopPropagation(); // منع انتشار الحدث للعنصر الأب
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notification = document.getElementById(`notification-${id}`);
                notification.classList.remove('unread');
                notification.querySelector('.mark-read-btn').remove();
                showToast('تم تحديد الإشعار كمقروء', 'success');
            }
        });
    }

    function markAllAsRead() {
        fetch('mark_all_notifications_read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.unread').forEach(el => {
                    el.classList.remove('unread');
                    el.querySelector('.mark-read-btn')?.remove();
                });
                showToast('تم تحديد جميع الإشعارات كمقروءة', 'success');
            }
        });
    }

    function showToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0 animate__animated animate__fadeInUp`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
