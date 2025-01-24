<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">حالة الكتاب</label>
                <select name="status" class="form-select">
                    <option value="">الكل</option>
                    <option value="draft" <?php echo ($_GET['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>مسودة</option>
                    <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>قيد الإرسال</option>
                    <option value="sent" <?php echo ($_GET['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>تم الإرسال</option>
                    <option value="received" <?php echo ($_GET['status'] ?? '') === 'received' ? 'selected' : ''; ?>>تم الاستلام</option>
                    <option value="processed" <?php echo ($_GET['status'] ?? '') === 'processed' ? 'selected' : ''; ?>>تمت المعالجة</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $_GET['start_date'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $_GET['end_date'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">بحث</label>
                <input type="text" name="search" class="form-control" placeholder="ابحث في العنوان أو المحتوى..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-secondary" id="filterBtn">
                    <i class="fas fa-filter"></i> تطبيق الفلتر
                </button>
            </div>
        </form>
    </div>
</div> 