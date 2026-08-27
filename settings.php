<?php 
include __DIR__ . '/includes/functions.php'; 
requireLogin();
require __DIR__ . '/includes/db.php';

$modelMap = [
    'gemini-3.1-flash-lite' => ['name' => 'SberMedAI Flash Lite 1.0', 'desc' => 'Базовая модель. Быстро отвечает на текстовые вопросы и анализирует снимки.'],
];

if (isset($_SERVER['HTTP_REFERER']) && !str_contains($_SERVER['HTTP_REFERER'], 'settings.php') && !str_contains($_SERVER['HTTP_REFERER'], 'profile.php')) {
    $_SESSION['settings_back_url'] = $_SERVER['HTTP_REFERER'];
}
$backUrl = $_SESSION['settings_back_url'] ?? 'dashboard.php';

$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['model'])) {
    $chosen = $_POST['model'];
    if (array_key_exists($chosen, $modelMap)) {
        $_SESSION['ai_model'] = $chosen; 
        $successMsg = 'Модель успешно сохранена!';
    }
}

$currentModel = $_SESSION['ai_model'] ?? 'gemini-3.1-flash-lite';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SberMedAI - Настройки</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="centered-body" style="justify-content: flex-start; padding-top: 40px;">

    <div style="width: 100%; max-width: 800px; text-align: left; margin-bottom: 15px; padding: 0 15px; box-sizing: border-box;">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="filter-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg class="inline-icon" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> Назад
        </a>
    </div>

    <div class="medai-title" style="color: var(--sber-blue);">Настройки</div>
    <div class="medai-subtitle">Управление ИИ-моделями SberMedAI</div>

    <div style="width: 100%; max-width: 800px; display: flex; flex-direction: column; gap: 20px; padding: 0 15px; box-sizing: border-box;">
        <form method="POST" action="">
            <div class="history-card" style="box-shadow: none;">
                <h4 style="font-size: 18px; color: var(--text-dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <svg class="inline-icon" viewBox="0 0 24 24" style="color: var(--sber-blue);"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    Выбор нейросети
                </h4>

                <?php if ($successMsg): ?>
                    <div style="background: var(--success-bg); border: 1px solid var(--primary-color); color: var(--text-dark); padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: bold; font-size: 14px;">
                        ✓ <?= htmlspecialchars($successMsg) ?>
                    </div>
                <?php endif; ?>

                <label style="display: block; font-size: 14px; color: var(--text-muted); margin-bottom: 12px;">Выберите активную модель для анализа</label>

                <?php foreach ($modelMap as $modelId => $info): ?>
                <label class="model-option">
                    <input type="radio" name="model" value="<?= htmlspecialchars($modelId) ?>" <?= $modelId === $currentModel ? 'checked' : '' ?>>
                    <div class="model-option-body">
                        <div class="model-option-name" style="color: var(--sber-blue); font-weight: bold;"><?= htmlspecialchars($info['name']) ?></div>
                        <div class="model-option-desc"><?= htmlspecialchars($info['desc']) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>

                <button type="submit" class="btn" style="margin-top: 8px; padding: 14px 24px; width: 100%;">Сохранить выбор</button>
            </div>
        </form>

        <div class="history-card" style="border-color: var(--sber-blue); background: var(--bg-accent); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="font-size: 20px; color: var(--sber-blue); display: flex; align-items: center; gap: 8px; margin: 0;">
                    <svg class="inline-icon" viewBox="0 0 24 24" style="color: var(--sber-blue); width: 24px; height: 24px;"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    SberMedAI PRO
                </h4>
                <span class="badge" style="background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border-color);">Неактивна</span>
            </div>
            <p style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 20px;">
                Перейдите на профессиональный уровень. Подписка открывает доступ к мощнейшим медицинским моделям Сбера, предназначенным для сложной диагностики и глубокого обучения.
            </p>
            <button class="btn" onclick="alert('Функция оформления подписки находится в разработке.');" style="background: var(--sber-blue); color: white; border: none; font-weight: bold; padding: 16px; width: 100%; transition: background 0.2s; cursor: pointer;">
                Оформить подписку
            </button>
        </div>
    </div>
</body>
</html>