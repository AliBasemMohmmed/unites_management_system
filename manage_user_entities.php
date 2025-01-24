<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    die('غير مصرح لك بإدارة انتماءات المستخدمين');
}

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    die('معرف المستخدم مطلوب');
}

// جلب معلومات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('المستخدم غير موجود');
}

// جلب انتماءات المستخدم الحالية
$stmt = $pdo->prepare("
    SELECT ue.*, 
        CASE 
            WHEN ue.entity_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = ue.entity_id)
            WHEN ue.entity_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = ue.entity_id)
            WHEN ue.entity_type = 'unit' THEN (SELECT name FROM units WHERE id = ue.entity_id)
        END as entity_name
    FROM user_entities ue
    WHERE ue.user_id = ?
    ORDER BY ue.is_primary DESC, ue.entity_type, entity_name
");
$stmt->execute([$userId]);
$userEntities = $stmt->fetchAll();

// جلب قائمة الجهات المتاحة
$ministryDepts = $pdo->query("SELECT id, name FROM ministry_departments ORDER BY name")->fetchAll();
$divisions = $pdo->query("SELECT id, name FROM university_divisions ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT id, name FROM units ORDER BY name")->fetchAll();

include 'header.php';
?>

<style>
.entity-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.entity-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.role-badge {
    font-size: 0.8rem;
    padding: 0.3rem 0.6rem;
    border-radius: 20px;
}

.role-head {
    background-color: #dc3545;
    color: white;
}

.role-employee {
    background-color: #28a745;
    color: white;
}

.role-secretary {
    background-color: #17a2b8;
    color: white;
}

.role-supervisor {
    background-color: #ffc107;
    color: black;
}

.primary-badge {
    background-color: #007bff;
    color: white;
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    line-height: 32px;
    text-align: center;
    border-radius: 50%;
}

.alert {
    border-radius: 10px;
    margin-bottom: 1rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease;
}
</style>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success animate-fade-in" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger animate-fade-in" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h3 class="mb-0">
                <i class="fas fa-user-cog me-2"></i>
                إدارة أدوار المستخدم: <?php echo htmlspecialchars($user['full_name']); ?>
            </h3>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addEntityModal">
                <i class="fas fa-plus me-1"></i>
                إضافة دور جديد
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($userEntities)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>لا توجد أدوار مضافة لهذا المستخدم</p>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($userEntities as $entity): ?>
                <div class="col-md-6">
                    <div class="entity-card card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($entity['entity_name']); ?>
                                    </h5>
                                    <div class="text-muted small">
                                        <?php
                                        switch ($entity['entity_type']) {
                                            case 'ministry':
                                                echo 'قسم الوزارة';
                                                break;
                                            case 'division':
                                                echo 'شعبة';
                                                break;
                                            case 'unit':
                                                echo 'وحدة';
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge role-badge role-<?php echo $entity['role']; ?>">
                                        <?php
                                        switch ($entity['role']) {
                                            case 'head':
                                                echo 'رئيس';
                                                break;
                                            case 'employee':
                                                echo 'موظف';
                                                break;
                                            case 'secretary':
                                                echo 'سكرتير';
                                                break;
                                            case 'supervisor':
                                                echo 'مشرف';
                                                break;
                                        }
                                        ?>
                                    </span>
                                    <?php if ($entity['is_primary']): ?>
                                    <span class="badge primary-badge ms-1">
                                        <i class="fas fa-star"></i>
                                        رئيسي
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2"
                                        onclick="updateRole('<?php echo $entity['entity_type']; ?>', <?php echo $entity['entity_id']; ?>, '<?php echo $entity['role']; ?>')">
                                    <i class="fas fa-edit"></i>
                                    تعديل الدور
                                </button>
                                <a href="process_user_entity.php?action=remove&user_id=<?php echo $userId; ?>&entity_type=<?php echo $entity['entity_type']; ?>&entity_id=<?php echo $entity['entity_id']; ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('هل أنت متأكد من حذف هذا الدور؟')">
                                    <i class="fas fa-trash"></i>
                                    حذف
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal إضافة دور جديد -->
<div class="modal fade" id="addEntityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    إضافة دور جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_user_entity.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">نوع الجهة</label>
                        <select name="entity_type" class="form-select" required onchange="updateEntitiesList(this.value)">
                            <option value="">اختر نوع الجهة</option>
                            <option value="ministry">قسم الوزارة</option>
                            <option value="division">شعبة</option>
                            <option value="unit">وحدة</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الجهة</label>
                        <select name="entity_id" class="form-select" required>
                            <option value="">اختر الجهة</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الدور</label>
                        <select name="role" class="form-select" required>
                            <option value="head">رئيس</option>
                            <option value="employee" selected>موظف</option>
                            <option value="secretary">سكرتير</option>
                            <option value="supervisor">مشرف</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_primary" class="form-check-input" id="isPrimary">
                            <label class="form-check-label" for="isPrimary">جهة رئيسية</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        حفظ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تعديل الدور -->
<div class="modal fade" id="updateRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    تعديل الدور
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_user_entity.php" method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                <input type="hidden" name="entity_type" id="updateEntityType">
                <input type="hidden" name="entity_id" id="updateEntityId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الدور الجديد</label>
                        <select name="role" class="form-select" required id="updateRole">
                            <option value="head">رئيس</option>
                            <option value="employee">موظف</option>
                            <option value="secretary">سكرتير</option>
                            <option value="supervisor">مشرف</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// تحديث قائمة الجهات عند اختيار النوع
function updateEntitiesList(type) {
    const entitySelect = document.querySelector('select[name="entity_id"]');
    entitySelect.innerHTML = '<option value="">اختر الجهة</option>';
    
    let entities = [];
    switch (type) {
        case 'ministry':
            entities = <?php echo json_encode($ministryDepts); ?>;
            break;
        case 'division':
            entities = <?php echo json_encode($divisions); ?>;
            break;
        case 'unit':
            entities = <?php echo json_encode($units); ?>;
            break;
    }
    
    entities.forEach(entity => {
        const option = document.createElement('option');
        option.value = entity.id;
        option.textContent = entity.name;
        entitySelect.appendChild(option);
    });
}

// فتح نافذة تعديل الدور
function updateRole(entityType, entityId, currentRole) {
    document.getElementById('updateEntityType').value = entityType;
    document.getElementById('updateEntityId').value = entityId;
    document.getElementById('updateRole').value = currentRole;
    
    new bootstrap.Modal(document.getElementById('updateRoleModal')).show();
}

// تنسيق الأدوار والشارات
document.addEventListener('DOMContentLoaded', function() {
    // إخفاء رسائل النجاح والخطأ بعد 5 ثواني
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include 'footer.php'; ?> 