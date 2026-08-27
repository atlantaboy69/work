<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php'; // Подключаем базу данных PostgreSQL

$login = trim($_POST['login'] ?? '');

if (!$login) {
    header('Location: forgot_password.php?error=Укажите Email');
    exit;
}

try {
    // 1. Генерируем уникальный токен сброса пароля
    $resetToken = bin2hex(random_bytes(16));

    // 2. Обновляем токен и ставим срок действия (текущее время + 1 час)
    $stmt = $pdo->prepare("
        UPDATE users 
        SET reset_token = :token, 
            reset_token_expires = NOW() + INTERVAL '1 hour' 
        WHERE login = :login
    ");

    $stmt->execute([
        'token' => $resetToken,
        'login' => $login
    ]);

    // 3. Отправляем письмо только если такой пользователь существовал и строка обновилась
    if ($stmt->rowCount() > 0) {
        // Динамически собираем ссылку восстановления с учетом проксирования и подпутей (/sbermedai/)
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
        $resetLink = $base . "/reset_password.php?token=" . $resetToken;

        $subject = "Восстановление пароля в MedAI";
        $message = "Здравствуйте!\n\nБыл получен запрос на изменение пароля от вашего аккаунта MedAI.\nДля того чтобы задать новый пароль, перейдите по ссылке:\n\n" . $resetLink . "\n\nСсылка активна в течение 1 часа.";

        // Отправка через PHPMailer (настройки берутся из функций)
        sendMailNotification($login, $subject, $message);
    }

} catch (PDOException $e) {
    // В случае технической ошибки базы данных можно залогировать её: error_log($e->getMessage());
    // Но пользователю интерфейс не ломаем ради безопасности
}

// Из соображений безопасности пишем "Успешно" всегда, чтобы исключить подбор Email базы
header('Location: index.php?success=Если этот Email зарегистрирован, на него было отправлено письмо со ссылкой для восстановления.');
exit;