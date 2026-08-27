<?php 
include __DIR__ . '/includes/functions.php'; 
allowGuestOrRequireLogin(); 
require __DIR__ . '/includes/db.php';

$isGuest = !empty($_SESSION['is_guest']);
$userLogin = $_SESSION['user_login'] ?? '';

$myChats = [];
$currentMessages = [];
$chatId = '';

// История чатов доступна только авторизованным пользователям
if (!$isGuest) {
    $chatId = $_GET['chat_id'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT chat_id, title, updated_at FROM chats WHERE user_login = :login AND mode = 'patient' ORDER BY updated_at DESC");
        $stmt->execute(['login' => $userLogin]);
        $myChats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if (!empty($chatId)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT 1 FROM chats WHERE chat_id = :chat_id AND user_login = :login AND mode = 'patient'");
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
    <title>MedAI - Консультация</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '\\[', right: '\\]', display: true}, {left: '\\(', right: '\\)', display: false}, {left: '$', right: '$', display: false}], throwOnError: false});"></script>
    <style>
        .btn-new-chat:hover { background-color: #1030bdd8; }
        .btn-new-chat:active { transform: scale(0.98); }
        .btn-new-chat { background: var(--primary-color); color: white; border: none; transition: background-color 0.2s ease, transform 0.1s ease; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 15px; }
        .history-item { display: flex; align-items: center; padding: 10px; color: #334155; text-decoration: none; border-radius: 6px; margin-bottom: 5px; font-size: 14px; background: #f8fafc; transition: 0.2s; }
        .history-item span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .history-item:hover { background: #e2e8f0; }
        .history-item.active { background: var(--primary-color); color: white; }
        .history-item-wrapper { position: relative; margin-bottom: 5px; }
        @media (hover: hover) {
            .delete-chat-btn { opacity: 0; pointer-events: none; transition: opacity 0.2s ease-in-out, background-color 0.2s; }
            .history-item-wrapper:hover .delete-chat-btn { opacity: 1; pointer-events: auto; }
            .delete-chat-btn:hover { background-color: rgba(239, 68, 68, 0.1); }
        }
        .msg { position: relative; }
        .ai-msg { padding-right: 44px !important; max-width: 92% !important; width: fit-content !important; }

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
            <a href="patient_chat.php" class="btn-new-chat">
                <svg class="inline-icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Новая консультация
            </a>
            <div style="font-size: 13px; color: #64748b; margin-bottom: 10px; text-transform: uppercase; font-weight: bold;">История записей</div>

            <?php foreach ($myChats as $c): ?>
                <?php $isActive = ($c['chat_id'] === $chatId); ?>
                <div class="history-item-wrapper">
                    <a href="?chat_id=<?= $c['chat_id'] ?>" class="history-item <?= $isActive ? 'active' : '' ?>" title="<?= htmlspecialchars($c['title']) ?>" style="margin-bottom: 0; padding-right: 40px; width: 100%;">
                        <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                        <span><?= htmlspecialchars($c['title']) ?></span>
                    </a>
                    <a href="delete_chat.php?chat_id=<?= $c['chat_id'] ?>" class="delete-chat-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: <?= $isActive ? '#fecaca' : '#ef4444' ?>; z-index: 10; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px;" title="Удалить чат">
                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12z"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (empty($myChats)): ?>
                <div style="font-size: 13px; color: #94a3b8; text-align: center; margin-top: 20px;">Нет прошлых консультаций</div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>

        <div class="chat-main">
            <div class="chat-container<?= $isEmpty ? ' chat-is-empty' : '' ?>">

                <div class="empty-center-header">
                    <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" class="empty-logo">
                    
                    <div class="mode-info-block">
                        <h2 class="mode-info-title">Медицинский ассистент MedAI</h2>
                        <p class="mode-info-subtitle">Предварительный анализ симптомов и помощь в подготовке к приёму врача</p>
                        
                        <div class="mode-features-grid">
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Проекта / Документов / Анализа (Из ГигаЧата) -->
                                    <svg viewBox="0 0 24 24" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path fill-rule="evenodd" clip-rule="evenodd" d="M13.25 2C13.4489 2 13.6396 2.07908 13.7803 2.21973L19.7803 8.21973C19.9209 8.36038 20 8.55109 20 8.75V17.25C20 17.9377 20.001 18.4986 19.9639 18.9531C19.926 19.4164 19.8446 19.8349 19.6455 20.2256C19.3339 20.837 18.837 21.3339 18.2256 21.6455C17.8349 21.8446 17.4164 21.926 16.9531 21.9639C16.4986 22.001 15.9377 22 15.25 22H9.78125L10.2754 20.5H15.25C15.9624 20.5 16.4518 20.4997 16.8311 20.4688C17.2015 20.4385 17.4009 20.383 17.5449 20.3096C17.874 20.1418 18.1418 19.874 18.3096 19.5449C18.383 19.4009 18.4385 19.2015 18.4688 18.8311C18.4997 18.4518 18.5 17.9624 18.5 17.25V9.5H16.4502C15.9025 9.5 15.4463 9.50103 15.0752 9.47071C14.6955 9.43967 14.3391 9.37191 14.002 9.2002C13.4845 8.93655 13.0635 8.5155 12.7998 7.99805C12.6281 7.66091 12.5603 7.30454 12.5293 6.92481C12.499 6.55368 12.5 6.09748 12.5 5.54981V3.5H8.75C8.03756 3.5 7.5482 3.50027 7.16894 3.53125C6.79851 3.56153 6.5991 3.61705 6.45508 3.69043C6.12597 3.85818 5.85818 4.12597 5.69043 4.45508C5.61704 4.59911 5.56152 4.79852 5.53125 5.16895C5.50026 5.5482 5.5 6.03756 5.5 6.75V13.9668C5.308 14.2588 5.03788 14.4938 4.7168 14.6406L4.52344 14.7158L4 14.8877V6.75C4 6.06231 3.999 5.50138 4.03613 5.04688C4.07398 4.58363 4.1554 4.16515 4.35449 3.77442C4.66605 3.16305 5.16304 2.66606 5.77441 2.35449C6.16515 2.15541 6.58362 2.07399 7.04687 2.03614C7.50138 1.999 8.06231 2 8.75 2H13.25ZM14 5.54981C14 6.12224 14.0002 6.50686 14.0244 6.80274C14.0479 7.08974 14.0906 7.22684 14.1367 7.31739C14.2565 7.5525 14.4475 7.74346 14.6826 7.86328C14.7732 7.90942 14.9103 7.95213 15.1973 7.97559C15.4931 7.99976 15.8778 8 16.4502 8H17.4395L14 4.56055V5.54981Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M7.73314 12.1928C7.81803 11.9357 8.18147 11.9357 8.26634 12.1928L8.85814 13.9916C9.19259 15.0086 9.99063 15.8066 11.0076 16.141L12.8064 16.7328C13.0639 16.8175 13.0638 17.1812 12.8064 17.266L11.0076 17.8578L10.8191 17.9262C9.89255 18.2931 9.17173 19.0541 8.85814 20.0072L8.3308 21.6088L8.26634 21.8061C8.18685 22.0476 7.86162 22.0626 7.7517 21.851L7.73314 21.8061L7.66771 21.6088L7.14134 20.0072C6.82776 19.0541 6.10695 18.2931 5.18041 17.9262L4.99193 17.8578L3.1931 17.266C2.93566 17.1813 2.9356 16.8175 3.1931 16.7328L3.38939 16.6674L4.99193 16.141C5.94512 15.8275 6.70599 15.1067 7.07299 14.1801L7.14134 13.9916L7.73314 12.1928Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Анализ симптомов</div>
                                </div>
                                <div class="feature-desc">Первичная оценка жалоб и анализ возможного характера состояния</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Лампочки (Из ГигаЧата) -->
                                    <svg viewBox="0 0 16 16" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path d="M5.87877 6.57877C6.08379 6.37374 6.41621 6.37374 6.62123 6.57877L8 7.95754L9.37877 6.57877C9.58379 6.37374 9.91621 6.37374 10.1212 6.57877C10.3263 6.78379 10.3263 7.11621 10.1212 7.32123L8.525 8.91746V10.45C8.525 10.7399 8.28995 10.975 8 10.975C7.71005 10.975 7.475 10.7399 7.475 10.45V8.91746L5.87877 7.32123C5.67374 7.11621 5.67374 6.78379 5.87877 6.57877Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M2.75 6.25C2.75 3.35051 5.10051 1 8 1C10.8995 1 13.25 3.35051 13.25 6.25C13.25 7.82841 12.5528 9.2446 11.4513 10.2062C11.2499 10.382 11.1188 10.5794 11.0702 10.78L10.9133 11.4281C10.7041 12.2918 9.93095 12.9 9.04234 12.9H6.95766C6.06905 12.9 5.29591 12.2918 5.08675 11.4281L4.92979 10.78C4.8812 10.5794 4.75009 10.382 4.54869 10.2062C3.44721 9.2446 2.75 7.82841 2.75 6.25ZM8 2.05C5.6804 2.05 3.8 3.9304 3.8 6.25C3.8 7.51256 4.35644 8.64454 5.23924 9.41524C5.55532 9.69119 5.83718 10.0659 5.95028 10.5329L6.10725 11.181C6.20232 11.5735 6.55375 11.85 6.95766 11.85H9.04234C9.44625 11.85 9.79768 11.5735 9.89275 11.181L10.0497 10.5329C10.1628 10.0659 10.4447 9.69119 10.7608 9.41524C11.6436 8.64454 12.2 7.51256 12.2 6.25C12.2 3.9304 10.3196 2.05 8 2.05Z" fill="currentColor"></path><path d="M6.25 13.95C5.96005 13.95 5.725 14.1851 5.725 14.475C5.725 14.7649 5.96005 15 6.25 15H9.75C10.0399 15 10.275 14.7649 10.275 14.475C10.275 14.1851 10.0399 13.95 9.75 13.95H6.25Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Клиническая база</div>
                                </div>
                                <div class="feature-desc">Поиск по 1 700+ выверенным клиническим случаям и заключениям</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Документов / Анализов (Из ГигаЧата) -->
                                    <svg viewBox="0 0 16 16" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path fill="currentColor" d="M13.9999 12.9999H12.7462C13.3818 12.2276 13.7948 11.2966 13.9409 10.307C14.0871 9.31749 13.9607 8.30685 13.5755 7.38374C13.1902 6.46064 12.5607 5.65999 11.7545 5.06787C10.9483 4.47574 9.99603 4.11455 8.99995 4.02311V1.99999C8.99995 1.73478 8.8946 1.48043 8.70706 1.29289C8.51953 1.10536 8.26517 1 7.99996 1H4.99998C4.73476 1 4.48041 1.10536 4.29288 1.29289C4.10534 1.48043 3.99998 1.73478 3.99998 1.99999V8.49995C3.99998 8.76517 4.10534 9.01952 4.29288 9.20706C4.48041 9.39459 4.73476 9.49995 4.99998 9.49995H7.99996C8.26517 9.49995 8.51953 9.39459 8.70706 9.20706C8.8946 9.01952 8.99995 8.76517 8.99995 8.49995V5.02873C9.88208 5.1276 10.7153 5.48515 11.3947 6.05639C12.0741 6.62764 12.5694 7.38706 12.8182 8.23912C13.0671 9.09117 13.0584 9.99779 12.7932 10.8449C12.5281 11.692 12.0183 12.4418 11.3281 12.9999H2C1.86739 12.9999 1.74021 13.0526 1.64645 13.1464C1.55268 13.2401 1.5 13.3673 1.5 13.4999C1.5 13.6325 1.55268 13.7597 1.64645 13.8535C1.74021 13.9472 1.86739 13.9999 2 13.9999H13.9999C14.1325 13.9999 14.2597 13.9472 14.3535 13.8535C14.4472 13.7597 14.4999 13.6325 14.4999 13.4999C14.4999 13.3673 14.4472 13.2401 14.3535 13.1464C14.2597 13.0526 14.1325 12.9999 13.9999 12.9999ZM7.99996 8.49995H4.99998V1.99999H7.99996V8.49995ZM4.49998 11.4999C4.36737 11.4999 4.2402 11.4473 4.14643 11.3535C4.05266 11.2597 3.99998 11.1325 3.99998 10.9999C3.99998 10.8673 4.05266 10.7402 4.14643 10.6464C4.2402 10.5526 4.36737 10.4999 4.49998 10.4999H8.49996C8.63256 10.4999 8.75974 10.5526 8.85351 10.6464C8.94728 10.7402 8.99995 10.8673 8.99995 10.9999C8.99995 11.1325 8.94728 11.2597 8.85351 11.3535C8.75974 11.4473 8.63256 11.4999 8.49996 11.4999H4.49998Z"></path></svg>
                                    <div class="feature-title">Расшифровка анализов</div>
                                </div>
                                <div class="feature-desc">Объяснение показателей общего и биохимического анализа крови</div>
                            </div>
                            <div class="mode-feature-card">
                                <div class="feature-title-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <!-- Иконка Поддержки / Первая помощь (Из ГигаЧата) -->
                                    <svg viewBox="0 0 24 24" fill="none" style="width: 18px; height: 18px; color: var(--sber-blue); flex-shrink: 0;"><path d="M10.398 7.65451C10.0709 7.90891 9.75 8.40525 9.75 9.5C9.75 9.91421 9.41421 10.25 9 10.25C8.58579 10.25 8.25 9.91421 8.25 9.5C8.25 8.09475 8.67913 7.09109 9.47704 6.47049C10.2368 5.87954 11.1917 5.75 12 5.75C12.8083 5.75 13.7632 5.87954 14.523 6.47049C15.3209 7.09109 15.75 8.09475 15.75 9.5C15.75 11.143 14.7118 11.9662 13.95 12.5375C13.1029 13.1728 12.75 13.4468 12.75 14C12.75 14.4142 12.4142 14.75 12 14.75C11.5858 14.75 11.25 14.4142 11.25 14C11.25 12.6619 12.2313 11.9403 12.8952 11.452C12.9491 11.4124 13.001 11.3743 13.05 11.3375C13.7882 10.7838 14.25 10.357 14.25 9.5C14.25 8.40525 13.9291 7.90891 13.602 7.65451C13.2368 7.37046 12.6917 7.25 12 7.25C11.3083 7.25 10.7632 7.37046 10.398 7.65451Z" fill="currentColor"></path><path d="M13 16.5C13 17.0523 12.5523 17.5 12 17.5C11.4477 17.5 11 17.0523 11 16.5C11 15.9477 11.4477 15.5 12 15.5C12.5523 15.5 13 15.9477 13 16.5Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM3.5 12C3.5 7.30558 7.30558 3.5 12 3.5C16.6944 3.5 20.5 7.30558 20.5 12C20.5 16.6944 16.6944 20.5 12 20.5C7.30558 20.5 3.5 16.6944 3.5 12Z" fill="currentColor"></path></svg>
                                    <div class="feature-title">Первая помощь</div>
                                </div>
                                <div class="feature-desc">Советы по первой помощи и выбор нужного профильного специалиста</div>
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
                            <div class="prompt-card" onclick="usePromptText(this)">Ужасно давит голову, это самая сильная головная боль в моей жизни</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Такое ощущение, будто перед глазами появляются слепые пятна</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Я пришла за рецептом и консультацией по лекарству от повышенного давления</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Появились бородавки и зуд, это нормально для моего возраста?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы первичные симптомы и признаки простуды и ОРВИ?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Немного чешется и болит нога после ушиба, что делать?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Удаляли аппендикс несколько лет назад, иногда чувствую ноющую боль</div>
                            <div class="prompt-card" onclick="usePromptText(this)">С обеих сторон моей семьи есть проблемы с сердцем и диабет</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как правильно подготовиться к сдаче общего и биохимического анализа крови?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Разница между сухим и влажным кашлем и когда обращаться к врачу</div>
                            <div class="prompt-card" onclick="usePromptText(this)">О чем говорит внезапная боль в пояснице после нагрузок</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как правильно измерить артериальное давление на дому</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Причины дискомфорта и тяжести в желудке после приема пищи</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Признаки обезвоживания и нормы суточного питьевого режима</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы нормы сахара в крови натощак и показатели анализа</div>
                            <!-- Повтор для закольцованной авто-прокрутки -->
                            <div class="prompt-card" onclick="usePromptText(this)">Ужасно давит голову, это самая сильная головная боль в моей жизни</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Такое ощущение, будто перед глазами появляются слепые пятна</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Я пришла за рецептом и консультацией по лекарству от повышенного давления</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Появились бородавки и зуд, это нормально для моего возраста?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы первичные симптомы и признаки простуды и ОРВИ?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Немного чешется и болит нога после ушиба, что делать?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Удаляли аппендикс несколько лет назад, иногда чувствую ноющую боль</div>
                            <div class="prompt-card" onclick="usePromptText(this)">С обеих сторон моей семьи есть проблемы с сердцем и диабет</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как правильно подготовиться к сдаче общего и биохимического анализа крови?</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Разница между сухим и влажным кашлем и когда обращаться к врачу</div>
                            <div class="prompt-card" onclick="usePromptText(this)">О чем говорит внезапная боль в пояснице после нагрузок</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Как правильно измерить артериальное давление на дому</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Причины дискомфорта и тяжести в желудке после приема пищи</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Признаки обезвоживания и нормы суточного питьевого режима</div>
                            <div class="prompt-card" onclick="usePromptText(this)">Каковы нормы сахара в крови натощак и показатели анализа</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; gap: 10px; align-items: center; width: 100%; margin-bottom: 10px;">
                        <div class="chat-input-container" style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                            
                            <input type="hidden" id="current-chat-id" value="<?= htmlspecialchars($chatId) ?>">
                            <input type="hidden" id="university-input" value="">

                            <form id="chat-form" class="chat-input-wrapper" onsubmit="event.preventDefault(); sendMessage('patient');" style="display: flex; flex-direction: column; align-items: stretch; padding: 16px 20px; border-radius: 24px; border: none; background: #ffffff;">

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
                                         <textarea id="user-query" class="chat-input" rows="1" placeholder="Опишите вашу проблему или жалобу..." style="flex-grow: 1; border: none; resize: none; outline: none; padding: 10px 16px; margin: 0 2px; font-size: 15px; background: transparent; max-height: 140px; line-height: 1.4;"></textarea>
                                         <span id="custom-chat-caret" class="custom-chat-caret"></span>
                                         <button type="button" id="btn-expand-textarea" class="btn-expand-textarea" onclick="toggleExpandTextarea(event)" title="Развернуть / свернуть ввод" style="display: none;">
                                             <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                                         </button>
                                     </div>

                                    <div class="chat-input-actions" style="flex-shrink: 0; margin-right: 2px;">
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
                sendMessage('patient'); 
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