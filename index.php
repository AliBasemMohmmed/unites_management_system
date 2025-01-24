<?php
require_once 'config.php';
session_start();

// إذا كان المستخدم مسجل دخوله بالفعل، حوله إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit;
}

// التحقق من محاولة تسجيل الدخول
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // التحقق من وجود البيانات المطلوبة
    if (empty($_POST['username']) || empty($_POST['password'])) {
      throw new Exception('يرجى إدخال اسم المستخدم وكلمة المرور');
    }
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // التحقق من المستخدم
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
      throw new Exception('اسم المستخدم أو كلمة المرور غير صحيحة');
    }
    
    // تسجيل الدخول بنجاح
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['entity_id'] = $user['entity_id'];
    
    // تسجيل عملية تسجيل الدخول
    $logLogin = $pdo->prepare("
      INSERT INTO activity_log (user_id, action, entity_type, details) 
      VALUES (?, 'login', 'user', ?)
    ");
    $logLogin->execute([
      $user['id'], 
      json_encode([
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
      ])
    ]);
    
    // تحديث آخر تسجيل دخول
    $updateLastLogin = $pdo->prepare("
      UPDATE users 
      SET last_login = NOW() 
      WHERE id = ?
    ");
    $updateLastLogin->execute([$user['id']]);
    
    header('Location: dashboard.php');
    exit;
    
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <title>تسجيل الدخول</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-form {
      width: 100%;
      max-width: 400px;
      padding: 15px;
      margin: auto;
    }
  </style>
</head>
<body>
  <div class="login-form">
    <div class="card">
      <div class="card-body">
        <h2 class="text-center mb-4">تسجيل الدخول</h2>
        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">اسم المستخدم</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">كلمة المرور</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">دخول</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
