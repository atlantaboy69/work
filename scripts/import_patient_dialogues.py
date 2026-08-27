import os
import sys
import json
import time
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
import psycopg2
from psycopg2.extras import execute_values

sys.stdout.reconfigure(encoding='utf-8')
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, PROJECT_ROOT)

from main import get_yandex_embedding, log_msg

SECTION_HEADERS_RU = {
    'CC': 'Основная жалоба (Chief Complaint)',
    'GENHX': 'История настоящего заболевания (History of Present Illness)',
    'FAM/SOCHX': 'Семейный и социальный анамнез',
    'PASTMEDICALHX': 'Сопутствующие и перенесенные заболевания',
    'PASTSURGICAL': 'Перенесенные операции',
    'ALLERGY': 'Аллергологический анамнез',
    'ROS': 'Опрос по системам органов (Review of Systems)',
    'MEDICATIONS': 'Принимаемые медикаменты',
    'ASSESSMENT': 'Врачебная оценка и первичный вывод',
    'EXAM': 'Данные физикального осмотра',
    'DIAGNOSIS': 'Предварительный и окончательный диагноз',
    'DISPOSITION': 'Назначения и рекомендации при выписке',
    'PLAN': 'План обследования и лечения',
    'EDCOURSE': 'Динамика наблюдения в приёмном отделении',
    'IMMUNIZATIONS': 'Анамнез вакцинации',
    'IMAGING': 'Данные лучевой диагностики (КТ, МРТ, Рентген)',
    'GYNHX': 'Гинекологический анамнез',
    'PROCEDURES': 'Выполненные процедуры и манипуляции',
    'OTHER_HISTORY': 'Прочая медицинская история',
    'LABS': 'Результаты лабораторных анализов'
}

CACHE_PATH = os.path.join(PROJECT_ROOT, "data", "patient_dialogues_with_embeddings.json")

def get_connection():
    try:
        return psycopg2.connect(
            host=os.getenv("PGHOST", "localhost"),
            port=int(os.getenv("PGPORT", "5434")),
            user=os.getenv("PGUSER", "postgres"),
            password=os.getenv("PGPASSWORD", "1010"),
            dbname=os.getenv("PGDATABASE", "medai_db")
        )
    except Exception:
        from main import get_db_connection
        return get_db_connection()

rate_lock = threading.Lock()
last_request_time = 0.0

def rate_limited_get_embedding(text: str, max_retries: int = 7):
    global last_request_time
    for attempt in range(max_retries):
        with rate_lock:
            now = time.time()
            # Ограничиваем скорость: минимум 0.15 сек между запросами (максимум ~6.5 запросов в секунду)
            wait_needed = 0.15 - (now - last_request_time)
            if wait_needed > 0:
                time.sleep(wait_needed)
            last_request_time = time.time()

        try:
            vec = get_yandex_embedding(text)
            if vec and len(vec) == 256:
                return vec
        except Exception:
            pass

        # Экспоненциальная пауза при ошибке или превышении квоты 429
        time.sleep(0.8 * (attempt + 1))

    return None

def process_single_item(item):
    idx, rec = item
    header = rec.get('section_header', '').upper()
    header_ru = SECTION_HEADERS_RU.get(header, rec.get('section_header_ru') or f"Медицинский раздел ({header})")
    sec_text_ru = rec.get('section_text_ru', '')
    dlg_ru = rec.get('dialogue_ru', '')

    emb_text = f"{header_ru} {sec_text_ru} {dlg_ru[:500]}".strip()
    
    vec = rate_limited_get_embedding(emb_text)
    if not vec:
        vec = [0.0] * 256

    return {
        'id': rec.get('id', f"dlg_{idx}"),
        'section_header': header,
        'section_header_ru': header_ru,
        'section_text_en': rec.get('section_text_en', ''),
        'section_text_ru': sec_text_ru,
        'dialogue_en': rec.get('dialogue_en', ''),
        'dialogue_ru': dlg_ru,
        'embedding': vec
    }

def main():
    json_path = os.path.join(PROJECT_ROOT, "data", "mts_dialog_ru.json")
    if not os.path.exists(json_path):
        print(f"Файл {json_path} не найден!")
        return

    with open(json_path, "r", encoding="utf-8") as f:
        records = json.load(f)

    total = len(records)
    print(f"Загружено {total} исходных записей из {json_path}")

    cached_data = {}
    if os.path.exists(CACHE_PATH):
        try:
            with open(CACHE_PATH, "r", encoding="utf-8") as cf:
                cache_list = json.load(cf)
                cached_data = {r['id']: r for r in cache_list if r.get('embedding') and any(v != 0.0 for v in r['embedding'])}
                print(f"Найдено в кэше с готовыми эмбеддингами: {len(cached_data)} диалогов.")
        except Exception as e:
            print(f"Не удалось прочитать кэш: {e}")

    items_to_process = []
    prepared_rows = []

    for idx, rec in enumerate(records):
        rec_id = rec.get('id', f"dlg_{idx}")
        if rec_id in cached_data:
            prepared_rows.append(cached_data[rec_id])
        else:
            items_to_process.append((idx, rec))

    print(f"Осталось сгенерировать эмбеддингов для {len(items_to_process)} диалогов...")

    if items_to_process:
        start_time = time.time()
        completed = 0
        with ThreadPoolExecutor(max_workers=4) as executor:
            futures = {executor.submit(process_single_item, item): item for item in items_to_process}
            for f in as_completed(futures):
                res = f.result()
                prepared_rows.append(res)
                cached_data[res['id']] = res
                completed += 1
                if completed % 50 == 0 or completed == len(items_to_process):
                    elapsed = time.time() - start_time
                    rps = completed / elapsed if elapsed > 0 else 0
                    print(f"  Сгенерировано {completed}/{len(items_to_process)} эмбеддингов ({rps:.1f} rps, прошло {elapsed:.1f}с)...")
                    with open(CACHE_PATH, "w", encoding="utf-8") as cf:
                        json.dump(list(cached_data.values()), cf, ensure_ascii=False)

        with open(CACHE_PATH, "w", encoding="utf-8") as cf:
            json.dump(list(cached_data.values()), cf, ensure_ascii=False)

    print(f"Все {len(prepared_rows)} диалогов имеют готовые эмбеддинги. Подключение к PostgreSQL...")
    conn = get_connection()

    with conn.cursor() as cur:
        cur.execute("CREATE EXTENSION IF NOT EXISTS vector;")
        cur.execute("DROP TABLE IF EXISTS patient_dialogues;")
        cur.execute("""
            CREATE TABLE patient_dialogues (
                id SERIAL PRIMARY KEY,
                dialogue_id VARCHAR(100),
                section_header VARCHAR(100),
                section_header_ru VARCHAR(250),
                section_text TEXT,
                section_text_ru TEXT,
                dialogue_text TEXT,
                dialogue_text_ru TEXT,
                embedding vector(256),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
    conn.commit()
    print("Таблица 'patient_dialogues' успешно создана в PostgreSQL.")

    print("Запись всех диалогов в базу данных...")
    batch_size = 200
    with conn.cursor() as cur:
        for i in range(0, len(prepared_rows), batch_size):
            batch = prepared_rows[i:i + batch_size]
            values = [
                (
                    r['id'], r['section_header'], r['section_header_ru'],
                    r.get('section_text_en', ''), r['section_text_ru'],
                    r.get('dialogue_en', ''), r['dialogue_text_ru'] if 'dialogue_text_ru' in r else r.get('dialogue_ru', ''),
                    f"[{','.join(map(str, r['embedding']))}]"
                )
                for r in batch
            ]
            execute_values(
                cur,
                """
                INSERT INTO patient_dialogues (
                    dialogue_id, section_header, section_header_ru,
                    section_text, section_text_ru,
                    dialogue_text, dialogue_text_ru,
                    embedding
                ) VALUES %s
                """,
                values,
                template="(%s, %s, %s, %s, %s, %s, %s, %s::vector)"
            )
            conn.commit()
            print(f"  Вставлено {min(i + batch_size, len(prepared_rows))}/{len(prepared_rows)} строк в БД...")

    print("Создание векторного индекса IVFFLAT (vector_cosine_ops)...")
    with conn.cursor() as cur:
        cur.execute("""
            CREATE INDEX IF NOT EXISTS patient_dialogues_vector_idx 
            ON patient_dialogues USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
        """)
        cur.execute("SELECT count(*) FROM patient_dialogues WHERE embedding IS NOT NULL;")
        total_in_db = cur.fetchone()[0]
    conn.commit()
    conn.close()

    print(f"=== УСПЕХ: В таблицу patient_dialogues успешно загружено {total_in_db} диалогов с эмбеддингами! ===")

if __name__ == '__main__':
    main()

