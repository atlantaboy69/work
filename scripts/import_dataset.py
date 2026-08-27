import json
import os
import sys
import time
import psycopg2
import requests

DATABASE_URL    = os.environ.get("DATABASE_URL")
YANDEX_API_KEY  = os.environ.get("YANDEX_CLOUD_API_KEY")
YANDEX_FOLDER_ID = os.environ.get("YANDEX_CLOUD_FOLDER")

if not DATABASE_URL or not YANDEX_API_KEY or not YANDEX_FOLDER_ID:
    print("[ERROR] Переменные окружения не заданы!")
    sys.exit(1)

def get_embedding(text: str) -> list | None:
    """Запрос 256-мерного вектора через Yandex Text Embedding API"""
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

def load_images_from_jsonl(jsonl_path):
    """Считывает Base64 картинки страниц из yandex_vision_dataset.jsonl"""
    page_images = {}
    if not os.path.exists(jsonl_path):
        print(f"[WARN] Файл {jsonl_path} не найден. Картинки не будут привязаны.")
        return page_images

    with open(jsonl_path, "r", encoding="utf-8") as f:
        for line_idx, line in enumerate(f):
            line = line.strip()
            if not line:
                continue
            try:
                item = json.loads(line)
                response_text = item.get("response", "")

                # Парсим номер страницы из текста
                page_num = None
                if "Стр." in response_text:
                    try:
                        page_num = int(response_text.split("Стр.")[1].split(")")[0].strip())
                    except:
                        pass

                # Извлекаем картинку Base64
                base64_img = None
                for req in item.get("request", []):
                    for part in req.get("content", []):
                        if part.get("type") == "image":
                            base64_img = part.get("image")
                            break
                    if base64_img:
                        break

                if base64_img and not base64_img.startswith("data:image"):
                    base64_img = f"data:image/jpeg;base64,{base64_img}"

                if page_num and base64_img:
                    page_images[page_num] = base64_img
                elif base64_img:
                    # Если страницу не распарсили, пишем по индексу
                    page_images[line_idx + 1] = base64_img
            except Exception as e:
                print(f"[Error JSONL line {line_idx+1}]: {e}")

    return page_images

def import_merged_datasets():
    structured_json_path = "../data/anatomy_structured_dataset.json"
    vision_jsonl_path = "../data/yandex_vision_dataset.jsonl"

    if not os.path.exists(structured_json_path):
        print(f"[ERROR] Файл {structured_json_path} не найден!")
        sys.exit(1)

    # 1. Загружаем Base64 картинки
    images_map = load_images_from_jsonl(vision_jsonl_path)

    # 2. Подключаемся к БД
    conn = psycopg2.connect(DATABASE_URL)
    cur = conn.cursor()

    # 3. Считываем структурированный файл
    with open(structured_json_path, "r", encoding="utf-8") as f:
        pages = json.load(f)

    print(f"[START] Импорт {len(pages)} страниц...")

    for page_obj in pages:
        page_num = page_obj.get("page")
        content = page_obj.get("content", {})
        main_text = content.get("main_text", "").strip()
        figures = content.get("figures", [])

        # Забираем картинку для этой страницы
        image_data = images_map.get(page_num, None)

        # ------------------------------------------------------------------
        # А) ВСТАВКА ТЕОРЕТИЧЕСКОГО ТЕКСТА (source_type = 'book_text')
        # Векторизуется ТОЛЬКО теоретический текст
        # ------------------------------------------------------------------
        if main_text:
            text_title = f"Атлас анатомии — Страница {page_num} (Теория)"
            vector_text = f"Теория анатомии страница {page_num}: {main_text}"
            vector = get_embedding(vector_text)

            if vector:
                cur.execute(
                    """INSERT INTO knowledge_base 
                       (source_type, page_num, title, main_text, content, image_data, embedding)
                       VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                    ("book_text", page_num, text_title, main_text, main_text, image_data, vector)
                )
                conn.commit()
                print(f"[OK] Страница {page_num}: Теория занесена")

        # ------------------------------------------------------------------
        # Б) ВСТАВКА КАЖДОГО РИСУНКА ОТДЕЛЬНО (source_type = 'figure')
        # Вектор строится СТРОГО ПО НАЗВАНИЮ РИСУНКА И ЕГО ВЫНОСКАМ!
        # ------------------------------------------------------------------
        for fig in figures:
            fig_num = fig.get("fig_number", "")
            fig_title = fig.get("title", "")
            labels_list = fig.get("labels", [])
            labels_str = ", ".join(labels_list)

            fig_full_title = f"Страница {page_num} — {fig_num}: {fig_title}"
            figure_info_str = f"{fig_num} ({fig_title})"

            # ВЕКТОР ТЕЛЬЦА: Только название рисунка и выноски! (Никакой лишней теории!)
            text_for_vector = f"Анатомический рисунок: {fig_num} {fig_title}. Анатомические детали, органы и выноски на рисунке: {labels_str}"

            # Текст для промпта
            full_content = f"Иллюстрация {fig_num}: {fig_title}\nАнатомические элементы и выноски: {labels_str}"

            vector = get_embedding(text_for_vector)
            if vector:
                cur.execute(
                    """INSERT INTO knowledge_base 
                       (source_type, page_num, title, figure_info, figure_labels, content, image_data, embedding)
                       VALUES (%s, %s, %s, %s, %s, %s, %s, %s)""",
                    ("figure", page_num, fig_full_title, figure_info_str, labels_str, full_content, image_data, vector)
                )
                conn.commit()
                print(f"  [OK Fig] {fig_num}: {fig_title}")

    cur.close()
    conn.close()
    print("\n[SUCCESS] База данных успешно сформирована и векторизована!")

if __name__ == "__main__":
    import_merged_datasets()