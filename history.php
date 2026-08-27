<?php 
// history.php
include __DIR__ . '/includes/functions.php'; 
require __DIR__ . '/includes/db.php';
requireLogin(); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - История запросов</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    
    <style>
        .history-list-container { width: 100%; max-width: 800px; display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
        .history-row { display: flex; align-items: center; background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 20px; cursor: pointer; position: relative; user-select: none; transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease; }
        .history-row:hover { background-color: var(--bg-secondary); box-shadow: var(--shadow-sm); border-color: var(--sber-blue); }
        .history-row:active { transform: scale(0.985); }
        .row-checkbox-wrapper { width: 30px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .chat-select-cb { opacity: 0; transform: scale(0.85); width: 18px; height: 18px; cursor: pointer; accent-color: var(--sber-blue); transition: opacity 0.2s ease, transform 0.2s ease; }
        .history-row:hover .chat-select-cb, .chat-select-cb:checked { opacity: 1; transform: scale(1); }
        .row-title { flex-grow: 1; display: flex; align-items: center; gap: 12px; min-width: 0; padding-right: 15px; }
        .chat-title-text { font-size: 15px; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row-date { font-size: 13px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; margin-right: 15px; }
        .row-actions { position: relative; flex-shrink: 0; }
        .dots-btn { background: transparent; border: none; cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: background-color 0.2s, color 0.2s; }
        .dots-btn:hover { background-color: var(--bg-subtle-hover); color: var(--sber-blue); }
        .dots-icon { width: 20px; height: 20px; fill: currentColor; }
        .row-menu { display: none; position: absolute; right: 0; top: calc(100% + 5px); background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); z-index: 100; min-width: 170px; overflow: hidden; animation: dropdownFadeIn 0.15s ease-out forwards; }
        .row-menu.show { display: block; }
        .menu-item { width: 100%; background: transparent; border: none; padding: 10px 16px; font-family: inherit; font-size: 14px; font-weight: 700; color: var(--text-dark); text-align: left; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: background-color 0.15s; }
        .menu-item:hover { background-color: var(--bg-secondary); color: var(--sber-blue); }
        .menu-item.delete { color: var(--danger-color); }
        .menu-item.delete:hover { background-color: var(--danger-bg); color: var(--danger-hover); }
        .menu-icon { width: 16px; height: 16px; fill: currentColor; }
        .bulk-delete-container { position: fixed; bottom: 30px; right: 30px; z-index: 999; transform: translateY(20px); opacity: 0; pointer-events: none; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease; }
        .bulk-delete-container.active { transform: translateY(0); opacity: 1; pointer-events: auto; }
        .btn-bulk-delete { background-color: var(--danger-color) !important; color: #ffffff !important; box-shadow: 0 10px 25px rgba(244, 67, 54, 0.3) !important; border-radius: 50px !important; padding: 14px 28px !important; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; transition: background-color 0.2s, transform 0.1s; }
        .btn-bulk-delete:hover { background-color: var(--danger-hover) !important; }
        .btn-bulk-delete:active { transform: scale(0.95); }
        .desktop-text { display: inline; }
        .mobile-text { display: none; }

        @media (max-width: 768px) {
            .desktop-text { display: none !important; }
            .mobile-text { display: inline !important; }
            .filter-tabs { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; justify-content: flex-start !important; width: 100% !important; gap: 8px !important; padding: 0 16px 12px 16px !important; margin-bottom: 15px !important; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
            .filter-tabs::-webkit-scrollbar { display: none; }
            .filter-btn { white-space: nowrap !important; padding: 8px 16px !important; font-size: 13px !important; flex-shrink: 0 !important; }
            .history-row { padding: 12px 14px !important; }
            .row-title .badge { width: 10px !important; height: 10px !important; min-width: 10px !important; border-radius: 50% !important; padding: 0 !important; font-size: 0 !important; color: transparent !important; display: inline-block !important; margin: 0 4px 0 0 !important; flex-shrink: 0 !important; }
            .badge-student { background-color: var(--sber-green) !important; }
            .badge-patient { background-color: var(--sber-blue) !important; }
            .chat-title-text { font-size: 14px !important; }
            .row-date { font-size: 12px !important; margin-right: 4px !important; }
        }
    </style>
</head>
<body class="centered-body" style="justify-content: flex-start; padding-top: 20px;">
    
    <div style="width: 100%; max-width: 800px; text-align: left; margin-bottom: 15px;">
        <a href="dashboard.php" class="filter-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; background-color: transparent;">
            <svg class="inline-icon" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> Назад
        </a>
    </div>

    <div class="medai-title">История запросов</div>
    <div class="medai-subtitle">Ваш архив обращений к SberMedAI</div>

    <div class="filter-tabs">
        <button class="filter-btn active" onclick="filterHistory('all', this)">
            <span class="desktop-text">Вся история</span>
            <span class="mobile-text">Все</span>
        </button>
        <button class="filter-btn" onclick="filterHistory('student', this)">Обучение</button>
        <button class="filter-btn" onclick="filterHistory('patient', this)">Диагностика</button>
    </div>

    <div class="history-list-container">
        <?php
        $hasItems = false;
        $userLogin = $_SESSION['user_login'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM chats WHERE user_login = :login ORDER BY updated_at DESC");
            $stmt->execute(['login' => $userLogin]);
            $allChats = $stmt->fetchAll();

            foreach ($allChats as $chat) {
                $hasItems = true;
                $chatId = htmlspecialchars($chat['chat_id']);
                $mode = htmlspecialchars($chat['mode'] ?? 'student');
                $badgeClass = ($mode === 'student') ? 'badge-student' : 'badge-patient';
                $badgeText = ($mode === 'student') ? 'Обучение' : 'Диагностика';
                
                $updatedAt = date('d.m.Y H:i', strtotime($chat['updated_at']));

                $chatUrl = ($mode === 'student') 
                    ? "student_chat.php?chat_id=" . urlencode($chat['chat_id'])
                    : "patient_chat.php?chat_id=" . urlencode($chat['chat_id']);
                ?>
                <div class="history-row" data-mode="<?= $mode ?>" id="row-<?= $chatId ?>" onclick="goToChat('<?= $chatUrl ?>', event)">
                    
                    <div class="row-checkbox-wrapper">
                        <input type="checkbox" class="chat-select-cb" value="<?= $chatId ?>" onclick="event.stopPropagation();" onchange="updateSelectedCount();">
                    </div>

                    <div class="row-title">
                        <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        <span class="chat-title-text" id="title-text-<?= $chatId ?>"><?= htmlspecialchars($chat['title'] ?? 'Диалог без названия') ?></span>
                    </div>

                    <div class="row-date"><?= $updatedAt ?></div>

                    <div class="row-actions" onclick="event.stopPropagation();">
                        <button class="dots-btn" onclick="toggleRowMenu('<?= $chatId ?>', event)" title="Опции">
                            <svg viewBox="0 0 24 24" class="dots-icon">
                                <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                            </svg>
                        </button>
                        
                        <div class="row-menu" id="menu-<?= $chatId ?>" onclick="event.stopPropagation();">
                            <button class="menu-item" onclick="event.stopPropagation(); openRenameModal('<?= $chatId ?>')">
                                <svg viewBox="0 0 24 24" class="menu-icon"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                Переименовать
                            </button>
                            <button class="menu-item delete" onclick="event.stopPropagation(); deleteSingleChat('<?= $chatId ?>')">
                                <svg viewBox="0 0 24 24" class="menu-icon"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                Удалить
                            </button>
                        </div>
                    </div>

                </div>
                <?php
            }
        } catch (PDOException $e) {
            echo "<div class='error-state' style='color: red;'>Ошибка загрузки истории: " . htmlspecialchars($e->getMessage()) . "</div>";
        }

        if (!$hasItems) {
            echo "<div class='empty-state' id='empty-message' style='margin-top: 20px;'>История пока пуста.</div>";
        }
        ?>
    </div>

    <div id="bulk-delete-container" class="bulk-delete-container">
        <button class="btn-bulk-delete" onclick="deleteSelectedChats()">
            <svg class="inline-icon" viewBox="0 0 24 24" style="fill: currentColor;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
            Удалить выбранные (<span id="selected-count">0</span>)
        </button>
    </div>

    <div id="renameModal" class="image-modal" onclick="closeRenameModal()" style="display: none;">
        <div class="auth-card" style="max-width: 400px; padding: 30px;" onclick="event.stopPropagation();">
            <h3 style="margin-bottom: 15px; color: var(--text-dark);">Переименовать чат</h3>
            <input type="hidden" id="rename-chat-id">
            <div class="input-field" style="margin-bottom: 20px;">
                <input type="text" id="rename-title-input" placeholder="Новое название">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-patient" onclick="closeRenameModal()" style="width: auto; padding: 10px 20px; font-size:14px; border-radius:12px;">Отмена</button>
                <button class="btn btn-student" onclick="submitRename()" style="width: auto; padding: 10px 20px; font-size:14px; border-radius:12px;">Сохранить</button>
            </div>
        </div>
    </div>

    <div id="confirmDeleteModal" class="image-modal" onclick="closeConfirmDeleteModal()" style="display: none;">
        <div class="auth-card" style="max-width: 420px; padding: 30px;" onclick="event.stopPropagation();">
            <h3 id="confirm-title" style="margin-bottom: 12px; color: var(--text-dark); text-align: left;">Удалить чат?</h3>
            <p id="confirm-text" style="margin-bottom: 25px; color: var(--text-muted); font-size: 14px; line-height: 1.5; text-align: left;">
                Вы действительно хотите удалить этот чат? Все сообщения и связанные файлы будут удалены навсегда. Это действие нельзя отменить.
            </p>
            <input type="hidden" id="confirm-chat-id">
            <input type="hidden" id="confirm-mode">
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-patient" onclick="closeConfirmDeleteModal()" style="width: auto; padding: 10px 20px; font-size:14px; border-radius:12px;">Отмена</button>
                <button class="btn btn-student" id="confirm-delete-btn" onclick="executeDelete()" style="width: auto; padding: 10px 20px; font-size:14px; border-radius:12px; background-color: var(--danger-color); border-color: var(--danger-color);">Удалить</button>
            </div>
        </div>
    </div>

    <!-- Твой стандартный JS код, который уже работает -->
    <script>
    function goToChat(url, event) {
        if (event.target.closest('.row-checkbox-wrapper') || event.target.closest('.row-actions') || event.target.closest('.row-menu') || event.target.closest('.dots-btn') || event.target.closest('.menu-item')) {
            return;
        }
        window.location.href = url;
    }
    function toggleRowMenu(chatId, event) {
        if (event) event.stopPropagation();
        document.querySelectorAll('.history-row').forEach(row => row.style.zIndex = '1');
        document.querySelectorAll('.row-menu').forEach(menu => {
            if (menu.id !== 'menu-' + chatId) {
                menu.classList.remove('show');
            }
        });
        const currentMenu = document.getElementById('menu-' + chatId);
        if (currentMenu) {
            const isOpen = currentMenu.classList.toggle('show');
            if (isOpen) {
                const parentRow = currentMenu.closest('.history-row');
                if (parentRow) parentRow.style.zIndex = '50';
            }
        }
    }
    window.addEventListener('click', function() {
        document.querySelectorAll('.row-menu').forEach(menu => menu.classList.remove('show'));
        document.querySelectorAll('.history-row').forEach(row => row.style.zIndex = '1');
    });
    function filterHistory(mode, btn) { document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); document.querySelectorAll('.history-row').forEach(row => { if (mode === 'all' || row.getAttribute('data-mode') === mode) { row.style.display = 'flex'; } else { row.style.display = 'none'; } }); }
    function updateSelectedCount() { const checked = document.querySelectorAll('.chat-select-cb:checked'); const countSpan = document.getElementById('selected-count'); const bulkContainer = document.getElementById('bulk-delete-container'); countSpan.innerText = checked.length; if (checked.length > 0) { bulkContainer.classList.add('active'); } else { bulkContainer.classList.remove('active'); } }
    function openRenameModal(chatId) { const titleText = document.getElementById('title-text-' + chatId).innerText; document.getElementById('rename-chat-id').value = chatId; document.getElementById('rename-title-input').value = titleText; const modal = document.getElementById('renameModal'); modal.style.display = 'flex'; document.getElementById('rename-title-input').focus(); }
    function closeRenameModal() { document.getElementById('renameModal').style.display = 'none'; }
    function submitRename() { const chatId = document.getElementById('rename-chat-id').value; const newTitle = document.getElementById('rename-title-input').value.trim(); if (!newTitle) { alert('Название не может быть пустым!'); return; } fetch('history_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'rename', chat_id: chatId, new_title: newTitle }) }).then(res => res.json()).then(data => { if (data.success) { document.getElementById('title-text-' + chatId).innerText = newTitle; closeRenameModal(); } else { alert('Ошибка: ' + data.error); } }).catch(() => alert('Ошибка соединения с сервером.')); }
    function deleteSingleChat(chatId) { const titleText = document.getElementById('title-text-' + chatId).innerText; document.getElementById('confirm-mode').value = 'single'; document.getElementById('confirm-chat-id').value = chatId; document.getElementById('confirm-title').innerText = 'Удалить этот чат?'; document.getElementById('confirm-text').innerHTML = `Вы действительно хотите удалить чат <strong>«${titleText}»</strong>? Все сообщения и связанные файлы будут стерты навсегда.`; document.getElementById('confirmDeleteModal').style.display = 'flex'; }
    function deleteSelectedChats() { const checked = document.querySelectorAll('.chat-select-cb:checked'); if (checked.length === 0) return; document.getElementById('confirm-mode').value = 'bulk'; document.getElementById('confirm-title').innerText = 'Удалить выбранные чаты?'; document.getElementById('confirm-text').innerHTML = `Вы действительно хотите удалить выбранные чаты в количестве <strong>${checked.length} шт.</strong>? Вся переписка в них будет безвозвратно стерта.`; document.getElementById('confirmDeleteModal').style.display = 'flex'; }
    function closeConfirmDeleteModal() { document.getElementById('confirmDeleteModal').style.display = 'none'; }
    function executeDelete() { const mode = document.getElementById('confirm-mode').value; if (mode === 'single') { const chatId = document.getElementById('confirm-chat-id').value; fetch('history_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', chat_id: chatId }) }).then(res => res.json()).then(data => { if (data.success) { const row = document.getElementById('row-' + chatId); row.style.opacity = '0'; row.style.transform = 'translateX(-30px)'; setTimeout(() => { row.remove(); updateSelectedCount(); checkIfListIsEmpty(); }, 300); closeConfirmDeleteModal(); } else { alert('Ошибка удаления: ' + data.error); } }).catch(() => alert('Ошибка соединения с сервером.')); } else if (mode === 'bulk') { const checked = document.querySelectorAll('.chat-select-cb:checked'); if (checked.length === 0) { closeConfirmDeleteModal(); return; } const chatIds = Array.from(checked).map(cb => cb.value); fetch('history_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'bulk_delete', chat_ids: chatIds }) }).then(res => res.json()).then(data => { if (data.success) { chatIds.forEach(id => { const row = document.getElementById('row-' + id); if (row) { row.style.opacity = '0'; row.style.transform = 'scale(0.9)'; setTimeout(() => row.remove(), 250); } }); setTimeout(() => { updateSelectedCount(); checkIfListIsEmpty(); }, 300); closeConfirmDeleteModal(); } else { alert('Ошибка при массовом удалении: ' + data.error); } }).catch(() => alert('Ошибка соединения с сервером.')); } }
    function checkIfListIsEmpty() { const rows = document.querySelectorAll('.history-row'); if (rows.length === 0) { const container = document.querySelector('.history-list-container'); container.innerHTML = "<div class='empty-state' id='empty-message' style='margin-top: 20px;'>История пока пуста.</div>"; } }
    document.addEventListener('keydown', function(event) { if (event.key === "Escape") { closeRenameModal(); closeConfirmDeleteModal(); } });
    </script>
</body>
</html>