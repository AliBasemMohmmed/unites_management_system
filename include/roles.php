<?php
// تعريف الأدوار والصلاحيات
$roles = [
    'admin' => [
        'title' => 'مدير النظام',
        'permissions' => [
            'manage_users',
            'manage_roles',
            'manage_settings',
            'view_all_documents',
            'manage_all_documents',
            'manage_departments',
            'view_reports',
            'manage_reports',
            'view_statistics',
            'manage_archive',
            'system_backup'
        ]
    ],
    'ministry' => [
        'title' => 'موظف وزارة',
        'permissions' => [
            'view_ministry_documents',
            'create_ministry_documents',
            'process_documents',
            'view_reports',
            'create_reports',
            'view_statistics'
        ]
    ],
    'division' => [
        'title' => 'موظف شعبة',
        'permissions' => [
            'view_division_documents',
            'create_division_documents',
            'process_division_documents',
            'view_division_reports',
            'create_division_reports'
        ]
    ],
    'unit' => [
        'title' => 'موظف وحدة',
        'permissions' => [
            'view_unit_documents',
            'create_unit_documents',
            'view_unit_reports',
            'create_unit_reports'
        ]
    ]
];

// دالة للتحقق من صلاحيات المستخدم
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// دالة للتحقق من الصلاحيات المتعددة
function hasAnyRole($roles) {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles);
}

// دالة للحصول على عنوان الدور
function getRoleTitle($role) {
    global $roles;
    return $roles[$role]['title'] ?? $role;
}

// دالة للحصول على صلاحيات الدور
function getRolePermissions($role) {
    global $roles;
    return $roles[$role]['permissions'] ?? [];
}

// دالة للتحقق من صلاحية معينة
function checkPermission($permission) {
    if (hasRole('admin')) return true;
    
    $userRole = $_SESSION['user_role'] ?? '';
    $rolePermissions = getRolePermissions($userRole);
    
    return in_array($permission, $rolePermissions);
}

// دالة لتوجيه المستخدم حسب دوره
function redirectBasedOnRole() {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'ministry':
            header('Location: ministry_dashboard.php');
            break;
        case 'division':
            header('Location: division_dashboard.php');
            break;
        case 'unit':
            header('Location: unit_dashboard.php');
            break;
        default:
            header('Location: dashboard.php');
    }
    exit;
}

// دالة لعرض القائمة حسب دور المستخدم
function getMenuByRole() {
    $menu = [];
    
    switch ($_SESSION['user_role']) {
        case 'admin':
            $menu = [
                'dashboard' => ['icon' => 'tachometer-alt', 'title' => 'لوحة التحكم'],
                'users' => ['icon' => 'users', 'title' => 'المستخدمين'],
                'departments' => ['icon' => 'building', 'title' => 'الأقسام'],
                'documents' => ['icon' => 'file-alt', 'title' => 'الكتب'],
                'reports' => ['icon' => 'chart-bar', 'title' => 'التقارير'],
                'archive' => ['icon' => 'archive', 'title' => 'الأرشيف'],
                'settings' => ['icon' => 'cog', 'title' => 'الإعدادات']
            ];
            break;
            
        case 'ministry':
            $menu = [
                'dashboard' => ['icon' => 'tachometer-alt', 'title' => 'لوحة التحكم'],
                'documents' => ['icon' => 'file-alt', 'title' => 'الكتب'],
                'reports' => ['icon' => 'chart-bar', 'title' => 'التقارير'],
                'statistics' => ['icon' => 'chart-line', 'title' => 'الإحصائيات']
            ];
            break;
            
        case 'division':
            $menu = [
                'dashboard' => ['icon' => 'tachometer-alt', 'title' => 'لوحة التحكم'],
                'division_documents' => ['icon' => 'file-alt', 'title' => 'كتب الشعبة'],
                'division_reports' => ['icon' => 'chart-bar', 'title' => 'تقارير الشعبة']
            ];
            break;
            
        case 'unit':
            $menu = [
                'dashboard' => ['icon' => 'tachometer-alt', 'title' => 'لوحة التحكم'],
                'unit_documents' => ['icon' => 'file-alt', 'title' => 'كتب الوحدة'],
                'unit_reports' => ['icon' => 'chart-bar', 'title' => 'تقارير الوحدة']
            ];
            break;
    }
    
    return $menu;
}
?>
