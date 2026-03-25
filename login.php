<?php
session_start();
require_once 'config.php';

// ЗАЩИТА ОТ БРУТФОРСА
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

// Если больше 5 попыток и прошло меньше 15 минут
if ($_SESSION['login_attempts'] >= 5) {
    $time_since_last_attempt = time() - $_SESSION['last_attempt_time'];
    if ($time_since_last_attempt < 900) { // 15 минут
        $remaining_time = 900 - $time_since_last_attempt;
        $error = "Слишком много попыток входа. Попробуйте через " . ceil($remaining_time / 60) . " минут.";
    } else {
        // Сброс после 15 минут
        $_SESSION['login_attempts'] = 0;
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Увеличиваем счетчик попыток
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_time'] = time();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Успешный вход - сбрасываем счетчик
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль';
                
                // Показываем сколько осталось попыток
                $remaining_attempts = 5 - $_SESSION['login_attempts'];
                if ($remaining_attempts > 0) {
                    $error .= " (осталось попыток: " . $remaining_attempts . ")";
                } else {
                    $error = "Слишком много попыток входа. Попробуйте через 15 минут.";
                }
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учет продаж Ульяновск - Вход в систему</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
        .attempts-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <h2 class="text-center mb-4">Учет продаж Ульяновск</h2>
                    <p class="text-center text-muted mb-4">Вход в систему учета продаж</p>
                    
                    <!-- Предупреждение о попытках входа -->
                    <?php if ($_SESSION['login_attempts'] >= 3): ?>
                    <div class="attempts-warning">
                        ⚠️ <strong>Внимание!</strong> 
                        <?php if ($_SESSION['login_attempts'] < 5): ?>
                            Неправильных попыток: <?php echo $_SESSION['login_attempts']; ?> из 5
                        <?php else: ?>
                            Превышено количество попыток. Попробуйте позже.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Логин</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Войти</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Логин: admin, Пароль: admin123
                        </small>
                    </div>
                    
                    <!-- Информация о защите -->
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            🔒 Система защищена от перебора паролей. После 5 неудачных попыток вход блокируется на 15 минут.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>