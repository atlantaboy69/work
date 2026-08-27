<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/includes/functions.php'; 

// Проверка авторизации и гостевого доступа
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if (!isset($_GET['auth']) && !isset($_GET['error']) && !isset($_GET['success'])) {
    allowGuestOrRequireLogin();
    if (isGuestLimitExceeded()) {
        $_GET['error'] = 'Вы исчерпали лимит бесплатных запросов (3). Пожалуйста, войдите в систему или зарегистрируйтесь.';
    } else {
        header('Location: dashboard.php');
        exit;
    }
} 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - Авторизация</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .alert-success { background-color: var(--primary-light); color: var(--text-dark); border: 1px solid var(--primary-color); padding: 12px; border-radius: var(--radius-sm); margin-top: 15px; font-size: 14px; text-align: center; }
        .forgot-link { display: block; margin-top: 15px; text-align: center; color: var(--text-muted); font-size: 13px; text-decoration: none; transition: 0.2s; }
        .forgot-link:hover { color: var(--sber-blue); }
    </style>
</head>
<body class="centered-body">
    <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <img src="logos/sbermedLogoCyan.svg" alt="Logo" style="height: 45px; width: auto;">
    </div>
    <div class="medai-subtitle">Экосистема медицинских сервисов на базе ИИ</div>

    <div class="auth-card">
        <h2>Войти</h2>
        <div class="no-account">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></div>

        <form method="POST" action="do_login.php">
            <div class="input-field">
                <input type="email" name="login" placeholder="Ваша почта..." required>
            </div>
            <div class="input-field">
                <input type="password" id="password" name="password" placeholder="Пароль..." required>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="show-pass" onclick="togglePassword()">
                <label for="show-pass">Показать пароль</label>
            </div>
            <button type="submit" class="btn-submit">Войти</button>
            <a href="forgot_password.php" class="forgot-link">Забыли пароль?</a>
        </form>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
    </div>
    <div class="footer-disclaimer">Не заменяет поход к специалисту</div>

    <script>
    function togglePassword() {
        var x = document.getElementById("password");
        if (x.type === "password") {
            x.type = "text";
        } else {
            x.type = "password";
        }
    }
    </script>
</body>
</html>