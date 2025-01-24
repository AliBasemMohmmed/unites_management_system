<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// جلب بيانات المستخدم
$stmt = $pdo->prepare("
    SELECT u.*, r.name as role_name,
           CASE 
             WHEN u.college_id IS NOT NULL THEN (SELECT name FROM colleges WHERE id = u.college_id)
             ELSE 'غير محدد'
           END as entity_name
    FROM users u 
    JOIN roles r ON u.role_id = r.id
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

<!-- إضافة Animate.css -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    .profile-card {
        background: linear-gradient(145deg, #ffffff, #f0f0f0);
        border-radius: 20px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
    }
    
    .profile-header {
        background: linear-gradient(45deg, #007bff, #00a5ff);
        color: white;
        border-radius: 20px 20px 0 0;
        padding: 20px;
    }
    
    .form-control {
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        padding: 12px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(0,123,255,0.2);
        border-color: #007bff;
    }
    
    .btn-primary {
        background: linear-gradient(45deg, #007bff, #00a5ff);
        border: none;
        border-radius: 10px;
        padding: 12px 30px;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,123,255,0.3);
    }
    
    .alert {
        border-radius: 10px;
        animation: fadeInDown 0.5s ease;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .readonly-field {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }
</style>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="profile-card animate__animated animate__fadeIn">
                <div class="profile-header">
                    <h3 class="mb-0 text-center">الملف الشخصي</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success animate__animated animate__fadeInDown">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger animate__animated animate__fadeInDown">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="animate__animated animate__fadeIn animate__delay-1s">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">اسم المستخدم</label>
                                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">الدور</label>
                                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['role_name']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">الاسم الكامل</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">البريد الإلكتروني</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">الجهة</label>
                                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['entity_name'] ?? 'غير محدد'); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-bold">تاريخ الإنشاء</label>
                                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['created_at']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="form-group">
                            <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" minlength="6">
                            <small class="text-muted">اتركها فارغة إذا لم ترد تغيير كلمة المرور</small>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- إضافة Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<?php include 'footer.php'; ?> 