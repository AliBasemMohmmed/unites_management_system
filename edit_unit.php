<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'معرف الوحدة غير صحيح';
    header('Location: units.php');
    exit;
}

$unitId = (int)$_GET['id'];

try {
    // جلب بيانات الوحدة
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            c.name as college_name,
            creator.full_name as created_by_name,
            updater.full_name as updated_by_name
        FROM units u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN users creator ON u.created_by = creator.id
        LEFT JOIN users updater ON u.updated_by = updater.id
        WHERE u.id = ?
    ");
    
    $stmt->execute([$unitId]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        $_SESSION['error'] = 'الوحدة غير موجودة';
        header('Location: units.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات الوحدة: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات';
    header('Location: units.php');
    exit;
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit me-2"></i>تعديل الوحدة
                    </h5>
                </div>

                <div class="card-body">
                    <form method="POST" action="process_unit.php" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?php echo $unit['id']; ?>">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="mb-3">
                            <label class="form-label">اسم الوحدة</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($unit['name']); ?>" required>
                            <div class="invalid-feedback">يرجى إدخال اسم الوحدة</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الكلية</label>
                            <select name="college_id" class="form-select" required>
                                <option value="">اختر الكلية</option>
                                <?php
                                $colleges = $pdo->query("SELECT * FROM colleges ORDER BY name")->fetchAll();
                                foreach ($colleges as $college) {
                                    $selected = $college['id'] == $unit['college_id'] ? 'selected' : '';
                                    echo "<option value='{$college['id']}' {$selected}>{$college['name']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار الكلية</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($unit['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">حالة الوحدة</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?php echo $unit['is_active'] == 1 ? 'selected' : ''; ?>>نشط</option>
                                <option value="0" <?php echo $unit['is_active'] == 0 ? 'selected' : ''; ?>>غير نشط</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>حفظ التعديلات
                            </button>
                            <a href="units.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>إلغاء
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-footer text-muted">
                    <small>
                        <i class="fas fa-user me-1"></i>تم الإنشاء بواسطة: <?php echo htmlspecialchars($unit['created_by_name']); ?> | 
                        <i class="fas fa-calendar me-1"></i>تاريخ الإنشاء: <?php echo date('Y-m-d H:i', strtotime($unit['created_at'])); ?>
                        <?php if ($unit['updated_at']): ?>
                            <br>
                            <i class="fas fa-user-edit me-1"></i>آخر تحديث بواسطة: <?php echo htmlspecialchars($unit['updated_by_name']); ?> | 
                            <i class="fas fa-calendar-check me-1"></i>تاريخ التحديث: <?php echo date('Y-m-d H:i', strtotime($unit['updated_at'])); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل التحقق من صحة النموذج
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include 'footer.php'; ?> 