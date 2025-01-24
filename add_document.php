<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
if (!hasPermission('add_document')) {
    die('ليس لديك صلاحية لإضافة كتب جديدة');
}

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من البيانات المطلوبة
        if (empty($_POST['title']) || empty($_POST['content'])) {
            throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
        }

        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $senderType = $_POST['sender_type'];
        $senderId = $_POST['sender_id'];
        $receiverType = $_POST['receiver_type'];
        $receiverId = $_POST['receiver_id'];

        // معالجة الملف المرفق
        $filePath = null;
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = getAllowedFileTypes();
            $fileType = $_FILES['document_file']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('نوع الملف غير مسموح به');
            }

            if ($_FILES['document_file']['size'] > getMaxFileSize()) {
                throw new Exception('حجم الملف كبير جداً');
            }

            $fileName = sanitizeFileName($_FILES['document_file']['name']);
            $uploadDir = UPLOAD_DIR;
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . uniqid() . '_' . $fileName;
            
            if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
                throw new Exception('فشل في رفع الملف');
            }
        }

        // إضافة الكتاب إلى قاعدة البيانات
        $pdo->beginTransaction();

        // إنشاء معرف فريد للكتاب
        $documentId = generateUniqueDocumentId();

        $stmt = $pdo->prepare("
            INSERT INTO documents (
                document_id, title, content, file_path, 
                sender_type, sender_id, 
                receiver_type, receiver_id,
                status, created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
        ");

        $stmt->execute([
            $documentId, $title, $content, $filePath,
            $senderType, $senderId,
            $receiverType, $receiverId,
            $_SESSION['user_id']
        ]);

        $documentId = $pdo->lastInsertId();

        // إضافة سجل في التاريخ
        $stmt = $pdo->prepare("
            INSERT INTO document_history (document_id, user_id, action, notes)
            VALUES (?, ?, 'create', 'تم إنشاء الكتاب')
        ");
        $stmt->execute([$documentId, $_SESSION['user_id']]);

        // جلب معلومات المرسل
        $senderName = '';
        switch ($senderType) {
            case 'ministry':
                $stmt = $pdo->prepare("SELECT name FROM ministry_departments WHERE id = ?");
                break;
            case 'division':
                $stmt = $pdo->prepare("SELECT name FROM university_divisions WHERE id = ?");
                break;
            case 'unit':
                $stmt = $pdo->prepare("SELECT name FROM units WHERE id = ?");
                break;
        }
        if ($stmt) {
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();
            $senderName = $sender['name'] ?? 'غير معروف';
        }

        // إرسال إشعار للجهة المستلمة
        // جلب المستخدمين المرتبطين بالجهة المستلمة
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN user_entities ue ON u.id = ue.user_id 
            WHERE ue.entity_type = ? AND ue.entity_id = ?
            AND u.role IN ('unit_head', 'division_head', 'department_head') -- فقط رؤساء الوحدات والشعب والأقسام
        ");
        $stmt->execute([$receiverType, $receiverId]);
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            // إدخال الإشعار في قاعدة البيانات
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    sender_id, receiver_id, title, message, 
                    type, entity_id, is_read, icon, color
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, 0, 'fas fa-envelope', 'primary'
                )
            ");

            $notificationTitle = "كتاب جديد";
            $notificationMessage = "تم استلام كتاب جديد بعنوان: $title من $senderName";

            $stmt->execute([
                $_SESSION['user_id'],  // sender_id (المستخدم الحالي)
                $user['id'],           // receiver_id
                $notificationTitle,     // title
                $notificationMessage,   // message
                $receiverType,         // type (نوع الجهة المستلمة)
                $receiverId            // entity_id (معرف الجهة المستلمة)
            ]);
        }

        $pdo->commit();

        // إعادة التوجيه إلى صفحة عرض الكتاب
        header("Location: view_document.php?id=$documentId&success=1");
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

include 'header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.form-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.form-control {
    border-radius: 10px;
    padding: 10px 15px;
    border: 2px solid #ddd;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.custom-file-input {
    cursor: pointer;
}

.submit-btn {
    border-radius: 20px;
    padding: 10px 30px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.alert {
    border-radius: 10px;
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<div class="container mt-4">
    <div class="form-container">
        <div class="card form-card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    إضافة كتاب جديد
                </h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">عنوان الكتاب</label>
                        <input type="text" name="title" class="form-control" required>
                        <div class="invalid-feedback">يرجى إدخال عنوان الكتاب</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">محتوى الكتاب</label>
                        <textarea name="content" class="form-control" rows="5" required></textarea>
                        <div class="invalid-feedback">يرجى إدخال محتوى الكتاب</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع المرسل</label>
                            <select name="sender_type" class="form-select" required>
                                <option value="">اختر نوع المرسل</option>
                                <option value="ministry">قسم الوزارة</option>
                                <option value="division">شعبة</option>
                                <option value="unit">وحدة</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المرسل</label>
                            <select name="sender_id" class="form-select" required>
                                <option value="">اختر المرسل</option>
                                <!-- سيتم ملء هذه القائمة عبر JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع المستلم</label>
                            <select name="receiver_type" class="form-select" required>
                                <option value="">اختر نوع المستلم</option>
                                <option value="ministry">قسم الوزارة</option>
                                <option value="division">شعبة</option>
                                <option value="unit">وحدة</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المستلم</label>
                            <select name="receiver_id" class="form-select" required>
                                <option value="">اختر المستلم</option>
                                <!-- سيتم ملء هذه القائمة عبر JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الملف المرفق</label>
                        <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <small class="text-muted">
                            الملفات المسموح بها: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG
                            (الحد الأقصى: <?php echo formatFileSize(getMaxFileSize()); ?>)
                        </small>
                    </div>

                    <div class="text-end">
                        <a href="documents.php" class="btn btn-secondary me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary submit-btn">
                            <i class="fas fa-save me-2"></i>
                            حفظ الكتاب
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// التحقق من صحة النموذج
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// تحديث قوائم المرسل والمستلم
async function updateEntityList(type, targetSelect) {
    try {
        const response = await fetch(`get_entities.php?type=${type}`);
        const data = await response.json();
        
        if (data.success) {
            targetSelect.innerHTML = '<option value="">اختر...</option>';
            data.entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.id;
                option.textContent = entity.name;
                targetSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('خطأ في جلب البيانات:', error);
    }
}

// ربط الأحداث بتغيير نوع المرسل والمستلم
document.querySelector('[name="sender_type"]').addEventListener('change', function() {
    updateEntityList(this.value, document.querySelector('[name="sender_id"]'));
});

document.querySelector('[name="receiver_type"]').addEventListener('change', function() {
    updateEntityList(this.value, document.querySelector('[name="receiver_id"]'));
});
</script>

<?php include 'footer.php'; ?> 