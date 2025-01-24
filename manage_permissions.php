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

// جلب قائمة المستخدمين مع صلاحياتهم
$users = $pdo->query("
    SELECT u.id, u.username, u.full_name, r.id as role_id, r.name as role_name, r.display_name as role_display_name,
           GROUP_CONCAT(DISTINCT rdp.permission_name) as current_permissions
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN role_default_permissions rdp ON rdp.role_id = r.id
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

// معالجة تحديث الصلاحيات الافتراضية للأدوار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_default_permissions'])) {
    // التحقق من أن الطلب هو طلب AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
              
    if (!$isAjax) {
        header('HTTP/1.1 400 Bad Request');
        die('طلب غير صالح');
    }

    // تنظيف أي مخرجات سابقة
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if (!isset($_POST['role_id'])) {
            throw new Exception("لم يتم تحديد الدور");
        }

        $pdo->beginTransaction();
        
        $role_id = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);
        if (!$role_id) {
            throw new Exception("معرف الدور غير صالح");
        }

        $permissions = isset($_POST['permissions']) ? (array)$_POST['permissions'] : [];
        
        // التحقق من وجود الدور
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        if (!$stmt->fetch()) {
            throw new Exception("الدور غير موجود");
        }
        
        // حذف الصلاحيات القديمة للدور
        $stmt = $pdo->prepare("DELETE FROM role_default_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // إضافة الصلاحيات الجديدة
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_default_permissions (role_id, permission_name) VALUES (?, ?)");
            foreach ($permissions as $permission) {
                // التحقق من صحة اسم الصلاحية
                if (!array_key_exists($permission, $available_permissions)) {
                    continue; // تخطي الصلاحيات غير الصالحة
                }
                try {
                    $stmt->execute([$role_id, $permission]);
                } catch (PDOException $e) {
                    // تجاهل الأخطاء المتعلقة بتكرار القيم
                    if ($e->getCode() != '23000') {
                        throw $e;
                    }
                }
            }
        }
        
        $pdo->commit();
        
        // تسجيل النشاط
        logSystemActivity("تم تحديث صلاحيات الدور: " . $role_id, 'permissions', $_SESSION['user_id']);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'تم تحديث صلاحيات الدور بنجاح'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating permissions: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'حدث خطأ أثناء تحديث الصلاحيات: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
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
                    <div class="permission-card mb-4">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas <?php echo getGroupIcon($groupName); ?> ml-2"></i>
                                    <?php echo $groupName; ?>
                                </h6>
                                <div class="form-check">
                                    <input type="checkbox" 
                                           class="form-check-input select-all" 
                                           id="select-all-<?php echo $role_id; ?>-<?php echo str_replace(' ', '_', $groupName); ?>"
                                           data-group="role<?php echo $role_id; ?>-<?php echo str_replace(' ', '_', $groupName); ?>">
                                    <label class="form-check-label" for="select-all-<?php echo $role_id; ?>-<?php echo str_replace(' ', '_', $groupName); ?>">
                                        تحديد الكل
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="permission-group p-3">
                            <?php foreach ($permissions as $key => $label): ?>
                            <div class="form-check mb-2">
                                <input type="checkbox" 
                                       class="form-check-input role<?php echo $role_id; ?>-<?php echo str_replace(' ', '_', $groupName); ?>"
                                       id="perm-<?php echo $role_id; ?>-<?php echo $key; ?>"
                                       name="permissions[]" 
                                       value="<?php echo $key; ?>" 
                                       <?php echo in_array($key, getUserRolePermissions($role_id)) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="perm-<?php echo $role_id; ?>-<?php echo $key; ?>">
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
document.addEventListener('DOMContentLoaded', function() {
    // معالجة حفظ الصلاحيات
    const permissionForms = document.querySelectorAll('.permission-form');
    permissionForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // إظهار تأكيد الحفظ
            Swal.fire({
                title: 'تأكيد الحفظ',
                text: 'هل أنت متأكد من حفظ هذه التغييرات؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، حفظ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // إظهار رسالة التحميل
                    Swal.fire({
                        title: 'جاري الحفظ...',
                        text: 'يرجى الانتظار...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // إرسال البيانات
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error('استجابة غير صالحة من الخادم');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: 'تم بنجاح!',
                                text: data.message,
                                icon: 'success',
                                timer: 1500
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'فشل تحديث الصلاحيات');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'خطأ!',
                            text: error.message || 'حدث خطأ أثناء حفظ الصلاحيات',
                            icon: 'error'
                        });
                    });
                }
            });
        });
    });

    // تحديث زر تحديد الكل
    const selectAllButtons = document.querySelectorAll('.select-all');
    selectAllButtons.forEach(button => {
        updateSelectAllState(button);
        
        button.addEventListener('click', function() {
            const groupName = this.dataset.group;
            const checkboxes = document.querySelectorAll('input[type="checkbox"].' + groupName);
            const isChecked = this.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });
    });

    // تحديث حالة زر تحديد الكل عند تغيير أي صلاحية
    const permissionCheckboxes = document.querySelectorAll('.permission-group input[type="checkbox"]');
    permissionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const classes = Array.from(this.classList);
            const groupClass = classes.find(cls => cls.startsWith('role'));
            if (groupClass) {
                const selectAllBtn = document.querySelector(`.select-all[data-group="${groupClass}"]`);
                if (selectAllBtn) {
                    updateSelectAllState(selectAllBtn);
                }
            }
        });
    });

    // دالة تحديث حالة زر تحديد الكل
    function updateSelectAllState(selectAllBtn) {
        const groupName = selectAllBtn.dataset.group;
        const checkboxes = document.querySelectorAll('input[type="checkbox"].' + groupName);
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        selectAllBtn.checked = checkedCount === checkboxes.length;
        selectAllBtn.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }
});
</script>

<!-- إضافة مكتبات إضافية -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<?php include 'footer.php'; ?> 