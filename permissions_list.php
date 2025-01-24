<?php
// قائمة الصلاحيات المتاحة في النظام
$available_permissions = [
    // صلاحيات المستندات
    'view_documents' => 'عرض المستندات',
    'create_documents' => 'إنشاء مستندات جديدة',
    'delete_documents' => 'حذف المستندات',
    'edit_documents' => 'تعديل المستندات',
    'manage_documents' => 'إدارة المستندات',
    'archive_documents' => 'أرشفة المستندات',
    'download_documents' => 'تحميل المستندات',
    'share_documents' => 'مشاركة المستندات',
    
    // صلاحيات الجامعات
    'view_universities' => 'عرض الجامعات',
    'add_university' => 'إضافة جامعة',
    'edit_university' => 'تعديل جامعة',
    'delete_university' => 'حذف جامعة',
    'manage_universities' => 'إدارة الجامعات',
    
    // صلاحيات الكليات
    'view_colleges' => 'عرض الكليات',
    'add_college' => 'إضافة كلية',
    'edit_college' => 'تعديل كلية',
    'delete_college' => 'حذف كلية',
    'manage_colleges' => 'إدارة الكليات',
    
    // صلاحيات الشعب
    'view_divisions' => 'عرض الشعب',
    'add_division' => 'إضافة شعبة',
    'edit_division' => 'تعديل شعبة',
    'delete_division' => 'حذف شعبة',
    'manage_divisions' => 'إدارة الشعب',
    
    // صلاحيات الوحدات
    'view_units' => 'عرض الوحدات',
    'add_unit' => 'إضافة وحدة',
    'edit_unit' => 'تعديل وحدة',
    'delete_unit' => 'حذف وحدة',
    'manage_units' => 'إدارة الوحدات',
    
    // صلاحيات المستخدمين
    'view_users' => 'عرض المستخدمين',
    'add_user' => 'إضافة مستخدم',
    'edit_user' => 'تعديل مستخدم',
    'delete_user' => 'حذف مستخدم',
    'manage_users' => 'إدارة المستخدمين',
    
    // صلاحيات التقارير
    'view_reports' => 'عرض التقارير',
    'add_report' => 'إضافة تقرير',
    'edit_report' => 'تعديل تقرير',
    'delete_report' => 'حذف تقرير',
    'manage_reports' => 'إدارة التقارير',
    
    // صلاحيات النظام
    'manage_settings' => 'إدارة إعدادات النظام',
    'view_logs' => 'عرض سجلات النظام',
    'manage_permissions' => 'إدارة الصلاحيات'
];

// الصلاحيات الافتراضية لكل دور
function getDefaultRolePermissions($role) {
    switch($role) {
        case 'admin':
            return [
                // صلاحيات المستندات
                'view_documents',
                'create_documents',
                'delete_documents',
                'edit_documents',
                'manage_documents',
                'archive_documents',
                'download_documents',
                'share_documents',
                
                // صلاحيات النظام
                'manage_settings',
                'view_logs',
                'manage_permissions',
                
                // صلاحيات المستخدمين
                'view_users',
                'add_user',
                'edit_user',
                'delete_user',
                'manage_users',
                
                // صلاحيات الجامعات والكليات والشعب والوحدات
                'view_universities', 'manage_universities',
                'view_colleges', 'manage_colleges',
                'view_divisions', 'manage_divisions',
                'view_units', 'manage_units',
                
                // صلاحيات التقارير
                'view_reports', 'manage_reports'
            ];
            
        case 'ministry':
            return [
                // صلاحيات المستندات
                'view_documents',
                'create_documents',
                'edit_documents',
                'download_documents',
                'share_documents',
                
                // صلاحيات الجامعات والكليات
                'view_universities',
                'view_colleges',
                
                // صلاحيات التقارير
                'view_reports'
            ];
            
        case 'division':
            return [
                // صلاحيات المستندات
                'view_documents',
                'create_documents',
                'edit_documents',
                'download_documents',
                
                // صلاحيات الوحدات
                'view_units',
                'add_unit',
                'edit_unit',
                
                // صلاحيات التقارير
                'view_reports',
                'add_report',
                'edit_report'
            ];
            
        case 'unit':
            return [
                // صلاحيات المستندات
                'view_documents',
                'create_documents',
                'download_documents',
                
                // صلاحيات التقارير
                'view_reports',
                'add_report'
            ];
            
        default:
            return ['view_documents'];
    }
} 