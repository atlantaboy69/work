<?php
// delete_chat.php
include __DIR__ . '/includes/functions.php';
allowGuestOrRequireLogin();
require __DIR__ . '/includes/db.php';

$chatId    = $_GET['chat_id'] ?? '';
$userLogin = $_SESSION['user_login'] ?? '';
$isAjax    = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function jsonError(string $msg): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function jsonOk(): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if (empty($chatId)) {
    if ($isAjax) jsonError('chat_id не указан');
    header('Location: dashboard.php');
    exit;
}

$redirectPage = 'student_chat.php';

try {
    $stmtCheck = $pdo->prepare("SELECT mode FROM chats WHERE chat_id = :chat_id AND user_login = :login");
    $stmtCheck->execute(['chat_id' => $chatId, 'login' => $userLogin]);
    $chat = $stmtCheck->fetch();

    if (!$chat) {
        // Чат не найден или не принадлежит пользователю
        if ($isAjax) jsonError('Чат не найден');
        header('Location: dashboard.php');
        exit;
    }

    if ($chat['mode'] === 'patient') {
        $redirectPage = 'patient_chat.php';
    }

    // Удаляем прикреплённые локальные файлы из сообщений
    $stmtMsg = $pdo->prepare("SELECT parts FROM messages WHERE chat_id = :chat_id");
    $stmtMsg->execute(['chat_id' => $chatId]);
    foreach ($stmtMsg->fetchAll() as $msg) {
        $parts = is_string($msg['parts']) ? json_decode($msg['parts'], true) : $msg['parts'];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (isset($part['_local_image']) && file_exists($part['_local_image'])) {
                    unlink($part['_local_image']);
                }
            }
        }
    }

    // Удаляем из БД в транзакции
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM messages WHERE chat_id = :chat_id")->execute(['chat_id' => $chatId]);
    $pdo->prepare("DELETE FROM chats WHERE chat_id = :chat_id AND user_login = :login")->execute(['chat_id' => $chatId, 'login' => $userLogin]);
    $pdo->commit();

    if ($isAjax) jsonOk();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($isAjax) jsonError($e->getMessage());
}

// Обычный браузерный переход — редиректим назад или на чат-страницу
$referer = $_SERVER['HTTP_REFERER'] ?? $redirectPage;
if (strpos($referer, 'chat_id=' . $chatId) !== false) {
    header('Location: ' . $redirectPage);
} else {
    header('Location: ' . $referer);
}
exit;
