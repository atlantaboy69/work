<?php include __DIR__ . '/includes/functions.php'; if (isLoggedIn()) { header('Location: dashboard.php'); exit; } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - Регистрация</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="centered-body">
    <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <img src="logos/sbermedLogoCyan.svg" alt="Logo" style="height: 45px; width: auto;">
    </div>
    <div class="medai-subtitle">Экосистема медицинских сервисов на базе ИИ</div>

    <div class="auth-card">
        <h2>Регистрация</h2>
        <div class="no-account">Уже есть аккаунт? <a href="index.php?auth=1">Войти</a></div>

        <form method="POST" action="do_register.php">
            <div class="input-field">
                <input type="email" name="login" placeholder="Введите почту..." required>
            </div>
            <div class="input-field">
                <input type="password" name="password" placeholder="Придумайте пароль..." required minlength="3">
            </div>
            <button type="submit" class="btn-submit">Зарегистрироваться</button>
        </form>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
    </div>
    <div class="footer-disclaimer">Не заменяет поход к специалисту</div>
</body>
</html>