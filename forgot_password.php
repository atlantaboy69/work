<?php include __DIR__ . '/includes/functions.php'; if (isLoggedIn()) { header('Location: dashboard.php'); exit; } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedAI - Сброс пароля</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="centered-body">
    <div class="medai-title">MedAI</div>
    <div class="medai-subtitle">Восстановление доступа</div>

    <div class="auth-card">
        <h2>Восстановить пароль</h2>
        <div class="no-account">Вспомнили данные? <a href="index.php">Войти</a></div>

        <form method="POST" action="do_forgot.php">
            <div class="input-field">
                <input type="email" name="login" placeholder="Введите ваш Email..." required>
            </div>
            <button type="submit" class="btn-submit">Отправить ссылку</button>
        </form>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>