<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php'; // Подключаем PDO

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (!$token || strlen($password) < 3) {
    header('Location: index.php?error=Ошибка при изменении пароля');
    exit;
}

try {
    // 1. Проверяем токен на валидность и срок действия
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE reset_token = :token 
          AND reset_token_expires > NOW()
    ");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // 2. Обновляем пароль, сбрасываем токены восстановления и подтверждаем email
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET password = :password, 
                reset_token = NULL, 
                reset_token_expires = NULL, 
                is_verified = true 
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            'password' => $passwordHash,
            'id' => $user['id']
        ]);

        header('Location: index.php?success=Пароль изменен! Войдите, используя новые данные.');
    } else {
        header('Location: index.php?error=Не удалось обновить пароль. Ссылка просрочена.');
    }
    exit;

} catch (PDOException $e) {
    header('Location: index.php?error=Ошибка базы данных при сохранении пароля');
    exit;
}