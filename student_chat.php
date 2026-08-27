<?php 
include __DIR__ . '/includes/functions.php'; 
allowGuestOrRequireLogin(); 
require __DIR__ . '/includes/db.php';

$isGuest = !empty($_SESSION['is_guest']);
$userLogin = $_SESSION['user_login'] ?? '';

$myChats = [];
$currentMessages = [];
$chatId = '';

// История чатов и загрузка сообщений доступны только авторизованным пользователям
if (!$isGuest) {
    $chatId = $_GET['chat_id'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT chat_id, title, updated_at FROM chats WHERE user_login = :login AND mode = 'student' ORDER BY updated_at DESC");
        $stmt->execute(['login' => $userLogin]);
        $myChats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if (!empty($chatId)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT 1 FROM chats WHERE chat_id = :chat_id AND user_login = :login AND mode = 'student'");
            $stmtCheck->execute(['chat_id' => $chatId, 'login' => $userLogin]);
            if ($stmtCheck->fetch()) {
                $stmtMsg = $pdo->prepare("SELECT role, parts FROM messages WHERE chat_id = :chat_id ORDER BY id ASC");
                $stmtMsg->execute(['chat_id' => $chatId]);
                $currentMessages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $chatId = '';
            }
        } catch (PDOException $e) {
            $currentMessages = [];
        }
    }
}

$isEmpty = empty($currentMessages);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedAI - Обучение</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '\\[', right: '\\]', display: true}, {left: '\\(', right: '\\)', display: false}, {left: '$', right: '$', display: false}], throwOnError: false});"></script>
    <style>
        .btn-new-chat { background: #10b981; color: white; border: none; padding: 12px; border-radius: 8px; transition: background-color 0.2s ease, transform 0.1s ease; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 15px; }
        .btn-new-chat:hover { background-color: #059669; }
        .btn-new-chat:active { transform: scale(0.98); }
        .history-item { display: flex; align-items: center; padding: 10px; color: #334155; text-decoration: none; border-radius: 6px; margin-bottom: 5px; font-size: 14px; background: #f8fafc; transition: 0.2s; }
        .history-item span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .history-item:hover { background: #e2e8f0; }
        .history-item.active { background: var(--sber-blue-hover); color: white; }
        .history-item-wrapper { position: relative; margin-bottom: 5px; }
        @media (hover: hover) {
            .delete-chat-btn { opacity: 0; pointer-events: none; transition: opacity 0.2s ease-in-out, background-color 0.2s; }
            .history-item-wrapper:hover .delete-chat-btn { opacity: 1; pointer-events: auto; }
            .delete-chat-btn:hover { background-color: rgba(239, 68, 68, 0.1); }
        }
        .msg { position: relative; }
        .ai-msg { padding-right: 44px !important; max-width: 85% !important; width: fit-content !important; }

        .empty-center-header img {
            display: none !important;
        }
        <?php if ($isGuest): ?>
        /* Скрытие кнопки вызова меню на мобильных для гостей */
        .menu-toggle-btn { display: none !important; }
        <?php endif; ?>
    </style>
</head>
<body class="chat-body">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <?php include __DIR__ . '/includes/header_chat.php'; ?>

    <div class="chat-layout">
        <?php if (!$isGuest): ?>
        <aside class="chat-sidebar">
            <a href="student_chat.php" class="btn-new-chat">
                <svg class="inline-icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Новый диалог
            </a>
            <div style="font-size: 13px; color: #64748b; margin-bottom: 10px; text-transform: uppercase; font-weight: bold;">Ваши чаты</div>

            <?php foreach ($myChats as $c): ?>
                <?php $isActive = ($c['chat_id'] === $chatId); ?>
                <div class="history-item-wrapper">
                    <a href="?chat_id=<?= $c['chat_id'] ?>" class="history-item <?= $isActive ? 'active' : '' ?>" title="<?= htmlspecialchars($c['title']) ?>" style="margin-bottom: 0; padding-right: 40px; width: 100%;">
                        <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                        <span><?= htmlspecialchars($c['title']) ?></span>
                    </a>
                    <a href="delete_chat.php?chat_id=<?= $c['chat_id'] ?>" class="delete-chat-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: <?= $isActive ? '#fecaca' : '#ef4444' ?>; z-index: 10; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px;" title="Удалить диалог">
                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12z"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (empty($myChats)): ?>
                <div style="font-size: 13px; color: #94a3b8; text-align: center; margin-top: 20px;">Нет прошлых чатов</div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>

        <div class="chat-main">
            <div class="chat-container<?= $isEmpty ? ' chat-is-empty' : '' ?>">

                <div class="empty-center-header">
                    <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" class="empty-logo">
                    
                    <div class="mode-info-block">
                        <h2 class="mode-info-title">Учебный ассистент MedAI</h2>
                        <p class="mode-info-subtitle">Интеллектуальный ИИ-помощник в изучении медицины, анатомии и латинского языка</p>
                        
                        <div class="mode-features-grid">
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Документов / Проектов (Из ГигаЧата) -->
                                    <svg viewBox="0 0 24 24" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path fill-rule="evenodd" clip-rule="evenodd" d="M13.25 2C13.4489 2 13.6396 2.07908 13.7803 2.21973L19.7803 8.21973C19.9209 8.36038 20 8.55109 20 8.75V17.25C20 17.9377 20.001 18.4986 19.9639 18.9531C19.926 19.4164 19.8446 19.8349 19.6455 20.2256C19.3339 20.837 18.837 21.3339 18.2256 21.6455C17.8349 21.8446 17.4164 21.926 16.9531 21.9639C16.4986 22.001 15.9377 22 15.25 22H9.78125L10.2754 20.5H15.25C15.9624 20.5 16.4518 20.4997 16.8311 20.4688C17.2015 20.4385 17.4009 20.383 17.5449 20.3096C17.874 20.1418 18.1418 19.874 18.3096 19.5449C18.383 19.4009 18.4385 19.2015 18.4688 18.8311C18.4997 18.4518 18.5 17.9624 18.5 17.25V9.5H16.4502C15.9025 9.5 15.4463 9.50103 15.0752 9.47071C14.6955 9.43967 14.3391 9.37191 14.002 9.2002C13.4845 8.93655 13.0635 8.5155 12.7998 7.99805C12.6281 7.66091 12.5603 7.30454 12.5293 6.92481C12.499 6.55368 12.5 6.09748 12.5 5.54981V3.5H8.75C8.03756 3.5 7.5482 3.50027 7.16894 3.53125C6.79851 3.56153 6.5991 3.61705 6.45508 3.69043C6.12597 3.85818 5.85818 4.12597 5.69043 4.45508C5.61704 4.59911 5.56152 4.79852 5.53125 5.16895C5.50026 5.5482 5.5 6.03756 5.5 6.75V13.9668C5.308 14.2588 5.03788 14.4938 4.7168 14.6406L4.52344 14.7158L4 14.8877V6.75C4 6.06231 3.999 5.50138 4.03613 5.04688C4.07398 4.58363 4.1554 4.16515 4.35449 3.77442C4.66605 3.16305 5.16304 2.66606 5.77441 2.35449C6.16515 2.15541 6.58362 2.07399 7.04687 2.03614C7.50138 1.999 8.06231 2 8.75 2H13.25ZM14 5.54981C14 6.12224 14.0002 6.50686 14.0244 6.80274C14.0479 7.08974 14.0906 7.22684 14.1367 7.31739C14.2565 7.5525 14.4475 7.74346 14.6826 7.86328C14.7732 7.90942 14.9103 7.95213 15.1973 7.97559C15.4931 7.99976 15.8778 8 16.4502 8H17.4395L14 4.56055V5.54981Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M7.73314 12.1928C7.81803 11.9357 8.18147 11.9357 8.26634 12.1928L8.85814 13.9916C9.19259 15.0086 9.99063 15.8066 11.0076 16.141L12.8064 16.7328C13.0639 16.8175 13.0638 17.1812 12.8064 17.266L11.0076 17.8578L10.8191 17.9262C9.89255 18.2931 9.17173 19.0541 8.85814 20.0072L8.3308 21.6088L8.26634 21.8061C8.18685 22.0476 7.86162 22.0626 7.7517 21.851L7.73314 21.8061L7.66771 21.6088L7.14134 20.0072C6.82776 19.0541 6.10695 18.2931 5.18041 17.9262L4.99193 17.8578L3.1931 17.266C2.93566 17.1813 2.9356 16.8175 3.1931 16.7328L3.38939 16.6674L4.99193 16.141C5.94512 15.8275 6.70599 15.1067 7.07299 14.1801L7.14134 13.9916L7.73314 12.1928Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">ВУЗовская программа</div>
                                </div>
                                <div class="feature-desc">Методические материалы РНИМУ, Сеченова, МГУ и других мединститутов</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Видео / Анатомия (Из ГигаЧата) -->
                                    <svg viewBox="0 0 24 24" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.12109 4H18.8789C19.2743 3.99999 19.6154 3.99998 19.8963 4.02293C20.1928 4.04715 20.4877 4.10064 20.7715 4.24524C21.1948 4.46095 21.5391 4.80516 21.7548 5.22852C21.8994 5.51231 21.9528 5.80722 21.9771 6.10373C22 6.38463 22 6.72567 22 7.12098V16.8789C22 17.2742 22 17.6154 21.9771 17.8963C21.9528 18.1928 21.8994 18.4877 21.7548 18.7715C21.5391 19.1948 21.1948 19.5391 20.7715 19.7548C20.4877 19.8994 20.1928 19.9528 19.8963 19.9771C19.6153 20 19.2743 20 18.8789 20H5.12108C4.72572 20 4.38466 20 4.10373 19.9771C3.80722 19.9528 3.51231 19.8994 3.22852 19.7548C2.80516 19.5391 2.46095 19.1948 2.24524 18.7715C2.10064 18.4877 2.04715 18.1928 2.02293 17.8963C1.99998 17.6154 1.99999 17.2743 2 16.8789V7.12109C1.99999 6.72575 1.99998 6.38465 2.02293 6.10373C2.04715 5.80722 2.10064 5.51231 2.24524 5.22852C2.46095 4.80516 2.80516 4.46095 3.22852 4.24524C3.51231 4.10064 3.80722 4.04715 4.10373 4.02293C4.38465 3.99998 4.72575 3.99999 5.12109 4ZM20.5 11.25V9.25H18V11.25H20.5ZM18 12.75H20.5V14.75H18V12.75ZM20.5 16.25H18V18.5H18.85C19.2824 18.5 19.5616 18.4994 19.7741 18.4821C19.9779 18.4654 20.0534 18.4372 20.0905 18.4183C20.2316 18.3464 20.3464 18.2316 20.4183 18.0905C20.4372 18.0534 20.4654 17.9779 20.4821 17.7741C20.4994 17.5616 20.5 17.2824 20.5 16.85V16.25ZM20.5 7.75V7.15C20.5 6.71759 20.4994 6.43838 20.4821 6.22588C20.4654 6.02213 20.4372 5.94659 20.4183 5.90951C20.3464 5.76839 20.2316 5.65365 20.0905 5.58175C20.0534 5.56285 19.9779 5.53459 19.7741 5.51795C19.5616 5.50059 19.2824 5.5 18.85 5.5H18V7.75H20.5ZM4.22588 5.51795C4.02213 5.53459 3.94659 5.56285 3.90951 5.58175C3.76839 5.65365 3.65365 5.76839 3.58175 5.90951C3.56285 5.94659 3.53459 6.02213 3.51795 6.22588C3.50059 6.43838 3.5 6.71759 3.5 7.15V7.75H6V5.5H5.15C4.71759 5.5 4.43838 5.50059 4.22588 5.51795ZM3.5 16.85V16.25H6V18.5H5.15C4.71759 18.5 4.43838 18.4994 4.22588 18.4821C4.02213 18.4654 3.94659 18.4372 3.90951 18.4183C3.76839 18.3464 3.65365 18.2316 3.58175 18.0905C3.56285 18.0534 3.53459 17.9779 3.51795 17.7741C3.50059 17.5616 3.5 17.2824 3.5 16.85ZM6 14.75H3.5V12.75H6V14.75ZM6 11.25H3.5V9.25H6V11.25ZM16.5 18.5H7.5V5.5H16.5V18.5Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Анатомия и Атласы</div>
                                </div>
                                <div class="feature-desc">Разбор строения органов, наглядные схемы и иллюстрированные атласы</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Лампочки (Из ГигаЧата) -->
                                    <svg viewBox="0 0 16 16" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path d="M5.87877 6.57877C6.08379 6.37374 6.41621 6.37374 6.62123 6.57877L8 7.95754L9.37877 6.57877C9.58379 6.37374 9.91621 6.37374 10.1212 6.57877C10.3263 6.78379 10.3263 7.11621 10.1212 7.32123L8.525 8.91746V10.45C8.525 10.7399 8.28995 10.975 8 10.975C7.71005 10.975 7.475 10.7399 7.475 10.45V8.91746L5.87877 7.32123C5.67374 7.11621 5.67374 6.78379 5.87877 6.57877Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M2.75 6.25C2.75 3.35051 5.10051 1 8 1C10.8995 1 13.25 3.35051 13.25 6.25C13.25 7.82841 12.5528 9.2446 11.4513 10.2062C11.2499 10.382 11.1188 10.5794 11.0702 10.78L10.9133 11.4281C10.7041 12.2918 9.93095 12.9 9.04234 12.9H6.95766C6.06905 12.9 5.29591 12.2918 5.08675 11.4281L4.92979 10.78C4.8812 10.5794 4.75009 10.382 4.54869 10.2062C3.44721 9.2446 2.75 7.82841 2.75 6.25ZM8 2.05C5.6804 2.05 3.8 3.9304 3.8 6.25C3.8 7.51256 4.35644 8.64454 5.23924 9.41524C5.55532 9.69119 5.83718 10.0659 5.95028 10.5329L6.10725 11.181C6.20232 11.5735 6.55375 11.85 6.95766 11.85H9.04234C9.44625 11.85 9.79768 11.5735 9.89275 11.181L10.0497 10.5329C10.1628 10.0659 10.4447 9.69119 10.7608 9.41524C11.6436 8.64454 12.2 7.51256 12.2 6.25C12.2 3.9304 10.3196 2.05 8 2.05Z" fill="currentColor"></path><path d="M6.25 13.95C5.96005 13.95 5.725 14.1851 5.725 14.475C5.725 14.7649 5.96005 15 6.25 15H9.75C10.0399 15 10.275 14.7649 10.275 14.475C10.275 14.1851 10.0399 13.95 9.75 13.95H6.25Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Медицинская Латынь</div>
                                </div>
                                <div class="feature-desc">Словарные формы, грамматика, склонения и терминоэлементы (Чернявский)</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Профиля / Диагностики (Из ГигаЧата) -->
                                    <svg viewBox="0 0 24 24" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.25C9.92893 6.25 8.25 7.92893 8.25 10C8.25 12.0711 9.92893 13.75 12 13.75C14.0711 13.75 15.75 12.0711 15.75 10C15.75 7.92893 14.0711 6.25 12 6.25ZM9.75 10C9.75 8.75736 10.7574 7.75 12 7.75C13.2426 7.75 14.25 8.75736 14.25 10C14.25 11.2426 13.2426 12.25 12 12.25C10.7574 12.25 9.75 11.2426 9.75 10Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 14.847 20.8093 17.4171 18.9008 19.2374C17.1068 20.9483 14.6754 22 12 22C9.32456 22 6.89316 20.9483 5.09924 19.2374C3.19071 17.4171 2 14.847 2 12ZM12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1176 4.27358 16.0535 5.55494 17.542C6.97361 15.9682 9.3752 15 12 15C14.6248 15 17.0264 15.9682 18.4451 17.542C19.7264 16.0535 20.5 14.1176 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.3689 18.5901C16.3045 17.3811 14.3443 16.5 12 16.5C9.65569 16.5 7.69553 17.3811 6.63109 18.5901C8.09517 19.7845 9.9634 20.5 12 20.5C14.0366 20.5 15.9048 19.7845 17.3689 18.5901Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Клинический анализ</div>
                                </div>
                                <div class="feature-desc">Схемы дифференциальной диагностики, патология и этиология</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="chat-window" class="chat-window">
                    <?php if (!$isEmpty): ?>
                        <?php foreach ($currentMessages as $msg):
                            $roleClass = $msg['role'] === 'user' ? 'user-msg' : 'ai-msg';
                            $textHtml = "";
                            $rawText  = "";

                            $filesGridHtml = '<div class="chat-message-files" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">';
                            $aiImagesHtml = '';
                            $hasMsgFiles = false;

                            $parts = is_string($msg['parts']) ? json_decode($msg['parts'], true) : $msg['parts'];
                            $parts = $parts ?: [];

                            $msgInteractiveTerms = null;
                            foreach ($parts as $p) {
                                if (isset($p['interactive_terms']) && is_array($p['interactive_terms'])) {
                                    $msgInteractiveTerms = $p['interactive_terms'];
                                    break;
                                }
                            }

                            foreach ($parts as $part) {
                                $fileName = htmlspecialchars($part['file_name'] ?? 'Вложение');

                                // 1. ИЗОБРАЖЕНИЯ (Модель или Пользователь)
                                if (isset($part['inline_data'])) {
                                    $mime = htmlspecialchars($part['inline_data']['mime_type'] ?? 'image/jpeg');
                                    $b64  = $part['inline_data']['data'] ?? '';
                                    $imgSrc = 'data:' . $mime . ';base64,' . $b64;

                                    if ($msg['role'] === 'model') {
                                        $aiImagesHtml .= '<div class="ai-image-container" style="margin-top: 12px;">
                                            <img src="' . $imgSrc . '" class="chat-generated-image" onclick="openImageModal(this.src)" alt="Иллюстрация" style="max-width:100%; border-radius:var(--radius-md, 12px); cursor:pointer;">
                                        </div>';
                                    } else {
                                        $hasMsgFiles = true;
                                        $filesGridHtml .= '
                                        <div class="file-preview-item" onclick="openImageModal(\'' . $imgSrc . '\')" style="cursor: pointer;">
                                            <img src="' . $imgSrc . '" alt="Фото">
                                            <span class="file-name-tooltip">' . $fileName . '</span>
                                        </div>';
                                    }
                                } 
                                // 2. ДОКУМЕНТЫ (Векторная SVG иконка)
                                elseif (isset($part['file_name'])) {
                                    $hasMsgFiles = true;
                                    $filesGridHtml .= '
                                    <div class="file-preview-item">
                                        <span class="file-icon-placeholder">
                                            <svg class="file-icon-svg" viewBox="0 0 24 24" style="width:26px;height:26px;fill:var(--sber-blue, #333F48);"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                        </span>
                                        <span class="file-name-tooltip">' . $fileName . '</span>
                                    </div>';
                                }

                                // 3. ТЕКСТОВОЕ СОДЕРЖИМОЕ
                                if (isset($part['text'])) {
                                    $rawText .= $part['text'];
                                    if ($msg['role'] !== 'user') {
                                        $textHtml .= formatMarkdownMessage($part['text'], $msgInteractiveTerms);
                                    } else {
                                        $partText = trim($part['text']);
                                        $isFileContent = isset($part['file_name']) 
                                            || str_starts_with($partText, '[СОДЕРЖИМОЕ ПРИКРЕПЛЕННОГО ДОКУМЕНТА') 
                                            || str_starts_with($partText, '[Прикреплен документ:');
                                        
                                        if (!$isFileContent) {
                                            $textHtml .= nl2br(htmlspecialchars($part['text']));
                                        }
                                    }
                                }
                            }
                            if ($msgInteractiveTerms) {
                                $textHtml .= '<script>registerInteractiveTerms(' . json_encode($msgInteractiveTerms, JSON_UNESCAPED_UNICODE) . ');</script>';
                            }
                            $filesGridHtml .= '</div>';
                        ?>
                            <div class="msg <?= $roleClass ?>">
                                <?= ($msg['role'] === 'user' && $hasMsgFiles) ? $filesGridHtml : '' ?>
                                <div><?= $textHtml ?></div>
                                <?php if ($msg['role'] !== 'user'): ?>
                                    <?= $aiImagesHtml ?>
                                    <button class="copy-msg-btn" data-text="<?= htmlspecialchars($rawText) ?>" onclick="copyMessageText(this)" title="Копировать текст">
                                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="chat-controls">
                    <?php if ($isEmpty): ?>
                    <div class="prompts-carousel-container">
                        <div class="prompts-carousel-track">
                            <div class="prompt-card" onclick="usePromptText(this)">Покажи череп</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Расскажи о строении костей</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Проанализируй документ</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы функции костей в теле человека и какие типы тканей входят в их состав?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие слои различают в надкостнице и какие функции они выполняют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие части различают в длинных костях и как они называются?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие кости входят в состав осевого скелета и какова их роль?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как устроена грудная клетка и какие основные элементы её формируют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие околоносовые пазухи существуют и где они расположены?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как устроена нижняя часть плечевой кости и её функции?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие кости входят в первый и второй ряд костей запястья?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как формируется вертлужная впадина (acetabulum)?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие виды фиброзных и синовиальных соединений костей существуют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Анатомические особенности сошника и его соединения с костями черепа</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие основные изгибы образует позвоночный столб в сагиттальной плоскости?</div>
                            <!-- Повтор для закольцованной авто-прокрутки -->
                            <div class="prompt-card" onclick="usePromptText(this)">Покажи череп</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Расскажи о строении костей</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Проанализируй документ</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы функции костей в теле человека и какие типы тканей входят в их состав?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие слои различают в надкостнице и какие функции они выполняют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие части различают в длинных костях и как они называются?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие кости входят в состав осевого скелета и какова их роль?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как устроена грудная клетка и какие основные элементы её формируют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие околоносовые пазухи существуют и где они расположены?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как устроена нижняя часть плечевой кости и её функции?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие кости входят в первый и второй ряд костей запястья?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как формируется вертлужная впадина (acetabulum)?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие виды фиброзных и синовиальных соединений костей существуют?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Анатомические особенности сошника и его соединения с костями черепа</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Какие основные изгибы образует позвоночный столб в сагиттальной плоскости?</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; gap: 10px; align-items: center; width: 100%; margin-bottom: 10px;">
                        <div class="chat-input-container" style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                            
                            <input type="hidden" id="current-chat-id" value="<?= htmlspecialchars($chatId) ?>">
                            <input type="hidden" id="university-input" value="">

                            <form id="chat-form" class="chat-input-wrapper" onsubmit="event.preventDefault(); sendMessage('student');" style="display: flex; flex-direction: column; align-items: stretch; padding: 10px; border-radius: 24px; border: none; background: #ffffff;">
                                <div id="file-preview-grid" class="file-preview-grid" style="display: none;"></div>
                                <div class="chat-input-row" style="display: flex; align-items: flex-end; width: 100%; gap: 6px; position: relative;">
                                    
                                    <div class="left-controls upload-area" style="display: flex; align-items: center; flex-shrink: 0;">
                                        <input type="file" id="photo-input" accept="image/*" multiple style="display:none;" onchange="handleFileSelect('photo')">
                                        <input type="file" id="file-input" accept="image/*,.pdf,.txt,.doc,.docx,.csv" multiple style="display:none;" onchange="handleFileSelect('file')">
                                        <button type="button" onclick="toggleUploadMenu(event)" class="btn-upload-plus" title="Прикрепить файл">+</button>
                                        <div id="selected-university-badge" class="selected-university-badge" style="display: none;">
                                            <span class="badge-text"></span>
                                            <span class="badge-remove" onclick="removeUniversitySelection(event)">
                                                <svg style="width: 10px; height: 10px; fill: currentColor; display: block;" viewBox="0 0 24 24">
                                                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        
                                        <div id="uploadMenu" class="upload-menu">
                                            <button type="button" class="upload-menu-item" onclick="document.getElementById('file-input').click(); closeUploadMenu();">
                                                <svg style="width:16px;height:16px;fill:currentColor;" viewBox="0 0 24 24"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5a2.5 2.5 0 0 1 5 0v10.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5V6H9v9.5a3 3 0 0 0 6 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg>
                                                Прикрепить файл / фото
                                            </button>
                                            <button type="button" class="upload-menu-item" onclick="openUniversityModal(event); closeUploadMenu();">
                                                <svg style="width:16px;height:16px;fill:currentColor;" viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h-2v-5.77l-7 3.82-7-3.82V17H5V11.23L12 15l11-6-11-6z"/></svg>
                                                Выбрать ВУЗ
                                            </button>
                                        </div>
                                    </div>

                                    <div class="chat-input-textarea-wrapper" style="position: relative; flex-grow: 1; display: flex; align-items: center; width: 100%;">
                                        <textarea id="user-query" class="chat-input" rows="1" placeholder="Спросите о чём угодно" style="flex-grow: 1; border: none; resize: none; outline: none; padding: 10px 16px; margin: 0 2px; font-size: 15px; background: transparent; max-height: 140px; line-height: 1.4;"></textarea>
                                        <span id="custom-chat-caret" class="custom-chat-caret"></span>
                                    </div>

                                    <div class="chat-input-actions" style="flex-shrink: 0;">
                                        <button type="submit" class="chat-send-btn" title="Отправить">
                                            <svg class="inline-icon" viewBox="0 0 24 24" style="transform: rotate(-45deg); fill: white; width: 20px; height: 20px;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                        </button>
                                    </div>

                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        <aside id="annotation-sidebar" class="annotation-sidebar">
            <div class="annotation-resize-handle" id="annotationResizeHandle" title="Потяните, чтобы изменить ширину"></div>
            <div class="annotation-content-inner" id="annotationContentInner">
                <div class="annotation-header">
                    <div class="annotation-header-top">
                        <div class="annotation-badge">
                            <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            <span>Аннотация</span>
                        </div>
                        <button type="button" class="annotation-close-btn" onclick="closeAnnotationDrawer()" title="Закрыть панель (Esc)">
                            <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: currentColor;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        </button>
                    </div>
                    <h3 class="annotation-title" id="annotationSidebarTitle"></h3>
                    <div class="annotation-latin-tag" id="annotationSidebarLatin" style="display: none;">
                        <span class="latin-val" id="annotationSidebarLatinVal"></span>
                    </div>
                </div>

                <div class="annotation-img-box" onclick="openImageModal(document.getElementById('annotationSidebarImg').src)" title="Нажмите, чтобы открыть полноразмерный предпросмотр">
                    <img id="annotationSidebarImg" class="annotation-img" src="" alt="Иллюстрация" />
                    <div class="annotation-zoom-badge">
                        <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zm2.5-4h-2v2H9v-2H7V9h2V7h1v2h2v1z"/></svg>
                        <span>Увеличить</span>
                    </div>
                </div>

                <div class="annotation-meta-card" id="annotationSidebarMeta" style="display: none;">
                    <svg viewBox="0 0 24 24" style="width: 15px; height: 15px; fill: var(--primary-active); flex-shrink: 0;"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                    <span id="annotationSidebarPage"></span>
                </div>
            </div>
        </aside>
    </div>

    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <span class="modal-close" style="color: #ef4444 !important; font-size: 36px; font-weight: bold; cursor: pointer;" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="fullSizeImage">
    </div>

    <div id="termModal" class="modal" onclick="closeTermModal()">
        <div class="term-modal-card" onclick="event.stopPropagation()">
            <div class="term-modal-header">
                <div>
                    <h3 id="termModalTitle" class="term-modal-title"></h3>
                </div>
                <span class="modal-close" style="position:static;font-size:28px;color:#ef4444 !important;font-weight:bold;cursor:pointer;line-height:1;" onclick="closeTermModal()">&times;</span>
            </div>
            <div class="term-modal-img-container" style="position:relative;width:100%;text-align:center;cursor:zoom-in;margin-top:8px;" onclick="openImageModal(document.getElementById('termModalImage').src)">
                <img id="termModalImage" class="term-modal-img" src="" alt="Иллюстрация" title="Кликните, чтобы увеличить фото" style="cursor:zoom-in;display:block;margin:0 auto;">
                <div style="position:absolute;bottom:10px;right:10px;background:rgba(15,23,42,0.75);color:#ffffff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;pointer-events:none;backdrop-filter:blur(4px);">
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zm2.5-4h-2v2H9v-2H7V9h2V7h1v2h2v1z"/></svg>
                    Увеличить
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/chat.js?v=<?= time() ?>"></script>
    <script src="assets/js/medical-physics.js?v=<?= time() ?>"></script>
    <script>
        const cw = document.getElementById('chat-window');
        if (cw) { cw.scrollTop = cw.scrollHeight; }

        document.getElementById('user-query').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage('student');
            }
        });

        document.getElementById('user-query').addEventListener('focus', function() {
            setTimeout(() => { if (cw) cw.scrollTo({ top: cw.scrollHeight, behavior: 'smooth' }); }, 300);
        });

        (function() {
            const ta = document.getElementById('user-query');
            if (!ta) return;
            const maxH = 140; 
            function resize() {
                ta.style.height = 'auto';
                const newH = Math.min(ta.scrollHeight, maxH);
                ta.style.height = newH + 'px';
                ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
            }
            ta.addEventListener('input', resize);
            window.addEventListener('resize', resize);
            resize();
        })();

        <?php if ($isEmpty): ?>
            window.medicalPhysicsInstance = new MedicalPhysics('.chat-container');
        <?php endif; ?>
    </script>
</body>
</html>