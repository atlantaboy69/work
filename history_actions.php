<?php
// history_actions.php - Фоновый AJAX контроллер для управления историей
include __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
requireLogin();

header('Content-Type: application/json');

$userLogin = $_SESSION['user_login'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if (empty($userLogin)) {
    echo json_encode(['success' => false, 'error' => 'Пользователь не авторизован']);
    exit;
}

$action = $data['action'] ?? '';

try {
    // 1. ОДИНОЧНОЕ ПЕРЕИМЕНОВАНИЕ
    if ($action === 'rename') {
        $chatId = $data['chat_id'] ?? '';
        $newTitle = trim($data['new_title'] ?? '');

        if (empty($chatId) || empty($newTitle)) {
            echo json_encode(['success' => false, 'error' => 'Неверные параметры запроса']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE chats SET title = :title WHERE chat_id = :chat_id AND user_login = :login");
        $stmt->execute([
            'title' => $newTitle,
            'chat_id' => $chatId,
            'login' => $userLogin
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 2. ОДИНОЧНОЕ УДАЛЕНИЕ
    if ($action === 'delete') {
        $chatId = $data['chat_id'] ?? '';

        if (empty($chatId)) {
            echo json_encode(['success' => false, 'error' => 'ID чата пуст']);
            exit;
        }

        // Очистка локальных медиа-файлов перед удалением чата
        $stmtMsg = $pdo->prepare("SELECT parts FROM messages WHERE chat_id = :chat_id");
        $stmtMsg->execute(['chat_id' => $chatId]);
        $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as $msg) {
            $parts = is_string($msg['parts']) ? json_decode($msg['parts'], true) : $msg['parts'];
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if (isset($part['_local_image']) && file_exists($part['_local_image'])) {
                        unlink($part['_local_image']);
                    }
                }
            }
        }

        $pdo->beginTransaction();
        
        // Удаляем сообщения
        $stmtDelMsgs = $pdo->prepare("DELETE FROM messages WHERE chat_id = :chat_id");
        $stmtDelMsgs->execute(['chat_id' => $chatId]);

        // Удаляем сам чат
        $stmtDelChat = $pdo->prepare("DELETE FROM chats WHERE chat_id = :chat_id AND user_login = :login");
        $stmtDelChat->execute(['chat_id' => $chatId, 'login' => $userLogin]);
        
        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;
    }

    // 3. МАССОВОЕ УДАЛЕНИЕ
    if ($action === 'bulk_delete') {
        $chatIds = $data['chat_ids'] ?? [];

        if (empty($chatIds) || !is_array($chatIds)) {
            echo json_encode(['success' => false, 'error' => 'Список чатов пуст']);
            exit;
        }

        $pdo->beginTransaction();
        
        foreach ($chatIds as $chatId) {
            // Очистка медиа-файлов
            $stmtMsg = $pdo->prepare("SELECT parts FROM messages WHERE chat_id = :chat_id");
            $stmtMsg->execute(['chat_id' => $chatId]);
            $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as $msg) {
                $parts = is_string($msg['parts']) ? json_decode($msg['parts'], true) : $msg['parts'];
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        if (isset($part['_local_image']) && file_exists($part['_local_image'])) {
                            unlink($part['_local_image']);
                        }
                    }
                }
            }

            // Удаляем сообщения чата
            $stmtDelMsgs = $pdo->prepare("DELETE FROM messages WHERE chat_id = :chat_id");
            $stmtDelMsgs->execute(['chat_id' => $chatId]);

            // Удаляем сам чат
            $stmtDelChat = $pdo->prepare("DELETE FROM chats WHERE chat_id = :chat_id AND user_login = :login");
            $stmtDelChat->execute(['chat_id' => $chatId, 'login' => $userLogin]);
        }
        
        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}