<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php'; // Подключаем PDO ($pdo)

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if (strlen($login) < 3 || strlen($password) < 3) {
    header('Location: register.php?error=Логин и пароль должны быть не короче 3 символов');
    exit;
}

if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=Введите корректный Email адрес');
    exit;
}

try {
    // 1. Проверяем, существует ли уже такой пользователь в БД
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE login = :login");
    $stmtCheck->execute(['login' => $login]);

    if ($stmtCheck->fetch()) {
        header('Location: register.php?error=Пользователь с таким Email уже существует');
        exit;
    }

    // 2. Генерируем уникальный токен подтверждения
    $verificationToken = bin2hex(random_bytes(16));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 3. Вставляем нового пользователя в таблицу
    $stmtInsert = $pdo->prepare("
        INSERT INTO users (login, password, is_verified, verification_token) 
        VALUES (:login, :password, false, :token)
    ");

    $stmtInsert->execute([
        'login' => $login,
        'password' => $passwordHash,
        'token' => $verificationToken
    ]);

    // 4. Логика генерации ссылки с учетом проксирования и подпутей (/sbermedai/)
    $appUrl = getenv('APP_URL');
    if (!empty($appUrl)) {
        $base = rtrim($appUrl, '/');
    } else {
        $prefix = $_SERVER['HTTP_X_FORWARDED_PREFIX'] ?? '';
        if (empty($prefix) && !empty($_SERVER['HTTP_REFERER'])) {
            $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
            if (strpos($refererPath, '/sbermedai') === 0) {
                $prefix = '/sbermedai';
            }
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? "https://" : "http://";
        $base = $protocol . $_SERVER['HTTP_HOST'] . rtrim($prefix, '/');
    }
    $verifyLink = $base . "/verify.php?token=" . $verificationToken;

    $subject = "Подтверждение аккаунта MedAI";
    $message = "Здравствуйте!\n\nВы зарегистрировались на платформе MedAI.\nДля подтверждения вашей электронной почты перейдите по ссылке:\n" . $verifyLink . "\n\nЕсли вы не регистрировались, просто проигнорируйте это письмо.";

    // Вызываем функцию, которая настроена на SMTP Google
    sendMailNotification($login, $subject, $message);

    // Перенаправляем на индексную страницу с уведомлением
    header('Location: index.php?success=На вашу почту отправлено письмо для подтверждения аккаунта.');
    exit;

} catch (PDOException $e) {
    header('Location: register.php?error=Ошибка базы данных: ' . htmlspecialchars($e->getMessage()));
    exit;
}