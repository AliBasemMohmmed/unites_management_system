<?php
require_once 'functions.php';
require_once 'auth.php';
require_once 'permissions_list.php';
requireLogin();

// التحقق من أن المستخدم مدير
$userInfo = getUserFullInfo($_SESSION['user_id']);
if (!$userInfo || $userInfo['role_name'] !== 'admin') {
    logSystemActivity('محاولة وصول غير مصرح لإدارة الصلاحيات', 'security_violation', $_SESSION['user_id']);
    header('Location: dashboard.php');
    exit('غير مصرح لك بالوصول');
}

// معالجة تحديث الصلاحيات الافتراضية للأدوار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_default_permissions'])) {
    try {
        $role_id = $_POST['role_id'];
        $permissions = $_POST['permissions'] ?? [];
        
        // حذف الصلاحيات القديمة للدور
        $stmt = $pdo->prepare("DELETE FROM role_default_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // إضافة الصلاحيات الجديدة
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_default_permissions (role_id, permission_name) VALUES (?, ?)");
            foreach ($permissions as $permission) {
                $stmt->execute([$role_id, $permission]);
            }
        }
        
        $_SESSION['success'] = "تم تحديث صلاحيات الدور بنجاح";
        logSystemActivity("تم تحديث صلاحيات الدور: $role_id", 'permissions', $_SESSION['user_id']);
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ أثناء تحديث الصلاحيات";
        error_log($e->getMessage());
    }
}

// جلب قائمة المستخدمين مع صلاحياتهم
$users = $pdo->query("
    SELECT u.id, u.username, u.full_name, r.id as role_id, r.name as role_name, r.display_name as role_display_name,
           GROUP_CONCAT(p.permission_name) as current_permissions
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN permissions p ON u.id = p.user_id 
    WHERE r.name != 'admin'
    GROUP BY u.id, u.username, u.full_name, r.id, r.name, r.display_name
")->fetchAll();

// جلب قائمة الأدوار
$roles_query = $pdo->query("SELECT id, name, display_name FROM roles ORDER BY id");
$roles = [];
while ($role = $roles_query->fetch()) {
    $roles[$role['id']] = $role['display_name'];
}

include 'header.php';

// تنظيم الصلاحيات حسب المجموعات
$permissionGroups = [
    'المستندات' => array_filter($available_permissions, fn($key) => strpos($key, 'document') !== false, ARRAY_FILTER_USE_KEY),
    'الكليات' => array_filter($available_permissions, fn($key) => strpos($key, 'college') !== false, ARRAY_FILTER_USE_KEY),
    'الشعب' => array_filter($available_permissions, fn($key) => strpos($key, 'division') !== false, ARRAY_FILTER_USE_KEY),
    'الوحدات' => array_filter($available_permissions, fn($key) => strpos($key, 'unit') !== false, ARRAY_FILTER_USE_KEY),
    'المستخدمين' => array_filter($available_permissions, fn($key) => strpos($key, 'user') !== false, ARRAY_FILTER_USE_KEY),
    'التقارير' => array_filter($available_permissions, fn($key) => strpos($key, 'report') !== false, ARRAY_FILTER_USE_KEY),
    'النظام' => array_filter($available_permissions, fn($key) => 
        strpos($key, 'log') !== false || 
        strpos($key, 'setting') !== false || 
        strpos($key, 'permission') !== false
    , ARRAY_FILTER_USE_KEY),
];
?>

<style>
:root {
    --primary-color: #2196F3;
    --secondary-color: #607D8B;
    --success-color: #4CAF50;
    --warning-color: #FFC107;
    --danger-color: #F44336;
    --light-bg: #F5F5F5;
    --dark-bg: #263238;
}

body {
    background-color: var(--light-bg);
}

.main-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.page-header {
    background: linear-gradient(135deg, var(--primary-color), #1976D2);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.page-header h2 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
}

.nav-tabs {
    border: none;
    background: white;
    padding: 1rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.nav-tabs .nav-link {
    border: none;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: 500;
    color: var(--secondary-color);
    transition: all 0.3s ease;
    margin: 0 0.5rem;
}

.nav-tabs .nav-link:hover {
    background: rgba(33, 150, 243, 0.1);
    color: var(--primary-color);
}

.nav-tabs .nav-link.active {
    background: linear-gradient(45deg, var(--primary-color), #1976D2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
}

.permission-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

.permission-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.1);
}

.permission-card .card-header {
    background: linear-gradient(45deg, var(--secondary-color), #455A64);
    color: white;
    padding: 1rem 1.5rem;
    border-bottom: none;
}

.permission-group {
    padding: 1.5rem;
    opacity: 0;
    transform: translateY(20px);
    animation: slideUp 0.5s ease forwards;
}

.form-check {
    margin: 0.5rem 0;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-check:hover {
    background: rgba(33, 150, 243, 0.05);
}

.form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    margin-left: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.form-check-input:checked {
    background-color: var(--success-color);
    border-color: var(--success-color);
    transform: scale(1.1);
}

.form-check-label {
    cursor: pointer;
    font-size: 0.95rem;
    color: var(--dark-bg);
}

.btn-save {
    background: linear-gradient(45deg, var(--success-color), #388E3C);
    color: white;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
}

.users-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.users-table th {
    background: var(--dark-bg);
    color: white;
    font-weight: 500;
    padding: 1rem;
    border: none;
}

.users-table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: rgba(0,0,0,0.05);
}

.btn-view-permissions {
    background: linear-gradient(45deg, var(--primary-color), #1976D2);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-view-permissions:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
}

.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), #1976D2);
    color: white;
    border: none;
    padding: 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.permission-list-item {
    display: flex;
    align-items: center;
    padding: 0.8rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: rgba(33, 150, 243, 0.05);
    transition: all 0.3s ease;
}

.permission-list-item:hover {
    background: rgba(33, 150, 243, 0.1);
    transform: translateX(-5px);
}

.permission-icon {
    margin-left: 1rem;
    color: var(--success-color);
    font-size: 1.2rem;
}

@keyframes slideUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    z-index: 9999;
    animation: slideIn 0.5s ease;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.notification.show {
    opacity: 1;
    visibility: visible;
}

.notification.success {
    border-right: 4px solid var(--success-color);
}

.notification.warning {
    border-right: 4px solid var(--warning-color);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style>

<div class="main-container">
    <div class="page-header">
        <h2>
            <i class="fas fa-shield-alt ml-2"></i>
            إدارة صلاحيات الأدوار والمستخدمين
        </h2>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success">
            <i class="fas fa-check-circle ml-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification warning">
            <i class="fas fa-exclamation-circle ml-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="permissions-container">
        <ul class="nav nav-tabs" id="permissionTabs" role="tablist">
            <?php foreach ($roles as $role_id => $role_display_name): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $role_id === 1 ? 'active' : ''; ?>" 
                        id="role<?php echo $role_id; ?>-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#role<?php echo $role_id; ?>-pane" 
                        type="button" 
                        role="tab">
                    <i class="fas <?php echo getRoleIcon($role_id); ?> ml-2"></i>
                    <?php echo $role_display_name; ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content" id="permissionTabsContent">
            <?php foreach ($roles as $role_id => $role_display_name): ?>
            <div class="tab-pane fade <?php echo $role_id === 1 ? 'show active' : ''; ?>" 
                 id="role<?php echo $role_id; ?>-pane" 
                 role="tabpanel">
                <form method="POST" class="permission-form">
                    <input type="hidden" name="update_default_permissions" value="1">
                    <input type="hidden" name="role_id" value="<?php echo $role_id; ?>">

                    <?php foreach ($permissionGroups as $groupName => $permissions): ?>
                    <div class="permission-card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas <?php echo getGroupIcon($groupName); ?> ml-2"></i>
                                <?php echo $groupName; ?>
                            </h6>
                        </div>
                        <div class="permission-group">
                            <?php foreach ($permissions as $key => $label): ?>
                            <div class="form-check">
                                <input type="checkbox" 
                                       name="permissions[]" 
                                       value="<?php echo $key; ?>" 
                                       class="form-check-input"
                                       <?php echo in_array($key, getUserRolePermissions($role_id)) ? 'checked' : ''; ?>>
                                <label class="form-check-label">
                                    <?php echo $label; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save ml-2"></i>
                            حفظ صلاحيات <?php echo $role_display_name; ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="users-table mt-5">
        <div class="table-header p-3">
            <h5 class="mb-0">
                <i class="fas fa-users ml-2"></i>
                المستخدمون وصلاحياتهم
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>الدور</th>
                        <th>الصلاحيات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-circle ml-2 text-primary"></i>
                                <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo getRoleBadgeColor($user['role_name']); ?>">
                                <?php echo $user['role_display_name']; ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" 
                                    class="btn-view-permissions" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#permissionsModal<?php echo $user['id']; ?>">
                                <i class="fas fa-eye ml-1"></i>
                                عرض الصلاحيات
                            </button>
                        </td>
                    </tr>

                    <!-- Modal عرض الصلاحيات -->
                    <div class="modal fade" id="permissionsModal<?php echo $user['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-shield-alt ml-2"></i>
                                        صلاحيات: <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php 
                                    $userRolePermissions = getUserRolePermissions($user['role_id']);
                                    if (!empty($userRolePermissions)): 
                                        foreach ($userRolePermissions as $permission): 
                                    ?>
                                    <div class="permission-list-item">
                                        <i class="fas fa-check-circle permission-icon"></i>
                                        <?php echo $available_permissions[$permission] ?? $permission; ?>
                                    </div>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                    <div class="alert alert-info">
                                        لا توجد صلاحيات محددة لهذا المستخدم
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// دوال مساعدة للأيقونات والألوان
function getRoleIcon($role) {
    $icons = [
        'admin' => 'fa-user-shield',
        'ministry' => 'fa-building',
        'division' => 'fa-sitemap',
        'unit' => 'fa-users-cog'
    ];
    return $icons[$role] ?? 'fa-user';
}

function getGroupIcon($group) {
    $icons = [
        'المستندات' => 'fa-file-alt',
        'الجامعات' => 'fa-university',
        'الكليات' => 'fa-graduation-cap',
        'الشعب' => 'fa-project-diagram',
        'الوحدات' => 'fa-users-cog',
        'المستخدمين' => 'fa-users',
        'التقارير' => 'fa-chart-bar',
        'النظام' => 'fa-cogs'
    ];
    return $icons[$group] ?? 'fa-folder';
}

function getRoleBadgeColor($role) {
    $colors = [
        'admin' => 'danger',
        'ministry' => 'primary',
        'division' => 'success',
        'unit' => 'info'
    ];
    return $colors[$role] ?? 'secondary';
}

function getEntityIcon($type) {
    $icons = [
        'ministry' => 'fa-building',
        'division' => 'fa-sitemap',
        'unit' => 'fa-users-cog'
    ];
    return $icons[$type] ?? 'fa-circle';
}
?>

<script>
// تحسين التأثيرات الحركية للتبويبات
document.querySelectorAll('.nav-link').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.permission-group').forEach((group, index) => {
            group.style.animation = 'none';
            group.offsetHeight;
            group.style.animation = `slideUp 0.5s ease forwards ${index * 0.1}s`;
        });
    });
});

// تحسين تأثيرات تحديد الصلاحيات
document.querySelectorAll('.form-check-input').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const permissionItem = this.closest('.form-check');
        permissionItem.style.animation = 'none';
        permissionItem.offsetHeight;
        permissionItem.style.animation = 'fadeIn 0.3s ease';
        
        const permissionName = this.nextElementSibling.textContent.trim();
        const action = this.checked ? 'تفعيل' : 'إلغاء';
        showNotification(`تم ${action} صلاحية: ${permissionName}`, this.checked ? 'success' : 'warning');
    });
});

// تحديث دالة إظهار التنبيهات
function showNotification(message, type = 'success') {
    // إزالة أي تنبيهات سابقة
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());

    // إنشاء تنبيه جديد
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} ml-2"></i>
        ${message}
    `;
    document.body.appendChild(notification);

    // إظهار التنبيه
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // إخفاء وإزالة التنبيه بعد 3 ثواني
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.5s ease forwards';
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 3000);
}

// تحديث معالجة التنبيهات الموجودة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        notification.classList.add('show');
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.5s ease forwards';
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    });
});

// تحسين تأثير "تحديد الكل"
document.querySelectorAll('.select-all').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const group = this.dataset.group;
        const checkboxes = document.querySelectorAll('.' + group);
        const action = this.checked ? 'تفعيل' : 'إلغاء';
        
        checkboxes.forEach((item, index) => {
            setTimeout(() => {
                item.checked = this.checked;
                item.closest('.form-check').style.animation = 'fadeIn 0.3s ease';
            }, index * 50);
        });
        
        showNotification(`تم ${action} جميع الصلاحيات في المجموعة`, this.checked ? 'success' : 'warning');
    });
});

// تحسين تأكيد حفظ التغييرات
document.querySelectorAll('.permission-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'تأكيد حفظ التغييرات',
            text: 'هل أنت متأكد من حفظ التغييرات على الصلاحيات؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، حفظ التغييرات',
            cancelButtonText: 'إلغاء',
            customClass: {
                confirmButton: 'btn btn-success ms-2',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

// تحسين عرض النوافذ المنبثقة
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('show.bs.modal', function() {
        this.querySelector('.modal-content').style.animation = 'slideUp 0.3s ease';
    });
});
</script>

<!-- إضافة مكتبات إضافية -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<?php include 'footer.php'; ?> 