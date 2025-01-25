<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// إزالة عمود division_id من جدول الوحدات
try {
    $pdo->exec("ALTER TABLE units DROP FOREIGN KEY IF EXISTS fk_units_division");
    $pdo->exec("ALTER TABLE units DROP COLUMN IF EXISTS division_id");
} catch (PDOException $e) {
    error_log("خطأ في إزالة عمود division_id: " . $e->getMessage());
}

// إنشاء جدول الوحدات إذا لم يكن موجوداً
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS units (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            university_id INT,
            description TEXT,
            college_id INT,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            created_by INT,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
            FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    error_log("خطأ في إنشاء جدول الوحدات: " . $e->getMessage());
}

// إضافة بيانات تجريبية إذا كان الجدول فارغاً
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM units");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $pdo->exec("
            INSERT INTO units (name, college_id, description, user_id, is_active, created_by, created_at) 
            VALUES 
            ('وحدة الحاسب الآلي', 1, 'وحدة متخصصة في علوم الحاسب', 1, 1, 1, NOW()),
            ('وحدة الرياضيات', 1, 'وحدة متخصصة في الرياضيات', 2, 1, 1, NOW()),
            ('وحدة اللغة العربية', 2, 'وحدة متخصصة في اللغة العربية', 3, 1, 1, NOW())
        ");
        error_log("تم إضافة بيانات تجريبية للوحدات");
    }
} catch (PDOException $e) {
    error_log("خطأ في إضافة البيانات التجريبية: " . $e->getMessage());
}

// تعريف متغيرات دور المستخدم ونوع الكيان
$userRole = $_SESSION['user_role'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
$userEntityType = $_SESSION['entity_type'] ?? null;
$userEntityId = $_SESSION['entity_id'] ?? null;

// التأكد من وجود role_id
if (!$roleId) {
    die('خطأ: لم يتم العثور على دور المستخدم');
}

// التحقق من الصلاحيات للوصول إلى الصفحة
$stmt = $pdo->prepare("
    SELECT COUNT(*) as has_permission 
    FROM role_default_permissions rdp 
    WHERE rdp.role_id = ? 
    AND rdp.permission_name IN ('view_units', 'manage_units', 'add_unit', 'edit_unit', 'delete_unit', 'all_permissions')
");
$stmt->execute([$roleId]);
$permission = $stmt->fetch(PDO::FETCH_ASSOC);

// إذا كان المستخدم admin أو لديه الصلاحيات المطلوبة
if (!($userRole === 'admin' || $permission['has_permission'] > 0)) {
    die('غير مصرح لك بالوصول إلى هذه الصفحة. الرجاء التواصل مع مدير النظام.');
}

// التحقق من نوع الكيان للمستخدم
try {
    $stmt = $pdo->prepare("
        SELECT entity_type 
        FROM user_entities 
        WHERE user_id = ? 
        AND is_primary = 1 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userEntityType = $stmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    error_log("خطأ في جلب نوع الكيان للمستخدم: " . $e->getMessage());
}

// جلب قائمة الكليات التي لم يتم إنشاء وحدات لها
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        c.id, 
        c.name 
    FROM colleges c
    WHERE c.id NOT IN (
        SELECT DISTINCT college_id 
        FROM units 
        WHERE college_id IS NOT NULL
    )
    ORDER BY c.name
");
$stmt->execute();
$colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<style>
    /* تأثيرات حركية للعناصر */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    .slide-in {
        animation: slideIn 0.5s ease-out;
    }
    
    .bounce-in {
        animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .scale-in {
        animation: scaleIn 0.5s ease-out;
    }
    
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: slideIn 0.5s ease-out;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    
    .btn {
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .table tbody tr {
        transition: all 0.3s ease;
        animation: fadeIn 0.5s ease-in;
        animation-fill-mode: both;
    }
    
    .table tbody tr:hover {
        transform: scale(1.01);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        z-index: 1;
        position: relative;
        background-color: rgba(0,123,255,0.05);
    }

    .table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }
    
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .search-box input {
        padding-right: 30px;
        border-radius: 20px;
        border: 1px solid #ddd;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        box-shadow: 0 0 10px rgba(0,123,255,0.2);
        border-color: #80bdff;
        width: 300px;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes bounceIn {
        0% { transform: scale(0.3); opacity: 0; }
        50% { transform: scale(1.05); }
        70% { transform: scale(0.9); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    @keyframes scaleIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .filter-dropdown {
        min-width: 150px;
    }

    .stats-container {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        flex: 1;
        padding: 15px;
        border-radius: 10px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #007bff;
    }

    .stat-label {
        color: #6c757d;
        font-size: 14px;
    }

    /* تحسينات إضافية */
    .modal-content {
        border-radius: 15px;
        overflow: hidden;
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
    }

    .form-control, .form-select {
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        border-color: #80bdff;
    }

    .alert {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .btn-primary {
        background-color: #007bff;
        border: none;
        box-shadow: 0 2px 4px rgba(0,123,255,0.2);
    }

    .btn-primary:hover {
        background-color: #0056b3;
        box-shadow: 0 4px 8px rgba(0,123,255,0.3);
    }

    .btn-danger {
        background-color: #dc3545;
        border: none;
        box-shadow: 0 2px 4px rgba(220,53,69,0.2);
    }

    .btn-danger:hover {
        background-color: #c82333;
        box-shadow: 0 4px 8px rgba(220,53,69,0.3);
    }
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="bounce-in">إدارة الوحدات</h2>
        <?php if ($userRole === 'admin' || $permission['has_permission'] > 0): ?>
        <button type="button" class="btn btn-primary rounded-pill" onclick="showAddUnitModal()">
            <i class="fas fa-plus-circle me-2"></i>إضافة وحدة جديدة
        </button>
        <?php endif; ?>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number" id="totalUnits">0</div>
            <div class="stat-label">إجمالي الوحدات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="activeUnits">0</div>
            <div class="stat-label">الوحدات النشطة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="recentlyAdded">0</div>
            <div class="stat-label">أضيفت حديثاً</div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success slide-in">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger slide-in">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card scale-in">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <span>الوحدات الحالية</span>
                <div class="search-box">
                    <select class="form-select form-select-sm filter-dropdown" id="sortFilter">
                        <option value="name_asc">الاسم (تصاعدي)</option>
                        <option value="name_desc">الاسم (تنازلي)</option>
                        <option value="date_asc">التاريخ (الأقدم)</option>
                        <option value="date_desc" selected>التاريخ (الأحدث)</option>
                    </select>
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="بحث..." style="width: 250px;">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="unitsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الوحدة</th>
                            <th>الكلية</th>
                            <th>الوصف</th>
                            <th>مدير الوحدة</th>
                            <th>حالة الوحدة</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // تسجيل معلومات التصحيح
                            error_log("دور المستخدم: " . $userRole);
                            error_log("معرف المستخدم: " . $_SESSION['user_id']);

                            // التحقق من صلاحيات المستخدم
                            if ($userRole === 'admin') {
                                $query = "
                                    SELECT 
                                        u.*,
                                        c.name as college_name,
                                        COALESCE(us.full_name, 'غير معين') as unit_manager_name,
                                        cr.full_name as created_by_name
                                    FROM units u 
                                    LEFT JOIN colleges c ON u.college_id = c.id
                                    LEFT JOIN users us ON u.user_id = us.id
                                    LEFT JOIN users cr ON u.created_by = cr.id
                                    ORDER BY u.created_at DESC
                                ";
                                $stmt = $pdo->query($query);
                                error_log("تم تنفيذ استعلام المشرف");
                            } else {
                                // جلب معلومات المستخدم
                                $userQuery = "
                                    SELECT 
                                        u.id,
                                        u.username,
                                        u.college_id,
                                        u.role_id,
                                        r.name as role_name,
                                        c.name as college_name
                                    FROM users u
                                    LEFT JOIN roles r ON u.role_id = r.id
                                    LEFT JOIN colleges c ON u.college_id = c.id
                                    WHERE u.id = ?
                                ";
                                $userStmt = $pdo->prepare($userQuery);
                                $userStmt->execute([$_SESSION['user_id']]);
                                $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                                
                                error_log("معلومات المستخدم: " . print_r($userInfo, true));

                                // التحقق من صلاحيات المستخدم
                                $hasAccess = false;
                                
                                // المشرف له صلاحية كاملة
                                if ($userRole === 'admin') {
                                    $hasAccess = true;
                                }
                                // رئيس الوحدة أو موظف الوحدة يمكنه رؤية وحدات كليته
                                elseif (($userRole === 'unit_head' || $userRole === 'unit_employee') && $userInfo['college_id']) {
                                    $hasAccess = true;
                                }
                                // مستخدم له صلاحية عرض الوحدات
                                elseif (hasPermission('view_units')) {
                                    $hasAccess = true;
                                }

                                if ($hasAccess) {
                                    $query = "
                                        SELECT 
                                            u.*,
                                            c.name as college_name,
                                            COALESCE(us.full_name, 'غير معين') as unit_manager_name,
                                            cr.full_name as created_by_name
                                        FROM units u 
                                        LEFT JOIN colleges c ON u.college_id = c.id
                                        LEFT JOIN users us ON u.user_id = us.id
                                        LEFT JOIN users cr ON u.created_by = cr.id
                                        WHERE 1=1
                                    ";
                                    
                                    $params = [];
                                    
                                    // إذا كان المستخدم ليس مشرفاً، نقيد الوصول لوحدات كليته فقط
                                    if ($userRole !== 'admin' && $userInfo['college_id']) {
                                        $query .= " AND u.college_id = ?";
                                        $params[] = $userInfo['college_id'];
                                    }
                                    
                                    $query .= " ORDER BY u.created_at DESC";
                                    
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute($params);
                                    error_log("تم تنفيذ استعلام المستخدم للكلية: " . ($userInfo['college_id'] ?? 'غير محدد'));
                                } else {
                                    throw new Exception("ليس لديك الصلاحيات الكافية لعرض الوحدات");
                                }
                            }

                            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            error_log("عدد الوحدات التي تم جلبها: " . count($units));

                            if (empty($units)) {
                                echo "<tr><td colspan='8' class='text-center'>لا توجد وحدات لعرضها</td></tr>";
                            } else {
                                foreach ($units as $row) {
                                    $statusClass = $row['is_active'] ? 'success' : 'danger';
                                    $statusText = $row['is_active'] ? 'نشط' : 'غير نشط';
                                    
                                    echo "<tr data-unit-id='{$row['id']}'>
                                            <td>{$row['id']}</td>
                                            <td class='unit-name'>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['college_name']) . "</td>
                                            <td class='unit-description'>" . htmlspecialchars($row['description'] ?? '') . "</td>
                                            <td class='unit-manager'>" . htmlspecialchars($row['unit_manager_name']) . "</td>
                                            <td><span class='badge bg-{$statusClass}'>{$statusText}</span></td>
                                            <td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>
                                            <td class='text-nowrap'>";
                                    
                                    if ($userRole === 'admin' || hasPermission('edit_unit')) {
                                        echo "<button onclick='editUnit({$row['id']})' class='btn btn-sm btn-outline-primary me-1 rounded-pill'>
                                                <i class='fas fa-edit'></i>
                                              </button>";
                                    }
                                    
                                    if ($userRole === 'admin' || hasPermission('delete_unit')) {
                                        echo "<button onclick='deleteUnit({$row['id']}, `" . htmlspecialchars($row['name'], ENT_QUOTES) . "`)' 
                                              class='btn btn-sm btn-outline-danger rounded-pill'>
                                                <i class='fas fa-trash'></i>
                                              </button>";
                                    }
                                    echo "</td></tr>";
                                }
                            }
                        } catch (Exception $e) {
                            error_log("خطأ في عرض الوحدات: " . $e->getMessage());
                            echo "<tr><td colspan='8' class='text-center'>
                                    <div class='alert alert-danger'>
                                        <i class='fas fa-exclamation-triangle me-2'></i>
                                        " . htmlspecialchars($e->getMessage()) . "
                                    </div>
                                </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مودال التعديل -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-labelledby="editUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUnitModalLabel">تعديل الوحدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- سيتم تحميل نموذج التعديل هنا -->
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة وحدة جديدة -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-labelledby="addUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUnitModalLabel">إضافة وحدة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUnitForm" method="POST" action="process_unit.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">اسم الوحدة</label>
                        <input type="text" name="name" class="form-control" required>
                        <div class="invalid-feedback">يرجى إدخال اسم الوحدة</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الكلية</label>
                        <select name="college_id" id="college_select" class="form-select" required>
                            <option value="">اختر الكلية</option>
                            <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo $college['id']; ?>">
                                <?php echo htmlspecialchars($college['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الكلية</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">رئيس الوحدة</label>
                        <select name="user_id" id="unit_manager_select" class="form-select" required>
                            <option value="">اختر رئيس الوحدة</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار رئيس الوحدة</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">حالة الوحدة</label>
                        <select name="is_active" class="form-select">
                            <option value="1">نشط</option>
                            <option value="0">غير نشط</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>حفظ
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل التأثيرات الحركية للعناصر
document.addEventListener('DOMContentLoaded', function() {
    // تأثيرات الصفوف
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        setTimeout(() => {
            row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // تأثيرات البطاقات
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });

    updateStats();
});

// دالة التعديل
async function editUnit(unitId) {
    try {
        const response = await fetch(`get_unit_details.php?id=${unitId}`);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'حدث خطأ أثناء جلب البيانات');
        }

        // تحضير نموذج التعديل
        const modalContent = `
            <form id="editUnitForm" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="${data.id}">
                <input type="hidden" name="action" value="edit">
                
                <div class="mb-3">
                    <label class="form-label">اسم الوحدة</label>
                    <input type="text" name="name" class="form-control" value="${data.name}" required>
                    <div class="invalid-feedback">يرجى إدخال اسم الوحدة</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">الكلية</label>
                    <select name="college_id" id="modal_college_select" class="form-select" required>
                        <option value="">اختر الكلية</option>
                        ${data.colleges.map(college => `
                            <option value="${college.id}" ${college.id == data.college_id ? 'selected' : ''}>
                                ${college.name}
                            </option>
                        `).join('')}
                    </select>
                    <div class="invalid-feedback">يرجى اختيار الكلية</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-control" rows="3">${data.description || ''}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">حالة الوحدة</label>
                    <select name="is_active" class="form-select">
                        <option value="1" ${data.is_active == 1 ? 'selected' : ''}>نشط</option>
                        <option value="0" ${data.is_active == 0 ? 'selected' : ''}>غير نشط</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">مدير الوحدة</label>
                    <select name="user_id" id="modal_unit_manager_select" class="form-select" required>
                        <option value="">اختر مدير الوحدة</option>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار مدير الوحدة</div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>حفظ التعديلات
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>إلغاء
                    </button>
                </div>
            </form>
        `;

        // عرض المودال
        document.querySelector('#editUnitModal .modal-body').innerHTML = modalContent;
        const modal = new bootstrap.Modal(document.getElementById('editUnitModal'));
        modal.show();

        // تحميل مدراء الوحدات المتاحين عند فتح المودال
        const loadUnitManagers = async () => {
            const collegeId = document.getElementById('modal_college_select').value;
            const managerSelect = document.getElementById('modal_unit_manager_select');
            
            if (collegeId) {
                try {
                    const response = await fetch(`get_unit_users.php?college_id=${collegeId}`);
                    const users = await response.json();
                    
                    managerSelect.innerHTML = '<option value="">اختر مدير الوحدة</option>';
                    
                    if (users.length === 0) {
                        managerSelect.innerHTML += '<option value="" disabled>لا يوجد مستخدمين متاحين</option>';
                    } else {
                        users.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.full_name;
                            if (user.id == data.user_id) {
                                option.selected = true;
                            }
                            managerSelect.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    managerSelect.innerHTML = '<option value="">حدث خطأ في جلب البيانات</option>';
                }
            }
        };

        // تحميل مدراء الوحدات عند فتح المودال
        await loadUnitManagers();

        // إضافة مستمع حدث لتغيير الكلية في نموذج التعديل
        document.getElementById('modal_college_select').addEventListener('change', loadUnitManagers);

        // تفعيل الأحداث للنموذج
        const form = document.getElementById('editUnitForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!form.checkValidity()) {
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            try {
                const formData = new FormData(form);
                const response = await fetch('process_unit.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    modal.hide();
                    // تحديث صف الجدول مع تأثير حركي
                    const row = document.querySelector(`tr[data-unit-id="${data.id}"]`);
                    if (row) {
                        // إضافة تأثير وميض للصف
                        row.style.backgroundColor = '#28a745';
                        row.style.transition = 'background-color 0.5s';
                        
                        // تحديث البيانات في الصف
                        row.querySelector('.unit-name').textContent = formData.get('name');
                        row.querySelector('.unit-description').textContent = formData.get('description') || '';
                        
                        // إعادة لون الخلفية بعد الانتهاء
                        setTimeout(() => {
                            row.style.backgroundColor = '';
                        }, 1000);
                    }

                    // عرض رسالة النجاح
                    await showSuccessAnimation();
                    
                    // تحديث الصفحة
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(result.message || 'حدث خطأ أثناء التعديل');
                }
            } catch (error) {
                Swal.fire({
                    title: 'خطأ!',
                    text: error.message,
                    icon: 'error',
                    showClass: {
                        popup: 'animate__animated animate__shakeX'
                    }
                });
            }
        });

    } catch (error) {
        Swal.fire({
            title: 'خطأ!',
            text: error.message,
            icon: 'error',
            showClass: {
                popup: 'animate__animated animate__shakeX'
            }
        });
    }
}

// دالة الحذف
async function deleteUnit(unitId, unitName) {
    const result = await Swal.fire({
        title: 'تأكيد الحذف',
        html: `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <p>هل أنت متأكد من حذف الوحدة "${unitName}"؟</p>
                <p class="text-muted small">لا يمكن التراجع عن هذا الإجراء</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        customClass: {
            confirmButton: 'btn btn-danger mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false,
        showClass: {
            popup: 'animate__animated animate__fadeIn'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOut'
        }
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`delete_unit.php?id=${unitId}`);
            const data = await response.json();

            if (data.success) {
                // حذف الصف مع تأثير حركي
                const row = document.querySelector(`tr[data-unit-id="${unitId}"]`);
                if (row) {
                    row.style.transition = 'all 0.5s ease';
                    row.style.transform = 'translateX(100%)';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                    }, 500);
                }

                await Swal.fire({
                    title: 'تم الحذف!',
                    text: 'تم حذف الوحدة بنجاح',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    timerProgressBar: true,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });

                // تحديث الصفحة
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'حدث خطأ أثناء الحذف');
            }
        } catch (error) {
            Swal.fire({
                title: 'خطأ!',
                text: error.message,
                icon: 'error',
                showClass: {
                    popup: 'animate__animated animate__shakeX'
                }
            });
        }
    }
}

// تحسين دالة عرض النجاح
function showSuccessAnimation() {
    return Swal.fire({
        icon: 'success',
        title: 'تمت العملية بنجاح!',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    });
}

// تفعيل التحديث التلقائي للقوائم المنسدلة في نموذج الإضافة
document.getElementById('college_select').addEventListener('change', async function() {
    const collegeId = this.value;
    const managerSelect = document.getElementById('unit_manager_select');
    
    // تفريغ القائمة
    managerSelect.innerHTML = '<option value="">اختر رئيس الوحدة</option>';
    
    if (collegeId) {
        try {
            // جلب المستخدمين حسب الكلية
            const response = await fetch(`get_unit_users.php?college_id=${collegeId}`);
            const result = await response.json();
            
            console.log('API Response:', result); // للتحقق من البيانات المسترجعة
            
            if (!result.success || !result.data || result.data.length === 0) {
                managerSelect.innerHTML += '<option value="" disabled>لا يوجد رؤساء وحدات متاحين في هذه الكلية</option>';
            } else {
                result.data.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.full_name;
                    managerSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error:', error);
            managerSelect.innerHTML = '<option value="">حدث خطأ في جلب البيانات</option>';
        }
    }
});

// دالة إظهار مودال إضافة وحدة جديدة
function showAddUnitModal() {
    const modal = new bootstrap.Modal(document.getElementById('addUnitModal'));
    modal.show();
}

// تعديل معالج نموذج الإضافة
document.getElementById('addUnitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        e.stopPropagation();
        this.classList.add('was-validated');
        return;
    }

    try {
        const formData = new FormData(this);
        
        // التأكد من وجود قيمة user_id
        const userId = document.getElementById('unit_manager_select').value;
        if (!userId) {
            throw new Error('يرجى اختيار مدير الوحدة');
        }

        // إضافة user_id للنموذج
        formData.set('user_id', userId);
        
        // إضافة معرف المستخدم الحالي كمنشئ للوحدة
        formData.append('created_by', '<?php echo $_SESSION['user_id']; ?>');

        // طباعة البيانات للتأكد من إرسالها
        console.log('Form Data:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        const response = await fetch('process_unit.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        console.log('Server Response:', result);

        if (result.success) {
            // إغلاق المودال
            const modal = bootstrap.Modal.getInstance(document.getElementById('addUnitModal'));
            modal.hide();
            
            // عرض رسالة النجاح
            await showSuccessAnimation();
            
            // تحديث الصفحة
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'حدث خطأ أثناء إضافة الوحدة');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            title: 'خطأ!',
            text: error.message,
            icon: 'error',
            showClass: {
                popup: 'animate__animated animate__shakeX'
            }
        });
    }
});

// دالة البحث في الجدول
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('unitsTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;

        for (let j = 0; j < cells.length - 1; j++) { // نستثني عمود الإجراءات
            const cellText = cells[j].textContent.toLowerCase();
            if (cellText.includes(searchText)) {
                found = true;
                break;
            }
        }

        // تطبيق تأثير حركي عند إظهار/إخفاء الصفوف
        if (found) {
            row.style.display = '';
            row.style.animation = 'fadeIn 0.5s';
        } else {
            row.style.display = 'none';
        }
    }

    updateStats();
});

// دالة تحديث الإحصائيات
function updateStats() {
    const table = document.getElementById('unitsTable');
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    const rows = tbody.getElementsByTagName('tr');
    let totalUnits = 0;
    let activeUnits = 0;
    let recentlyAdded = 0;
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    // حساب الإحصائيات
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.style.display !== 'none') {
            totalUnits++;
            
            // التحقق من حالة الوحدة
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.classList.contains('bg-success')) {
                activeUnits++;
            }

            // التحقق من تاريخ الإضافة
            const dateCell = row.cells[6]; // عمود التاريخ
            if (dateCell && dateCell.textContent) {
                const rowDate = new Date(dateCell.textContent);
                if (rowDate >= thirtyDaysAgo) {
                    recentlyAdded++;
                }
            }
        }
    }

    // تحديث العناصر في الواجهة
    const totalElement = document.getElementById('totalUnits');
    const activeElement = document.getElementById('activeUnits');
    const recentElement = document.getElementById('recentlyAdded');

    if (totalElement) totalElement.textContent = totalUnits;
    if (activeElement) activeElement.textContent = activeUnits;
    if (recentElement) recentElement.textContent = recentlyAdded;
}

// دالة ترتيب الجدول
function sortTable(sortType) {
    const table = document.getElementById('unitsTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(sortType) {
            case 'name_asc':
                aValue = a.querySelector('.unit-name').textContent;
                bValue = b.querySelector('.unit-name').textContent;
                return aValue.localeCompare(bValue, 'ar');
                
            case 'name_desc':
                aValue = a.querySelector('.unit-name').textContent;
                bValue = b.querySelector('.unit-name').textContent;
                return bValue.localeCompare(aValue, 'ar');
                
            case 'date_asc':
                aValue = new Date(a.cells[5].textContent);
                bValue = new Date(b.cells[5].textContent);
                return aValue - bValue;
                
            case 'date_desc':
                aValue = new Date(a.cells[5].textContent);
                bValue = new Date(b.cells[5].textContent);
                return bValue - aValue;
        }
    });
    
    rows.forEach(row => {
        tbody.appendChild(row);
        row.style.animation = 'fadeIn 0.5s';
    });
}

// تفعيل خيارات الترتيب
document.getElementById('sortFilter').addEventListener('change', function() {
    sortTable(this.value);
});
</script>

<?php include 'footer.php'; ?>
