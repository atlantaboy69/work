<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php'; // Подключаем PDO

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: index.php?error=Токен сброса пароля отсутствует');
    exit;
}

$tokenValid = false;

try {
    // Проверяем, существует ли токен и не истекло ли его время (сравниваем с текущим временем БД)
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE reset_token = :token 
          AND reset_token_expires > NOW()
    ");
    $stmt->execute(['token' => $token]);
    
    if ($stmt->fetch()) {
        $tokenValid = true;
    }
} catch (PDOException $e) {
    header('Location: index.php?error=Ошибка базы данных при проверке токена');
    exit;
}

if (!$tokenValid) {
    header('Location: index.php?error=Ссылка на сброс пароля устарела или недействительна');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - Установка нового пароля</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="centered-body">
    <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <img src="logos/sbermedLogoCyan.svg" alt="Logo" style="height: 45px; width: auto;">
    </div>
    <div class="medai-subtitle">Создание нового пароля</div>

    <div class="auth-card">
        <h2>Новый пароль</h2>
        <form method="POST" action="do_reset_password.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="input-field">
                <input type="password" id="password" name="password" placeholder="Придумайте новый пароль..." required minlength="3">
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="show-pass" onclick="togglePassword()">
                <label for="show-pass">Показать пароль</label>
            </div>
            <button type="submit" class="btn-submit">Сохранить пароль</button>
        </form>
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