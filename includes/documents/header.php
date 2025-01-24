<div class="row mb-3">
    <div class="col">
        <h2>إدارة الكتب والمراسلات</h2>
    </div>
    <div class="col-auto">
        <?php if (hasPermission('create_documents')): ?>
        <a href="create_document.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> إنشاء كتاب جديد
        </a>
        <?php endif; ?>
    </div>
</div> 