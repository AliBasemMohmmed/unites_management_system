<?php
require_once 'functions.php';
require_once 'auth.php';
require_once 'config.php';

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
        setUserEntityInfo($user['id']);
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Tajawal', sans-serif;
    }
    .login-form {
      width: 100%;
      max-width: 400px;
      padding: 15px;
      margin: auto;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.9);
      transform: translateY(0);
      transition: all 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }
    .form-control {
      border-radius: 10px;
      padding: 12px;
      border: 2px solid #eee;
      transition: all 0.3s ease;
    }
    .form-control:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
    }
    .btn-primary {
      border-radius: 10px;
      padding: 12px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      background: linear-gradient(45deg, #007bff, #00bfff);
      border: none;
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,123,255,0.4);
    }
    .form-label {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 8px;
    }
    .error-message {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      padding: 12px;
      border-radius: 10px;
      margin-bottom: 20px;
      border: 1px solid rgba(220, 53, 69, 0.2);
      font-size: 0.9rem;
      text-align: center;
      opacity: 0;
      transform: translateY(-10px);
      animation: fadeInDown 0.5s ease forwards;
    }
    @keyframes fadeInDown {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="login-form animate__animated animate__fadeIn">
    <div class="card">
      <div class="card-body p-4">
        <h2 class="text-center mb-4 animate__animated animate__fadeInDown">تسجيل الدخول</h2>
        <?php if (isset($error)): ?>
          <div class="error-message">
            <?php echo $error; ?>
          </div>
        <?php endif; ?>
        <form method="POST" class="animate__animated animate__fadeInUp">
          <div class="mb-3">
            <label class="form-label">اسم المستخدم</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">كلمة المرور</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 mt-3">دخول</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
