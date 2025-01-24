<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// إضافة عمود updated_at
try {
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS university_id INT AFTER role",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS college_id INT AFTER university_id",
        "ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS fk_users_university FOREIGN KEY (university_id) REFERENCES universities(id)",
        "ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS fk_users_college FOREIGN KEY (college_id) REFERENCES colleges(id)",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];

    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            error_log("خطأ في تنفيذ استعلام التعديل: " . $query . " - " . $e->getMessage());
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في تحديث جدول المستخدمين: " . $e->getMessage());
}

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['add_user'])) {
            // التحقق من عدم تكرار اسم المستخدم والبريد الإلكتروني
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$_POST['username'], $_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل");
            }

            // إضافة مستخدم جديد
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, password, full_name, email, 
                    role, university_id, college_id, 
                    created_at
                ) VALUES (
                    :username, :password, :full_name, :email,
                    :role, :university_id, :college_id,
                    CURRENT_TIMESTAMP
                )
            ");
            
            $stmt->execute([
                'username' => $_POST['username'],
                'password' => $hashedPassword,
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'role' => $_POST['role'],
                'university_id' => $_POST['university_id'],
                'college_id' => isset($_POST['college_id']) ? $_POST['college_id'] : null
            ]);

            $userId = $pdo->lastInsertId();

            // إضافة الصلاحيات المحددة
            if (!empty($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO permissions (user_id, permission_name) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission) {
                    $stmt->execute([$userId, $permission]);
                }
            }

            // جلب معلومات الجامعة والكلية
            $entityInfo = '';
            if ($_POST['university_id']) {
                $stmt = $pdo->prepare("SELECT name FROM universities WHERE id = ?");
                $stmt->execute([$_POST['university_id']]);
                $universityName = $stmt->fetchColumn();
                $entityInfo .= " في " . $universityName;

                if (isset($_POST['college_id']) && $_POST['college_id']) {
                    $stmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
                    $stmt->execute([$_POST['college_id']]);
                    $collegeName = $stmt->fetchColumn();
                    $entityInfo .= " - كلية " . $collegeName;
                }
            }

            // تحديد المسمى الوظيفي
            $roleTitle = '';
            switch($_POST['role']) {
                case 'admin':
                    $roleTitle = "مدير نظام";
                    break;
                case 'division':
                    $roleTitle = "موظف شعبة";
                    break;
                case 'unit':
                    $roleTitle = "موظف وحدة";
                    break;
            }

            // جلب نوع المستخدم
            $stmt = $pdo->prepare("SELECT name FROM user_types WHERE id = ?");
            $stmt->execute([$_POST['user_type_id']]);
            $userTypeName = $stmt->fetchColumn();

            $_SESSION['success'] = sprintf(
                "تم إضافة المستخدم %s بنجاح ك%s%s",
                $_POST['full_name'],
                $roleTitle,
                $entityInfo
            );

            $pdo->commit();
            header('Location: users.php');
            exit;
        }
        elseif (isset($_POST['delete_user'])) {
            // حذف صلاحيات المستخدم أولاً
            $stmt = $pdo->prepare("DELETE FROM permissions WHERE user_id = ?");
            $stmt->execute([$_POST['user_id']]);

            // حذف المستخدم
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);

            $_SESSION['success'] = "تم حذف المستخدم بنجاح";
        }

        $pdo->commit();
        header('Location: users.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
        header('Location: users.php');
        exit;
    }
}

// التحقق من وجود العمود وإضافته
try {
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS university_id INT AFTER role",
        "ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS fk_users_university FOREIGN KEY (university_id) REFERENCES universities(id)"
    ];

    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            error_log("خطأ في تنفيذ استعلام التعديل: " . $query . " - " . $e->getMessage());
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في تحديث جدول المستخدمين: " . $e->getMessage());
}

// تحقق من صلاحيات إدارة المستخدمين
if (!hasPermission('manage_users')) {
  die('غير مصرح لك بإدارة المستخدمين');
}

include 'header.php';
?>

<div class="container mt-4">
  <h2>إدارة المستخدمين</h2>
  
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  
  <div class="card mb-4">
    <div class="card-header">
      إضافة مستخدم جديد
    </div>
    <div class="card-body">
      <form method="POST" action="users.php">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">اسم المستخدم</label>
              <input type="text" name="username" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">الاسم الكامل</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">البريد الإلكتروني</label>
              <input type="email" name="email" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">كلمة المرور</label>
              <input type="password" name="password" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">الدور</label>
              <select name="role" class="form-control" required>
                <option value="">اختر الدور</option>
                <option value="admin">مدير النظام</option>
                <option value="division">موظف شعبة</option>
                <option value="unit">موظف وحدة</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label for="university_select" class="form-label">الجامعة</label>
              <select class="form-select" id="university_select" name="university_id" required>
                <option value="">اختر الجامعة</option>
                <?php
                $stmt = $pdo->query("SELECT id, name FROM universities ORDER BY name");
                while ($row = $stmt->fetch()) {
                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                }
                ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label for="college_select" class="form-label">الكلية</label>
              <select class="form-select" id="college_select" name="college_id">
                <option value="">اختر الكلية</option>
              </select>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">الصلاحيات</label>
          <div class="row">
            <?php
            $permissions = [
                // صلاحيات المستخدمين
                'manage_users' => 'إدارة المستخدمين',
                'view_users' => 'عرض المستخدمين',
                'add_user' => 'إضافة مستخدم',
                'edit_user' => 'تعديل مستخدم',
                'delete_user' => 'حذف مستخدم',
                
                // صلاحيات الجامعات
                'manage_universities' => 'إدارة الجامعات',
                'view_universities' => 'عرض الجامعات',
                'add_university' => 'إضافة جامعة',
                'edit_university' => 'تعديل جامعة',
                'delete_university' => 'حذف جامعة',
                
                // صلاحيات الكليات
                'manage_colleges' => 'إدارة الكليات',
                'view_colleges' => 'عرض الكليات',
                'add_college' => 'إضافة كلية',
                'edit_college' => 'تعديل كلية',
                'delete_college' => 'حذف كلية',
                
                // صلاحيات الأقسام الوزارية
                'manage_ministry_departments' => 'إدارة الأقسام الوزارية',
                'view_ministry_departments' => 'عرض الأقسام الوزارية',
                'add_ministry_department' => 'إضافة قسم وزاري',
                'edit_ministry_department' => 'تعديل قسم وزاري',
                'delete_ministry_department' => 'حذف قسم وزاري',
                
                // صلاحيات الشعب الجامعية
                'manage_divisions' => 'إدارة الشعب الجامعية',
                'view_divisions' => 'عرض الشعب الجامعية',
                'add_division' => 'إضافة شعبة جامعية',
                'edit_division' => 'تعديل شعبة جامعية',
                'delete_division' => 'حذف شعبة جامعية',
                
                // صلاحيات الوحدات
                'manage_units' => 'إدارة الوحدات',
                'view_units' => 'عرض الوحدات',
                'add_unit' => 'إضافة وحدة',
                'edit_unit' => 'تعديل وحدة',
                'delete_unit' => 'حذف وحدة',
                
                // صلاحيات المراسلات والكتب
                'manage_correspondence' => 'إدارة المراسلات',
                'view_correspondence' => 'عرض المراسلات',
                'add_correspondence' => 'إضافة مراسلة',
                'edit_correspondence' => 'تعديل مراسلة',
                'delete_correspondence' => 'حذف مراسلة',
                
                // صلاحيات التقارير
                'manage_reports' => 'إدارة التقارير',
                'view_reports' => 'عرض التقارير',
                'generate_reports' => 'إنشاء التقارير',
                'export_reports' => 'تصدير التقارير',
                
                // صلاحيات النظام
                'view_logs' => 'عرض سجلات النظام',
                'manage_settings' => 'إدارة إعدادات النظام',
                'manage_permissions' => 'إدارة الصلاحيات',
                'view_statistics' => 'عرض الإحصائيات'
            ];
            
            foreach ($permissions as $key => $label): ?>
              <div class="col-md-3">
                <div class="form-check">
                  <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" class="form-check-input">
                  <label class="form-check-label"><?php echo $label; ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary">إضافة المستخدم</button>
      </form>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم المستخدم</th>
              <th>الاسم الكامل</th>
              <th>البريد الإلكتروني</th>
              <th>نوع المستخدم</th>
              <th>الجامعة</th>
              <th>الكلية</th>
              <th>الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       COALESCE(un.name, 'غير محدد') as university_name,
                       COALESCE(c.name, 'غير محدد') as college_name
                FROM users u
                LEFT JOIN universities un ON u.university_id = un.id
                LEFT JOIN colleges c ON u.college_id = c.id
                ORDER BY u.id DESC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                echo "<tr>
                        <td>{$user['id']}</td>
                        <td>{$user['username']}</td>
                        <td>{$user['full_name']}</td>
                        <td>{$user['email']}</td>
                        <td>{$user['role']}</td>
                        <td>{$user['university_name']}</td>
                        <td>" . ($user['role'] === 'unit' ? $user['college_name'] : '-') . "</td>
                        <td>
                            <div class='btn-group'>
                                <button onclick='editUser({$user['id']})' class='btn btn-sm btn-primary'>
                                    <i class='fas fa-edit'></i> تعديل
                                </button>
                                <button onclick='deleteUser({$user['id']})' class='btn btn-sm btn-danger'>
                                    <i class='fas fa-trash'></i> حذف
                                </button>
                            </div>
                        </td>
                    </tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- نافذة تعديل المستخدم -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تعديل المستخدم</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" name="user_id" id="edit_user_id">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">اسم المستخدم</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">الاسم الكامل</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" id="edit_password" class="form-control" placeholder="اتركه فارغاً إذا لم ترد تغيير كلمة المرور">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">الدور</label>
                <select name="role" id="edit_role" class="form-control" required>
                  <option value="">اختر الدور</option>
                  <option value="admin">مدير النظام</option>
                  <option value="division">موظف شعبة</option>
                  <option value="unit">موظف وحدة</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">الجامعة</label>
                <select name="university_id" id="edit_university_id" class="form-control" required>
                  <option value="">اختر الجامعة</option>
                  <?php
                  $universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll();
                  foreach ($universities as $university): ?>
                    <option value="<?php echo $university['id']; ?>"><?php echo htmlspecialchars($university['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">الكلية</label>
                <select name="college_id" id="edit_college_id" class="form-control">
                  <option value="">اختر الكلية</option>
                </select>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" onclick="updateUser()">حفظ التغييرات</button>
      </div>
    </div>
  </div>
</div>

<!-- نافذة حذف المستخدم -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">حذف المستخدم</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>هل أنت متأكد من حذف هذا المستخدم؟</p>
        <input type="hidden" id="delete_user_id">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteUser()">تأكيد الحذف</button>
      </div>
    </div>
  </div>
</div>

<script>
// تحديث الكود الخاص بالأزرار في الجدول
function updateTableButtons() {
    const actionButtons = `
        <div class='btn-group'>
            <button onclick='editUser(USER_ID)' class='btn btn-sm btn-primary'>
                <i class='fas fa-edit'></i> تعديل
            </button>
            <button onclick='deleteUser(USER_ID)' class='btn btn-sm btn-danger'>
                <i class='fas fa-trash'></i> حذف
            </button>
        </div>
    `;
    
    document.querySelectorAll('table tbody tr').forEach(row => {
        const userId = row.cells[0].textContent;
        const actionsCell = row.cells[row.cells.length - 1];
        actionsCell.innerHTML = actionButtons.replace(/USER_ID/g, userId);
    });
}

// دالة تحميل بيانات المستخدم للتعديل
function editUser(userId) {
    fetch(`get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(user => {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_university_id').value = user.university_id || '';
            
            // تحديث قائمة الكليات وتحديد الكلية المختارة
            if (user.university_id && user.role === 'unit') {
                const collegeDiv = document.getElementById('edit_college_id').closest('.mb-3');
                collegeDiv.style.display = 'block';
                
                fetch(`get_colleges.php?university_id=${user.university_id}`)
                    .then(response => response.json())
                    .then(colleges => {
                        const collegeSelect = document.getElementById('edit_college_id');
                        collegeSelect.innerHTML = '<option value="">اختر الكلية</option>';
                        colleges.forEach(college => {
                            const option = document.createElement('option');
                            option.value = college.id;
                            option.textContent = college.name;
                            if (college.id === user.college_id) {
                                option.selected = true;
                            }
                            collegeSelect.appendChild(option);
                        });
                        collegeSelect.required = true;
                    });
            } else {
                const collegeDiv = document.getElementById('edit_college_id').closest('.mb-3');
                collegeDiv.style.display = user.role === 'unit' ? 'block' : 'none';
                document.getElementById('edit_college_id').value = '';
            }
            
            // عرض النافذة المنبثقة
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
}

// دالة تحديث بيانات المستخدم
function updateUser() {
    const formData = new FormData(document.getElementById('editUserForm'));
    formData.append('update_user', '1');
    
    // التحقق من القيم قبل الإرسال
    const role = document.getElementById('edit_role').value;
    const universityId = document.getElementById('edit_university_id').value;
    const collegeId = document.getElementById('edit_college_id').value;
    
    // إذا كان نوع المستخدم وحدة، تأكد من اختيار الكلية
    if (role === 'unit' && !collegeId) {
        alert('يجب اختيار الكلية لمستخدم الوحدة');
        return;
    }
    
    // إذا كان نوع المستخدم ليس وحدة، احذف قيمة الكلية
    if (role !== 'unit') {
        formData.set('college_id', '');
    }
    
    fetch('process_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إغلاق النافذة المنبثقة
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            // تحديث الجدول
            location.reload();
        } else {
            alert(data.error || 'حدث خطأ أثناء تحديث البيانات');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تحديث البيانات');
    });
}

// دالة عرض نافذة حذف المستخدم
function deleteUser(userId) {
    document.getElementById('delete_user_id').value = userId;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

// دالة تأكيد حذف المستخدم
function confirmDeleteUser() {
    const userId = document.getElementById('delete_user_id').value;
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('delete_user', '1');
    
    fetch('process_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تحديث الجدول
            location.reload();
        } else {
            alert(data.error || 'حدث خطأ أثناء حذف المستخدم');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء حذف المستخدم');
    });
}

// تحديث أزرار الجدول عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updateTableButtons();
});

// تحديث قائمة الكليات عند تغيير الجامعة في نموذج التعديل
document.getElementById('edit_university_id').addEventListener('change', function() {
    const universityId = this.value;
    const collegeSelect = document.getElementById('edit_college_id');
    const userRole = document.getElementById('edit_role').value;
    
    collegeSelect.innerHTML = '<option value="">اختر الكلية</option>';
    
    if (universityId && userRole === 'unit') {
        collegeSelect.disabled = true;
        
        fetch(`get_colleges.php?university_id=${universityId}`)
            .then(response => response.json())
            .then(colleges => {
                if (colleges.length === 0) {
                    collegeSelect.innerHTML = '<option value="">لا توجد كليات متاحة</option>';
                } else {
                    colleges.forEach(college => {
                        const option = document.createElement('option');
                        option.value = college.id;
                        option.textContent = college.name;
                        collegeSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                collegeSelect.innerHTML = '<option value="">حدث خطأ في جلب البيانات</option>';
            })
            .finally(() => {
                collegeSelect.disabled = false;
            });
    }
});

// التحكم في ظهور حقل الكلية في نموذج التعديل
document.getElementById('edit_role').addEventListener('change', function() {
    const collegeDiv = document.getElementById('edit_college_id').closest('.mb-3');
    const collegeSelect = document.getElementById('edit_college_id');
    
    if (this.value === 'unit') {
        collegeDiv.style.display = 'block';
        collegeSelect.required = true;
        const universitySelect = document.getElementById('edit_university_id');
        if (universitySelect.value) {
            universitySelect.dispatchEvent(new Event('change'));
        }
    } else {
        collegeDiv.style.display = 'none';
        collegeSelect.required = false;
        collegeSelect.value = '';
    }
});
</script>

<?php include 'footer.php'; ?>
