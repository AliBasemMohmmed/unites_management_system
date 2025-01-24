<?php
require_once 'auth.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// جلب معلومات المستخدم ودوره
$stmt = $pdo->prepare("
    SELECT u.*, r.id as role_id, r.name as role_name, r.display_name as role_display_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$userInfo = $stmt->fetch();

// تحديث معلومات الدور في الجلسة
if ($userInfo) {
    $_SESSION['role_id'] = $userInfo['role_id'];
    $_SESSION['role_name'] = $userInfo['role_name'];
} else {
    // إذا لم يتم العثور على معلومات المستخدم، قم بتسجيل الخروج
    session_destroy();
    header('Location: login.php');
    exit;
}

// جلب عدد الإشعارات غير المقروءة
$unreadNotifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadNotifications = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("خطأ في جلب الإشعارات: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نظام إدارة التعليم العالي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background-color: #004d40; }
        .navbar-brand, .nav-link { color: white !important; }
        .dropdown-menu { min-width: 200px; }
        .dropdown-item:hover { background-color: #f8f9fa; }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 0.25rem 0.5rem;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            font-size: 0.75rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* تنسيقات إضافية للأزرار */
        .btn-outline-primary, .btn-outline-danger {
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,123,255,0.2);
        }
        
        .btn-outline-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(220,53,69,0.2);
        }

        /* تنسيق مربعات الحوار */
        .swal2-popup {
            border-radius: 15px;
        }

        .swal2-title {
            font-weight: 600 !important;
        }

        .swal2-confirm, .swal2-cancel {
            border-radius: 20px !important;
            padding: 8px 25px !important;
            font-weight: 500 !important;
        }

        /* تحسينات إضافية للقوائم المنسدلة */
        .dropdown-menu {
            margin-top: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            border: none;
            animation: fadeIn 0.2s ease-in;
        }

        /* تحسين مظهر الإشعارات */
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.25rem 0.5rem;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            font-size: 0.75rem;
            transform: translate(50%, -50%);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: translate(50%, -50%) scale(1); }
            50% { transform: translate(50%, -50%) scale(1.2); }
            100% { transform: translate(50%, -50%) scale(1); }
        }

        .notifications-dropdown {
            width: 350px !important;
            padding: 0;
            max-height: 500px;
            overflow-y: auto;
        }

        .notifications-header {
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color), #00796b);
            color: white;
            border-radius: 5px 5px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notifications-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background: linear-gradient(to right, #f0f7ff, white);
            border-right: 4px solid var(--primary-color);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 1rem;
            font-size: 1.2em;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #2c3e50;
        }

        .notification-message {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            font-size: 0.8em;
            color: #999;
        }

        .view-all-link {
            display: block;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            border-radius: 0 0 5px 5px;
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            background: #e9ecef;
            color: #00695c;
        }

        /* تحسين السكرولبار */
        .notifications-dropdown::-webkit-scrollbar {
            width: 8px;
        }

        .notifications-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .notifications-dropdown::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .notifications-dropdown::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-university me-2"></i>نظام إدارة التعليم العالي
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- شريط البحث -->
            <?php if (hasPermission('search_system')): ?>
            <form class="d-flex me-auto" action="search.php" method="GET">
                <input class="form-control me-2" type="search" name="q" placeholder="بحث في النظام..." required>
                <button class="btn btn-outline-light" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            <?php endif; ?>

            <!-- القائمة الرئيسية -->
            <ul class="navbar-nav me-auto">
                <?php
                // التحقق من صلاحيات الشعب
                $canViewDivisions = hasPermission('view_divisions') || hasPermission('manage_divisions');
                
                // التحقق من صلاحيات الكليات
                $canViewColleges = hasPermission('view_colleges') || hasPermission('manage_colleges');
                
                // التحقق من صلاحيات الوحدات
                $canViewUnits = hasPermission('view_units') || hasPermission('manage_units');
                ?>

                <?php if (isAdmin() || $canViewDivisions): ?>
                <li class="nav-item">
                    <a class="nav-link" href="divisions.php">
                        <i class="fas fa-layer-group me-1"></i>الشعب
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isAdmin() || $canViewColleges): ?>
                <li class="nav-item">
                    <a class="nav-link" href="colleges.php">
                        <i class="fas fa-building me-1"></i>الكليات
                    </a>
                </li>
                <?php endif; ?>

                <?php if (isAdmin() || $canViewUnits): ?>
                <li class="nav-item">
                    <a class="nav-link" href="units.php">
                        <i class="fas fa-boxes me-1"></i>الوحدات
                    </a>
                </li>
                <?php endif; ?>

                <?php if (isAdmin() || hasPermission('view_documents')): ?>
                    <!-- قائمة الكتب والمراسلات -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-file-alt me-1"></i>الكتب والمراسلات
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="documents.php">عرض الكتب</a></li>
                            <?php if (isAdmin() || hasPermission('manage_documents')): ?>
                                <li><a class="dropdown-item" href="process_document.php">إضافة كتاب جديد</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="document_workflow.php">تدفق الكتب</a></li>
                            <li><a class="dropdown-item" href="archive.php">الأرشيف</a></li>
                        </ul>
                    </li>

                    <!-- قائمة التقارير -->
                    <?php if (isAdmin() || hasPermission('view_reports')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-bar me-1"></i>التقارير
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="reports.php">التقارير العامة</a></li>
                            <?php if (isAdmin() || hasPermission('manage_reports')): ?>
                                <li><a class="dropdown-item" href="advanced_reports.php">التقارير المتقدمة</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="statistics.php">الإحصائيات</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- قائمة المستخدم -->
            <ul class="navbar-nav">
                <!-- الإشعارات -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown">
                        <div class="notifications-header">
                            <h6 class="mb-0">الإشعارات</h6>
                            <?php if ($unreadNotifications > 0): ?>
                            <button class="btn btn-sm btn-light" onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i>
                                تحديد الكل كمقروء
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="notifications-list">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT n.*, 
                                       CASE 
                                           WHEN n.is_read = 0 THEN 'unread'
                                           ELSE ''
                                       END as read_status
                                FROM notifications n
                                WHERE n.receiver_id = ? 
                                ORDER BY n.created_at DESC 
                                LIMIT 5
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $headerNotifications = $stmt->fetchAll();

                            if (empty($headerNotifications)): ?>
                                <div class="text-center p-3">
                                    <i class="fas fa-bell-slash text-muted"></i>
                                    <p class="text-muted mb-0">لا توجد إشعارات جديدة</p>
                                </div>
                            <?php else:
                                foreach ($headerNotifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['read_status']; ?>" 
                                     onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                    <div class="notification-icon <?php echo htmlspecialchars($notification['type']); ?>">
                                        <i class="<?php echo htmlspecialchars($notification['icon']); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach;
                            endif; ?>
                        </div>
                        <a href="notifications.php" class="view-all-link">
                            عرض كل الإشعارات
                            <i class="fas fa-arrow-left ms-1"></i>
                        </a>
                    </div>
                </li>

                <!-- قائمة المستخدم المنسدلة -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($userInfo['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user me-2"></i>الملف الشخصي
                        </a></li>
                        
                        <?php if ($userInfo['role_name'] == 'admin'): ?>
                            <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">إدارة النظام</h6></li>
                        <li><a class="dropdown-item" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>لوحة التحكم
                        </a></li>
                        <li><a class="dropdown-item" href="manage_permissions.php">
                            <i class="fas fa-key me-2"></i>إدارة الصلاحيات
                        </a></li>
                        <li><a class="dropdown-item" href="system_monitor.php">
                            <i class="fas fa-desktop me-2"></i>مراقبة النظام
                        </a></li>
                        <!-- <li><a class="dropdown-item" href="manage_permission_types.php">
                            <i class="fas fa-key me-2"></i>إدارة نوع الصلاحيات
                        </a></li> -->
                        <li><a class="dropdown-item" href="backup.php">
                            <i class="fas fa-database me-2"></i>النسخ الاحتياطي
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">الأدوات</h6></li>
                        <li><a class="dropdown-item" href="reminders.php">
                            <i class="fas fa-clock me-2"></i>التذكيرات
                        </a></li>
                        <li><a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i>الإعدادات
                        </a></li>
                        <li><a class="dropdown-item" href="users.php">
                            <i class="fas fa-users me-2"></i>المستخدمين
                        </a></li>
                        <li><a class="dropdown-item" href="export.php">
                            <i class="fas fa-file-export me-2"></i>تصدير البيانات
                     
                        <?php endif; ?>
  
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">الأدوات</h6></li>
                        <li><a class="dropdown-item" href="reminders.php">
                            <i class="fas fa-clock me-2"></i>التذكيرات
                        </a></li>
                        <li><a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i>الإعدادات
                        </a></li>
                         </a></li>  <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                        </a></li>
                      
                    </ul>
                </li>
            </ul>

            <!-- عرض اسم المستخدم -->
            <!-- <span class="navbar-text">
                مرحباً، <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'مستخدم'; ?>
            </span> -->
        </div>
    </div>
</nav>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // تفعيل جميع القوائم المنسدلة في Bootstrap
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // إضافة تأثير hover للقوائم المنسدلة
    $('.dropdown').hover(
        function() {
            if (window.innerWidth >= 992) { // فقط للشاشات الكبيرة
                $(this).find('.dropdown-menu').addClass('show');
            }
        },
        function() {
            if (window.innerWidth >= 992) {
                $(this).find('.dropdown-menu').removeClass('show');
            }
        }
    );

    // تفعيل التنقل بين عناصر القائمة باستخدام لوحة المفاتيح
    $('.dropdown-menu a').on('keydown', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var items = $(this).closest('.dropdown-menu').find('a:not(.disabled)');
            var index = items.index(this);
            var nextIndex = e.key === 'ArrowDown' ? 
                (index + 1) % items.length : 
                (index - 1 + items.length) % items.length;
            items.eq(nextIndex).focus();
        }
    });

    // إغلاق القائمة المنسدلة عند النقر خارجها
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').removeClass('show');
        }
    });

    // تحديث الإشعارات
    function updateNotifications() {
        $.get('get_notifications.php', function(data) {
            // تحديث العداد
            if (data.unreadCount > 0) {
                let badge = $('.notification-badge');
                if (badge.length === 0) {
                    $('#notificationsDropdown').append(`<span class="notification-badge">${data.unreadCount}</span>`);
                } else {
                    badge.text(data.unreadCount);
                }
            } else {
                $('.notification-badge').remove();
            }

            // تحديث قائمة الإشعارات
            if (data.notifications && data.notifications.length > 0) {
                let notificationsList = '';
                data.notifications.forEach(function(notification) {
                    notificationsList += `
                        <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                             onclick="markAsRead(${notification.id})">
                            <div class="notification-icon ${notification.type}">
                                <i class="${notification.icon}"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notification.title}</div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${notification.timeAgo}</div>
                            </div>
                        </div>
                    `;
                });
                $('.notifications-list').html(notificationsList);
            } else {
                $('.notifications-list').html(`
                    <div class="text-center p-3">
                        <i class="fas fa-bell-slash text-muted"></i>
                        <p class="text-muted mb-0">لا توجد إشعارات جديدة</p>
                    </div>
                `);
            }
        });
    }

    // تحديث الإشعارات كل 30 ثانية
    setInterval(updateNotifications, 30000);

    // تحديث فوري عند فتح القائمة
    $('#notificationsDropdown').on('show.bs.dropdown', function () {
        updateNotifications();
    });

    // تحديد إشعار كمقروء
    window.markAsRead = function(notificationId) {
        $.post('mark_notification_read.php', { notification_id: notificationId }, function(data) {
            if (data.success) {
                updateNotifications();
                // تحديث مظهر الإشعار مباشرة
                $(`.notification-item[data-id="${notificationId}"]`).removeClass('unread');
            }
        });
    };

    // تحديد كل الإشعارات كمقروءة
    window.markAllAsRead = function() {
        $.post('mark_all_notifications_read.php', function(data) {
            if (data.success) {
                updateNotifications();
                // تحديث مظهر جميع الإشعارات مباشرة
                $('.notification-item').removeClass('unread');
                $('.notification-badge').remove();
            }
        });
    };
});

// تفعيل tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<style>
/* تحسينات إضافية للقوائم المنسدلة */
.dropdown-menu {
    margin-top: 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    border: none;
    animation: fadeIn 0.2s ease-in;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
}

.dropdown-item i {
    margin-left: 0.5rem;
    width: 20px;
    text-align: center;
}

.dropdown-header {
    color: #004d40;
    font-weight: bold;
    padding: 0.5rem 1rem;
}

.dropdown-divider {
    margin: 0.3rem 0;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* تحسين مظهر الإشعارات */
.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    padding: 0.25rem 0.5rem;
    border-radius: 50%;
    background-color: #dc3545;
    color: white;
    font-size: 0.75rem;
    transform: translate(50%, -50%);
}

/* تحسين مظهر القوائم في الشاشات الصغيرة */
@media (max-width: 991.98px) {
    .dropdown-menu {
        border: none;
        box-shadow: none;
        padding: 0;
        margin: 0;
    }
    
    .dropdown-item {
        padding: 0.75rem 1.5rem;
    }
    
    .navbar-collapse {
        background-color: #004d40;
        padding: 1rem;
        border-radius: 0 0 0.5rem 0.5rem;
    }
}
</style>
</body>
</html>
