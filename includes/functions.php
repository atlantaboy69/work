<?php
// functions.php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function loadDotEnv(string $file = ''): void {
    if (!is_readable($file)) return;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) continue;

        $name = $matches[1];
        $value = $matches[2];

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
            $value = str_replace(['\\n', '\\r', '\\t', '\\"'], ["\n", "\r", "\t", '"'], $value);
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadDotEnv(__DIR__ . '/../.env');

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }
    session_save_path($sessionPath);
    
    // Устанавливаем долговечные куки для сессии (30 дней)
    session_set_cookie_params([
        'lifetime' => 30 * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();

    // Автоматическая очистка устаревших сессий (> 30 дней)
    if (rand(1, 20) === 1) {
        $files = glob($sessionPath . '/sess_*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > 30 * 86400)) {
                @unlink($file);
            }
        }
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_login']) && !isset($_SESSION['is_guest']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php?auth=1');
        exit;
    }
}

/**
 * Разрешить гостям доступ к чату с сохранением кук
 */
function allowGuestOrRequireLogin(): void {
    if (!isset($_SESSION['user_login'])) {
        $_SESSION['is_guest'] = true;
        if (!empty($_COOKIE['medai_guest_id'])) {
            $_SESSION['user_login'] = $_COOKIE['medai_guest_id'];
        } else {
            $guestId = 'guest_' . substr(uniqid(), 0, 8);
            $_SESSION['user_login'] = $guestId;
            setcookie('medai_guest_id', $guestId, time() + (30 * 86400), '/');
        }
    }

    if (!isset($_SESSION['guest_queries']) && isset($_COOKIE['medai_guest_queries'])) {
        $_SESSION['guest_queries'] = (int)$_COOKIE['medai_guest_queries'];
    }
}

function getGuestQueryCount(): int {
    $today = date('Y-m-d');
    $cookieDate = $_COOKIE['medai_guest_date'] ?? '';
    $sessionDate = $_SESSION['guest_date'] ?? '';

    // Если наступил новый день — сбрасываем дневной лимит запросов
    if ($sessionDate !== $today && $cookieDate !== $today) {
        $_SESSION['guest_queries'] = 0;
        $_SESSION['guest_date'] = $today;
        setcookie('medai_guest_queries', '0', time() + (30 * 86400), '/');
        setcookie('medai_guest_date', $today, time() + (30 * 86400), '/');
        return 0;
    }

    if (!isset($_SESSION['guest_queries'])) {
        $_SESSION['guest_queries'] = (int)($_COOKIE['medai_guest_queries'] ?? 0);
        $_SESSION['guest_date'] = $today;
    }
    return $_SESSION['guest_queries'];
}

function incrementGuestQueryCount(): void {
    $today = date('Y-m-d');
    $current = getGuestQueryCount();
    $newCount = $current + 1;

    $_SESSION['guest_queries'] = $newCount;
    $_SESSION['guest_date'] = $today;

    setcookie('medai_guest_queries', (string)$newCount, time() + (30 * 86400), '/');
    setcookie('medai_guest_date', $today, time() + (30 * 86400), '/');
}

function isGuestLimitExceeded(): bool {
    return !empty($_SESSION['is_guest']) && getGuestQueryCount() >= 3;
}

/**
 * ОПРЕДЕЛЕНИЕ ОБЛАСТИ: Относится ли запрос к грамматике латыни или запросу перевода
 */
function isLatinLanguageQuery(string $query): bool {
    $q = mb_strtolower($query, 'UTF-8');

    $latinTriggers = [
        'латын', 'латинск', 'падеж', 'склонен', 'грамматик', 'предлог',
        'суффикс', 'приставк', 'терминоэлемент', 'морфем', 'словообразовани',
        'окончани', 'правило', 'грамматическ', 'согласован', 'чернявски',
        'на латыни', 'на латинском', 'как на латинском', 'как будет на латинском',
        'переведи на латынь', 'словарная форма'
    ];

    foreach ($latinTriggers as $trigger) {
        if (mb_strpos($q, $trigger) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Вспомогательная функция для пословного стемминга
 */
function getRussianStem(string $text): string {
    $words = preg_split('/\s+/u', mb_strtolower(trim($text), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    if (empty($words)) return '';

    $stemmedWords = array_map(function($word) {
        if (mb_strlen($word, 'UTF-8') <= 3) {
            return $word;
        }
        return preg_replace('/(оща|еща|ами|ями|ого|его|ому|ему|ыми|ими|ах|ях|ов|ев|ей|ом|ем|ам|ям|а|я|о|е|ы|и|ь|у|ю)$/u', '', $word);
    }, $words);

    return implode(' ', $stemmedWords);
}

/**
 * ДЕТЕКТОР НАМЕРЕНИЙ
 */
function detectIntent(string $query): array 
{
    $cleanQuery = mb_strtolower(trim($query), 'UTF-8');
    $words = preg_split('/\s+/u', $cleanQuery, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($words);

    $imageTriggers = [
        'покажи', 'схема', 'рисунок', 'фото', 'картинка', 
        'иллюстрация', 'где находится', 'как выглядит', 'рис'
    ];

    $textTriggers = [
        'объясни', 'расскажи', 'функция', 'функции', 
        'строение', 'патология', 'принцип', 'опиши', 'теория',
        'падеж', 'склонение', 'правило', 'перевод', 'предлог', 'суффикс', 'корень',
        'причины', 'этиология', 'симптом', 'лечение', 'разбор'
    ];

    $wantsImage = false;
    $wantsText  = false;

    foreach ($imageTriggers as $trigger) {
        if (mb_strpos($cleanQuery, $trigger) !== false) {
            $wantsImage = true;
            break;
        }
    }

    foreach ($textTriggers as $trigger) {
        if (mb_strpos($cleanQuery, $trigger) !== false) {
            $wantsText = true;
            break;
        }
    }

    if (!$wantsImage && !$wantsText) {
        if ($wordCount <= 3) {
            $wantsImage = true;
            $wantsText  = true;
        } else {
            $wantsText  = true;
        }
    }

    return [
        'wants_image' => $wantsImage,
        'wants_text'  => $wantsText
    ];
}

/**
 * Проверка наличия контекстных местоимений
 */
function hasContextualPronouns(string $query): bool {
    $q = mb_strtolower($query, 'UTF-8');
    $pronouns = [
        ' их ', ' его ', ' её ', ' ей ', ' ему ', ' им ', ' ними ', ' они ',
        ' это ', ' этого ', ' этой ', ' этим ', ' этих ', ' данных ', ' данного ',
        ' у него ', ' у нее ', ' у них ', ' их', 'его', 'её', ' у мужчин', ' у женщин'
    ];

    foreach ($pronouns as $p) {
        if (mb_strpos(' ' . $q . ' ', $p) !== false) {
            return true;
        }
    }

    $clean = preg_replace('/(покажи|показать|где|находится|как|выглядит|местонахождение|расположение|функция|иннервация|кровоснабжение|причины|этиология|у|мужчин|женщин)/ui', '', $q);
    $clean = trim($clean);
    if (mb_strlen($clean, 'UTF-8') < 4) {
        return true;
    }

    return false;
}

/**
 * Извлечение понятия из контекста сообщений
 */
function extractContextSubject(array $dbMessages, $pdo): ?string {
    if (empty($dbMessages)) return null;

    $count = count($dbMessages);
    for ($i = $count - 2; $i >= 0; $i--) {
        $msg = $dbMessages[$i];
        if (($msg['role'] ?? '') === 'user') {
            $parts = is_array($msg['parts']) ? $msg['parts'] : json_decode($msg['parts'], true);
            $prevText = '';
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    if (isset($p['text'])) $prevText .= $p['text'] . ' ';
                }
            }
            $prevText = trim($prevText);
            if (empty($prevText)) continue;

            $cleanPrev = preg_replace('/(покажи|показать|объясни|расскажи|где находится|как выглядит|фото|картинка|рисунок|схема|описание|строение|функции|полное|подробное|а|как|будет|на|латинском|латыни|переведи|причины|причина|вывиха|вывих|патология|этиология|симптом|симптомы|лечение|почему|факторы|положение|кровоснабжение|иннервация|разбор|мне|его|её|их|у|в|для|о|об)/ui', '', $prevText);
            $cleanPrev = trim($cleanPrev) ?: $prevText;
            $stemPrev  = getRussianStem($cleanPrev);

            try {
                $stmt = $pdo->prepare("
                    SELECT term_ru, term_lat 
                    FROM anatomy_terms 
                    WHERE term_ru % :q OR term_lat % :q OR term_ru % :stem OR term_ru ILIKE :like
                    ORDER BY GREATEST(similarity(term_ru, :q), similarity(term_ru, :stem)) DESC
                    LIMIT 1
                ");
                $stmt->execute([
                    'q'    => $cleanPrev,
                    'stem' => $stemPrev,
                    'like' => '%' . $cleanPrev . '%'
                ]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row['term_ru'];
                }
            } catch (Exception $e) {}

            try {
                $stmtVocab = $pdo->prepare("
                    SELECT title, content, figure_labels 
                    FROM knowledge_base 
                    WHERE source_type = 'latin_vocab'
                      AND (title ILIKE :like OR content ILIKE :like)
                    LIMIT 1
                ");
                $stmtVocab->execute(['like' => '%' . $cleanPrev . '%']);
                $vRow = $stmtVocab->fetch(PDO::FETCH_ASSOC);
                if ($vRow) {
                    if (preg_match('/Перевод:\s*([^\n]+)/ui', $vRow['content'], $mRu)) {
                        return trim($mRu[1]);
                    }
                    return $cleanPrev;
                }
            } catch (Exception $e) {}

            if (mb_strlen($cleanPrev, 'UTF-8') >= 3) {
                return $cleanPrev;
            }
        }
    }
    return null;
}

/**
 * Инлайн-маркдаун
 */
function formatInlineMarkdown(string $str): string {
    if (empty($str)) return '';
    $str = preg_replace('/\*\*\*(.*?)\*\*\*/s', '<strong><em>$1</em></strong>', $str);
    $str = preg_replace('/___(.*?)___/s', '<strong><em>$1</em></strong>', $str);
    $str = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $str);
    $str = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $str);
    $str = preg_replace('/`([^`\n]+)`/', '<code class="markdown-inline-code">$1</code>', $str);
    $str = preg_replace('/\*([^\*\n]+)\*/s', '<em>$1</em>', $str);
    $str = preg_replace('/_([^_\n]+)_/s', '<em>$1</em>', $str);
    $str = preg_replace('/~~(.*?)~~/s', '<del>$1</del>', $str);
    return $str;
}

function formatMarkdownMessage(?string $text, ?array $interactiveTerms = null): string {
    if (empty($text)) return '';

    $text = preg_replace('/<(think|thought)>.*?<\/\1>/s', '', $text);

    // 1. ИЗВЛЕКАЕМ МАТЕМАТИЧЕСКИЕ БЛОКИ ($$ ... $$ или \[ ... \]) В ЗАГЛУШКИ ДО ЭКРАНИРОВАНИЯ
    $mathBlocks = [];
    $text = preg_replace_callback('/(\$\$[\s\S]*?\$\$|\\\[[\s\S]*?\\\])/s', function($matches) use (&$mathBlocks) {
        $placeholder = "@@@MATH_BLOCK_" . count($mathBlocks) . "@@@";
        $mathBlocks[$placeholder] = $matches[1];
        return $placeholder;
    }, $text);

    // 2. ИЗВЛЕКАЕМ БЛОКИ КОДА (``` ... ```) В ЗАГЛУШКИ
    $codeBlocks = [];
    $text = preg_replace_callback('/```([a-zA-Z0-9_+-]*)[ \t]*\r?\n?(.*?)```/s', function($matches) use (&$codeBlocks) {
        $placeholder = "@@@CODE_BLOCK_" . count($codeBlocks) . "@@@";
        $codeBlocks[$placeholder] = [
            'lang' => trim($matches[1]),
            'code' => trim($matches[2])
        ];
        return $placeholder;
    }, $text);

    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $lines = explode("\n", $text);
    $inTable = false;
    $processedLines = [];
    $tableRows = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (strpos($trimmed, '@@@CODE_BLOCK_') !== false || strpos($trimmed, '@@@MATH_BLOCK_') !== false) {
            $processedLines[] = $line;
            continue;
        }

        if ($trimmed === '*' || $trimmed === '-' || $trimmed === '***') continue;

        if (substr_count($trimmed, '|') >= 2) {
            if (preg_replace('/[\s|:-]/', '', $trimmed) === '') continue;

            $cells = explode('|', $trimmed);
            if (empty(trim($cells[0]))) array_shift($cells);
            if (!empty($cells) && empty(trim($cells[count($cells) - 1]))) array_pop($cells);
            $cells = array_map('trim', $cells);

            if (!$inTable) {
                $inTable = true;
                $tableRows[] = '<thead><tr>';
                foreach ($cells as $cell) $tableRows[] = '<th>' . formatInlineMarkdown($cell) . '</th>';
                $tableRows[] = '</tr></thead><tbody>';
            } else {
                $tableRows[] = '<tr>';
                foreach ($cells as $cell) $tableRows[] = '<td>' . formatInlineMarkdown($cell) . '</td>';
                $tableRows[] = '</tr>';
            }
            continue;
        } else if ($inTable) {
            $tableRows[] = '</tbody>';
            $processedLines[] = '<div class="table-container"><table class="markdown-table">' . implode('', $tableRows) . '</table></div>';
            $inTable = false;
            $tableRows = [];
        }

        if (preg_match('/^(#{1,6})\s*(.*?)$/', $trimmed, $m)) {
            $level = strlen($m[1]);
            $title = formatInlineMarkdown(trim($m[2]));
            $processedLines[] = "<h{$level} class=\"chat-h{$level}\">{$title}</h{$level}>";
            continue;
        }
        if (preg_match('/^---$/', $trimmed)) {
            $processedLines[] = '<hr class="markdown-hr">';
            continue;
        }
        if (preg_match('/^&gt;[ \t]*(.*?)$/', $trimmed, $m)) {
            $processedLines[] = '<blockquote class="markdown-blockquote">' . formatInlineMarkdown($m[1]) . '</blockquote>';
            continue;
        }

        if (preg_match('/^[\*\-\+\x{2022}\x{2013}\x{2014}]\s*(.*)$/u', $trimmed, $m)) {
            $itemContent = formatInlineMarkdown($m[1]);
            $processedLines[] = '<div class="md-line">• ' . $itemContent . '</div>';
            continue;
        }

        if (preg_match('/^(\d+\.)\s*(.*)$/', $trimmed, $m)) {
            $processedLines[] = '<div class="md-line"><strong>' . $m[1] . '</strong> ' . formatInlineMarkdown($m[2]) . '</div>';
            continue;
        }

        if (!empty($trimmed)) {
            $lineContent = formatInlineMarkdown($trimmed);
            $processedLines[] = '<div class="md-line">' . $lineContent . '</div>';
        }
    }

    if ($inTable) {
        $tableRows[] = '</tbody>';
        $processedLines[] = '<div class="table-container"><table class="markdown-table">' . implode('', $tableRows) . '</table></div>';
    }

    $finalText = implode("", $processedLines);

    // ВОССТАНАВЛИВАЕМ МАТЕМАТИЧЕСКИЕ БЛОКИ
    foreach ($mathBlocks as $placeholder => $block) {
        $mathHtml = '<div class="math-block-container" style="margin: 10px 0; overflow-x: auto;">' . $block . '</div>';
        $finalText = str_replace($placeholder, $mathHtml, $finalText);
    }

    // ВОССТАНАВЛИВАЕМ БЛОКИ КОДА
    foreach ($codeBlocks as $placeholder => $data) {
        $lang = $data['lang'] ? htmlspecialchars($data['lang'], ENT_QUOTES, 'UTF-8') : 'code';
        $code = htmlspecialchars($data['code'], ENT_QUOTES, 'UTF-8');

        $html = '
        <div class="code-block-wrapper">
            <div class="code-block-header">
                <span class="code-lang">' . $lang . '</span>
                <button class="code-copy-btn" onclick="copyCodeSnippet(this)" data-code="' . $code . '">
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    Копировать
                </button>
            </div>
            <pre class="code-block-body"><code>' . $code . '</code></pre>
        </div>';

        $finalText = str_replace($placeholder, $html, $finalText);
    }

    if (!empty($interactiveTerms) && is_array($interactiveTerms) && is_string($finalText)) {
        uksort($interactiveTerms, function($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });

        $termPlaceholders = [];

        foreach ($interactiveTerms as $termKey => $termData) {
            if (empty($termKey) || mb_strlen($termKey, 'UTF-8') < 3) continue;
            $esc = preg_quote($termKey, '/');
            $pattern = '/(<[^>]+>)|(\b' . $esc . '\b)/ui';

            $res = preg_replace_callback($pattern, function($matches) use ($termKey, $termData, &$termPlaceholders) {
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                $matchedTerm = $matches[2];
                $img = htmlspecialchars($termData['image_url'] ?? '', ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($termData['title'] ?? $matchedTerm, ENT_QUOTES, 'UTF-8');
                $pageNum = intval($termData['page_num'] ?? 0);
                $termAttr = htmlspecialchars($termKey, ENT_QUOTES, 'UTF-8');
                $badge = '<span class="interactive-term" data-term="' . $termAttr . '" data-img="' . $img . '" data-title="' . $title . '" data-page="' . $pageNum . '" onclick="handleTermClick(this, event)">' . $matchedTerm . '</span>';
                
                $ph = '@@@INTERACTIVE_TERM_' . count($termPlaceholders) . '@@@';
                $termPlaceholders[$ph] = $badge;
                return $ph;
            }, $finalText);

            if ($res !== null) {
                $finalText = $res;
            }
        }

        foreach ($termPlaceholders as $ph => $htmlTag) {
            $finalText = str_replace($ph, $htmlTag, $finalText);
        }

        // Пост-обработка: нахождение скобок вокруг интерактивных плашек (включая <em>/<strong>) и объединение в .interactive-term-group
        $finalText = preg_replace_callback('/\(\s*([^\)]*?<span class="interactive-term"[^>]*>.*?<\/span>[^\(]*?)\s*\)/ui', function($m) {
            return '<span class="interactive-term-group">(' . trim($m[1]) . ')</span>';
        }, $finalText);
    }

    return $finalText;
}

/**
 * 3-ЭТАПНЫЙ ГИБРИДНЫЙ ПОИСК ПО БАЗЕ ЗНАНИЙ (С ПОДДЕРЖКОЙ КОНТЕКСТА ДИАЛОГА)
 */
function searchVectorKnowledgeBase($pdo, string $userQuery, int $limit = 3, array $dbMessages = []): array {
    $yandexApiKey   = getenv('YANDEX_CLOUD_API_KEY');
    $yandexFolderId = getenv('YANDEX_CLOUD_FOLDER');

    $result = [
        'context'         => '',
        'image'           => null,
        'image_not_found' => false,
        'wants_image'     => false,
        'wants_text'      => false,
        'is_latin_query'  => false
    ];

    $logFile = __DIR__ . '/../logs/rag_debug.log';
    $log = function(string $msg) use ($logFile) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    };

    $trimmedQuery = trim($userQuery);
    if (empty($trimmedQuery)) {
        $log("Запрос пустой.");
        return $result;
    }

    $log("=================== НОВЫЙ ЗАПРОС ПОЛЬЗОВАТЕЛЯ ===================");
    $log("Запрос: '{$trimmedQuery}'");

    // 1. АВТООПРЕДЕЛЕНИЕ ОБЛАСТИ И КОНТЕКСТА ДИАЛОГА
    $effectiveQuery = $trimmedQuery;
    $contextSubject = null;

    if (hasContextualPronouns($trimmedQuery) && !empty($dbMessages)) {
        $contextSubject = extractContextSubject($dbMessages, $pdo);
        if ($contextSubject) {
            $effectiveQuery = $contextSubject . ' ' . $trimmedQuery;
            $log("Обнаружен контекстный запрос. Извлечен предмет из истории: '{$contextSubject}'");
            $log("Эффективный запрос для RAG: '{$effectiveQuery}'");
        }
    }

    $isLatinQuery = isLatinLanguageQuery($effectiveQuery);
    $result['is_latin_query'] = $isLatinQuery;
    $log("Область знания: " . ($isLatinQuery ? "УЧЕБНИК ЛАТЫНИ / ПЕРЕВОД" : "АНАТОМИЯ (учебник/атлас)"));

    // 2. ДЕТЕКТОР НАМЕРЕНИЙ
    $intent = detectIntent($trimmedQuery);
    $wantsImage = $intent['wants_image'];
    $wantsText  = $intent['wants_text'];

    $result['wants_image'] = $wantsImage;
    $result['wants_text']  = $wantsText;

    $log("Намерение -> Показать картинку: " . ($wantsImage ? 'ДА' : 'НЕТ') . " | Текст: " . ($wantsText ? 'ДА' : 'НЕТ'));

    // Расширенная очистка от служебного мусора, мата, домашек и вводных слов
    $stopWordsPattern = '/(покажи|показать|объясни|расскажи|где находится|как выглядит|фото|картинка|рисунок|схема|описание|строение|функции|полное|подробное|а|как|будет|на|латинском|латыни|переведи|причины|причина|вывиха|вывих|патология|этиология|симптом|симптомы|лечение|почему|факторы|местонахождение|расположение|положение|разбор|мне|его|её|их|у|в|для|о|об|про|заебал|заебала|заебали|задолбал|домашки|домашка|задает|задал|задали|учитель|препод|калина|слушай|подскажи|плиз|пожалуйста|пж|че|что|такое|работа|работу)/ui';

    $cleanSearchQuery = preg_replace($stopWordsPattern, '', $effectiveQuery);
    $cleanSearchQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleanSearchQuery);
    $cleanSearchQuery = trim(preg_replace('/\s+/u', ' ', $cleanSearchQuery));
    $cleanSearchQuery = $cleanSearchQuery ?: $trimmedQuery;

    $stemmedQuery  = getRussianStem($cleanSearchQuery);
    $stemFirstWord = '%' . explode(' ', $stemmedQuery)[0] . '%';

    $log("Очищенный ключевой запрос для поиска: '{$cleanSearchQuery}' (стемминг: '{$stemmedQuery}')");

    $modifierQuery = '';
    if ($contextSubject) {
        $modifierQuery = preg_replace('/(покажи|показать|мне|его|её|их|у|в|для|о|об)/ui', '', $trimmedQuery);
        $modifierQuery = trim($modifierQuery);
    }

    // 3. ШАГ 1: ПОИСК В СЛОВАРЕ ANATOMY_TERMS
    $matchedTermRu  = $contextSubject;
    $matchedTermLat = null;

    if (!$matchedTermRu) {
        try {
            $stmtTerm = $pdo->prepare("
                SELECT canonical_name, term_ru, term_lat,
                       GREATEST(
                           similarity(term_ru, :q), 
                           similarity(COALESCE(term_lat, ''), :q),
                           similarity(term_ru, :stem)
                       ) as sim
                FROM anatomy_terms
                WHERE (term_ru ILIKE :like OR term_lat ILIKE :like)
                   OR similarity(term_ru, :q) >= 0.40
                   OR similarity(COALESCE(term_lat, ''), :q) >= 0.40
                ORDER BY sim DESC
                LIMIT 1
            ");

            $stmtTerm->execute([
                'q'    => $cleanSearchQuery,
                'stem' => $stemmedQuery,
                'like' => '%' . $cleanSearchQuery . '%'
            ]);

            $matchedTerm = $stmtTerm->fetch(PDO::FETCH_ASSOC);

            if ($matchedTerm) {
                $matchedTermRu  = $matchedTerm['term_ru'];
                $matchedTermLat = $matchedTerm['term_lat'];
                $log("Шаг 1: anatomy_terms распознали: '{$cleanSearchQuery}' -> RU: '{$matchedTermRu}', LAT: '{$matchedTermLat}' (Sim: " . round((float)$matchedTerm['sim'], 4) . ")");
            } else {
                $log("Шаг 1: В словаре anatomy_terms прямого совпадения не найдено.");
            }
        } catch (PDOException $e) {
            $log("Предупреждение: ошибка обращения к anatomy_terms: " . $e->getMessage());
        }
    }

    // 4. ВЕКТОР ЗАПРОСА
    $vectorText = $matchedTermRu ?: $cleanSearchQuery;
    if (!empty($modifierQuery)) {
        $vectorText .= ' ' . $modifierQuery;
    }

    $queryVector = null;
    if (!empty($yandexApiKey) && !empty($yandexFolderId)) {
        $ch = curl_init("https://llm.api.cloud.yandex.net/foundationModels/v1/textEmbedding");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                "modelUri" => "emb://{$yandexFolderId}/text-search-query/latest",
                "text"     => mb_substr($vectorText, 0, 2000, 'UTF-8')
            ]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Api-Key ' . $yandexApiKey,
                'x-folder-id: ' . $yandexFolderId
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            if (isset($resData['embedding']) && is_array($resData['embedding'])) {
                $queryVector = $resData['embedding'];
            }
        }
    }

    // 5. ШАГ 2: ПОИСК В LATIN_VOCAB
    $isShortOrPronoun = (mb_strlen($cleanSearchQuery, 'UTF-8') < 3 || preg_match('/^(их|его|ее|ей|ему|им|они|это|этого|этой|все|всё)$/ui', $cleanSearchQuery));

    if (!$matchedTermRu && !$isLatinQuery && !$isShortOrPronoun) {
        try {
            $vectorStr = $queryVector ? '[' . implode(',', $queryVector) . ']' : null;

            $sqlVocab = "
                SELECT title, content, figure_labels,
                       " . ($vectorStr ? "(1.0 - (embedding <=> :vector))" : "0.0") . " AS vec_sim,
                       GREATEST(
                           similarity(title, :q), 
                           similarity(COALESCE(figure_labels, ''), :q),
                           similarity(content, :q),
                           similarity(title, :stem)
                       ) AS tri_sim
                FROM knowledge_base 
                WHERE source_type = 'latin_vocab'
                  AND (
                      title ILIKE :like 
                   OR content ILIKE :like
                   OR similarity(title, :q) >= 0.2
                   OR similarity(title, :stem) >= 0.2
                   " . ($vectorStr ? "OR (1.0 - (embedding <=> :vector)) >= 0.35" : "") . "
                  )
                ORDER BY (
                    " . ($vectorStr ? "((1.0 - (embedding <=> :vector)) * 1.5)" : "0.0") . "
                    + (GREATEST(similarity(title, :q), similarity(COALESCE(figure_labels, ''), :q), similarity(content, :q), similarity(title, :stem)) * 2.0)
                    + (CASE WHEN title ILIKE :like THEN 2.0 WHEN content ILIKE :like THEN 1.0 ELSE 0.0 END)
                ) DESC
                LIMIT 1
            ";

            $stmtVocab = $pdo->prepare($sqlVocab);
            if ($vectorStr) {
                $stmtVocab->bindValue(':vector', $vectorStr, PDO::PARAM_STR);
            }
            $stmtVocab->bindValue(':q',    $cleanSearchQuery, PDO::PARAM_STR);
            $stmtVocab->bindValue(':stem', $stemmedQuery,     PDO::PARAM_STR);
            $stmtVocab->bindValue(':like', '%' . $cleanSearchQuery . '%', PDO::PARAM_STR);

            $stmtVocab->execute();
            $vocabRow = $stmtVocab->fetch(PDO::FETCH_ASSOC);

            if ($vocabRow) {
                $foundLat = !empty($vocabRow['figure_labels']) ? $vocabRow['figure_labels'] : '';
                $foundRu  = '';

                if (preg_match('/Перевод:\s*([^\n]+)/ui', $vocabRow['content'], $mRu)) {
                    $foundRu = trim($mRu[1]);
                } elseif (preg_match('/—\s*(.+)$/u', $vocabRow['title'], $mRu2)) {
                    $foundRu = trim($mRu2[1]);
                } else {
                    $foundRu = $vocabRow['title'];
                }

                $matchedTermRu  = $foundRu;
                $matchedTermLat = $foundLat;

                $log("Шаг 2: В latin_vocab гибридным поиском найден термин -> RU: '{$matchedTermRu}', LAT: '{$matchedTermLat}'");
            } else {
                $log("Шаг 2: В latin_vocab гибридным поиском ничего не найдено.");
            }
        } catch (PDOException $e) {
            $log("Предупреждение: ошибка гибридного поиска в latin_vocab: " . $e->getMessage());
        }
    }

    // 6. КЛЮЧЕВЫЕ СЛОВА ДЛЯ УЧЕБНИКА
    $searchTermToUse = $matchedTermRu ?: $cleanSearchQuery;
    $subTermToUse    = !empty($modifierQuery) ? $modifierQuery : ($matchedTermLat ?: $cleanSearchQuery);

    // 7. ШАГ 3: ПОИСК В KNOWLEDGE_BASE С БАЛАНСИРОВКОЙ
    try {
        $vectorStr       = $queryVector ? '[' . implode(',', $queryVector) . ']' : null;
        $searchTermLike  = '%' . $searchTermToUse . '%';
        $searchStemFirst = '%' . explode(' ', getRussianStem($searchTermToUse))[0] . '%';
        $subTermLike     = '%' . getRussianStem($subTermToUse) . '%';

        $rawRows = [];

        if ($isLatinQuery) {
            $sql = "
                SELECT title, content, image_data, figure_info, figure_labels, source_type, page_num,
                       " . ($vectorStr ? "(1.0 - (embedding <=> :vector))" : "0.0") . " AS vec_sim
                FROM knowledge_base
                WHERE source_type IN ('latin_vocab', 'latin_morpheme', 'latin_rule', 'latin_table')
                ORDER BY (
                    " . ($vectorStr ? "((1.0 - (embedding <=> :vector)) * 2.0)" : "0.0") . "
                    + (CASE WHEN title ILIKE :term THEN 2.0 WHEN title ILIKE :sub_term THEN 1.5 ELSE 0.0 END)
                    + (CASE WHEN content ILIKE :term THEN 0.8 WHEN content ILIKE :sub_term THEN 0.5 ELSE 0.0 END)
                ) DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($sql);
            if ($vectorStr) $stmt->bindValue(':vector', $vectorStr, PDO::PARAM_STR);
            $stmt->bindValue(':term',     $searchTermLike, PDO::PARAM_STR);
            $stmt->bindValue(':sub_term', $subTermLike,    PDO::PARAM_STR);
            $stmt->execute();
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $sql = "
                SELECT title, content, image_data, figure_info, figure_labels, source_type, page_num,
                       " . ($vectorStr ? "(1.0 - (embedding <=> :vector))" : "0.0") . " AS vec_sim
                FROM knowledge_base
                WHERE (source_type NOT LIKE 'latin_%' OR source_type IS NULL)
                ORDER BY (
                    " . ($vectorStr ? "((1.0 - (embedding <=> :vector)) * 2.5)" : "0.0") . "
                    + (CASE WHEN title ILIKE :term OR title ILIKE :stem_first THEN 3.0 WHEN figure_labels ILIKE :stem_first THEN 2.0 ELSE 0.0 END)
                    + (CASE WHEN content ILIKE :term OR content ILIKE :stem_first THEN 2.5 ELSE 0.0 END)
                    + (CASE WHEN (title ILIKE :term OR title ILIKE :stem_first OR content ILIKE :stem_first) AND (title ILIKE :sub_term OR figure_labels ILIKE :sub_term OR content ILIKE :sub_term) THEN 3.0 ELSE 0.0 END)
                ) DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($sql);
            if ($vectorStr) $stmt->bindValue(':vector', $vectorStr, PDO::PARAM_STR);
            $stmt->bindValue(':term',       $searchTermLike,  PDO::PARAM_STR);
            $stmt->bindValue(':stem_first', $searchStemFirst, PDO::PARAM_STR);
            $stmt->bindValue(':sub_term',   $subTermLike,     PDO::PARAM_STR);
            $stmt->execute();
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $rows = [];
        if (!empty($rawRows)) {
            $textRows   = [];
            $figureRows = [];

            foreach ($rawRows as $r) {
                if (($r['source_type'] ?? '') === 'figure') {
                    $figureRows[] = $r;
                } else {
                    $textRows[] = $r;
                }
            }

            if ($wantsText && !empty($textRows)) {
                $maxTextToTake = ($wantsImage && !empty($figureRows)) ? ($limit - 1) : $limit;
                foreach ($textRows as $tr) {
                    $rows[] = $tr;
                    if (count($rows) >= $maxTextToTake) break;
                }
            }

            foreach ($rawRows as $rr) {
                if (count($rows) >= $limit) break;
                $alreadyAdded = false;
                foreach ($rows as $added) {
                    if (($added['page_num'] ?? 0) === ($rr['page_num'] ?? 0) && ($added['source_type'] ?? '') === ($rr['source_type'] ?? '')) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if (!$alreadyAdded) {
                    $rows[] = $rr;
                }
            }
        }

        if (!empty($rows)) {
            $log("Шаг 3: Сбалансировано и получено материалов: " . count($rows));
            $contextText = "\n\n[СПРАВОЧНЫЕ МАТЕРИАЛЫ ИЗ БАЗЫ ЗНАНИЙ]:\n";

            $searchStemLower = mb_strtolower(explode(' ', getRussianStem($searchTermToUse))[0], 'UTF-8');

            foreach ($rows as $i => $row) {
                $sourceType = $row['source_type'] ?? 'general';
                $pageNum    = $row['page_num'] ?? 0;
                $cleanTitle = preg_replace('/^Страница\s+\d+\s+—\s+Рис\.\s*\d+:\s*/ui', '', $row['title']);
                $vecSim     = isset($row['vec_sim']) ? round((float)$row['vec_sim'], 4) : 0;

                $log("  -> [Запись " . ($i + 1) . "] Категория: '{$sourceType}' | Стр: {$pageNum} | Вектор: {$vecSim} | Заголовок: '{$cleanTitle}'");

                if (str_starts_with($sourceType, 'latin_')) {
                    $contextText .= "--- [УЧЕБНИК ЛАТИНСКОГО ЯЗЫКА (Стр. {$pageNum})]: {$cleanTitle} ---\n{$row['content']}\n\n";
                } else {
                    $contextText .= "--- [АНАТОМИЧЕСКИЙ АТЛАС/УЧЕБНИК (Стр. {$pageNum})]: {$cleanTitle} ---\n{$row['content']}\n\n";
                }

                if ($wantsImage && $result['image'] === null && !empty($row['image_data'])) {
                    $titleAndLabels = mb_strtolower($row['title'] . ' ' . ($row['figure_labels'] ?? '') . ' ' . $row['content'], 'UTF-8');

                    $isRelevantImage = false;
                    if (!empty($searchStemLower) && mb_strpos($titleAndLabels, $searchStemLower) !== false) {
                        $isRelevantImage = true;
                    }

                    if ($isRelevantImage) {
                        $result['image'] = $row['image_data'];
                        $log("     Выбрано РЕЛЕВАНТНОЕ изображение: {$row['title']}");
                    } else {
                        $log("     Изображение ОТКЛОНЕНО как нерелевантное (не содержит корень '{$searchStemLower}'): {$row['title']}");
                    }
                }
            }

            $result['context'] = $contextText;
        } else {
            $log("Шаг 3: Поиск в учебнике не дал результатов.");
        }

        if ($wantsImage && $result['image'] === null) {
            $result['image_not_found'] = true;
            $log("Флаг: Попросили картинку, но релевантное изображение в БД НЕ найдено.");
        }

        return $result;

    } catch (PDOException $e) {
        $log("Ошибка гибридного запроса SQL: " . $e->getMessage());
        return $result;
    }
}

function sendMailNotification(string $to, string $subject, string $message): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;                                
        $mail->CharSet    = 'UTF-8';

        $senderEmail = getenv('SMTP_USER');
        $mail->setFrom($senderEmail, 'MedAI Сервис');
        $mail->addAddress($to);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        file_put_contents('mail_errors.txt', date('Y-m-d H:i:s') . " - Ошибка отправки на {$to}: {$mail->ErrorInfo}\n", FILE_APPEND);
        return false;
    }
}