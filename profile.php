<?php 
include __DIR__ . '/includes/functions.php'; 
requireLogin(); 

$user = $_SESSION['user_login'];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedAI - Профиль пользователя</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="centered-body" style="justify-content: flex-start; padding-top: 40px;">

    <div style="width: 100%; max-width: 450px; text-align: left; margin-bottom: 15px;">
        <a href="dashboard.php" class="filter-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; background-color: transparent; cursor: pointer; padding: 0;">
            <svg class="inline-icon" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> Назад
        </a>
    </div>

    <div class="medai-title">Профиль</div>
    <div class="medai-subtitle">Управление вашим аккаунтом MedAI</div>

    <div class="profile-card">
        <div class="role-icon">👤</div>
        <h3 style="margin-bottom: 10px; font-size: 22px;"><?= htmlspecialchars($user) ?></h3>
        <p style="color: var(--text-muted); margin-bottom: 20px;">Статус: Активен</p>
        
        <div style="background: var(--accent-bg); padding: 15px; border-radius: var(--radius-sm); margin-bottom: 30px; text-align: left;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>Доступ к моделям:</span>
                <span style="color: #10b981; font-weight: bold;">MedAI Flash Lite 1.0</span>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="dashboard.php" class="btn">Вернуться в меню</a>
            <a href="history.php" class="btn btn-patient">Посмотреть историю</a>
            <a href="settings.php" class="btn btn-patient">Настройки</a>
            <a href="logout.php" class="btn" style="background-color: #ef4444; color: white;">Выйти из аккаунта</a>
        </div>
    </div>

    <div class="footer-disclaimer">MedAI - автоматизированная система поддержки решений</div>

</body>
</html>