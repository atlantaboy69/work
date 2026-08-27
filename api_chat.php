<?php
// api_chat.php - Прозрачный шлюз на FastAPI (GigaChat-2-Max) с проверкой гостевого лимита
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$logFile = __DIR__ . '/logs/rag_debug.log';
$logPHP = function(string $msg) use ($logFile) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - [PHP Gateway] " . $msg . "\n", FILE_APPEND);
};

// ПУНКТ 4: Проверяем гостевой лимит запросов (максимум 3 бесплатных запроса)
if (isGuestLimitExceeded()) {
    $logPHP("Гостевой лимит запросов на сегодня исчерпан (требуется регистрация)");
    echo json_encode([
        'response' => 'Вы исчерпали дневной лимит бесплатных запросов (3 запроса в день). Пожалуйста, войдите в систему или зарегистрируйтесь для неограниченного доступа к MedAI.',
        'require_auth' => true,
        'chat_id'  => $_POST['chat_id'] ?? ''
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim($_POST['query'] ?? '');
$logPHP("Получен запрос от пользователя: '" . $query . "'");

$uploadsDir = __DIR__ . '/uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Асинхронная загрузка файлов
if (isset($_POST['async_upload']) && $_POST['async_upload'] == '1') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('img_') . '.' . $ext;
        $targetPath = $uploadsDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $logPHP("Асинхронно сохранен файл: " . $file['name'] . " -> " . 'uploads/' . $newFileName . " (Размер: " . $file['size'] . " байт)");
            echo json_encode([
                'success' => true, 
                'path'    => 'uploads/' . $newFileName, 
                'mime'    => mime_content_type($targetPath) ?: $file['type'], 
                'name'    => $file['name']
            ]);
            exit;
        }
    }
    $logPHP("Ошибка асинхронной загрузки файла");
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
    exit;
}

// Отправка в FastAPI бэкенд
$fastapiUrl = getenv('FASTAPI_URL') ?: "http://127.0.0.1:8000/api/chat"; 
$postFields = [
    'query'      => $query,
    'mode'       => $_POST['mode'] ?? 'student',
    'university' => trim($_POST['university'] ?? ''),
    'chat_id'    => $_POST['chat_id'] ?? '',
    'user_login' => $_SESSION['user_login'] ?? 'guest'
];

$fileIndex = 0;
if (isset($_POST['uploaded_files']) && is_array($_POST['uploaded_files'])) {
    foreach ($_POST['uploaded_files'] as $index => $filePath) {
        $fullPath = __DIR__ . '/' . ltrim($filePath, '/');
        if (file_exists($fullPath)) {
            $originalName = $_POST['uploaded_names'][$index] ?? basename($fullPath);
            $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            $postFields["files[$fileIndex]"] = new CURLFile($fullPath, $mime, $originalName);
            $logPHP("Передача файла в FastAPI: " . $originalName . " (" . $mime . ", " . filesize($fullPath) . " байт)");
            $fileIndex++;
        } else {
            $logPHP("Предупреждение: файл не найден по пути " . $fullPath);
        }
    }
}

$ch = curl_init($fastapiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 120
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Очистка файлов
if (isset($_POST['uploaded_files']) && is_array($_POST['uploaded_files'])) {
    foreach ($_POST['uploaded_files'] as $filePath) {
        $fullPath = __DIR__ . '/' . ltrim($filePath, '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}

if ($httpCode === 200 && $response) {
    // Если запрос прошел успешно и пользователь гость — увеличиваем счётчик запросов
    if (!empty($_SESSION['is_guest'])) {
        incrementGuestQueryCount();
    }
    $logPHP("Ответ от FastAPI получен успешно.");
    echo $response;
} else {
    $logPHP("Ошибка запроса к FastAPI. HTTP " . $httpCode . ", cURL error: " . $curlError);
    $errorMsg = 'Ошибка взаимодействия с ИИ-сервисом (Код ' . $httpCode . ')';
    if ($curlError) {
        $errorMsg .= ': ' . $curlError;
    }
    echo json_encode([
        'response' => $errorMsg,
        'chat_id'  => $_POST['chat_id'] ?? ''
    ], JSON_UNESCAPED_UNICODE);
}
?>