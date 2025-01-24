<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من أن المستخدم مدير
if (!isAdmin()) {
    header('Location: index.php');
    exit('غير مصرح لك بالوصول');
}

// معالجة إضافة/تعديل نوع المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['add_type'])) {
            // إضافة نوع مستخدم جديد
            $stmt = $pdo->prepare("INSERT INTO user_types (name, description) VALUES (?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description']]);
            
            $typeId = $pdo->lastInsertId();
            
            // إضافة الصلاحيات المحددة
            if (!empty($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO user_type_permissions (user_type_id, permission_name) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission) {
                    $stmt->execute([$typeId, $permission]);
                }
            }
            
            $_SESSION['success'] = "تم إضافة نوع المستخدم بنجاح";
        }
        elseif (isset($_POST['edit_type'])) {
            $typeId = $_POST['type_id'];
            
            // تحديث معلومات نوع المستخدم
            $stmt = $pdo->prepare("UPDATE user_types SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $typeId]);
            
            // حذف الصلاحيات القديمة
            $stmt = $pdo->prepare("DELETE FROM user_type_permissions WHERE user_type_id = ?");
            $stmt->execute([$typeId]);
            
            // إضافة الصلاحيات الجديدة
            if (!empty($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO user_type_permissions (user_type_id, permission_name) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission) {
                    $stmt->execute([$typeId, $permission]);
                }
            }
            
            $_SESSION['success'] = "تم تحديث نوع المستخدم بنجاح";
        }
        elseif (isset($_POST['delete_type'])) {
            $stmt = $pdo->prepare("DELETE FROM user_types WHERE id = ?");
            $stmt->execute([$_POST['type_id']]);
            $_SESSION['success'] = "تم حذف نوع المستخدم بنجاح";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
}

// جلب قائمة أنواع المستخدمين مع صلاحياتهم
$userTypes = $pdo->query("
    SELECT ut.*, GROUP_CONCAT(utp.permission_name) as permissions
    FROM user_types ut
    LEFT JOIN user_type_permissions utp ON ut.id = utp.user_type_id
    GROUP BY ut.id
    ORDER BY ut.name
")->fetchAll();

// قائمة الصلاحيات المتاحة
$availablePermissions = [
    'إدارة المستخدمين' => 'إدارة حسابات المستخدمين',
    'إدارة الصلاحيات' => 'إدارة صلاحيات المستخدمين',
    'إدارة الوحدات' => 'إدارة الوحدات والأقسام',
    'إدارة الشعب' => 'إدارة الشعب والإدارات',
    'إدارة المستندات' => 'إدارة المستندات والكتب',
    'إدارة التقارير' => 'إدارة التقارير والإحصائيات',
    'إدارة الإعدادات' => 'إدارة إعدادات النظام',
    'عرض لوحة التحكم' => 'الوصول إلى لوحة التحكم',
    'عرض المستندات' => 'عرض المستندات والكتب',
    'إنشاء مستندات' => 'إنشاء مستندات جديدة',
    'عرض التقارير' => 'عرض التقارير والإحصائيات',
    'عرض الإحصائيات' => 'عرض إحصائيات النظام',
    'إدارة موظفي الوحدة' => 'إدارة موظفي الوحدة',
    'إدارة موظفي الشعبة' => 'إدارة موظفي الشعبة'
];

include 'header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>إدارة أنواع المستخدمين</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeModal">
            <i class="fas fa-plus"></i> إضافة نوع مستخدم
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>نوع المستخدم</th>
                            <th>الوصف</th>
                            <th>الصلاحيات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userTypes as $type): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type['name']); ?></td>
                                <td><?php echo htmlspecialchars($type['description']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $permissions = explode(',', $type['permissions']);
                                        echo implode(', ', array_filter($permissions));
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($type['is_active']): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غير نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="editType(<?php echo htmlspecialchars(json_encode($type)); ?>)">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="deleteType(<?php echo $type['id']; ?>)">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة نوع مستخدم -->
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة نوع مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم نوع المستخدم</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الصلاحيات</label>
                        <div class="row">
                            <?php foreach ($availablePermissions as $key => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" 
                                               class="form-check-input" id="perm_<?php echo $key; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_type" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تعديل نوع مستخدم -->
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="type_id" id="editTypeId">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل نوع المستخدم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم نوع المستخدم</label>
                        <input type="text" name="name" id="editTypeName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" id="editTypeDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الصلاحيات</label>
                        <div class="row">
                            <?php foreach ($availablePermissions as $key => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" 
                                               class="form-check-input" id="edit_perm_<?php echo $key; ?>">
                                        <label class="form-check-label" for="edit_perm_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="edit_type" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نموذج حذف نوع المستخدم -->
<form id="deleteTypeForm" method="POST" style="display: none;">
    <input type="hidden" name="type_id" id="deleteTypeId">
    <input type="hidden" name="delete_type" value="1">
</form>

<script>
function editType(type) {
    document.getElementById('editTypeId').value = type.id;
    document.getElementById('editTypeName').value = type.name;
    document.getElementById('editTypeDescription').value = type.description;
    
    // إعادة تعيين جميع الصلاحيات
    document.querySelectorAll('#editTypeModal input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // تحديد الصلاحيات الحالية
    if (type.permissions) {
        const permissions = type.permissions.split(',');
        permissions.forEach(permission => {
            const checkbox = document.getElementById('edit_perm_' + permission);
            if (checkbox) checkbox.checked = true;
        });
    }
    
    new bootstrap.Modal(document.getElementById('editTypeModal')).show();
}

function deleteType(typeId) {
    if (confirm('هل أنت متأكد من حذف هذا النوع من المستخدمين؟')) {
        document.getElementById('deleteTypeId').value = typeId;
        document.getElementById('deleteTypeForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?> 