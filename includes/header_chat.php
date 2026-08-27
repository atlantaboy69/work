<?php 
$isGuestHeader = !empty($_SESSION['is_guest']); 
$currentScriptHeader = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isPatientModeHeader = ($currentScriptHeader === 'patient_chat.php');
$toggleModeUrl = $isPatientModeHeader ? 'student_chat.php' : 'patient_chat.php';
$toggleModeTitle = $isPatientModeHeader ? 'Переключить в режим Студента' : 'Переключить в режим Пациента';
?>
<!-- Критические инлайн-стили прелоадера для мгновенного перекрытия до загрузки style.css -->
<style>
html, body { background: #F4F5F7; }
body:not(.app-loaded) .chat-layout,
body:not(.app-loaded) .chat-header { opacity: 0; }
body.app-loaded .chat-layout,
body.app-loaded .chat-header { opacity: 1; transition: opacity 0.25s ease; }
.site-preloader {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: #F4F5F7 !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 1;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.site-preloader.preloader-hidden {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
}
.preloader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.preloader-logo {
    height: 48px;
    width: auto;
}
.preloader-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(51, 63, 72, 0.1);
    border-top: 3px solid #6BEAC7;
    border-radius: 50%;
    animation: preloaderSpin 0.8s linear infinite;
}
@keyframes preloaderSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
<!-- Стартовый Прелоадер загрузки сайта -->
<div id="site-preloader" class="site-preloader">
    <div class="preloader-content">
        <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" class="preloader-logo">
        <div class="preloader-spinner"></div>
    </div>
</div>
<script>
(function(){
    try {
        if (<?= $isGuestHeader ? 'true' : 'false' ?>) {
            document.documentElement.classList.add('is-guest');
        }
        if (window.innerWidth > 768) {
            var c = localStorage.getItem('sidebar_collapsed');
            if (c === '1' || c === null) {
                document.documentElement.classList.add('sidebar-init-collapsed');
            }
        }
    } catch(e) {}

    function hidePreloader() {
        if (document.body) document.body.classList.add('app-loaded');
        var p = document.getElementById('site-preloader');
        if (p) {
            p.classList.add('preloader-hidden');
            setTimeout(function() { p.remove(); }, 300);
        }
    }

    if (document.readyState === 'complete') {
        setTimeout(hidePreloader, 100);
    } else {
        window.addEventListener('load', function() { setTimeout(hidePreloader, 100); });
        setTimeout(hidePreloader, 2000); // Резервный таймер снятия загрузки
    }
})();
</script>
<div class="chat-header">
    <?php if (!$isGuestHeader): ?>
    <button class="menu-toggle-btn" onclick="toggleSidebar(event)" title="Развернуть / скрыть историю чатов" aria-label="История чатов">
        <svg class="inline-icon" viewBox="0 0 24 24" style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="5" ry="5"/>
            <line x1="9" y1="3" x2="9" y2="21"/>
            <path d="M14 9l3 3-3 3"/>
        </svg>
    </button>
    <?php endif; ?>

    <a href="dashboard.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none;">
        <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" style="height: 28px; width: auto;">
    </a>
    
    <div class="header-menu">
        <a href="<?= $toggleModeUrl ?>" class="mode-toggle-link" title="<?= htmlspecialchars($toggleModeTitle) ?>" aria-label="<?= htmlspecialchars($toggleModeTitle) ?>">
            <div class="mode-toggle-icon-container">
                <?php if ($isPatientModeHeader): ?>
                    <!-- Иконка Пациента (сердце) в нормальном состоянии -->
                    <svg class="mode-icon mode-icon-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                <?php else: ?>
                    <!-- Иконка Студента (шапка) в нормальном состоянии -->
                    <svg class="mode-icon mode-icon-current" viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                <?php endif; ?>
                <!-- Иконка Переключения режимов (стрелки) при наведении -->
                <svg class="mode-icon mode-icon-switch" viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
            </div>
        </a>
        
        <div class="user-profile-dropdown">
            <button class="dropdown-toggle" onclick="toggleProfileMenu(event)" title="Профиль">
                <svg class="inline-icon" viewBox="0 0 24 24" style="width: 26px; height: 26px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </button>
            
            <div id="profileDropdownMenu" class="dropdown-menu">
                <?php if ($isGuestHeader): ?>
                    <a href="index.php?auth=1" class="dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.1 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                        Войти
                    </a>
                    <a href="register.php" class="dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Зарегистрироваться
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Мой профиль
                    </a>
                    <a href="history.php" class="dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                        История
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6-3.6z"/></svg>
                        Настройки
                    </a>
                    <hr class="dropdown-divider">
                    <a href="logout.php" class="dropdown-item logout-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                        Выйти
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>