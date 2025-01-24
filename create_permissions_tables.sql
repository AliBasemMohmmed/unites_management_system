DROP DATABASE IF EXISTS higher_education_db;
CREATE DATABASE higher_education_db;
USE higher_education_db;

-- إنشاء جدول صلاحيات المستخدمين
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_permission (user_id, permission_name)
);

-- إنشاء جدول الصلاحيات الافتراضية للأدوار
CREATE TABLE IF NOT EXISTS role_default_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission_name)
);

-- إنشاء جدول المستندات
CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    file_path VARCHAR(255),
    sender_type ENUM('ministry', 'division', 'unit') NOT NULL,
    sender_id INT NOT NULL,
    receiver_type ENUM('ministry', 'division', 'unit') NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'received', 'processed') DEFAULT 'pending',
    is_broadcast TINYINT(1) DEFAULT 0,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    send_date TIMESTAMP NULL,
    send_notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processor_id INT,
    INDEX (document_id),
    INDEX (processor_id)
);

-- إنشاء جدول مرفقات المستندات
CREATE TABLE IF NOT EXISTS document_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX (document_id)
);

-- إنشاء جدول تعليقات المستندات
CREATE TABLE IF NOT EXISTS document_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX (document_id)
);

-- إنشاء جدول سجل المستندات
CREATE TABLE IF NOT EXISTS document_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX (document_id)
); 