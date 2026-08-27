<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php'; // Подключаем наше PDO ($pdo)

$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: index.php?error=Токен подтверждения не найден');
    exit;
}

try {
    // 1. Ищем пользователя с таким токеном активации
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = :token");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Активируем аккаунт и очищаем токен
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET is_verified = true, 
                verification_token = NULL 
            WHERE id = :id
        ");
        $stmtUpdate->execute(['id' => $user['id']]);

        header('Location: index.php?success=Email успешно подтвержден! Теперь вы можете войти в систему.');
    } else {
        header('Location: index.php?error=Неверный или устаревший токен активации');
    }
    exit;

} catch (PDOException $e) {
    header('Location: index.php?error=Ошибка базы данных при подтверждении email');
    exit;
}