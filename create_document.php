<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

include 'header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h3>إنشاء كتاب جديد</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="process_document.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">معرف الكتاب</label>
                    <input type="text" name="document_id" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">عنوان الكتاب</label>
                    <input type="text" name="title" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">محتوى الكتاب</label>
                    <textarea name="content" class="form-control" rows="5" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">الأولوية</label>
                    <select name="priority" class="form-select">
                        <option value="normal">عادي</option>
                        <option value="important">مهم</option>
                        <option value="urgent">عاجل</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">الملف المرفق</label>
                    <input type="file" name="document_file" class="form-control">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ الكتاب
                    </button>
                    <a href="documents.php" class="btn btn-light">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 