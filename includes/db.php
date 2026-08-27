<?php
// db.php - Централизованное подключение к PostgreSQL (Исправлено под окружение Replit/helium)

$database_url = getenv('DATABASE_URL');
if (empty($database_url)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DATABASE_URL is not set on server']);
    exit;
}

try {
    $dbopts = parse_url($database_url);

    if (!isset($dbopts["host"])) {
        throw new Exception("Не удалось распарсить строку подключения. Убедитесь, что используется протокол postgresql://");
    }

    $host = $dbopts["host"];
    $port = $dbopts["port"] ?? 5432;
    $user = $dbopts["user"] ?? '';
    $pass = $dbopts["pass"] ?? '';
    $dbname = ltrim($dbopts["path"] ?? '', '/');

    // ИСПРАВЛЕНО: Проверяем, передан ли sslmode в query-параметрах самой строки DATABASE_URL
    parse_str($dbopts["query"] ?? '', $queryOptions);

    if (isset($queryOptions['sslmode'])) {
        $sslmode = htmlspecialchars($queryOptions['sslmode']);
    } else {
        // ИСПРАВЛЕНО: Добавляем 'helium' (внутреннее имя контейнера базы в Replit) в белый список локалок
        $isLocal = ($host === '127.0.0.1' || $host === 'localhost' || $host === 'db' || $host === 'helium');
        $sslmode = $isLocal ? 'disable' : 'require';
    }

    // Формируем DSN строку для PDO PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ошибка подключения к БД: ' . $e->getMessage()]);
    exit;
}