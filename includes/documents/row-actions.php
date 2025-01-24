<div class='btn-group'>
    <a href='view_document.php?id=<?php echo $row['id']; ?>' class='btn btn-sm btn-info' title='عرض'>
        <i class='fas fa-eye'></i>
    </a>
    
    <?php if ($row['status'] === 'draft' && hasPermission('send_documents')): ?>
        <a href='send_document.php?id=<?php echo $row['id']; ?>' class='btn btn-sm btn-success' title='إرسال'>
            <i class='fas fa-paper-plane'></i>
        </a>
    <?php endif; ?>
    
    <?php if (hasPermission('delete_documents')): ?>
        <a href='delete_document.php?id=<?php echo $row['id']; ?>' 
           class='btn btn-sm btn-danger' 
           onclick='return confirm("هل أنت متأكد من حذف هذا الكتاب؟")' 
           title='حذف'>
            <i class='fas fa-trash'></i>
        </a>
    <?php endif; ?>
</div> 