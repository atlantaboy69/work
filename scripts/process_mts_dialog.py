import os
import sys
import csv
import json
import uuid
import re
from typing import List, Dict, Any

# Подключаем корень проекта к пути импорта
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, PROJECT_ROOT)

from main import get_db_connection, get_yandex_embedding, log_msg, GIGACHAT_AUTH_KEY, GIGACHAT_SCOPE, GIGACHAT_MODEL
from gigachat import GigaChat
from gigachat.models import Chat, Messages, MessagesRole

GIGACHAT_LITE_MODEL = "GigaChat"

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

def translate_roles_and_terms(text: str) -> str:
    """Предварительная подготовка ролей в диалоге"""
    text = re.sub(r'\bDoctor:\s*', 'Врач: ', text)
    text = re.sub(r'\bPatient:\s*', 'Пациент: ', text)
    text = re.sub(r'\bGuest_family:\s*', 'Родственник: ', text)
    text = re.sub(r'\bGuest_family_\d+:\s*', 'Родственник: ', text)
    return text

def get_available_gigachat_model(giga_client: GigaChat) -> str:
    try:
        models = giga_client.get_models()
        if models and models.data:
            model_ids = [m.id_ for m in models.data]
            log_msg(f"Доступные модели GigaChat в вашем аккаунте: {model_ids}")
            # GigaChat-2 является официальным идентификатором модели GigaChat-2-Lite в API Сбера
            if "GigaChat-2" in model_ids:
                return "GigaChat-2"
            elif "GigaChat" in model_ids:
                return "GigaChat"
            return model_ids[0]
    except Exception as e:
        log_msg(f"Не удалось автоматически получить список моделей: {e}")
    return "GigaChat-2"

def translate_with_gigachat_lite(giga_client: GigaChat, model_name: str, text: str, is_dialogue: bool = True) -> str:
    """Перевод медицинского текста или диалога на русский язык с использованием GigaChat"""
    if not text or not text.strip():
        return ""

    if is_dialogue:
        sys_msg = (
            "Ты — профессиональный медицинский переводчик. Переведи данный диалог врача и пациента на естественный русский язык. "
            "Сохраняй метки спикеров: 'Врач:', 'Пациент:', 'Родственник:'. Сохраняй всю медицинскую терминологию и симптомы."
        )
    else:
        sys_msg = (
            "Ты — медицинский переводчик. Переведи данное врачебное резюме (клиническую выписку) на точный русский академический медицинский язык."
        )

    messages = [
        Messages(role=MessagesRole.SYSTEM, content=sys_msg),
        Messages(role=MessagesRole.USER, content=text)
    ]

    try:
        payload = Chat(
            model=model_name,
            messages=messages,
            temperature=0.2,
            max_tokens=2000
        )
        resp = giga_client.chat(payload)
        translated = resp.choices[0].message.content or ""
        translated = re.sub(r'<(think|thought)>.*?</\1>', '', translated, flags=re.DOTALL).strip()
        return translate_roles_and_terms(translated) if translated else translate_roles_and_terms(text)
    except Exception as e:
        log_msg(f"Предупреждение: ошибка перевода GigaChat ({model_name}: {e}). Используется базовый адаптер.")
        return translate_roles_and_terms(text)

# ==============================================================================
# ЭТАП 1: ПОЛНЫЙ ПЕРЕВОД ВСЕХ ФАЙЛОВ С СОХРАНЕНИЕМ В DATA/MTS_DIALOG_RU.JSON
# ==============================================================================
def stage1_translate_all_datasets() -> List[Dict[str, Any]]:
    dataset_dir = os.path.join(PROJECT_ROOT, "MTS-Dialog-main", "Main-Dataset")
    files_to_process = [
        "MTS-Dialog-TrainingSet.csv",
        "MTS-Dialog-ValidationSet.csv",
        "MTS-Dialog-TestSet-1-MEDIQA-Chat-2023.csv",
        "MTS-Dialog-TestSet-2-MEDIQA-Sum-2023.csv"
    ]

    data_dir = os.path.join(PROJECT_ROOT, "data")
    os.makedirs(data_dir, exist_ok=True)
    json_path = os.path.join(data_dir, "mts_dialog_ru.json")
    finetune_path = os.path.join(data_dir, "patient_mode_finetune.jsonl")

    all_records = []
    processed_ids = set()

    # Загружаем уже переведенные записи для возобновления работы
    if os.path.exists(json_path):
        try:
            with open(json_path, "r", encoding="utf-8") as f:
                loaded = json.load(f)
                for item in loaded:
                    dlg_ru = item.get("dialogue_ru", "")
                    sec_ru = item.get("section_text_ru", "")
                    # Проверяем наличие русской кириллицы в тексте диалога
                    has_cyrillic = bool(re.search(r'[а-яА-ЯёЁ]{3,}', dlg_ru)) and bool(re.search(r'[а-яА-ЯёЁ]{3,}', sec_ru))
                    if item.get("id") and has_cyrillic:
                        all_records.append(item)
                        processed_ids.add(item["id"])
            log_msg(f"Обнаружен ранее сохраненный чекпоинт. Загружено {len(processed_ids)} готовых переведенных записей на русском языке.")
        except Exception as e:
            log_msg(f"Не удалось загрузить чекпоинт ({e}), процесс начат заново.")

    def save_checkpoint(records):
        with open(json_path, "w", encoding="utf-8") as f:
            json.dump(records, f, ensure_ascii=False, indent=2)
        
        finetune_list = []
        for r in records:
            finetune_list.append({
                "messages": [
                    {
                        "role": "system",
                        "content": f"Ты — врач-диагност MedAI. Веди расспрос пациента по теме '{r['section_header_ru']}', выявляй симптомы и подводи итог."
                    },
                    {
                        "role": "user",
                        "content": f"Диалог общения с пациентом:\n{r['dialogue_ru']}"
                    },
                    {
                        "role": "assistant",
                        "content": f"Врачебное заключение ({r['section_header_ru']}):\n{r['section_text_ru']}"
                    }
                ]
            })
        with open(finetune_path, "w", encoding="utf-8") as f:
            for item in finetune_list:
                f.write(json.dumps(item, ensure_ascii=False) + "\n")

    auth_key = GIGACHAT_AUTH_KEY.replace("Basic ", "").strip()
    with GigaChat(credentials=auth_key, scope=GIGACHAT_SCOPE, verify_ssl_certs=False) as giga_client:
        active_model = get_available_gigachat_model(giga_client)
        log_msg(f"=== ЭТАП 1: Перевод датасета MTS-Dialog моделью GigaChat-2-Lite (ID в API: '{active_model}') ===")

        for filename in files_to_process:
            filepath = os.path.join(dataset_dir, filename)
            if not os.path.exists(filepath):
                log_msg(f"Пропуск файла {filename}: не найден")
                continue

            log_msg(f"Проверка/обработка файла: {filename}")
            with open(filepath, "r", encoding="utf-8") as f:
                reader = csv.DictReader(f)
                count = 0
                for row in reader:
                    dlg_id = row.get("ID", "").strip()
                    rec_id = f"{filename}_{dlg_id}"

                    if rec_id in processed_ids:
                        continue

                    header = row.get("section_header", "").strip().upper()
                    section_text = row.get("section_text", "").strip()
                    dialogue = row.get("dialogue", "").strip()

                    header_ru = SECTION_HEADERS_RU.get(header, f"Медицинский анамнез ({header})")

                    # Перевод диалога и врачебной заметки моделью GigaChat-2-Lite
                    dialogue_ru = translate_with_gigachat_lite(giga_client, active_model, dialogue, is_dialogue=True)
                    section_text_ru = translate_with_gigachat_lite(giga_client, active_model, section_text, is_dialogue=False)

                    rec = {
                        "id": rec_id,
                        "section_header": header,
                        "section_header_ru": header_ru,
                        "section_text_en": section_text,
                        "section_text_ru": section_text_ru,
                        "dialogue_en": dialogue,
                        "dialogue_ru": dialogue_ru
                    }
                    all_records.append(rec)
                    processed_ids.add(rec_id)

                    count += 1
                    if count % 10 == 0:
                        save_checkpoint(all_records)
                        log_msg(f"  Файл {filename}: Новых переведено {count} записей (Всего переведено: {len(all_records)})...")

            save_checkpoint(all_records)
            log_msg(f"Завершена обработка файла {filename} (Всего переведено: {len(all_records)})")

    save_checkpoint(all_records)
    log_msg(f"=== ЭТАП 1 ЗАВЕРШЕН: Все данные сохранены в {json_path} ({len(all_records)} записей) ===")
    return all_records

# ==============================================================================
# ЭТАП 2: ВНЕДРЕНИЕ В ОТДЕЛЬНУЮ ТАБЛИЦУ POSTGRESQL (PATIENT_DIALOGUES)
# ==============================================================================
def stage2_import_to_database(records: List[Dict[str, Any]]):
    log_msg("=== ЭТАП 2: Создание отдельной таблицы patient_dialogues и импорт переведенных данных ===")

    conn = get_db_connection()

    sample_vec = get_yandex_embedding("Тестовая проверка размерности вектора")
    vector_dim = len(sample_vec) if sample_vec else 256
    log_msg(f"Автоматически определенная размерность эмбеддингов Yandex: {vector_dim}")

    try:
        with conn.cursor() as cur:
            cur.execute("CREATE EXTENSION IF NOT EXISTS vector;")
            cur.execute("DROP TABLE IF EXISTS patient_dialogues;")
            cur.execute(f"""
                CREATE TABLE IF NOT EXISTS patient_dialogues (
                    id SERIAL PRIMARY KEY,
                    dialogue_id VARCHAR(100),
                    section_header VARCHAR(100),
                    section_header_ru VARCHAR(250),
                    section_text TEXT,
                    section_text_ru TEXT,
                    dialogue_text TEXT,
                    dialogue_text_ru TEXT,
                    embedding vector({vector_dim}),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            """)
            cur.execute("""
                CREATE INDEX IF NOT EXISTS patient_dialogues_vector_idx 
                ON patient_dialogues USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
            """)
        conn.commit()
        log_msg(f"Таблица 'patient_dialogues' с размерностью vector({vector_dim}) и векторный индекс созданы успешно.")
    except Exception as e:
        log_msg(f"Ошибка создания таблицы 'patient_dialogues': {e}")
        return

    imported_count = 0
    for rec in records:
        emb_text = f"{rec['section_header_ru']} {rec['section_text_ru']} {rec['dialogue_ru'][:500]}"
        vector = get_yandex_embedding(emb_text)

        if vector:
            if len(vector) < vector_dim:
                vector = vector + [0.0] * (vector_dim - len(vector))
            elif len(vector) > vector_dim:
                vector = vector[:vector_dim]

        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO patient_dialogues (
                    dialogue_id, section_header, section_header_ru, 
                    section_text, section_text_ru, dialogue_text, dialogue_text_ru, embedding
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                rec['id'], rec['section_header'], rec['section_header_ru'],
                rec['section_text_en'], rec['section_text_ru'], rec['dialogue_en'], rec['dialogue_ru'], vector
            ))
        conn.commit()
        imported_count += 1
        if imported_count % 50 == 0:
            log_msg(f"  В БД загружено {imported_count} из {len(records)} диалогов...")

    conn.close()
    log_msg(f"=== ЭТАП 2 ЗАВЕРШЕН: Всего загружено в отдельную таблицу patient_dialogues {imported_count} диалогов ===")

if __name__ == '__main__':
    translated_records = stage1_translate_all_datasets()
    stage2_import_to_database(translated_records)
