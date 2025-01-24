<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// جلب بيانات المستخدم
$stmt = $pdo->prepare("
    SELECT u.*, 
           CASE 
             WHEN u.role = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = u.entity_id)
             WHEN u.role = 'division' THEN (SELECT name FROM university_divisions WHERE id = u.entity_id)
             WHEN u.role = 'unit' THEN (SELECT name FROM units WHERE id = u.entity_id)
           END as entity_name
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من البريد الإلكتروني
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('البريد الإلكتروني غير صالح');
        }

        // تحديث البيانات الأساسية
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, 
                email = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['email'],
            $_SESSION['user_id']
        ]);

        // تحديث كلمة المرور إذا تم إدخالها
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([
                password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                $_SESSION['user_id']
            ]);
        }

        $success = 'تم تحديث البيانات بنجاح';
        
        // تحديث اسم المستخدم في الجلسة
        $_SESSION['user_name'] = $_POST['full_name'];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">الملف الشخصي</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الدور</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role']); ?>" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">الاسم الكامل</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">الجهة</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['entity_name'] ?? 'غير محدد'); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الإنشاء</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['created_at']); ?>" readonly>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" minlength="6">
                            <div class="form-text">اتركها فارغة إذا لم ترد تغيير كلمة المرور</div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 