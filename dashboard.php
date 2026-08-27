<?php 
include __DIR__ . '/includes/functions.php'; 
allowGuestOrRequireLogin();

$isGuest = !empty($_SESSION['is_guest']);
$userLogin = $isGuest ? 'Гость' : ($_SESSION['user_login'] ?? 'Пользователь');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - Выбор режима</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        .sbermed-logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 30px;
            margin-bottom: 10px;
        }

        .sbermed-main-logo {
            height: 52px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .dashboard-topbar .user-login-text {
                display: none !important;
            }
            .dashboard-topbar button {
                padding: 6px 10px !important;
                gap: 4px !important;
            }
            .sbermed-logo-box {
                margin-top: 15px;
                margin-bottom: 5px;
            }
            .sbermed-main-logo {
                height: 32px !important;
                max-width: 80%;
            }
        }

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
</head>
<body class="centered-body" style="padding-top: 80px; position: relative;">
    <div id="site-preloader" class="site-preloader">
        <div class="preloader-content">
            <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" class="preloader-logo">
            <div class="preloader-spinner"></div>
        </div>
    </div>
    <script>
    (function(){
        function hidePreloader() {
            var p = document.getElementById('site-preloader');
            if (p) {
                p.classList.add('preloader-hidden');
                setTimeout(function() { p.remove(); }, 300);
            }
        }
        if (document.readyState === 'complete') hidePreloader();
        else { window.addEventListener('load', hidePreloader); setTimeout(hidePreloader, 2500); }
    })();
    </script>

    <!-- Фоновый размытый декор title-bg -->
    <div class="dashboard-bg-decorations" style="position: fixed; inset: 0; pointer-events: none; z-index: -1; overflow: hidden; opacity: 0.18; filter: blur(2px);">
        <img src="logos/title-bg-big.svg" alt="" style="position: absolute; top: -10%; left: -10%; width: 55vw; max-width: 700px; transform: rotate(-15deg);">
        <img src="logos/title-bg-big.svg" alt="" style="position: absolute; bottom: -10%; right: -10%; width: 60vw; max-width: 750px; transform: rotate(20deg) scaleX(-1);">
    </div>

    <div class="dashboard-topbar" style="z-index: 10;">
        <div style="display: flex; align-items: center;">
            <a href="dashboard.php" style="display: flex; align-items: center; text-decoration: none;">
                <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" style="height: 28px; width: auto;">
            </a>
        </div>

        <div style="position: relative;">
            <button onclick="toggleProfileMenu(event)" style="display:flex;align-items:center;gap:8px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:8px 14px;cursor:pointer;font-family:inherit;font-size:14px;font-weight:700;color:var(--text-dark);transition:background 0.2s;" onmouseover="this.style.background='var(--bg-subtle-hover)'" onmouseout="this.style.background='var(--bg-secondary)'">
                <svg style="width:18px;height:18px;fill:currentColor;color:var(--sber-blue);" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span class="user-login-text"><?= htmlspecialchars($userLogin) ?></span>
                <svg style="width:14px;height:14px;fill:currentColor;" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
            </button>

            <div id="profileDropdownMenu" class="profile-dropdown" style="right:0;left:auto;">
                <?php if ($isGuest): ?>
                    <a href="index.php?auth=1" class="profile-dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.1 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                        Войти
                    </a>
                    <a href="register.php" class="profile-dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Зарегистрироваться
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="profile-dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Профиль
                    </a>
                    <a href="settings.php" class="profile-dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6-3.6z"/></svg>
                        Настройки
                    </a>
                    <a href="history.php" class="profile-dropdown-item">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                        История
                    </a>
                    <div style="border-top: 1px solid var(--border-color); margin: 4px 0;"></div>
                    <a href="logout.php" class="profile-dropdown-item" style="color: var(--danger-color);">
                        <svg class="inline-icon" viewBox="0 0 24 24"><path d="M16 17v-3H9v-4h7V7l5 5-5 5M14 2a2 2 0 0 1 2 2v2h-2V4H4v16h10v-2h2v2a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10z"/></svg>
                        Выйти
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sbermed-logo-box">
        <img src="logos/sbermedLogoCyan.svg" alt="SberMedAI" class="sbermed-main-logo">
    </div>

    <div class="medai-subtitle">Экосистема медицинских сервисов на базе ИИ</div>

    <div class="roles-container">
        <!-- Студент -->
        <div class="role-card">
            <div class="role-icon">
                <svg class="inline-icon" viewBox="0 0 24 24" style="width: 40px; height: 40px;"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
            </div>
            <div class="role-text">Поиск информации из методических материалов конкретного ВУЗ'а</div>
            <div class="role-badge">Обучающийся</div>
            <a href="student_chat.php" class="btn-role btn-student">Войти как студент</a>
        </div>

        <!-- Пациент -->
        <div class="role-card">
            <div class="role-icon">
                <svg class="inline-icon" viewBox="0 0 24 24" style="width: 40px; height: 40px;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>
            <div class="role-text">Получение предварительного анализа симптомов</div>
            <div class="role-badge">Пациент</div>
            <a href="patient_chat.php" class="btn-role btn-patient">Войти как пациент</a>
        </div>

        <!-- Врач (PRO) -->
        <div class="role-card" style="position: relative; opacity: 0.85;">
            <div style="position: absolute; top: 15px; right: 15px; background: var(--sber-blue); color: white; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: bold;">PRO</div>
            <div class="role-icon">
                <svg class="inline-icon" viewBox="0 0 24 24" style="width: 40px; height: 40px;"><path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11h-4v4h-4v-4H6v-4h4V6h4v4h4v4z"/></svg>
            </div>
            <div class="role-text">Доступ к закрытой базе клинических рекомендаций и помощь в постановке диагнозов.</div>
            <div class="role-badge" style="background: var(--bg-subtle-hover); color: var(--text-muted);">Врач</div>
            <button class="btn-role" disabled style="background: var(--border-color); color: var(--text-muted); cursor: not-allowed;">В разработке</button>
        </div>
    </div>
    <div class="footer-disclaimer">Не заменяет поход к специалисту</div>

    <script src="assets/js/chat.js?v=<?= time() ?>"></script>
    <script>
        function toggleProfileMenu(event) {
            event.stopPropagation();
            const menu = document.getElementById('profileDropdownMenu');
            if (menu) menu.classList.toggle('show');
        }
        window.addEventListener('click', function() {
            const menu = document.getElementById('profileDropdownMenu');
            if (menu && menu.classList.contains('show')) menu.classList.remove('show');
        });
    </script>
</body>
</html>