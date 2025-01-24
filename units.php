<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

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

    /* تأثيرات إضافية للعناصر */
    .form-control, .form-select {
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,123,255,0.1);
    }

    .alert {
        animation: slideIn 0.5s ease-out;
    }

    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: scale(0.9);
    }

    .modal.show .modal-dialog {
        transform: scale(1);
    }

    /* أنماط إضافية لمربع البحث */
    .search-box {
        position: relative;
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

    /* تحسين مظهر الجدول */
    .table {
        margin-bottom: 0;
    }

    .table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .table td {
        vertical-align: middle;
    }

    /* تأثير حركي للبحث */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="bounce-in">إدارة الوحدات</h2>
        <?php if ($userRole === 'admin' || ($userEntityType === 'division' && hasPermission('add_unit'))): ?>
        <button type="button" class="btn btn-primary rounded-pill" onclick="showAddUnitModal()">
            <i class="fas fa-plus-circle me-2"></i>إضافة وحدة جديدة
        </button>
        <?php endif; ?>
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
                            <th>الجامعة</th>
                            <th>الوصف</th>
                            <th>مدير الوحدة</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            if ($userRole === 'admin') {
                                $stmt = $pdo->query("
                                    SELECT u.*, un.name as university_name,
                                           COALESCE(creator.full_name, 'غير معروف') as created_by_name,
                                           COALESCE(updater.full_name, 'غير معروف') as updated_by_name,
                                           COALESCE(users.full_name, 'غير معين') as unit_manager_name,
                                           u.user_id
                                    FROM units u 
                                    LEFT JOIN universities un ON u.university_id = un.id 
                                    LEFT JOIN users creator ON u.created_by = creator.id
                                    LEFT JOIN users updater ON u.updated_by = updater.id
                                    LEFT JOIN users ON u.user_id = users.id
                                    ORDER BY u.id DESC
                                ");
                                
                                if (!$stmt) {
                                    throw new PDOException("فشل في تنفيذ الاستعلام");
                                }
                                
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT u.*, un.name as university_name,
                                           COALESCE(creator.full_name, 'غير معروف') as created_by_name,
                                           COALESCE(updater.full_name, 'غير معروف') as updated_by_name,
                                           COALESCE(users.full_name, 'غير معين') as unit_manager_name,
                                           u.user_id
                                    FROM units u 
                                    INNER JOIN universities un ON u.university_id = un.id 
                                    INNER JOIN university_divisions ud ON un.id = ud.university_id
                                    INNER JOIN user_entities ue ON ud.id = ue.entity_id
                                    LEFT JOIN users creator ON u.created_by = creator.id
                                    LEFT JOIN users updater ON u.updated_by = updater.id
                                    LEFT JOIN users ON u.user_id = users.id
                                    WHERE ue.user_id = ? 
                                    AND ue.entity_type = 'division'
                                    AND ue.is_primary = 1
                                    ORDER BY u.id DESC
                                ");
                                
                                if (!$stmt) {
                                    throw new PDOException("فشل في تحضير الاستعلام");
                                }
                                
                                if (!$stmt->execute([$_SESSION['user_id']])) {
                                    throw new PDOException("فشل في تنفيذ الاستعلام");
                                }
                            }

                            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if ($units === false) {
                                throw new PDOException("فشل في جلب البيانات");
                            }
                            
                            if (empty($units)) {
                                echo "<tr><td colspan='7' class='text-center'>لا توجد وحدات لعرضها</td></tr>";
                            } else {
                                foreach ($units as $row) {
                                    echo "<tr data-unit-id='{$row['id']}'>
                                            <td>{$row['id']}</td>
                                            <td class='unit-name'>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['university_name']) . "</td>
                                            <td class='unit-description'>" . htmlspecialchars($row['description'] ?? '') . "</td>
                                            <td class='unit-manager'>" . htmlspecialchars($row['unit_manager_name']) . "</td>
                                            <td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>
                                            <td class='text-nowrap'>";
                                    
                                    if ($userRole === 'admin' || 
                                        ($userEntityType === 'division' && hasPermission('edit_unit'))) {
                                        echo "<button onclick='editUnit({$row['id']})' class='btn btn-sm btn-outline-primary me-1 rounded-pill'>
                                                <i class='fas fa-edit'></i>
                                              </button>";
                                    }
                                    
                                    if ($userRole === 'admin' || 
                                        ($userEntityType === 'division' && hasPermission('delete_unit'))) {
                                        echo "<button onclick='deleteUnit({$row['id']}, `" . htmlspecialchars($row['name'], ENT_QUOTES) . "`)' 
                                              class='btn btn-sm btn-outline-danger rounded-pill'>
                                                <i class='fas fa-trash'></i>
                                              </button>";
                                    }
                                    echo "</td></tr>";
                                }
                            }
                        } catch (PDOException $e) {
                            error_log("خطأ في عرض الوحدات: " . $e->getMessage() . "\nSQL State: " . $e->getCode() . "\nTrace: " . $e->getTraceAsString());
                            echo "<tr><td colspan='7' class='text-danger'>
                                    <div class='alert alert-danger'>
                                        <i class='fas fa-exclamation-triangle me-2'></i>
                                        حدث خطأ في عرض البيانات. الرجاء المحاولة مرة أخرى لاحقاً.
                                        <br>
                                        <small class='text-muted'>(" . htmlspecialchars($e->getMessage()) . ")</small>
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
                        <label class="form-label">الجامعة</label>
                        <select name="university_id" id="university_select" class="form-select" required>
                            <option value="">اختر الجامعة</option>
                            <?php
                            $universities = $pdo->query("SELECT * FROM universities ORDER BY name")->fetchAll();
                            foreach ($universities as $univ) {
                                echo "<option value='{$univ['id']}'>{$univ['name']}</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الجامعة</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الكلية</label>
                        <select name="college_id" id="college_select" class="form-select" required>
                            <option value="">اختر الكلية</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الكلية</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الشعبة</label>
                        <select name="division_id" id="division_select" class="form-select" required>
                            <option value="">اختر الشعبة</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الشعبة</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">مدير الوحدة</label>
                        <select name="user_id" id="unit_manager_select" class="form-select" required>
                            <option value="">اختر مدير الوحدة</option>
                            <?php
                            try {
                                $users_stmt = $pdo->query("
                                    SELECT id, full_name 
                                    FROM users 
                                    WHERE is_active = 1 
                                    ORDER BY full_name
                                ");
                                $users = $users_stmt->fetchAll();
                                foreach ($users as $user) {
                                    echo "<option value='{$user['id']}'>{$user['full_name']}</option>";
                                }
                            } catch (PDOException $e) {
                                error_log("خطأ في جلب المستخدمين: " . $e->getMessage());
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار مدير الوحدة</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
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
                    <label class="form-label">الجامعة</label>
                    <select name="university_id" id="modal_university_select" class="form-select" required>
                        <option value="">اختر الجامعة</option>
                        ${data.universities.map(univ => `
                            <option value="${univ.id}" ${univ.id == data.university_id ? 'selected' : ''}>
                                ${univ.name}
                            </option>
                        `).join('')}
                    </select>
                    <div class="invalid-feedback">يرجى اختيار الجامعة</div>
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
                    <label class="form-label">الشعبة</label>
                    <select name="division_id" id="modal_division_select" class="form-select" required>
                        <option value="">اختر الشعبة</option>
                        ${data.divisions.map(division => `
                            <option value="${division.id}" ${division.id == data.division_id ? 'selected' : ''}>
                                ${division.name}
                            </option>
                        `).join('')}
                    </select>
                    <div class="invalid-feedback">يرجى اختيار الشعبة</div>
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
            const universityId = document.getElementById('modal_university_select').value;
            const collegeId = document.getElementById('modal_college_select').value;
            const managerSelect = document.getElementById('modal_unit_manager_select');
            
            if (universityId && collegeId) {
                try {
                    const response = await fetch(`get_unit_users.php?university_id=${universityId}&college_id=${collegeId}`);
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

        // إضافة مستمع حدث لتغيير الجامعة في نموذج التعديل
        document.getElementById('modal_university_select').addEventListener('change', async function() {
            const universityId = this.value;
            const collegeSelect = document.getElementById('modal_college_select');
            const divisionSelect = document.getElementById('modal_division_select');
            const managerSelect = document.getElementById('modal_unit_manager_select');
            
            // تفريغ القوائم
            collegeSelect.innerHTML = '<option value="">اختر الكلية</option>';
            divisionSelect.innerHTML = '<option value="">اختر الشعبة</option>';
            managerSelect.innerHTML = '<option value="">اختر مدير الوحدة</option>';
            
            if (universityId) {
                try {
                    // جلب الكليات
                    const collegesResponse = await fetch(`get_available_colleges.php?university_id=${universityId}`);
                    const colleges = await collegesResponse.json();
                    colleges.forEach(college => {
                        const option = document.createElement('option');
                        option.value = college.id;
                        option.textContent = college.name;
                        collegeSelect.appendChild(option);
                    });

                    // جلب الشعب
                    const divisionsResponse = await fetch(`get_divisions.php?university_id=${universityId}`);
                    const divisions = await divisionsResponse.json();
                    divisions.forEach(division => {
                        const option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.name;
                        divisionSelect.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error:', error);
                }
            }
        });

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
document.getElementById('university_select').addEventListener('change', async function() {
    const universityId = this.value;
    const collegeSelect = document.getElementById('college_select');
    const divisionSelect = document.getElementById('division_select');
    
    // تفريغ القوائم
    collegeSelect.innerHTML = '<option value="">اختر الكلية</option>';
    divisionSelect.innerHTML = '<option value="">اختر الشعبة</option>';
    
    if (universityId) {
        try {
            // جلب الكليات
            const collegesResponse = await fetch(`get_available_colleges.php?university_id=${universityId}`);
            const colleges = await collegesResponse.json();
            colleges.forEach(college => {
                const option = document.createElement('option');
                option.value = college.id;
                option.textContent = college.name;
                collegeSelect.appendChild(option);
            });

            // جلب الشعب
            const divisionsResponse = await fetch(`get_divisions.php?university_id=${universityId}`);
            const divisions = await divisionsResponse.json();
            divisions.forEach(division => {
                const option = document.createElement('option');
                option.value = division.id;
                option.textContent = division.name;
                divisionSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error:', error);
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
});

// تحديث معالج تغيير الكلية لجلب مديري الوحدات
document.getElementById('college_select').addEventListener('change', async function() {
    const universityId = document.getElementById('university_select').value;
    const collegeId = this.value;
    const managerSelect = document.getElementById('unit_manager_select');
    
    // تفريغ القائمة
    managerSelect.innerHTML = '<option value="">اختر مدير الوحدة</option>';
    
    if (collegeId && universityId) {
        try {
            // جلب المستخدمين حسب الجامعة والكلية
            const response = await fetch(`get_unit_users.php?university_id=${universityId}&college_id=${collegeId}`);
            const users = await response.json();
            
            if (users.length === 0) {
                managerSelect.innerHTML += '<option value="" disabled>لا يوجد مستخدمين متاحين</option>';
            } else {
                users.forEach(user => {
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

// إزالة الأحداث غير الضرورية
document.getElementById('college_select').removeEventListener('change', loadUnitManagers);
</script>

<?php include 'footer.php'; ?>
