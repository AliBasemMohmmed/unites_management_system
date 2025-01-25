<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
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
    AND rdp.permission_name IN ('view_colleges', 'manage_colleges', 'add_college', 'edit_college', 'delete_college', 'all_permissions')
");
$stmt->execute([$roleId]);
$permission = $stmt->fetch(PDO::FETCH_ASSOC);

// إذا كان المستخدم admin أو لديه الصلاحيات المطلوبة
if (!($userRole === 'admin' || $permission['has_permission'] > 0)) {
    die('غير مصرح لك بالوصول إلى هذه الصفحة. الرجاء التواصل مع مدير النظام.');
}

// التحقق من صلاحية إضافة كلية جديدة
$canAddCollege = $userRole === 'admin' || hasPermission('add_college');

// التحقق من وجود جدول الكليات وإنشائه إذا لم يكن موجوداً
try {
    // إنشاء جدول الكليات إذا لم يكن موجوداً
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS colleges (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            CONSTRAINT fk_colleges_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_colleges_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // إضافة الأعمدة الجديدة إذا لم تكن موجودة
    $alterQueries = [
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS created_by INT AFTER created_at",
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS updated_by INT AFTER updated_at",
        "ALTER TABLE colleges ADD CONSTRAINT IF NOT EXISTS fk_colleges_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
        "ALTER TABLE colleges ADD CONSTRAINT IF NOT EXISTS fk_colleges_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"
    ];

    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            // تجاهل الأخطاء إذا كان العمود أو القيد موجوداً بالفعل
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في إنشاء/تحديث جدول الكليات: " . $e->getMessage());
    die("
        <div class='alert alert-danger'>
            <h4 class='alert-heading'>خطأ في قاعدة البيانات</h4>
            <p>حدث خطأ أثناء إعداد جدول الكليات. الرجاء الاتصال بمسؤول النظام.</p>
            <hr>
            <p class='mb-0'><small>تفاصيل الخطأ: " . htmlspecialchars($e->getMessage()) . "</small></p>
        </div>
    ");
}

// التحقق من الانتماء الرئيسي للمستخدم
$userDivisionId = null;
if ($userEntityType === 'division') {
    $stmt = $pdo->prepare("
        SELECT ue.entity_id 
        FROM user_entities ue 
        WHERE ue.user_id = ? 
        AND ue.entity_type = 'division' 
        AND ue.is_primary = 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $userDivisionId = $result ? $result['entity_id'] : null;
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
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="bounce-in">إدارة كليات جامعة الأنبار</h2>
        <?php if ($canAddCollege): ?>
        <button type="button" class="btn btn-primary rounded-pill" onclick="showAddCollegeModal()">
            <i class="fas fa-plus-circle me-2"></i>إضافة كلية جديدة
        </button>
        <?php endif; ?>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number" id="totalColleges">0</div>
            <div class="stat-label">إجمالي الكليات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="activeColleges">0</div>
            <div class="stat-label">الكليات النشطة</div>
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
                <span>الكليات الحالية</span>
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
                <table class="table table-striped" id="collegesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الكلية</th>
                            <th>تاريخ الإضافة</th>
                            <th>أضيف بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            if ($userRole === 'admin') {
                                $stmt = $pdo->query("
                                    SELECT 
                                        c.id,
                                        c.name,
                                        c.created_at,
                                        c.updated_at,
                                        c.created_by,
                                        c.updated_by,
                                        u.full_name as created_by_name
                                    FROM colleges c
                                    LEFT JOIN users u ON c.created_by = u.id
                                    ORDER BY c.name
                                ");
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        c.id,
                                        c.name,
                                        c.created_at,
                                        c.updated_at,
                                        c.created_by,
                                        c.updated_by,
                                        u.full_name as created_by_name
                                    FROM colleges c
                                    LEFT JOIN users u ON c.created_by = u.id
                                    ORDER BY c.name
                                ");
                                $stmt->execute();
                            }

                            $hasRecords = false;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $hasRecords = true;
                                $createdByName = $row['created_by_name'] ?: 'غير معروف';
                                echo "<tr data-college-id='{$row['id']}'>
                                        <td>{$row['id']}</td>
                                        <td class='college-name'>" . htmlspecialchars($row['name']) . "</td>
                                        <td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>
                                        <td>{$createdByName}</td>
                                        <td class='text-nowrap'>";
                                
                                if ($userRole === 'admin' || hasPermission('edit_college')) {
                                    echo "<button onclick='editCollege({$row['id']})' class='btn btn-sm btn-outline-primary me-1 rounded-pill'>
                                            <i class='fas fa-edit'></i>
                                          </button>";
                                }
                                
                                if ($userRole === 'admin' || hasPermission('delete_college')) {
                                    echo "<button onclick='deleteCollege({$row['id']}, `" . htmlspecialchars($row['name'], ENT_QUOTES) . "`)' 
                                          class='btn btn-sm btn-outline-danger rounded-pill'>
                                            <i class='fas fa-trash'></i>
                                          </button>";
                                }
                                
                                echo "</td></tr>";
                            }

                            if (!$hasRecords) {
                                echo "<tr><td colspan='5' class='text-center'>
                                        <div class='alert alert-info mb-0'>
                                            <i class='fas fa-info-circle me-2'></i>
                                            لا توجد كليات مضافة حتى الآن
                                        </div>
                                      </td></tr>";
                            }

                        } catch (PDOException $e) {
                            error_log("خطأ في عرض الكليات: " . $e->getMessage());
                            echo "<tr><td colspan='5' class='text-center'>
                                    <div class='alert alert-danger mb-0'>
                                        <i class='fas fa-exclamation-triangle me-2'></i>
                                        حدث خطأ في عرض البيانات
                                        <details class='mt-2'>
                                            <summary>تفاصيل الخطأ</summary>
                                            <pre class='text-start mt-2'>" . htmlspecialchars($e->getMessage()) . "</pre>
                                        </details>
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

<!-- مودال إضافة كلية جديدة -->
<div class="modal fade" id="addCollegeModal" tabindex="-1" aria-labelledby="addCollegeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCollegeModalLabel">إضافة كلية جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCollegeForm" method="POST" action="process_college.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">اسم الكلية</label>
                        <input type="text" name="name" class="form-control" required>
                        <div class="invalid-feedback">يرجى إدخال اسم الكلية</div>
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

<!-- مودال تعديل الكلية -->
<div class="modal fade" id="editCollegeModal" tabindex="-1" aria-labelledby="editCollegeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCollegeModalLabel">تعديل الكلية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- سيتم تحميل نموذج التعديل هنا -->
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل التأثيرات الحركية للعناصر
document.addEventListener('DOMContentLoaded', function() {
    // تحديث الإحصائيات
    updateStats();
    
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        setTimeout(() => {
            row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 100);
    });

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

    // تفعيل خيارات التصفية
    document.getElementById('sortFilter').addEventListener('change', function() {
        sortTable(this.value);
    });
});

// دالة تحديث الإحصائيات
function updateStats() {
    const table = document.getElementById('collegesTable');
    const rows = table.getElementsByTagName('tr');
    const totalColleges = rows.length - 1; // نطرح 1 لاستبعاد صف العناوين
    
    // تحديث إجمالي الكليات
    document.getElementById('totalColleges').textContent = totalColleges;
    
    // تحديث الكليات النشطة (نفترض أن جميع الكليات نشطة حالياً)
    document.getElementById('activeColleges').textContent = totalColleges;
    
    // حساب الكليات المضافة حديثاً (خلال آخر 30 يوم)
    let recentlyAdded = 0;
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    for (let i = 1; i < rows.length; i++) {
        const dateCell = rows[i].cells[2].textContent;
        const rowDate = new Date(dateCell);
        if (rowDate >= thirtyDaysAgo) {
            recentlyAdded++;
        }
    }
    document.getElementById('recentlyAdded').textContent = recentlyAdded;
}

// دالة ترتيب الجدول
function sortTable(sortType) {
    const table = document.getElementById('collegesTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(sortType) {
            case 'name_asc':
                aValue = a.querySelector('.college-name').textContent;
                bValue = b.querySelector('.college-name').textContent;
                return aValue.localeCompare(bValue, 'ar');
                
            case 'name_desc':
                aValue = a.querySelector('.college-name').textContent;
                bValue = b.querySelector('.college-name').textContent;
                return bValue.localeCompare(aValue, 'ar');
                
            case 'date_asc':
                aValue = new Date(a.cells[2].textContent);
                bValue = new Date(b.cells[2].textContent);
                return aValue - bValue;
                
            case 'date_desc':
                aValue = new Date(a.cells[2].textContent);
                bValue = new Date(b.cells[2].textContent);
                return bValue - aValue;
        }
    });
    
    // إعادة ترتيب الصفوف في الجدول
    rows.forEach(row => {
        tbody.appendChild(row);
        row.style.animation = 'fadeIn 0.5s';
    });
}

// دالة إظهار مودال إضافة كلية جديدة
function showAddCollegeModal() {
    const modal = new bootstrap.Modal(document.getElementById('addCollegeModal'));
    modal.show();
}

// دالة التعديل
async function editCollege(collegeId) {
    try {
        const response = await fetch(`get_college_details.php?id=${collegeId}`);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'حدث خطأ أثناء جلب البيانات');
        }

        const modalContent = `
            <form id="editCollegeForm" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="${data.id}">
                <input type="hidden" name="action" value="edit">
                
                <div class="mb-3">
                    <label class="form-label">اسم الكلية</label>
                    <input type="text" name="name" class="form-control" value="${data.name}" required>
                    <div class="invalid-feedback">يرجى إدخال اسم الكلية</div>
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

        document.querySelector('#editCollegeModal .modal-body').innerHTML = modalContent;
        const modal = new bootstrap.Modal(document.getElementById('editCollegeModal'));
        modal.show();

        // تفعيل معالجة النموذج
        document.getElementById('editCollegeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!e.target.checkValidity()) {
                e.stopPropagation();
                e.target.classList.add('was-validated');
                return;
            }

            try {
                const formData = new FormData(e.target);
                formData.append('updated_by', '<?php echo $_SESSION['user_id']; ?>');
                
                const response = await fetch('process_college.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    modal.hide();
                    await showSuccessAnimation();
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
async function deleteCollege(collegeId, collegeName) {
    const result = await Swal.fire({
        title: 'تأكيد الحذف',
        html: `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <p>هل أنت متأكد من حذف الكلية "${collegeName}"؟</p>
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
            const response = await fetch(`delete_college.php?id=${collegeId}`);
            const data = await response.json();

            if (data.success) {
                const row = document.querySelector(`tr[data-college-id="${collegeId}"]`);
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
                    text: 'تم حذف الكلية بنجاح',
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

// دالة عرض رسالة النجاح
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

// دالة البحث في الجدول
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('collegesTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;

        for (let j = 0; j < cells.length - 1; j++) {
            const cellText = cells[j].textContent.toLowerCase();
            if (cellText.includes(searchText)) {
                found = true;
                break;
            }
        }

        if (found) {
            row.style.display = '';
            row.style.animation = 'fadeIn 0.5s';
        } else {
            row.style.display = 'none';
        }
    }
    
    // تحديث الإحصائيات بعد البحث
    updateStats();
});

// معالجة نموذج الإضافة
document.getElementById('addCollegeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        e.stopPropagation();
        this.classList.add('was-validated');
        return;
    }

    try {
        const formData = new FormData(this);
        formData.append('created_by', '<?php echo $_SESSION['user_id']; ?>');

        const response = await fetch('process_college.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCollegeModal'));
            modal.hide();
            
            await showSuccessAnimation();
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'حدث خطأ أثناء إضافة الكلية');
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
</script>

<?php include 'footer.php'; ?> 