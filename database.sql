-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS education_system;
USE education_system;

-- جدول الأقسام في وزارة التعليم العالي (المستوى الأول)
CREATE TABLE ministry_departments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الجامعات
CREATE TABLE universities (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  location VARCHAR(255),
  ministry_department_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ministry_department_id) REFERENCES ministry_departments(id)
);

-- جدول الشعب في الجامعات (المستوى الثاني)
CREATE TABLE university_divisions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  university_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (university_id) REFERENCES universities(id)
);

-- جدول الكليات
CREATE TABLE colleges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  university_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (university_id) REFERENCES universities(id)
);

-- جدول الوحدات في الكليات (المستوى الثالث)
CREATE TABLE units (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  college_id INT,
  division_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (college_id) REFERENCES colleges(id),
  FOREIGN KEY (division_id) REFERENCES university_divisions(id)
);

-- جدول الكتب والمراسلات
CREATE TABLE documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  document_id VARCHAR(50) UNIQUE NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT,
  file_path VARCHAR(255),
  sender_type ENUM('ministry', 'division', 'unit') NOT NULL,
  sender_id INT NOT NULL,
  receiver_type ENUM('ministry', 'division', 'unit') NOT NULL,
  receiver_id INT NOT NULL,
  status ENUM('pending', 'received', 'processed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول التقارير
CREATE TABLE reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  content TEXT,
  file_path VARCHAR(255),
  unit_id INT,
  document_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (unit_id) REFERENCES units(id),
  FOREIGN KEY (document_id) REFERENCES documents(id)
);

-- جدول المستخدمين
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  role ENUM('admin', 'ministry', 'division', 'unit') NOT NULL,
  entity_id INT NULL,
  university_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (university_id) REFERENCES universities(id)
);

-- جدول الصلاحيات
CREATE TABLE permissions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  permission_name VARCHAR(50) NOT NULL,
  permission_type_id INT,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (permission_type_id) REFERENCES permission_types(id)
);

-- جدول أنواع الصلاحيات
CREATE TABLE permission_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    entity_type ENUM('ministry', 'division', 'unit', 'all') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إضافة فهرس على نوع الكيان
CREATE INDEX idx_permission_types_entity ON permission_types(entity_type);

-- إدخال بيانات تجريبية للمستخدمين
INSERT INTO users (username, password, full_name, email, role, entity_id) VALUES
-- مدير النظام (admin)
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@example.com', 'admin', NULL),

-- مستخدمي الوزارة (ministry)
('ministry1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أحمد محمد', 'ahmed@ministry.gov', 'ministry', 1),
('ministry2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فاطمة علي', 'fatima@ministry.gov', 'ministry', 1),

-- مستخدمي الإدارات (division)
('division1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'خالد عمر', 'khaled@division.gov', 'division', 101),
('division2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مريم سعيد', 'mariam@division.gov', 'division', 102),

-- مستخدمي الوحدات (unit)
('unit1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'عمر حسن', 'omar@unit.gov', 'unit', 201),
('unit2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'نورة سالم', 'noura@unit.gov', 'unit', 202);

-- إضافة الصلاحيات الافتراضية
INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'view_documents' FROM users WHERE role IN ('admin', 'ministry', 'division', 'unit');

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'create_documents' FROM users WHERE role IN ('admin', 'ministry', 'division');

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'manage_users' FROM users WHERE role = 'admin';

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'access_admin_dashboard' FROM users WHERE role = 'admin';

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'manage_departments' FROM users WHERE role IN ('admin', 'ministry');

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'view_reports' FROM users WHERE role IN ('admin', 'ministry', 'division');

INSERT INTO permissions (user_id, permission_name) 
SELECT id, 'create_reports' FROM users WHERE role IN ('admin', 'unit');

-- جدول سجلات النظام
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول التذكيرات
CREATE TABLE reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reminder_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (document_id) REFERENCES documents(id)
);

-- جدول التعليقات
CREATE TABLE document_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('unit', 'college', 'university', 'system') NOT NULL,
    entity_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_document_history ON document_history(document_id);

CREATE TABLE document_copies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_id INT NOT NULL,
    receiver_type ENUM('ministry', 'division', 'unit') NOT NULL,
    receiver_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    file_path VARCHAR(255),
    status ENUM('pending', 'received', 'processed', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    send_notes TEXT,
    process_notes TEXT,
    FOREIGN KEY (original_id) REFERENCES documents(id) ON DELETE CASCADE
);

-- إضافة فهارس لتحسين الأداء
CREATE INDEX idx_document_copies_original ON document_copies(original_id);
CREATE INDEX idx_document_copies_receiver ON document_copies(receiver_type, receiver_id);
CREATE INDEX idx_document_copies_status ON document_copies(status);


CREATE TABLE user_entities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    entity_type ENUM('ministry', 'division', 'unit') NOT NULL,
    entity_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_entity (user_id, entity_type, entity_id)
);

-- إضافة فهارس لتحسين الأداء
CREATE INDEX idx_user_entities_user ON user_entities(user_id);
CREATE INDEX idx_user_entities_entity ON user_entities(entity_type, entity_id);

-- إضافة عمود university_id لجدول المستخدمين
ALTER TABLE users
ADD COLUMN university_id INT AFTER role;

-- إضافة المفتاح الأجنبي
ALTER TABLE users
ADD FOREIGN KEY (university_id) REFERENCES universities(id);

-- إضافة بعض أنواع الصلاحيات الافتراضية
INSERT INTO permission_types (name, description, entity_type) VALUES
('إدارة المستندات', 'صلاحية إنشاء وتعديل وحذف المستندات', 'all'),
('عرض المستندات', 'صلاحية عرض المستندات فقط', 'all'),
('إدارة التقارير', 'صلاحية إنشاء وتعديل التقارير', 'unit'),
('عرض التقارير', 'صلاحية عرض التقارير فقط', 'all'),
('إدارة المستخدمين', 'صلاحية إدارة حسابات المستخدمين', 'ministry'),
('إدارة الإعدادات', 'صلاحية تعديل إعدادات النظام', 'ministry');