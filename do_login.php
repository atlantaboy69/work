<?php
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = :login");
    $stmt->execute(['login' => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        if (isset($user['is_verified']) && (bool)$user['is_verified'] === false) {
            header('Location: index.php?auth=1&error=Вы не подтвердили свой Email. Пожалуйста, проверьте почту.');
            exit;
        }

        // Очищаем статус гостя и авторизуем пользователя
        unset($_SESSION['is_guest']);
        unset($_SESSION['guest_queries']);
        $_SESSION['user_login'] = $user['login'];
        header('Location: dashboard.php');
        exit;
    }

    header('Location: index.php?auth=1&error=Неверный логин или пароль');
    exit;

} catch (PDOException $e) {
    header('Location: index.php?auth=1&error=Ошибка базы данных при авторизации');
    exit;
}