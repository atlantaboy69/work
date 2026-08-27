import json
import os
import sys
import time
import psycopg2
import requests

# Настройка кодировки вывода для Windows
if sys.platform == "win32":
    sys.stdout.reconfigure(encoding='utf-8')
    sys.stderr.reconfigure(encoding='utf-8')

DATABASE_URL     = os.environ.get("DATABASE_URL")
YANDEX_API_KEY   = os.environ.get("YANDEX_CLOUD_API_KEY")
YANDEX_FOLDER_ID = os.environ.get("YANDEX_CLOUD_FOLDER")

if not DATABASE_URL or not YANDEX_API_KEY or not YANDEX_FOLDER_ID:
    print("[ERROR] Переменные окружения DATABASE_URL, YANDEX_CLOUD_API_KEY или YANDEX_CLOUD_FOLDER не заданы!")
    sys.exit(1)

def get_embedding(text: str) -> list | None:
    """Запрос вектора через Yandex Text Embedding API"""
    url = "https://llm.api.cloud.yandex.net/foundationModels/v1/textEmbedding"
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Api-Key {YANDEX_API_KEY}",
        "x-folder-id": YANDEX_FOLDER_ID,
    }
    payload = {
        "modelUri": f"emb://{YANDEX_FOLDER_ID}/text-search-doc/latest",
        "text": text[:2000]
    }
    for attempt in range(3):
        try:
            resp = requests.post(url, json=payload, headers=headers, timeout=20)
            if resp.status_code == 200:
                return resp.json().get("embedding")
            elif resp.status_code == 429:
                time.sleep(2 ** attempt)
            else:
                return None
        except Exception:
            return None
    return None

def import_morphemes(cur, conn, filepath="../data/chernyavsky_morphemes.jsonl"):
    """Импорт греко-латинских терминоэлементов (приставок, корней, суффиксов)"""
    if not os.path.exists(filepath):
        print(f"[SKIP] Файл {filepath} не найден.")
        return

    print(f"\n[START] Импорт терминоэлементов из {filepath}...")
    count = 0

    with open(filepath, "r", encoding="utf-8") as f:
        for line_num, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                item = json.loads(line)
                greek_te = item.get("greek_te") or ""
                latin_eq = item.get("latin_equiv") or ""
                meaning  = item.get("meaning") or ""
                category = item.get("category") or "терминоэлемент"
                page_num = item.get("source_page") or 0

                title = f"Латинско-греческий ТЭ: {greek_te or latin_eq} ({category})"
                content = f"Терминоэлемент (морфема): {greek_te}\nЛатинский эквивалент: {latin_eq}\nЗначение: {meaning}\nКатегория: {category}\nУчебник Чернявского (стр. {page_num})"
                text_for_vector = f"Медицинский терминоэлемент морфема {category} {greek_te} {latin_eq} значение {meaning}"

                vector = get_embedding(text_for_vector)
                if vector:
                    cur.execute(
                        """INSERT INTO knowledge_base 
                           (source_type, page_num, title, figure_labels, content, embedding)
                           VALUES (%s, %s, %s, %s, %s, %s)""",
                        ("latin_morpheme", page_num, title, f"{greek_te}, {latin_eq}", content, str(vector))
                    )
                    conn.commit()
                    count += 1
            except Exception as e:
                print(f"[ERROR Line {line_num}]: {e}")

    print(f"[OK] Успешно импортировано терминоэлементов: {count}")

def import_vocabulary(cur, conn, filepath="../data/chernyavsky_vocabulary.jsonl"):
    """Импорт полного латинского словаря"""
    if not os.path.exists(filepath):
        print(f"[SKIP] Файл {filepath} не найден.")
        return

    print(f"\n[START] Импорт словаря из {filepath}...")
    count = 0

    with open(filepath, "r", encoding="utf-8") as f:
        for line_num, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                item = json.loads(line)
                word = item.get("latin_word") or ""
                meaning = item.get("russian_meaning") or ""
                page_num = item.get("source_page") or 0

                title = f"Латинский термин: {word} — {meaning}"
                content = f"Словарь Чернявского:\nЛатинское слово/выражение: {word}\nПеревод: {meaning}\nСтраница: {page_num}"
                text_for_vector = f"Латинский термин анатомия медицина словарь {word} перевод {meaning}"

                vector = get_embedding(text_for_vector)
                if vector:
                    cur.execute(
                        """INSERT INTO knowledge_base 
                           (source_type, page_num, title, figure_labels, content, embedding)
                           VALUES (%s, %s, %s, %s, %s, %s)""",
                        ("latin_vocab", page_num, title, word, content, str(vector))
                    )
                    conn.commit()
                    count += 1
            except Exception as e:
                print(f"[ERROR Line {line_num}]: {e}")

    print(f"[OK] Успешно импортировано слов словаря: {count}")

def import_grammar_rules(cur, conn, filepath="../data/chernyavsky_grammar.jsonl"):
    """Импорт правил грамматики и таблиц склонений"""
    if not os.path.exists(filepath):
        print(f"[SKIP] Файл {filepath} не найден.")
        return

    print(f"\n[START] Импорт грамматики и таблиц из {filepath}...")
    count = 0

    with open(filepath, "r", encoding="utf-8") as f:
        for line_num, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                item = json.loads(line)
                page_num = item.get("page", 0)
                rules = item.get("rules", [])
                tables = item.get("tables", [])

                # 1. Заносим правила
                for rule in rules:
                    topic = rule.get("topic", "")
                    rule_body = rule.get("rule_body", "")
                    examples = rule.get("examples", [])
                    ex_str = ", ".join(examples)

                    title = f"Правило латыни: {topic} (Стр. {page_num})"
                    content = f"Тема: {topic}\nПравило: {rule_body}\nПримеры: {ex_str}\n(Чернявский, стр. {page_num})"
                    text_for_vector = f"Правило фонетики грамматики латинского языка {topic}: {rule_body}. Примеры: {ex_str}"

                    vector = get_embedding(text_for_vector)
                    if vector:
                        cur.execute(
                            """INSERT INTO knowledge_base 
                               (source_type, page_num, title, content, embedding)
                               VALUES (%s, %s, %s, %s, %s)""",
                            ("latin_rule", page_num, title, content, str(vector))
                        )
                        conn.commit()
                        count += 1

                # 2. Заносим таблицы
                for table in tables:
                    tbl_title = table.get("title", "Грамматическая таблица")
                    headers = table.get("headers", [])
                    rows = table.get("rows", [])

                    # Формируем читаемый вид таблицы
                    table_text = f"Таблица: {tbl_title}\n"
                    table_text += " | ".join(headers) + "\n"
                    for row in rows:
                        table_text += " | ".join(row) + "\n"

                    title = f"Таблица латыни: {tbl_title} (Стр. {page_num})"
                    content = f"{table_text}\n(Чернявский, стр. {page_num})"
                    text_for_vector = f"Таблица грамматики склонение латынь {tbl_title}: {table_text}"

                    vector = get_embedding(text_for_vector)
                    if vector:
                        cur.execute(
                            """INSERT INTO knowledge_base 
                               (source_type, page_num, title, content, embedding)
                               VALUES (%s, %s, %s, %s, %s)""",
                            ("latin_table", page_num, title, content, str(vector))
                        )
                        conn.commit()
                        count += 1

            except Exception as e:
                print(f"[ERROR Line {line_num}]: {e}")

    print(f"[OK] Успешно импортировано правил и таблиц: {count}")

def main():
    conn = psycopg2.connect(DATABASE_URL)
    cur = conn.cursor()

    print("=== НАЧАЛО ИМПОРТА СТРУКТУРИРОВАННЫХ ДАННЫХ ЧЕРНЯВСКОГО В KNOWLEDGE_BASE ===")

    import_morphemes(cur, conn)
    import_vocabulary(cur, conn)
    import_grammar_rules(cur, conn)

    cur.close()
    conn.close()
    print("\n[SUCCESS] Все структурированные файлы Чернявского успешно векторизованы и занесены в БД!")

if __name__ == "__main__":
    main()