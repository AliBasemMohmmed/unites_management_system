-- جدول المستخدمين
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  role ENUM('admin', 'ministry', 'division', 'unit') NOT NULL,
  entity_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الصلاحيات
CREATE TABLE permissions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  permission_name VARCHAR(50) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
