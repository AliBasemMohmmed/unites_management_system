<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// إضافة عمود updated_at
try {
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS college_id INT AFTER role",
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

// إضافة مستخدم جديد
if (isset($_POST['add_user'])) {
    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $roleId = $_POST['role_id'];
        $collegeId = $_POST['college_id'];

        // التحقق من البيانات
        if (empty($username) || empty($password) || empty($fullName) || empty($email) || empty($roleId) || empty($collegeId)) {
            throw new Exception('جميع الحقول مطلوبة');
        }

        // التحقق من عدم تكرار اسم المستخدم والبريد الإلكتروني
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل');
        }

        // إضافة المستخدم
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role_id, college_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $email,
            $roleId,
            $collegeId
        ]);

        $_SESSION['success'] = 'تم إضافة المستخدم بنجاح';
        header('Location: users.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container mt-4">
    <h2>إدارة المستخدمين</h2>

    <div id="alerts"></div>

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
            <form method="POST" action="users.php" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">اسم المستخدم</label>
                            <input type="text" name="username" class="form-control" required>
                            <div class="invalid-feedback">يرجى إدخال اسم المستخدم</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" name="full_name" class="form-control" required>
                            <div class="invalid-feedback">يرجى إدخال الاسم الكامل</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" required>
                            <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">كلمة المرور</label>
                            <input type="password" name="password" class="form-control" required>
                            <div class="invalid-feedback">يرجى إدخال كلمة المرور</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">نوع المستخدم</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">اختر نوع المستخدم</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, display_name FROM roles WHERE name != 'admin' ORDER BY id");
                                while ($role = $stmt->fetch()) {
                                    echo "<option value='{$role['id']}'>{$role['display_name']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار نوع المستخدم</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">الكلية</label>
                            <select name="college_id" class="form-select" required>
                                <option value="">اختر الكلية</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
                                while ($college = $stmt->fetch()) {
                                    echo "<option value='{$college['id']}'>{$college['name']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار الكلية</div>
                        </div>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">إضافة المستخدم</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            المستخدمون الحاليون
        </div>
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
                            <th>الكلية</th>
                            <th>آخر تسجيل دخول</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT u.*, 
                                   COALESCE(c.name, 'غير محدد') as college_name,
                                   r.display_name as role_name
                            FROM users u
                            LEFT JOIN colleges c ON u.college_id = c.id
                            LEFT JOIN roles r ON u.role_id = r.id
                            ORDER BY u.id DESC
                        ");
                        
                        while ($user = $stmt->fetch()) {
                            echo "<tr>
                                    <td>{$user['id']}</td>
                                    <td>{$user['username']}</td>
                                    <td>{$user['full_name']}</td>
                                    <td>{$user['email']}</td>
                                    <td>{$user['role_name']}</td>
                                    <td>{$user['college_name']}</td>
                                    <td>" . ($user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول بعد') . "</td>
                                    <td>
                                        <button onclick='editUser({$user['id']})' class='btn btn-sm btn-primary'>
                                            <i class='fas fa-edit'></i> تعديل
                                        </button>
                                        <button onclick='deleteUser({$user['id']})' class='btn btn-sm btn-danger'>
                                            <i class='fas fa-trash'></i> حذف
                                        </button>
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

<!-- مودال تعديل المستخدم -->
<div class="modal fade" id="editUserModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل المستخدم</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="form-group">
                        <label for="edit_username">اسم المستخدم</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_full_name">الاسم الكامل</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">كلمة المرور (اتركها فارغة إذا لم ترد تغييرها)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="form-group">
                        <label for="edit_role_id">نوع المستخدم</label>
                        <select class="form-control" id="edit_role_id" name="role_id" required>
                            <option value="">اختر نوع المستخدم</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, display_name FROM roles WHERE name != 'admin' ORDER BY id");
                            while ($role = $stmt->fetch()) {
                                echo "<option value='{$role['id']}'>{$role['display_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_college_id">الكلية</label>
                        <select class="form-control" id="edit_college_id" name="college_id" required>
                            <option value="">اختر الكلية</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
                            while ($college = $stmt->fetch()) {
                                echo "<option value='{$college['id']}'>{$college['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="updateUser()">حفظ التغييرات</button>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل التحقق من النموذج
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

function editUser(userId) {
    $.ajax({
        url: 'get_user_details.php',
        type: 'POST',
        data: { user_id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var user = response.data;
                $('#edit_user_id').val(user.id);
                $('#edit_username').val(user.username);
                $('#edit_full_name').val(user.full_name);
                $('#edit_email').val(user.email);
                $('#edit_role_id').val(user.role_id);
                $('#edit_college_id').val(user.college_id);
                $('#editUserModal').modal('show');
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'حدث خطأ أثناء جلب بيانات المستخدم');
        }
    });
}

function updateUser() {
    var formData = new FormData($('#editUserForm')[0]);
    
    $.ajax({
        url: 'process_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editUserModal').modal('hide');
                showAlert('success', response.message);
                refreshUserTable();
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'حدث خطأ أثناء تحديث بيانات المستخدم');
        }
    });
}

function deleteUser(userId) {
    if (confirm('هل أنت متأكد من حذف هذا المستخدم؟')) {
        $.ajax({
            url: 'delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    refreshUserTable();
                } else {
                    showAlert('error', response.message);
                }
            },
            error: function() {
                showAlert('error', 'حدث خطأ أثناء حذف المستخدم');
            }
        });
    }
}

function refreshUserTable() {
    location.reload();
}

function showAlert(type, message) {
    var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show">' +
                    message +
                    '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                    '</div>';
    
    $('#alerts').html(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}
</script>

<?php include 'footer.php'; ?>
